<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayoutAggregationService
{
    public function aggregate(CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $branchId = null): Collection
    {
        $query = DB::table('opname_items')
            ->join('stock_opnames', 'stock_opnames.id', '=', 'opname_items.opname_id')
            ->join('daily_closings', function ($join): void {
                $join->on('daily_closings.branch_id', '=', 'stock_opnames.branch_id')
                    ->on('daily_closings.business_date', '=', 'stock_opnames.business_date');
            })
            ->join('partners', 'partners.id', '=', 'opname_items.partner_id')
            ->join('products', 'products.id', '=', 'opname_items.product_id')
            ->whereBetween('stock_opnames.business_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->where('daily_closings.status', 'LOCKED_SETTLED')
            ->when($branchId !== null, fn ($builder) => $builder->where('stock_opnames.branch_id', $branchId))
            ->selectRaw('opname_items.partner_id, partners.code as partner_code, partners.name as partner_name, opname_items.product_id, products.sku_code, products.name as product_name, stock_opnames.branch_id, SUM(opname_items.qty_sold) as qty_sold, SUM(opname_items.gross_amount) as gross_amount, SUM(opname_items.commission_amount) as commission_amount, SUM(opname_items.net_amount) as payout_amount')
            ->groupBy('opname_items.partner_id', 'partners.code', 'partners.name', 'opname_items.product_id', 'products.sku_code', 'products.name', 'stock_opnames.branch_id')
            ->orderBy('partners.name')
            ->orderBy('products.name');

        return $query->get()->map(fn ($row): array => [
            'partner_id' => (int) $row->partner_id,
            'partner_code' => $row->partner_code,
            'partner_name' => $row->partner_name,
            'product_id' => (int) $row->product_id,
            'sku_code' => $row->sku_code,
            'product_name' => $row->product_name,
            'branch_id' => (int) $row->branch_id,
            'qty_sold' => (int) $row->qty_sold,
            'gross_amount' => (int) $row->gross_amount,
            'commission_amount' => (int) $row->commission_amount,
            'payout_amount' => (int) $row->payout_amount,
        ]);
    }
}
