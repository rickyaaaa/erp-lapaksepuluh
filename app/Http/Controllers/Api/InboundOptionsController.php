<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InboundOptionsController
{
    public function partners(Request $request): JsonResponse
    {
        $branchId = $this->branchId($request);

        $partners = DB::table('partners')
            ->join('products', 'products.partner_id', '=', 'partners.id')
            ->join('branch_products', function ($join) use ($branchId): void {
                $join->on('branch_products.product_id', '=', 'products.id')
                    ->where('branch_products.branch_id', $branchId)
                    ->where('branch_products.is_active', true);
            })
            ->where('partners.status', 'ACTIVE')
            ->where('products.is_active', true)
            ->select('partners.id', 'partners.code', 'partners.name')
            ->selectRaw('COUNT(DISTINCT products.id) AS active_products_count')
            ->groupBy('partners.id', 'partners.code', 'partners.name')
            ->orderBy('partners.name')
            ->get();

        return response()->json([
            'data' => $partners,
            'branch_id' => $branchId,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $branchId = $this->branchId($request);
        $partnerId = $request->integer('partner_id');

        $products = DB::table('products')
            ->join('partners', 'partners.id', '=', 'products.partner_id')
            ->join('branch_products', function ($join) use ($branchId): void {
                $join->on('branch_products.product_id', '=', 'products.id')
                    ->where('branch_products.branch_id', $branchId)
                    ->where('branch_products.is_active', true);
            })
            ->leftJoin('product_prices', function ($join) use ($branchId): void {
                $join->on('product_prices.product_id', '=', 'products.id')
                    ->where(function ($query) use ($branchId): void {
                        $query->whereNull('product_prices.branch_id')
                            ->orWhere('product_prices.branch_id', $branchId);
                    })
                    ->where('product_prices.status', 'ACTIVE')
                    ->whereDate('product_prices.effective_from', '<=', now()->toDateString())
                    ->where(function ($query): void {
                        $query->whereNull('product_prices.effective_to')
                            ->orWhereDate('product_prices.effective_to', '>=', now()->toDateString());
                    });
            })
            ->where('products.is_active', true)
            ->when($partnerId > 0, fn ($query) => $query->where('products.partner_id', $partnerId))
            ->select(
                'products.id',
                'products.sku_code',
                'products.name',
                'products.unit',
                'partners.id as partner_id',
                'partners.name as partner_name',
                'product_prices.price',
                'product_prices.commission',
            )
            ->orderBy('partners.name')
            ->orderBy('products.name')
            ->get();

        return response()->json([
            'data' => $products,
            'branch_id' => $branchId,
            'partner_id' => $partnerId > 0 ? $partnerId : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'min:1'],
            'partner_id' => ['required', 'integer', 'min:1'],
            'business_date' => ['required', 'date'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.qty_in' => ['required', 'integer', 'min:1'],
        ]);

        $branchId = $this->branchId($request);
        abort_unless($branchId === (int) $validated['branch_id'], 403, 'Cabang tidak sesuai dengan akses pengguna.');

        $itemProductIds = collect($validated['items'])
            ->pluck('product_id')
            ->map(fn ($productId): int => (int) $productId)
            ->unique()
            ->values();

        $activeProducts = DB::table('products')
            ->join('partners', 'partners.id', '=', 'products.partner_id')
            ->join('branch_products', function ($join) use ($branchId): void {
                $join->on('branch_products.product_id', '=', 'products.id')
                    ->where('branch_products.branch_id', $branchId)
                    ->where('branch_products.is_active', true);
            })
            ->where('products.partner_id', $validated['partner_id'])
            ->where('products.is_active', true)
            ->whereIn('products.id', $itemProductIds)
            ->select('products.id', 'products.name')
            ->get()
            ->keyBy('id');

        abort_unless($activeProducts->count() === $itemProductIds->count(), 422, 'Semua produk harus aktif di cabang dan milik mitra yang dipilih.');

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('inbounds', 'local')
            : null;

        try {
            $inboundId = DB::transaction(function () use ($validated, $branchId, $activeProducts, $photoPath): int {
                $inboundId = (int) DB::table('inbounds')->insertGetId([
                    'branch_id' => $branchId,
                    'partner_id' => $validated['partner_id'],
                    'business_date' => $validated['business_date'],
                    'photo_path' => $photoPath,
                    'status' => 'DRAFT',
                    'submitted_by' => $validated['submitted_by'] ?? request()->user()->id,
                    'submitted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($validated['items'] as $item) {
                    $productId = (int) $item['product_id'];
                    $price = DB::table('product_prices')
                        ->where('product_id', $productId)
                        ->where('status', 'ACTIVE')
                        ->whereDate('effective_from', '<=', $validated['business_date'])
                        ->where(function ($query) use ($branchId): void {
                            $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
                        })
                        ->where(function ($query) use ($validated): void {
                            $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $validated['business_date']);
                        })
                        ->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [$branchId])
                        ->orderByDesc('effective_from')
                        ->first(['price', 'commission']);

                    abort_unless($price !== null, 422, "Harga aktif tidak ditemukan untuk produk {$activeProducts[$productId]->name}.");

                    DB::table('inbound_items')->insert([
                        'inbound_id' => $inboundId,
                        'product_id' => $productId,
                        'qty_in' => $item['qty_in'],
                        'price_snapshot' => $price->price,
                        'commission_snapshot' => $price->commission,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return $inboundId;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                abort(409, 'Inbound untuk cabang, mitra, dan tanggal tersebut sudah ada.');
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Barang masuk tersimpan sebagai draft.',
            'id' => $inboundId,
            'status' => 'DRAFT',
            'photo_url' => null,
        ], 201);
    }

    public function show(Request $request, int $inbound): JsonResponse
    {
        $record = DB::table('inbounds')
            ->where('id', $inbound)
            ->first(['id', 'branch_id', 'partner_id', 'business_date', 'status', 'photo_path', 'locked_at']);

        abort_unless($record !== null, 404, 'Catatan barang masuk tidak ditemukan.');
        $this->branchId($request->merge(['branch_id' => $record->branch_id]));

        $items = DB::table('inbound_items')
            ->join('products', 'products.id', '=', 'inbound_items.product_id')
            ->where('inbound_items.inbound_id', $record->id)
            ->orderBy('products.name')
            ->get([
                'inbound_items.product_id',
                'products.sku_code',
                'products.name',
                'inbound_items.qty_in',
                'inbound_items.price_snapshot',
                'inbound_items.commission_snapshot',
            ]);

        $isFinal = $record->status === 'LOCKED';

        return response()->json([
            'data' => [
                'id' => $record->id,
                'branch_id' => $record->branch_id,
                'partner_id' => $record->partner_id,
                'business_date' => $record->business_date,
                'status' => $record->status,
                'is_final' => $isFinal,
                'final_label' => $isFinal ? 'Masuk Final' : null,
                'photo_path' => $record->photo_path,
                'photo_url' => null,
                'locked_at' => $record->locked_at,
                'items' => $items,
            ],
        ]);
    }

    public function photo(Request $request, int $inbound)
    {
        $record = DB::table('inbounds')
            ->where('id', $inbound)
            ->first(['branch_id', 'photo_path']);

        abort_unless($record !== null, 404, 'Catatan barang masuk tidak ditemukan.');
        $this->branchId($request->merge(['branch_id' => $record->branch_id]));
        abort_unless($record->photo_path !== null, 404, 'Bukti foto belum tersedia.');
        abort_unless(Storage::disk('local')->exists($record->photo_path), 404, 'Bukti foto tidak ditemukan.');

        return Storage::disk('local')->download($record->photo_path);
    }

    public function finalForOpname(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'min:1'],
            'business_date' => ['required', 'date'],
        ]);
        $branchId = $this->branchId($request);
        abort_unless($branchId === (int) $validated['branch_id'], 403, 'Cabang tidak sesuai dengan akses pengguna.');

        $inbounds = DB::table('inbounds')
            ->join('partners', 'partners.id', '=', 'inbounds.partner_id')
            ->where('inbounds.branch_id', $branchId)
            ->whereDate('inbounds.business_date', $validated['business_date'])
            ->where('inbounds.status', 'LOCKED')
            ->orderBy('partners.name')
            ->select('inbounds.id', 'inbounds.partner_id', 'partners.code as partner_code', 'partners.name as partner_name', 'inbounds.business_date', 'inbounds.status', 'inbounds.locked_at')
            ->get();

        $items = DB::table('inbound_items')
            ->join('products', 'products.id', '=', 'inbound_items.product_id')
            ->whereIn('inbound_items.inbound_id', $inbounds->pluck('id'))
            ->orderBy('products.name')
            ->get(['inbound_items.inbound_id', 'inbound_items.product_id', 'products.sku_code', 'products.name', 'inbound_items.qty_in', 'inbound_items.price_snapshot', 'inbound_items.commission_snapshot'])
            ->groupBy('inbound_id');

        return response()->json([
            'data' => $inbounds->map(function ($inbound) use ($items): array {
                return [
                    'id' => $inbound->id,
                    'partner_id' => $inbound->partner_id,
                    'partner_code' => $inbound->partner_code,
                    'partner_name' => $inbound->partner_name,
                    'business_date' => $inbound->business_date,
                    'status' => $inbound->status,
                    'is_final' => true,
                    'items' => $items->get($inbound->id, collect())->values(),
                ];
            })->values(),
            'branch_id' => $branchId,
            'business_date' => $validated['business_date'],
        ]);
    }

    private function branchId(Request $request): int
    {
        $branchId = $request->integer('branch_id');
        abort_if($branchId < 1, 422, 'branch_id wajib diisi.');

        $user = $request->user();
        $hasAccess = (int) $user->home_branch_id === $branchId
            || DB::table('user_branches')
                ->where('user_id', $user->id)
                ->where('branch_id', $branchId)
                ->exists();

        abort_unless($hasAccess, 403, 'Anda tidak memiliki akses ke cabang ini.');

        return $branchId;
    }
}
