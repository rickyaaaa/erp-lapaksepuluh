<?php

namespace App\Http\Controllers\Api;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\DailyClosingCalculator;

class DailyClosingController
{
    public function storeExpense(Request $request, int $closing): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:200'],
            'proof' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $record = DB::table('daily_closings')
            ->where('id', $closing)
            ->where('branch_id', $branchId)
            ->where('status', 'DRAFT')
            ->first(['id']);

        abort_unless($record !== null, 409, 'Closing tidak ditemukan atau sudah dikirim.');
        $proofPath = $request->file('proof')->store('closing-expenses', 'public');
        $expenseId = (int) DB::table('closing_expenses')->insertGetId([
            'daily_closing_id' => $record->id,
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'proof_path' => $proofPath,
            'is_valid' => true,
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pengeluaran operasional sah tersimpan.',
            'id' => $expenseId,
            'amount' => (int) $validated['amount'],
            'proof_url' => Storage::disk('public')->url($proofPath),
        ], 201);
    }

    public function summary(Request $request, int $closing): JsonResponse
    {
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $record = DB::table('daily_closings')
            ->where('id', $closing)
            ->where('branch_id', $branchId)
            ->first();

        abort_unless($record !== null, 404, 'Closing tidak ditemukan.');

        $expenses = DB::table('closing_expenses')
            ->where('daily_closing_id', $record->id)
            ->orderBy('created_at')
            ->get(['id', 'category', 'amount', 'description', 'is_valid', 'proof_path']);
        $attachments = DB::table('closing_attachments')
            ->where('daily_closing_id', $record->id)
            ->get(['id', 'type', 'file_path', 'uploaded_at']);

        return response()->json([
            'data' => [
                'id' => $record->id,
                'branch_id' => $record->branch_id,
                'business_date' => $record->business_date,
                'status' => $record->status,
                'system_gross' => (int) $record->system_gross,
                'counted_cash' => (int) $record->counted_cash,
                'qris_settlement' => (int) $record->qris_settlement,
                'total_valid_expenses' => (int) $record->total_valid_expenses,
                'total_accountability' => (int) $record->total_accountability,
                'difference' => (int) $record->difference,
                'difference_status' => $record->difference_status,
                'expenses' => $expenses,
                'attachments' => $attachments,
            ],
        ]);
    }

    public function submit(Request $request, int $closing): JsonResponse
    {
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $record = DB::table('daily_closings')
            ->where('id', $closing)
            ->where('branch_id', $branchId)
            ->where('status', 'DRAFT')
            ->first(['id']);

        abort_unless($record !== null, 409, 'Closing tidak ditemukan atau sudah dikirim.');
        $hasQrisProof = DB::table('closing_attachments')
            ->where('daily_closing_id', $record->id)
            ->where('type', 'bukti_qris_settlement')
            ->exists();
        abort_unless($hasQrisProof, 422, 'Foto settlement QRIS wajib diunggah sebelum submit.');

        DB::table('daily_closings')->where('id', $record->id)->update([
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Closing terkirim untuk verifikasi.', 'id' => $record->id, 'status' => 'SUBMITTED']);
    }

    public function storeAttachment(Request $request, int $closing): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:bukti_tunai,bukti_qris_settlement,logbook,lain'],
            'attachment' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $record = DB::table('daily_closings')
            ->where('id', $closing)
            ->where('branch_id', $branchId)
            ->where('status', 'DRAFT')
            ->first(['id']);

        abort_unless($record !== null, 409, 'Closing tidak ditemukan atau sudah dikirim.');
        $path = $request->file('attachment')->store('closing-attachments', 'public');
        $attachmentId = (int) DB::table('closing_attachments')->insertGetId([
            'daily_closing_id' => $record->id,
            'type' => $validated['type'],
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Lampiran closing tersimpan.',
            'id' => $attachmentId,
            'type' => $validated['type'],
            'file_url' => Storage::disk('public')->url($path),
        ], 201);
    }

    public function store(Request $request, DailyClosingCalculator $calculator): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'min:1'],
            'business_date' => ['required', 'date'],
            'counted_cash' => ['required', 'integer', 'min:0'],
            'qris_settlement' => ['required', 'integer', 'min:0'],
        ]);
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        abort_unless($branchId === (int) $validated['branch_id'], 403, 'Cabang tidak sesuai dengan akses pengguna.');

        $systemGross = DB::table('stock_opnames')
            ->where('branch_id', $branchId)
            ->whereDate('business_date', $validated['business_date'])
            ->whereIn('status', ['SUBMITTED', 'VERIFIED'])
            ->value('total_gross');
        abort_unless($systemGross !== null, 422, 'Stok opname belum tersedia untuk tanggal closing.');

        $existing = DB::table('daily_closings')
            ->where('branch_id', $branchId)
            ->whereDate('business_date', $validated['business_date'])
            ->first(['id', 'status']);
        abort_if($existing !== null && $existing->status !== 'DRAFT', 409, 'Closing sudah dikirim atau dikunci.');

        $closingId = DB::transaction(function () use ($validated, $branchId, $systemGross, $calculator, $existing, $request): int {
            $expenseTotal = (int) DB::table('closing_expenses')
                ->where('daily_closing_id', $existing?->id)
                ->where('is_valid', true)
                ->sum('amount');
            $calculated = $calculator->calculate((int) $systemGross, (int) $validated['counted_cash'], (int) $validated['qris_settlement'], $expenseTotal);
            $values = [
                'system_gross' => $systemGross,
                'counted_cash' => $validated['counted_cash'],
                'qris_settlement' => $validated['qris_settlement'],
                'total_valid_expenses' => $expenseTotal,
                'total_accountability' => $calculated['total_accountability'],
                'difference' => $calculated['difference'],
                'difference_status' => $calculated['difference_status'],
                'updated_at' => now(),
            ];

            if ($existing === null) {
                return (int) DB::table('daily_closings')->insertGetId($values + [
                    'branch_id' => $branchId,
                    'business_date' => $validated['business_date'],
                    'cashier_id' => $request->user()->id,
                    'status' => 'DRAFT',
                    'created_at' => now(),
                ]);
            }

            DB::table('daily_closings')->where('id', $existing->id)->update($values);

            return (int) $existing->id;
        });

        return response()->json(['message' => 'Draft closing tersimpan.', 'id' => $closingId, 'status' => 'DRAFT']);
    }

    public function show(Request $request): JsonResponse
    {
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $validated = $request->validate(['business_date' => ['required', 'date']]);
        $businessDate = CarbonImmutable::parse($validated['business_date'], 'Asia/Jakarta')->startOfDay();
        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();

        abort_if($businessDate->greaterThan($today), 422, 'Tanggal closing tidak boleh melewati tanggal operasional hari ini.');

        $closing = DB::table('daily_closings')
            ->where('branch_id', $branchId)
            ->whereDate('business_date', $businessDate->toDateString())
            ->first();

        return response()->json([
            'data' => $closing,
            'branch_id' => $branchId,
            'business_date' => $businessDate->toDateString(),
        ]);
    }
}
