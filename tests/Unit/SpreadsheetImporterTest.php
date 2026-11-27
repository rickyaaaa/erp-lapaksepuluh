<?php

namespace Tests\Unit;

use App\Services\L10SpreadsheetImporter;
use PHPUnit\Framework\TestCase;

final class SpreadsheetImporterTest extends TestCase
{
    public function test_source_workbook_dry_run_preserves_grouping_and_flags_ambiguous_prices(): void
    {
        $path = dirname(__DIR__, 2).'/docs/references/CONTOH SPREADSHEET L10.xlsx';
        $result = (new L10SpreadsheetImporter())->import($path, true);

        self::assertSame(252, count($result['rows']));
        self::assertGreaterThan(0, $result['success']);
        self::assertGreaterThan(0, $result['flagged']);
        self::assertSame('LS01', $result['rows'][0]['source_values']['partner_code']);
        self::assertSame('FLAGGED', $result['rows'][0]['status']);
    }
}
