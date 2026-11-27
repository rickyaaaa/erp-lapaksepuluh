# Requirement Reconciliation — ERP Lapak Sepuluh

Tanggal: 27 November 2026

## Sumber yang diverifikasi

| Sumber | Status | Bukti |
|---|---|---|
| `docs/references/SYSTEM REQUIREMENT SPECIFICATION LAPAK10.docx` | tersedia | diekstrak ke `docs/SRS_EXTRACT.txt` |
| `docs/references/CONTOH SPREADSHEET L10.xlsx` | tersedia | Sheet1, 13 kolom operasional |
| `docs/references/prototype_appsheet_google_sheets_l10_10_cabang_mvp.html` | tersedia | diekstrak ke `docs/HTML_PROTOTYPE_EXTRACT.txt` |
| `erp-lapak-sepuluh-PRD.md` | tersedia | PRD terbaru |
| `PRODUCT.md` | tersedia | kontrak produk mock |
| `AGENTS.md` | tersedia | workflow NgodingPakeAI |

## Temuan utama sumber asli

- SRS menetapkan 10 cabang, 10 akun kasir, 2 akun SPV untuk cluster 5 cabang, 1 Manager/Owner, dan 1 Admin Pusat.
- SRS menetapkan state harian `OPEN_INBOUND` 05.00–07.00, `OPEN_CLOSING` 11.00–13.30, `MENUNGGU_CEK_SPV`, `TERVERIFIKASI_SPV`, lalu `LOCKED_SETTLED`.
- SRS mendefinisikan 10 tab relasional Google Sheets dan meminta payout hanya dari baris `LOCKED_SETTLED`.
- SRS mencatat complaint/refund sebagai potongan payout yang masih perlu kebijakan final.
- Spreadsheet mempertahankan kode partner seperti LS01, LS02, LS04, LS05, SKU sumber, nilai `1OK`, harga `12K` dan `15K`, serta baris lanjutan dengan partner kosong karena grouping visual.
- Prototype menampilkan 11 tab visual, role simulator 14 akun, jam simulasi berbeda dari SRS, dan bukti inbound opsional. Prototype adalah referensi UX, bukan sumber kebenaran database.

## Traceability matrix

| Requirement | Original SRS | Spreadsheet | HTML Prototype | Latest PRD | Existing Code | Final Decision | Status |
|---|---|---|---|---|---|---|---|
| 10 cabang dan role tetap | 10 kasir, 2 SPV cluster, Manager/Owner, Admin | Tidak ada tabel user | 14 akun simulator dan role switcher | RBAC dan branch access | Tidak ada User model/auth runtime | Gunakan assignment database dan RBAC server-side | MISSING |
| Inbound 05.00–07.00 | `MASUK_KASIR`, foto logbook TTD, lock 07.00 | Kolom MASUK dan partner grouping | Form inbound 06.00, foto opsional | Inbound schema/controller, scheduler lock | Foto wajib mengikuti SRS sampai ada keputusan lain | BACKEND PARTIAL |
| Opname 11.00–13.30 | `SISA_KASIR`, koreksi SPV sebelum 13.30 | SISA, TERJUAL | Quick edit stock dan simulator jam | Stock opname server-side | Opname schema/controller | Pertahankan formula dan validasi batas | BACKEND PARTIAL |
| Rumus finansial | Gross, commission, net per snapshot | Omset Kotor, Komisi Admin, Omset Bersih | Formula spreadsheet live | Snapshot wajib | `opname_items`, calculator | Server-side integer Rupiah | BACKEND PARTIAL |
| Blind closing | Cash, QRIS agregat, laci, bukti setor | Kolom closing | Closing screen dan variance | KLOP/MINUS/PLUS | Closing controller/calculator | System gross tidak boleh dibocorkan sebelum submit | BACKEND PARTIAL |
| Evidence | Foto logbook, settlement QRIS, slip setor, nota | Bukti per proses | Beberapa bukti opsional | Private storage | Sebagian endpoint dan disk local | Private authenticated download | BACKEND PARTIAL |
| SPV verification | Verifikasi bukti dan koreksi sebelum/after close | Koreksi columns | Review queue | Maker-checker after closing | Verification/correction endpoints | Role permission wajib | BACKEND PARTIAL |
| Manager bank confirmation | Cek mutasi masuk, transition `LOCKED_SETTLED` | Status closing | Mutasi bank simulator | Separate authorized step | Bank mutation endpoint | Upload receipt bukan settlement | BACKEND PARTIAL |
| Payout cycles | Senin–Rabu, Kamis–Sabtu, only settled | Payout totals | Two cycle views | Settled-only | Aggregation, cycles, export | Keep two official cycles | BACKEND PARTIAL |
| Partner grouping | Partner code and identity | Blank continuation rows | Grouped partner UI | Aggregate across branches | Payout service | Normalize blank partner rows from source | MISSING |
| Historical prices | SRS requires history | `12K`, `15K`, `1OK` exception | New-price UI examples | New prices 10K/15K | No importer/master price runtime | Preserve historical values, flag ambiguous labels | MISSING |
| Complaint/refund | Complaint tab and payout deduction | Not verified from extracted sample | Prototype mentions complaint flow | Policy unresolved | No complaint module | Keep as open decision | PROPOSED |
| Export bank | BRI/BNI template mentioned, official template required | No template in workbook sample | Export simulation | Generic until official template | Generic CSV | Do not claim bank compatibility | CONFIRMED |

## Readiness implication

The original sources confirm the business flow but also reveal unresolved differences in time gates, evidence requirement, complaint/refund, and bank export. M1–M2 remain unintegrated because the checkout still lacks Laravel runtime/authentication and a source-data importer.
