<?php

namespace App\Http\Controllers\Api;

use App\Services\PayoutAggregationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PayoutController
{
    public function index(Request $request, PayoutAggregationService $aggregation): JsonResponse
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'partner_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $this->assertOfficialCycle($validated['period_start'], $validated['period_end']);
        $branchId = $request->attributes->get('authorized_branch_id');
        if ($request->filled('branch_id')) {
            abort_unless((int) $validated['branch_id'] === (int) $branchId, 403, 'Cabang tidak sesuai dengan akses pengguna.');
        }

        $rows = $aggregation->aggregate(CarbonImmutable::parse($validated['period_start']), CarbonImmutable::parse($validated['period_end']), (int) $branchId);
        if ($request->filled('partner_id')) {
            $rows = $rows->where('partner_id', (int) $validated['partner_id'])->values();
        }

        return response()->json([
            'period' => ['start' => $validated['period_start'], 'end' => $validated['period_end']],
            'data' => $rows->groupBy('partner_id')->map(function ($items): array {
                $first = $items->first();
                return [
                    'partner_id' => $first['partner_id'], 'partner_code' => $first['partner_code'], 'partner_name' => $first['partner_name'],
                    'gross_amount' => $items->sum('gross_amount'), 'commission_amount' => $items->sum('commission_amount'),
                    'payout_amount' => $items->sum('payout_amount'), 'items' => $items->values(),
                ];
            })->values(),
        ]);
    }

    public function detail(Request $request, PayoutAggregationService $aggregation, int $partner): JsonResponse
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);
        $this->assertOfficialCycle($validated['period_start'], $validated['period_end']);
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $rows = $aggregation->aggregate(CarbonImmutable::parse($validated['period_start']), CarbonImmutable::parse($validated['period_end']), $branchId)
            ->where('partner_id', $partner)->values();
        abort_if($rows->isEmpty(), 404, 'Rincian payout mitra tidak ditemukan.');

        return response()->json([
            'partner_id' => $partner,
            'period' => ['start' => $validated['period_start'], 'end' => $validated['period_end']],
            'summary' => [
                'gross_amount' => $rows->sum('gross_amount'),
                'commission_amount' => $rows->sum('commission_amount'),
                'payout_amount' => $rows->sum('payout_amount'),
            ],
            'data' => $rows,
        ]);
    }

    public function export(Request $request, PayoutAggregationService $aggregation)
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);
        $this->assertOfficialCycle($validated['period_start'], $validated['period_end']);
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $rows = $aggregation->aggregate(CarbonImmutable::parse($validated['period_start']), CarbonImmutable::parse($validated['period_end']), $branchId);
        $filename = 'transfer-massal-'.$validated['period_start'].'-'.$validated['period_end'].'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['partner_code', 'partner_name', 'branch_id', 'payout_amount']);
            foreach ($rows->groupBy('partner_id') as $items) {
                $first = $items->first();
                fputcsv($handle, [$first['partner_code'], $first['partner_name'], $items->pluck('branch_id')->unique()->implode('|'), $items->sum('payout_amount')]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function markPaid(Request $request, int $cycle, int $partner): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'bank_reference' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]{5,119}$/'],
            'proof' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);
        $this->assertOfficialCycle($validated['period_start'], $validated['period_end']);
        $record = DB::table('payout_cycles')->where('id', $cycle)->whereIn('status', ['LOCKED', 'PAID'])->first();
        abort_unless($record !== null, 404, 'Siklus payout tidak ditemukan atau belum dikunci.');
        abort_if(DB::table('payout_payments')->where('payout_cycle_id', $cycle)->where('partner_id', $partner)->exists(), 409, 'Payout mitra pada siklus ini sudah dibayar.');
        $items = DB::table('payout_items')->where('payout_cycle_id', $cycle)->where('partner_id', $partner)->where('status', 'READY')->get(['id', 'payout_amount']);
        abort_if($items->isEmpty(), 404, 'Payout mitra tidak tersedia untuk dibayar.');
        $expected = (int) $items->sum('payout_amount');
        abort_unless((int) $validated['amount'] === $expected, 422, 'Nominal pembayaran tidak sesuai rekap payout.');
        $proofPath = $request->file('proof')->store('payout-proofs', 'local');

        DB::transaction(function () use ($validated, $proofPath, $cycle, $partner, $items, $request): void {
            $paymentId = DB::table('payout_payments')->insertGetId([
                'payout_cycle_id' => $cycle, 'partner_id' => $partner, 'amount' => $validated['amount'],
                'bank_reference' => $validated['bank_reference'], 'proof_path' => $proofPath,
                'paid_by' => $request->user()->id, 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('payout_items')->whereIn('id', $items->pluck('id'))->update(['status' => 'PAID', 'updated_at' => now()]);
            DB::table('audit_trails')->insert([
                'branch_id' => null, 'actor_id' => $request->user()->id, 'event' => 'PAYOUT_PAID',
                'auditable_type' => 'payout_payment', 'auditable_id' => $paymentId,
                'after_data' => json_encode(['cycle_id' => $cycle, 'partner_id' => $partner, 'amount' => $validated['amount'], 'bank_reference' => $validated['bank_reference']]),
                'reason' => 'Payout ditandai sudah dibayar.', 'created_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Payout ditandai sudah dibayar.', 'proof_url' => null]);
    }

    public function eligible(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $rows = DB::table('daily_closings')
            ->where('branch_id', $branchId)
            ->whereBetween('business_date', [$validated['period_start'], $validated['period_end']])
            ->orderBy('business_date')
            ->get(['id', 'business_date', 'status', 'difference_status'])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'business_date' => $row->business_date,
                'status' => $row->status,
                'eligible' => $row->status === 'LOCKED_SETTLED',
                'exclusion_reason' => $row->status === 'LOCKED_SETTLED' ? null : 'TRANSAKSI_BELUM_LOCKED_SETTLED',
                'difference_status' => $row->difference_status,
            ]);

        return response()->json(['period' => ['start' => $validated['period_start'], 'end' => $validated['period_end']], 'data' => $rows]);
    }

    public function paymentHistory(Request $request, int $cycle): JsonResponse
    {
        $payments = DB::table('payout_payments')
            ->join('partners', 'partners.id', '=', 'payout_payments.partner_id')
            ->where('payout_payments.payout_cycle_id', $cycle)
            ->orderByDesc('payout_payments.paid_at')
            ->get(['payout_payments.id', 'payout_payments.partner_id', 'partners.code as partner_code', 'partners.name as partner_name', 'payout_payments.amount', 'payout_payments.bank_reference', 'payout_payments.proof_path', 'payout_payments.paid_by', 'payout_payments.paid_at'])
            ->map(fn ($payment): array => [
                'id' => (int) $payment->id,
                'partner_id' => (int) $payment->partner_id,
                'partner_code' => $payment->partner_code,
                'partner_name' => $payment->partner_name,
                'amount' => (int) $payment->amount,
                'bank_reference' => $payment->bank_reference,
                'proof_url' => null,
                'paid_by' => (int) $payment->paid_by,
                'paid_at' => $payment->paid_at,
            ]);

        return response()->json(['cycle_id' => $cycle, 'data' => $payments]);
    }

    private function assertOfficialCycle(string $start, string $end): void
    {
        $periodStart = CarbonImmutable::parse($start);
        $periodEnd = CarbonImmutable::parse($end);
        $days = $periodStart->diffInDays($periodEnd);
        $valid = ($days === 2 && $periodStart->dayOfWeekIso === 1 && $periodEnd->dayOfWeekIso === 3)
            || ($days === 2 && $periodStart->dayOfWeekIso === 4 && $periodEnd->dayOfWeekIso === 6);
        abort_unless($valid, 422, 'Siklus payout harus Senin–Rabu atau Kamis–Sabtu.');
    }
}
