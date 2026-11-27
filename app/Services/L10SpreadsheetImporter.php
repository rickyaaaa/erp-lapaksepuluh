<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class L10SpreadsheetImporter
{
    public function import(string $path, bool $dryRun = true, ?int $actorId = null): array
    {
        $rows = $this->readRows($path);
        $result = ['source_file' => basename($path), 'dry_run' => $dryRun, 'rows' => [], 'success' => 0, 'flagged' => 0, 'rejected' => 0];
        $partnerCode = null;

        foreach ($rows as $row) {
            $code = trim((string) ($row['partner_code'] ?? ''));
            if ($code !== '') {
                $partnerCode = $code;
            }
            $row['partner_code'] = $partnerCode;
            $validation = $this->validateRow($row);
            $result['rows'][] = $validation + ['source_row' => $row['source_row'], 'source_values' => $row];
            $result[$validation['status'] === 'SUCCESS' ? 'success' : strtolower($validation['status'])]++;
        }

        if (!$dryRun) {
            $this->persist($result, $actorId);
        }

        return $result;
    }

    private function validateRow(array $row): array
    {
        if (!$row['partner_code'] || !$row['sku_code'] || !$row['product_name']) {
            return ['status' => 'REJECTED', 'error_message' => 'Partner, SKU, atau nama produk kosong setelah grouping.'];
        }
        $price = strtoupper(trim((string) $row['price_label']));
        if ($price === '1OK') {
            return ['status' => 'FLAGGED', 'normalization_notes' => 'Label harga 1OK ambigu dan tidak dinormalisasi.', 'error_message' => null];
        }
        if (!in_array($price, ['10K', '12K', '15K'], true)) {
            return ['status' => 'FLAGGED', 'normalization_notes' => 'Label harga tidak dikenali, nilai historis dipertahankan.', 'error_message' => null];
        }

        return ['status' => 'SUCCESS', 'normalization_notes' => 'Harga dinormalisasi ke integer Rupiah.', 'error_message' => null];
    }

    private function persist(array $result, ?int $actorId): void
    {
        DB::transaction(function () use ($result, $actorId): void {
            $batchId = DB::table('import_batches')->insertGetId([
                'source_file' => $result['source_file'], 'status' => 'COMMITTED', 'row_count' => count($result['rows']),
                'success_count' => $result['success'], 'flagged_count' => $result['flagged'], 'rejected_count' => $result['rejected'],
                'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($result['rows'] as $row) {
                DB::table('import_rows')->insert([
                    'import_batch_id' => $batchId, 'source_row' => $row['source_row'],
                    'partner_code' => $row['source_values']['partner_code'], 'sku_code' => $row['source_values']['sku_code'],
                    'status' => $row['status'], 'normalization_notes' => $row['normalization_notes'] ?? null,
                    'error_message' => $row['error_message'] ?? null, 'source_values' => json_encode($row['source_values']),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    private function readRows(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('File spreadsheet tidak ditemukan.');
        }
        $zip = new ZipArchive();
        abort_unless($zip->open($path) === true, 422, 'Spreadsheet tidak dapat dibuka.');
        $shared = $this->sharedStrings($zip);
        $sheet = new SimpleXMLElement($zip->getFromName('xl/worksheets/sheet1.xml'));
        $rows = [];
        foreach ($sheet->sheetData->row as $xmlRow) {
            $values = [];
            foreach ($xmlRow->c as $cell) {
                $ref = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $ref);
                $value = (string) $cell->v;
                if ((string) $cell['t'] === 's' && $value !== '') {
                    $value = $shared[(int) $value] ?? '';
                }
                $values[$column] = $value;
            }
            if ((int) $xmlRow['r'] === 1) {
                continue;
            }
            $rows[] = [
                'source_row' => (int) $xmlRow['r'], 'partner_code' => $values['A'] ?? '', 'product_name' => $values['D'] ?? '',
                'sku_code' => $values['C'] ?? '', 'price_label' => $values['E'] ?? '', 'qty_in' => $values['F'] ?? '', 'qty_remaining' => $values['G'] ?? '',
            ];
        }
        $zip->close();

        return $rows;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $strings = [];
        $root = new SimpleXMLElement($xml);
        $root->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($root->xpath('//m:si') as $item) {
            $item->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $texts = $item->xpath('.//m:t');
            $strings[] = implode('', array_map('strval', $texts ?: []));
        }
        return $strings;
    }
}
