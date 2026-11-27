<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankMutationController
{
    public function confirm(Request $request, int $mutation): JsonResponse
    {
        abort_unless($request->user()->hasPermission('bank.confirm'), 403, 'Permission konfirmasi bank tidak mencukupi.');
        $branchId = (int) $request->attributes->get('authorized_branch_id');
        $record = DB::table('bank_mutations')->where('id', $mutation)->where('branch_id', $branchId)->first();
        abort_unless($record !== null, 404, 'Mutasi bank tidak ditemukan.');
        abort_if($record->status === 'CONFIRMED', 409, 'Mutasi bank sudah dikonfirmasi.');

        DB::transaction(function () use ($mutation, $record, $request): void {
            DB::table('bank_mutations')->where('id', $mutation)->update([
                'status' => 'CONFIRMED', 'confirmed_by' => $request->user()->id,
                'confirmed_at' => now(), 'updated_at' => now(),
            ]);
            if ($record->book_closing_id !== null) {
                $bookClosing = DB::table('book_closings')->where('id', $record->book_closing_id)->first(['period_start', 'period_end']);
                abort_unless($bookClosing !== null, 404, 'Periode tutup buku tidak ditemukan.');
                DB::table('daily_closings')
                    ->where('branch_id', $record->branch_id)
                    ->whereBetween('business_date', [$bookClosing->period_start, $bookClosing->period_end])
                    ->whereIn('status', ['SUBMITTED', 'VERIFIED'])
                    ->update(['status' => 'LOCKED_SETTLED', 'updated_at' => now()]);
            }
            DB::table('audit_trails')->insert([
                'branch_id' => $record->branch_id, 'actor_id' => $request->user()->id,
                'event' => 'BANK_MUTATION_CONFIRMED', 'auditable_type' => 'bank_mutation',
                'auditable_id' => $mutation, 'before_data' => json_encode(['status' => $record->status]),
                'after_data' => json_encode(['status' => 'CONFIRMED', 'reference' => $record->reference, 'amount' => $record->amount]),
                'reason' => 'Konfirmasi mutasi bank oleh Manager.', 'created_at' => now(),
            ]);
        });

        return response()->json(['data' => DB::table('bank_mutations')->find($mutation)]);
    }
}
