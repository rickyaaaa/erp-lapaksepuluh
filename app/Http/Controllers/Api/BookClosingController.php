<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookClosingController
{
    public function validateClosing(Request $request, int $bookClosing): JsonResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $record = DB::table('book_closings')
            ->where('id', $bookClosing)
            ->where('branch_id', $branchId)
            ->first();
        abort_unless($record !== null, 404, 'Tutup buku tidak ditemukan.');
        abort_if($record->status === 'CLOSED', 409, 'Tutup buku sudah divalidasi.');

        $closingCount = DB::table('daily_closings')
            ->where('branch_id', $branchId)
            ->whereBetween('business_date', [$record->period_start, $record->period_end])
            ->where('status', '!=', 'DRAFT')
            ->count();
        abort_if($closingCount === 0, 422, 'Belum ada daily closing yang dapat divalidasi.');

        DB::transaction(function () use ($bookClosing, $request, $validated, $record): void {
            DB::table('book_closings')->where('id', $bookClosing)->update([
                'status' => 'CLOSED', 'closed_by' => $request->user()->id,
                'closed_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('audit_trails')->insert([
                'branch_id' => $record->branch_id, 'actor_id' => $request->user()->id,
                'event' => 'BOOK_CLOSING_VALIDATED', 'auditable_type' => 'book_closing',
                'auditable_id' => $bookClosing, 'before_data' => json_encode(['status' => $record->status]),
                'after_data' => json_encode(['status' => 'CLOSED', 'note' => $validated['note'] ?? null]),
                'reason' => $validated['note'] ?? 'Validasi tutup buku oleh SPV.', 'created_at' => now(),
            ]);
        });

        return response()->json(['data' => DB::table('book_closings')->find($bookClosing)]);
    }
}
