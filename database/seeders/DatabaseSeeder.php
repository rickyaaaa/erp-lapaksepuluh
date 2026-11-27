<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (range(1, 10) as $number) {
            DB::table('branches')->updateOrInsert(
                ['code' => sprintf('CB-%02d', $number)],
                ['name' => 'Cabang Demo '.sprintf('%02d', $number), 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        foreach (['KASIR', 'SPV', 'MANAGER_OWNER', 'ADMIN_PUSAT'] as $role) {
            DB::table('roles')->updateOrInsert(['name' => $role], ['updated_at' => now(), 'created_at' => now()]);
        }
        foreach (['inbound.create', 'opname.create', 'closing.create', 'closing.verify', 'correction.request', 'correction.approve', 'correction.apply', 'bank.confirm', 'payout.approve', 'payout.mark_paid', 'payout.export', 'master.manage', 'audit.view', 'branch.view'] as $permission) {
            DB::table('permissions')->updateOrInsert(['name' => $permission], ['updated_at' => now(), 'created_at' => now()]);
        }
    }
}
