<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LockMorningInbounds extends Command
{
    protected $signature = 'inbounds:lock-morning';

    protected $description = 'Mengunci inbound pagi setelah cutoff operasional WIB';

    public function handle(): int
    {
        $now = CarbonImmutable::now('Asia/Jakarta');
        $lockTime = $this->lockTime();
        $cutoff = $now->setTimeFromTimeString($lockTime);

        if ($now->lessThan($cutoff)) {
            $this->line("Belum melewati cutoff {$lockTime} WIB.");

            return self::SUCCESS;
        }

        $lockedAt = $now->toDateTimeString();
        $locked = DB::table('inbounds')
            ->where('status', 'DRAFT')
            ->whereDate('business_date', '<=', $now->toDateString())
            ->update([
                'status' => 'LOCKED',
                'locked_at' => $lockedAt,
                'updated_at' => $lockedAt,
            ]);

        $this->info("{$locked} inbound dikunci pada {$now->format('Y-m-d H:i:s')} WIB.");

        return self::SUCCESS;
    }

    private function lockTime(): string
    {
        $setting = DB::table('system_settings')
            ->where('key', 'inbound_lock_time')
            ->value('value');

        if (is_string($setting)) {
            $decoded = json_decode($setting, true);

            if (is_string($decoded) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $decoded)) {
                return $decoded;
            }
        }

        return '07:00:00';
    }
}
