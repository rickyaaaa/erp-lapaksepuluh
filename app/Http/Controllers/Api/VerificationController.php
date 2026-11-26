<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerificationController
{
    public function compare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_date' => ['required', 'date'],
        ]);
        $businessDate = $validated['business_date'];
        $branchId = (int) $request->attributes->get('authorized_branch_id');

        // Start from locked inbound rows so an item missing from the opname is
        // visible to the verifier instead of disappearing from the comparison.
        $rows = DB::table('inbounds')
            ->join('inbound_items', 'inbound_items.inbound_id', '=', 'inbounds.id')
            ->join('products', 'products.id', '=', 'inbound_items.product_id')
            ->leftJoin('partners', 'partners.id', '=', 'inbounds.partner_id')
            ->leftJoin('stock_opnames', function ($join) use ($businessDate): void {
                $join->on('stock_opnames.branch_id', '=', 'inbounds.branch_id')
                    ->whereDate('stock_opnames.business_date', $businessDate)
                    ->whereIn('stock_opnames.status', ['SUBMITTED', 'VERIFIED', 'REJECTED']);
            })
            ->leftJoin('opname_items', function ($join): void {
                $join->on('opname_items.inbound_item_id', '=', 'inbound_items.id')
                    ->on('opname_items.opname_id', '=', 'stock_opnames.id');
            })
            ->where('inbounds.branch_id', $branchId)
            ->whereDate('inbounds.business_date', $businessDate)
            ->where('inbounds.status', 'LOCKED')
            ->select([
                'opname_items.id as opname_item_id',
                'inbound_items.id as inbound_item_id',
                'inbound_items.product_id',
                'inbounds.partner_id',
                'partners.code as partner_code',
                'partners.name as partner_name',
                'products.sku_code',
                'products.name as product_name',
                'inbound_items.qty_in as inbound_qty',
                'opname_items.qty_in_final',
                'opname_items.qty_remaining_final',
                'opname_items.qty_damaged',
                'opname_items.qty_sold',
                'opname_items.price_snapshot',
                'opname_items.commission_snapshot',
                'opname_items.gross_amount',
                'opname_items.commission_amount',
                'opname_items.net_amount',
            ])
            ->orderBy('partners.name')
            ->orderBy('products.name')
            ->get()
            ->map(function ($row): array {
                $inboundQty = (int) $row->inbound_qty;
                $qtyInFinal = $row->qty_in_final === null ? null : (int) $row->qty_in_final;
                $qtyRemaining = $row->qty_remaining_final === null ? null : (int) $row->qty_remaining_final;
                $qtySold = $row->qty_sold === null ? null : (int) $row->qty_sold;
                $expectedSold = $qtyInFinal === null || $qtyRemaining === null
                    ? null
                    : $qtyInFinal - $qtyRemaining;

                return [
                    'opname_item_id' => $row->opname_item_id === null ? null : (int) $row->opname_item_id,
                    'inbound_item_id' => (int) $row->inbound_item_id,
                    'product_id' => (int) $row->product_id,
                    'partner_id' => (int) $row->partner_id,
                    'partner_code' => $row->partner_code,
                    'partner_name' => $row->partner_name,
                    'sku_code' => $row->sku_code,
                    'product_name' => $row->product_name,
                    'inbound_qty' => $inboundQty,
                    'qty_in_final' => $qtyInFinal,
                    'qty_in_variance' => $qtyInFinal === null ? null : $qtyInFinal - $inboundQty,
                    'qty_remaining_final' => $qtyRemaining,
                    'qty_damaged' => $row->qty_damaged === null ? null : (int) $row->qty_damaged,
                    'qty_sold' => $qtySold,
                    'qty_sold_expected' => $expectedSold,
                    'qty_sold_variance' => $qtySold === null || $expectedSold === null ? null : $qtySold - $expectedSold,
                    'price_snapshot' => $row->price_snapshot === null ? null : (int) $row->price_snapshot,
                    'commission_snapshot' => $row->commission_snapshot === null ? null : (int) $row->commission_snapshot,
                    'gross_amount' => $row->gross_amount === null ? null : (int) $row->gross_amount,
                    'commission_amount' => $row->commission_amount === null ? null : (int) $row->commission_amount,
                    'net_amount' => $row->net_amount === null ? null : (int) $row->net_amount,
                    'comparison_status' => $qtyInFinal === null || $qtySold === null || $expectedSold === null
                        ? 'MISSING_OPNAME'
                        : (($qtyInFinal - $inboundQty) === 0 && ($qtySold - $expectedSold) === 0 ? 'MATCH' : 'MISMATCH'),
                ];
            });

        $closing = DB::table('daily_closings')
            ->where('branch_id', $branchId)
            ->whereDate('business_date', $businessDate)
            ->first([
                'id', 'status', 'system_gross', 'counted_cash', 'qris_settlement',
                'total_valid_expenses', 'total_accountability', 'difference', 'difference_status',
            ]);

        $grossFromItems = (int) $rows->sum(fn (array $row): int => (int) ($row['gross_amount'] ?? 0));
        $qtyInVariance = (int) $rows->sum(fn (array $row): int => (int) ($row['qty_in_variance'] ?? 0));
        $qtySoldVariance = (int) $rows->sum(fn (array $row): int => (int) ($row['qty_sold_variance'] ?? 0));
        $grossVariance = $closing === null ? null : $grossFromItems - (int) $closing->system_gross;
        $needsReview = $rows->contains(fn (array $row): bool => $row['comparison_status'] !== 'MATCH')
            || ($grossVariance !== null && $grossVariance !== 0);

        return response()->json([
            'branch_id' => $branchId,
            'business_date' => $businessDate,
            'data' => $rows->values(),
            'summary' => [
                'item_count' => $rows->count(),
                'inbound_qty' => (int) $rows->sum('inbound_qty'),
                'qty_in_final' => (int) $rows->sum(fn (array $row): int => (int) ($row['qty_in_final'] ?? 0)),
                'qty_remaining_final' => (int) $rows->sum(fn (array $row): int => (int) ($row['qty_remaining_final'] ?? 0)),
                'qty_sold' => (int) $rows->sum(fn (array $row): int => (int) ($row['qty_sold'] ?? 0)),
                'gross_from_items' => $grossFromItems,
                'qty_in_variance' => $qtyInVariance,
                'qty_sold_variance' => $qtySoldVariance,
                'comparison_status' => $needsReview ? 'NEEDS_REVIEW' : 'MATCH',
            ],
            'closing' => $closing ? [
                'id' => (int) $closing->id,
                'status' => $closing->status,
                'system_gross' => (int) $closing->system_gross,
                'gross_variance' => $grossVariance,
                'counted_cash' => (int) $closing->counted_cash,
                'qris_settlement' => (int) $closing->qris_settlement,
                'total_valid_expenses' => (int) $closing->total_valid_expenses,
                'total_accountability' => (int) $closing->total_accountability,
                'difference' => (int) $closing->difference,
                'difference_status' => $closing->difference_status,
            ] : null,
        ]);
    }
}
