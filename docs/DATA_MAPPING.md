# Data Mapping — ERP Lapak Sepuluh

## Spreadsheet source

Workbook `docs/references/CONTOH SPREADSHEET L10.xlsx` berisi `Sheet1` dengan kolom:

`kode`, `Nama Mitra`, `Kode SKU`, `Nama Produk`, `HARGA`, `MASUK`, `SISA`, `TERJUAL`, `Omset Kotor`, `Komisi Admin`, `Omset Bersih`, `TOTAL PAYOUT`, `STATUS`.

Contoh yang harus dipertahankan saat import:

- Partner `LS01`, `LS02`, `LS04`, dan partner lain tidak boleh dinomori ulang.
- Baris lanjutan LS01 memiliki kolom partner kosong dan harus mewarisi partner sebelumnya secara eksplisit dengan catatan sumber baris.
- SKU `LS01-01` memakai label harga `1OK`, yang harus ditandai sebagai exception.
- Label `12K` dan `15K` harus disimpan sebagai harga historis setelah normalisasi yang dapat diaudit.
- Kuantitas kosong berbeda dari nilai nol dan tidak boleh diisi otomatis.
- Nilai sumber `TERJUAL`, `Omset Kotor`, `Komisi Admin`, dan `Omset Bersih` harus dibandingkan dengan hasil kalkulasi.

## Mapping ke model aplikasi

| Sumber | Target | Catatan |
|---|---|---|
| Kode partner | `partners.code` | Unique, preserve original |
| Nama partner | `partners.name` | Inherited only for blank continuation rows |
| Kode SKU | `products.sku_code` or import staging | Preserve source code |
| HARGA | historical price staging/product prices | `1OK` flagged, not silently rewritten |
| MASUK | inbound item quantity | Integer quantity |
| SISA | opname remaining quantity | Empty versus zero preserved |
| TERJUAL | derived opname quantity | Validate against source |
| Omset Kotor | derived gross amount | Integer Rupiah |
| Komisi Admin | derived commission | Snapshot per transaction |
| Omset Bersih | derived partner proceeds | Validate against source |
| TOTAL PAYOUT | payout reconciliation | Do not overwrite source evidence |
| STATUS | import validation status | Successful, rejected, flagged |

Importer must also store source workbook, sheet, source row, validation status, normalization notes, and rejection reason.
