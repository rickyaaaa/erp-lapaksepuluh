<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class StockOpnameController
{
    public function finalize(Request $request, int $opname): JsonResponse
    {
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $record = DB::table('stock_opnames')
            ->where('id', $opname)
            ->where('branch_id', $branchId)
            ->where('status', 'SUBMITTED')
            ->first(['id']);

        abort_unless($record !== null, 409, 'Opname harus berstatus SUBMITTED sebelum difinalisasi.');

        $totals = DB::table('opname_items')
            ->where('opname_id', $record->id)
            ->selectRaw('COALESCE(SUM(gross_amount), 0) AS gross')
            ->selectRaw('COALESCE(SUM(commission_amount), 0) AS commission')
            ->selectRaw('COALESCE(SUM(net_amount), 0) AS net')
            ->first();

        DB::table('stock_opnames')->where('id', $record->id)->update([
            'status' => 'VERIFIED',
            'total_gross' => $totals->gross,
            'total_commission' => $totals->commission,
            'total_net' => $totals->net,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Stok opname difinalisasi.',
            'id' => $record->id,
            'status' => 'VERIFIED',
            'total_gross' => (int) $totals->gross,
            'total_commission' => (int) $totals->commission,
            'total_net' => (int) $totals->net,
        ]);
    }

    public function summary(Request $request, int $opname): JsonResponse
    {
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $record = DB::table('stock_opnames')
            ->where('id', $opname)
            ->where('branch_id', $branchId)
            ->first(['id', 'business_date', 'status', 'total_gross', 'total_commission', 'total_net']);

        abort_unless($record !== null, 404, 'Stok opname tidak ditemukan.');

        $counts = DB::table('opname_items')
            ->where('opname_id', $record->id)
            ->selectRaw('COUNT(*) AS item_count')
            ->selectRaw('COALESCE(SUM(qty_in_final), 0) AS qty_in_final')
            ->selectRaw('COALESCE(SUM(qty_remaining_final), 0) AS qty_remaining_final')
            ->selectRaw('COALESCE(SUM(qty_damaged), 0) AS qty_damaged')
            ->selectRaw('COALESCE(SUM(qty_sold), 0) AS qty_sold')
            ->first();

        return response()->json([
            'data' => [
                'id' => $record->id,
                'business_date' => $record->business_date,
                'status' => $record->status,
                'item_count' => (int) $counts->item_count,
                'qty_in_final' => (int) $counts->qty_in_final,
                'qty_remaining_final' => (int) $counts->qty_remaining_final,
                'qty_damaged' => (int) $counts->qty_damaged,
                'qty_sold' => (int) $counts->qty_sold,
                'total_gross' => (int) $record->total_gross,
                'total_commission' => (int) $record->total_commission,
                'total_net' => (int) $record->total_net,
            ],
        ]);
    }

    public function updateDamaged(Request $request, int $opname, int $item): JsonResponse
    {
        $validated = $request->validate(['qty_damaged' => ['required', 'integer', 'min:0']]);
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $record = DB::table('opname_items')
            ->join('stock_opnames', 'stock_opnames.id', '=', 'opname_items.opname_id')
            ->where('opname_items.id', $item)
            ->where('opname_items.opname_id', $opname)
            ->where('stock_opnames.branch_id', $branchId)
            ->whereIn('stock_opnames.status', ['DRAFT', 'SUBMITTED'])
            ->first(['opname_items.id', 'opname_items.qty_remaining_final']);

        abort_unless($record !== null, 404, 'Item opname tidak ditemukan atau sudah terkunci.');
        abort_unless((int) $validated['qty_damaged'] <= (int) $record->qty_remaining_final, 422, 'Jumlah rusak tidak boleh melebihi sisa final.');

        DB::table('opname_items')->where('id', $record->id)->update([
            'qty_damaged' => $validated['qty_damaged'],
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Jumlah rusak tersimpan.',
            'id' => $record->id,
            'qty_damaged' => (int) $validated['qty_damaged'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'min:1'],
            'business_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inbound_item_id' => ['required', 'integer', 'min:1'],
            'items.*.qty_remaining_final' => ['required', 'integer', 'min:0'],
            'items.*.qty_damaged' => ['nullable', 'integer', 'min:0'],
        ]);

        $branchId = (int) $request->attributes->get('authorized_branch_id');
        abort_unless($branchId === (int) $validated['branch_id'], 403, 'Cabang tidak sesuai dengan akses pengguna.');

        $itemIds = collect($validated['items'])
            ->pluck('inbound_item_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $inboundItems = DB::table('inbound_items')
            ->join('inbounds', 'inbounds.id', '=', 'inbound_items.inbound_id')
            ->join('products', 'products.id', '=', 'inbound_items.product_id')
            ->whereIn('inbound_items.id', $itemIds)
            ->where('inbounds.branch_id', $branchId)
            ->whereDate('inbounds.business_date', $validated['business_date'])
            ->where('inbounds.status', 'LOCKED')
            ->get(['inbound_items.id', 'inbound_items.product_id', 'inbound_items.qty_in', 'inbound_items.price_snapshot', 'inbound_items.commission_snapshot', 'products.partner_id'])
            ->keyBy('id');

        abort_unless($inboundItems->count() === $itemIds->count(), 422, 'Semua item harus berasal dari inbound final cabang dan tanggal yang dipilih.');

        try {
            $opnameId = DB::transaction(function () use ($validated, $branchId, $inboundItems): int {
                $opnameId = (int) DB::table('stock_opnames')->insertGetId([
                    'branch_id' => $branchId,
                    'business_date' => $validated['business_date'],
                    'status' => 'SUBMITTED',
                    'submitted_by' => $validated['submitted_by'] ?? request()->user()->id,
                    'submitted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $totals = ['gross' => 0, 'commission' => 0, 'net' => 0];

                foreach ($validated['items'] as $input) {
                    $source = $inboundItems[(int) $input['inbound_item_id']];
                    $qtyRemaining = (int) $input['qty_remaining_final'];
                    $qtyDamaged = (int) ($input['qty_damaged'] ?? 0);
                    abort_unless($qtyRemaining <= $source->qty_in, 422, 'Sisa final tidak boleh melebihi jumlah masuk.');
                    abort_unless($qtyDamaged <= $qtyRemaining, 422, 'Jumlah rusak tidak boleh melebihi sisa final.');

                    $qtySold = $source->qty_in - $qtyRemaining;
                    $gross = $qtySold * $source->price_snapshot;
                    $commission = $qtySold * $source->commission_snapshot;
                    $net = $gross - $commission;
                    $totals['gross'] += $gross;
                    $totals['commission'] += $commission;
                    $totals['net'] += $net;

                    DB::table('opname_items')->insert([
                        'opname_id' => $opnameId,
                        'inbound_item_id' => $source->id,
                        'product_id' => $source->product_id,
                        'partner_id' => $source->partner_id,
                        'qty_in_final' => $source->qty_in,
                        'qty_remaining_final' => $qtyRemaining,
                        'qty_damaged' => $qtyDamaged,
                        'qty_sold' => $qtySold,
                        'price_snapshot' => $source->price_snapshot,
                        'commission_snapshot' => $source->commission_snapshot,
                        'gross_amount' => $gross,
                        'commission_amount' => $commission,
                        'net_amount' => $net,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('stock_opnames')->where('id', $opnameId)->update([
                    'total_gross' => $totals['gross'],
                    'total_commission' => $totals['commission'],
                    'total_net' => $totals['net'],
                    'updated_at' => now(),
                ]);

                return $opnameId;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                abort(409, 'Stok opname untuk cabang dan tanggal tersebut sudah ada.');
            }

            throw $exception;
        }

        return response()->json(['message' => 'Stok opname tersimpan.', 'id' => $opnameId, 'status' => 'SUBMITTED'], 201);
    }

    public function openingStock(Request $request): JsonResponse
    {
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $validated = $request->validate(['business_date' => ['required', 'date']]);

        $items = DB::table('inbounds')
            ->join('inbound_items', 'inbound_items.inbound_id', '=', 'inbounds.id')
            ->join('products', 'products.id', '=', 'inbound_items.product_id')
            ->join('partners', 'partners.id', '=', 'inbounds.partner_id')
            ->where('inbounds.branch_id', $branchId)
            ->whereDate('inbounds.business_date', $validated['business_date'])
            ->where('inbounds.status', 'LOCKED')
            ->orderBy('partners.name')
            ->orderBy('products.name')
            ->get([
                'inbound_items.id as inbound_item_id',
                'inbounds.id as inbound_id',
                'partners.id as partner_id',
                'partners.code as partner_code',
                'partners.name as partner_name',
                'products.id as product_id',
                'products.sku_code',
                'products.name as product_name',
                'inbound_items.qty_in',
                'inbound_items.price_snapshot',
                'inbound_items.commission_snapshot',
            ]);

        return response()->json([
            'data' => $items,
            'branch_id' => $branchId,
            'business_date' => $validated['business_date'],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $date = $request->date('business_date');

        $query = DB::table('stock_opnames')
            ->where('branch_id', $branchId)
            ->orderByDesc('business_date');

        if ($date !== null) {
            $query->whereDate('business_date', $date->toDateString());
        }

        return response()->json([
            'data' => $query->get(['id', 'branch_id', 'business_date', 'status', 'total_gross', 'total_commission', 'total_net']),
            'branch_id' => $branchId,
        ]);
    }

    public function show(Request $request, int $opname): JsonResponse
    {
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $record = DB::table('stock_opnames')
            ->where('id', $opname)
            ->where('branch_id', $branchId)
            ->first();

        abort_unless($record !== null, 404, 'Stok opname tidak ditemukan.');

        $items = DB::table('opname_items')
            ->join('products', 'products.id', '=', 'opname_items.product_id')
            ->where('opname_items.opname_id', $record->id)
            ->orderBy('products.name')
            ->get(['opname_items.product_id', 'products.sku_code', 'products.name', 'opname_items.qty_in_final', 'opname_items.qty_remaining_final', 'opname_items.qty_damaged', 'opname_items.qty_sold', 'opname_items.price_snapshot', 'opname_items.commission_snapshot', 'opname_items.gross_amount', 'opname_items.commission_amount', 'opname_items.net_amount']);

        return response()->json([
            'data' => [
                'id' => $record->id,
                'branch_id' => $record->branch_id,
                'business_date' => $record->business_date,
                'status' => $record->status,
                'total_gross' => $record->total_gross,
                'total_commission' => $record->total_commission,
                'total_net' => $record->total_net,
                'items' => $items,
            ],
        ]);
    }
}
