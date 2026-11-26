<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CorrectionController
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('correction_requests')->where('branch_id', $request->attributes->get('authorized_branch_id'));
        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->string('status')));
        }

        return response()->json(['data' => $query->latest('id')->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', 'string', 'max:40'],
            'entity_id' => ['required', 'integer', 'min:1'],
            'field_name' => ['required', 'string', 'max:80'],
            'original_value' => ['nullable', 'string'],
            'proposed_value' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:5'],
        ]);
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $this->assertEntityInBranch($data['entity_type'], (int) $data['entity_id'], $branchId);
        abort_if(DB::table('correction_requests')->where($data + ['branch_id' => $branchId, 'status' => 'PENDING'])->exists(), 409, 'Pengajuan koreksi serupa masih menunggu review.');

        $data['branch_id'] = $branchId;
        $data['requested_by'] = $request->user()->id;
        $data['status'] = 'PENDING';

        $id = DB::transaction(fn (): int => (int) DB::table('correction_requests')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]));
        $this->writeAudit($data['branch_id'], $request->user()->id, 'CORRECTION_REQUESTED', 'correction_request', $id, null, $data, $data['reason']);

        return response()->json(['data' => DB::table('correction_requests')->find($id)], 201);
    }

    public function review(Request $request, int $correction): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:APPROVED,REJECTED'], 'review_note' => ['nullable', 'string', 'required_if:decision,REJECTED', 'min:5']]);
        $row = DB::table('correction_requests')->where('id', $correction)->where('branch_id', $request->attributes->get('authorized_branch_id'))->first();
        abort_if(!$row, 404);
        abort_if($row->status !== 'PENDING', 422, 'Correction sudah direview.');

        DB::transaction(function () use ($correction, $data, $request, $row): void {
            DB::table('correction_requests')->where('id', $correction)->update([
                'status' => $data['decision'], 'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(), 'review_note' => $data['review_note'] ?? null, 'updated_at' => now(),
            ]);
            $this->writeAudit($row->branch_id, $request->user()->id, 'CORRECTION_'.$data['decision'], 'correction_request', $correction, (array) $row, $data, $data['review_note'] ?? null);
        });

        return response()->json(['data' => DB::table('correction_requests')->find($correction)]);
    }

    public function managerDecision(Request $request, int $correction): JsonResponse
    {
        $request->merge(['manager_decision' => true]);
        return $this->review($request, $correction);
    }

    public function apply(Request $request, int $correction): JsonResponse
    {
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $row = DB::table('correction_requests')->where('id', $correction)->where('branch_id', $branchId)->first();
        abort_unless($row !== null, 404, 'Pengajuan koreksi tidak ditemukan.');
        abort_if($row->status !== 'APPROVED', 409, 'Hanya koreksi yang disetujui yang dapat diterapkan.');
        abort_if($row->payout_status === 'PAID', 409, 'Koreksi diblokir karena payout sudah dibayar.');
        abort_if(DB::table('audit_trails')->where('event', 'CORRECTION_APPLIED')->where('auditable_id', $correction)->exists(), 409, 'Koreksi sudah diterapkan.');
        abort_unless($row->entity_type === 'opname_item' && in_array($row->field_name, ['qty_remaining_final', 'qty_in_final'], true), 422, 'Koreksi field belum didukung untuk penerapan otomatis.');

        $item = DB::table('opname_items')->where('id', $row->entity_id)->first();
        abort_unless($item !== null, 404, 'Item stok opname tidak ditemukan.');
        $newValue = filter_var($row->proposed_value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        abort_if($newValue === false, 422, 'Nilai koreksi harus berupa bilangan bulat nol atau lebih.');

        $updated = DB::transaction(function () use ($row, $item, $newValue, $correction, $request): object {
            $qtyIn = $row->field_name === 'qty_in_final' ? $newValue : (int) $item->qty_in_final;
            $qtyRemaining = $row->field_name === 'qty_remaining_final' ? $newValue : (int) $item->qty_remaining_final;
            abort_if($qtyRemaining > $qtyIn, 422, 'Sisa stok tidak boleh melebihi stok masuk.');
            $qtySold = $qtyIn - $qtyRemaining;
            $gross = $qtySold * (int) $item->price_snapshot;
            $commission = $qtySold * (int) $item->commission_snapshot;
            $net = $gross - $commission;
            $previousNet = (int) $item->net_amount;
            $delta = $net - $previousNet;
            $impact = $delta === 0 ? 'UNCHANGED' : ($delta > 0 ? 'INCREASE' : 'DECREASE');
            DB::table('opname_items')->where('id', $item->id)->update([
                'qty_in_final' => $qtyIn, 'qty_remaining_final' => $qtyRemaining,
                'qty_sold' => $qtySold, 'gross_amount' => $gross,
                'commission_amount' => $commission, 'net_amount' => $net,
                'updated_at' => now(),
            ]);
            DB::table('correction_requests')->where('id', $correction)->update(['payout_impact' => $impact, 'payout_delta' => $delta, 'updated_at' => now()]);
            if ($delta !== 0) {
                DB::table('correction_adjustments')->insert([
                    'correction_request_id' => $correction, 'payout_delta' => $delta,
                    'status' => 'PENDING', 'created_by' => $request->user()->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('audit_trails')->insert([
                'branch_id' => $row->branch_id, 'actor_id' => $request->user()->id,
                'event' => 'CORRECTION_APPLIED', 'auditable_type' => 'correction_request', 'auditable_id' => $correction,
                'before_data' => json_encode((array) $item), 'after_data' => json_encode(['qty_in_final' => $qtyIn, 'qty_remaining_final' => $qtyRemaining, 'qty_sold' => $qtySold, 'gross_amount' => $gross, 'commission_amount' => $commission, 'net_amount' => $net, 'payout_impact' => $impact, 'payout_delta' => $delta]),
                'reason' => $row->reason, 'created_at' => now(),
            ]);
            return DB::table('opname_items')->find($item->id);
        });

        return response()->json(['data' => $updated]);
    }

    public function payoutImpact(Request $request, int $correction): JsonResponse
    {
        $row = DB::table('correction_requests')->where('id', $correction)->where('branch_id', $request->attributes->get('authorized_branch_id'))->first(['id', 'status', 'payout_impact', 'payout_delta']);
        abort_unless($row !== null, 404, 'Pengajuan koreksi tidak ditemukan.');
        return response()->json(['data' => $row]);
    }

    public function audit(Request $request): JsonResponse
    {
        $query = DB::table('audit_trails')->where('branch_id', $request->attributes->get('authorized_branch_id'));
        $request->validate([
            'auditable_type' => ['nullable', 'string', 'max:80'],
            'auditable_id' => ['nullable', 'integer', 'min:1'],
            'event' => ['nullable', 'string', 'max:80'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        if ($request->filled('auditable_type') && $request->filled('auditable_id')) {
            $query->where('auditable_type', $request->string('auditable_type'))->where('auditable_id', $request->integer('auditable_id'));
        }
        if ($request->filled('event')) {
            $query->where('event', $request->string('event'));
        }
        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->integer('actor_id'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }
        return response()->json(['data' => $query->latest('id')->paginate(50)]);
    }

    private function writeAudit(?int $branch, ?int $actor, string $event, string $type, int $id, ?array $before, ?array $after, ?string $reason): void
    {
        DB::table('audit_trails')->insert(['branch_id' => $branch, 'actor_id' => $actor, 'event' => $event, 'auditable_type' => $type, 'auditable_id' => $id, 'before_data' => $before ? json_encode($before) : null, 'after_data' => $after ? json_encode($after) : null, 'reason' => $reason, 'created_at' => now()]);
    }

    private function assertEntityInBranch(string $type, int $id, int $branchId): void
    {
        $tables = ['inbound' => 'inbounds', 'stock_opname' => 'stock_opnames', 'daily_closing' => 'daily_closings'];
        if ($type === 'opname_item') {
            abort_unless(DB::table('opname_items')->join('stock_opnames', 'stock_opnames.id', '=', 'opname_items.opname_id')->where('opname_items.id', $id)->where('stock_opnames.branch_id', $branchId)->exists(), 404, 'Data koreksi tidak ditemukan di cabang ini.');
            return;
        }
        abort_unless(isset($tables[$type]), 422, 'Jenis data koreksi tidak didukung.');
        abort_unless(DB::table($tables[$type])->where('id', $id)->where('branch_id', $branchId)->exists(), 404, 'Data koreksi tidak ditemukan di cabang ini.');
    }
}
