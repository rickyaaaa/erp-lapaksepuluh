<?php

use App\Http\Controllers\Api\InboundOptionsController;
use App\Http\Controllers\Api\StockOpnameController;
use App\Http\Controllers\Api\DailyClosingController;
use App\Http\Controllers\Api\CorrectionController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\BookClosingController;
use App\Http\Controllers\Api\BankMutationController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Middleware\EnsureBranchAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('inbound')->group(function (): void {
    Route::post('/', [InboundOptionsController::class, 'store']);
    Route::get('/final-for-opname', [InboundOptionsController::class, 'finalForOpname']);
    Route::get('/{inbound}/photo', [InboundOptionsController::class, 'photo'])->whereNumber('inbound');
    Route::get('/{inbound}', [InboundOptionsController::class, 'show'])->whereNumber('inbound');
    Route::get('/partners', [InboundOptionsController::class, 'partners']);
    Route::get('/products', [InboundOptionsController::class, 'products']);
});

Route::middleware(['web', 'auth', EnsureBranchAccess::class])->prefix('stock-opnames')->group(function (): void {
    Route::get('/opening-stock', [StockOpnameController::class, 'openingStock']);
    Route::post('/', [StockOpnameController::class, 'store']);
    Route::patch('/{opname}/items/{item}/damaged', [StockOpnameController::class, 'updateDamaged'])->whereNumber('opname')->whereNumber('item');
    Route::post('/{opname}/finalize', [StockOpnameController::class, 'finalize'])->whereNumber('opname');
    Route::get('/{opname}/summary', [StockOpnameController::class, 'summary'])->whereNumber('opname');
    Route::get('/', [StockOpnameController::class, 'index']);
    Route::get('/{opname}', [StockOpnameController::class, 'show'])->whereNumber('opname');
});

Route::middleware(['web', 'auth', EnsureBranchAccess::class])->prefix('daily-closings')->group(function (): void {
    Route::post('/', [DailyClosingController::class, 'store']);
    Route::post('/{closing}/expenses', [DailyClosingController::class, 'storeExpense'])->whereNumber('closing');
    Route::post('/{closing}/attachments', [DailyClosingController::class, 'storeAttachment'])->whereNumber('closing');
    Route::get('/{closing}/attachments/{attachment}', [DailyClosingController::class, 'evidence'])->whereNumber('closing')->whereNumber('attachment');
    Route::post('/{closing}/submit', [DailyClosingController::class, 'submit'])->whereNumber('closing');
    Route::get('/{closing}/summary', [DailyClosingController::class, 'summary'])->whereNumber('closing');
    Route::get('/', [DailyClosingController::class, 'show']);
});

Route::middleware(['web', 'auth', EnsureBranchAccess::class])->prefix('corrections')->group(function (): void {
    Route::get('/', [CorrectionController::class, 'index']);
    Route::post('/', [CorrectionController::class, 'store']);
    Route::post('/{correction}/review', [CorrectionController::class, 'review'])->whereNumber('correction');
    Route::post('/{correction}/manager-decision', [CorrectionController::class, 'managerDecision'])->whereNumber('correction');
    Route::post('/{correction}/apply', [CorrectionController::class, 'apply'])->whereNumber('correction');
    Route::get('/{correction}/payout-impact', [CorrectionController::class, 'payoutImpact'])->whereNumber('correction');
    Route::get('/audit', [CorrectionController::class, 'audit']);
});

Route::middleware(['web', 'auth', EnsureBranchAccess::class])->prefix('verification')->group(function (): void {
    Route::get('/comparison', [VerificationController::class, 'compare']);
});

Route::middleware(['web', 'auth', EnsureBranchAccess::class])->prefix('book-closings')->group(function (): void {
    Route::post('/{bookClosing}/validate', [BookClosingController::class, 'validateClosing'])->whereNumber('bookClosing');
});

Route::middleware(['web', 'auth', EnsureBranchAccess::class])->prefix('bank-mutations')->group(function (): void {
    Route::post('/{mutation}/confirm', [BankMutationController::class, 'confirm'])->whereNumber('mutation');
});

Route::middleware(['web', 'auth', EnsureBranchAccess::class])->prefix('payouts')->group(function (): void {
    Route::get('/', [PayoutController::class, 'index']);
    Route::get('/partners/{partner}/detail', [PayoutController::class, 'detail'])->whereNumber('partner');
    Route::get('/export', [PayoutController::class, 'export']);
    Route::get('/eligible', [PayoutController::class, 'eligible']);
    Route::get('/{cycle}/payment-history', [PayoutController::class, 'paymentHistory'])->whereNumber('cycle');
    Route::post('/{cycle}/partners/{partner}/paid', [PayoutController::class, 'markPaid'])->whereNumber('cycle')->whereNumber('partner');
});


