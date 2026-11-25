<?php

use App\Http\Controllers\Api\InboundOptionsController;
use App\Http\Controllers\Api\StockOpnameController;
use App\Http\Controllers\Api\DailyClosingController;
use App\Http\Middleware\EnsureBranchAccess;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('inbound')->group(function (): void {
    Route::post('/', [InboundOptionsController::class, 'store']);
    Route::get('/final-for-opname', [InboundOptionsController::class, 'finalForOpname']);
    Route::get('/{inbound}/photo', [InboundOptionsController::class, 'photo'])->whereNumber('inbound');
    Route::get('/{inbound}', [InboundOptionsController::class, 'show'])->whereNumber('inbound');
    Route::get('/partners', [InboundOptionsController::class, 'partners']);
    Route::get('/products', [InboundOptionsController::class, 'products']);
});

Route::middleware(['auth:sanctum', EnsureBranchAccess::class])->prefix('stock-opnames')->group(function (): void {
    Route::get('/opening-stock', [StockOpnameController::class, 'openingStock']);
    Route::post('/', [StockOpnameController::class, 'store']);
    Route::patch('/{opname}/items/{item}/damaged', [StockOpnameController::class, 'updateDamaged'])->whereNumber('opname')->whereNumber('item');
    Route::post('/{opname}/finalize', [StockOpnameController::class, 'finalize'])->whereNumber('opname');
    Route::get('/{opname}/summary', [StockOpnameController::class, 'summary'])->whereNumber('opname');
    Route::get('/', [StockOpnameController::class, 'index']);
    Route::get('/{opname}', [StockOpnameController::class, 'show'])->whereNumber('opname');
});

Route::middleware(['auth:sanctum', EnsureBranchAccess::class])->prefix('daily-closings')->group(function (): void {
    Route::post('/', [DailyClosingController::class, 'store']);
    Route::post('/{closing}/expenses', [DailyClosingController::class, 'storeExpense'])->whereNumber('closing');
    Route::post('/{closing}/attachments', [DailyClosingController::class, 'storeAttachment'])->whereNumber('closing');
    Route::post('/{closing}/submit', [DailyClosingController::class, 'submit'])->whereNumber('closing');
    Route::get('/{closing}/summary', [DailyClosingController::class, 'summary'])->whereNumber('closing');
    Route::get('/', [DailyClosingController::class, 'show']);
});
