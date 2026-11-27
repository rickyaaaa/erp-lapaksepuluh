<?php

namespace App\Console\Commands;

use App\Services\L10SpreadsheetImporter;
use Illuminate\Console\Command;

class ImportL10Spreadsheet extends Command
{
    protected $signature = 'l10:import-spreadsheet {path} {--commit : Persist validated rows instead of dry-run}';
    protected $description = 'Dry-run atau commit import workbook L10 dengan grouping partner dan row report.';

    public function handle(L10SpreadsheetImporter $importer): int
    {
        $result = $importer->import($this->argument('path'), !$this->option('commit'), $this->userId());
        $this->table(['Status', 'Jumlah'], [
            ['SUCCESS', $result['success']], ['FLAGGED', $result['flagged']], ['REJECTED', $result['rejected']],
        ]);
        $this->line($result['dry_run'] ? 'Dry-run selesai. Gunakan --commit setelah review.' : 'Import tersimpan.');

        return $result['rejected'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function userId(): ?int
    {
        return null;
    }
}
