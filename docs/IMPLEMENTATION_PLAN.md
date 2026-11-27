# Implementation Plan — ERP Lapak Sepuluh

## Checkpoint A — Requirements reconciliation

Status: selesai secara dokumentasi.

- Sumber asli diperiksa keberadaannya.
- Sumber yang hilang dicatat tanpa mengarang isinya.
- Matriks traceability dibuat.
- Keputusan bisnis terbuka dicatat.
- Mapping data yang dapat diverifikasi dari checkout dibuat.

## Checkpoint B — Foundation and critical stabilization

Status: sebagian.

- Payout settled-only, private storage path, duplicate payment guard, dan beberapa audit trail sudah ada.
- Scaffold Laravel, Composer dependency, authentication, policies, dan runtime migration masih belum tersedia.
- Prioritas berikutnya adalah membangun foundation Laravel minimal setelah versi Laravel dan kredensial environment disediakan.

## Checkpoint C — M1/M2 integration

Status: belum siap diverifikasi.

Prototype statis dan kontrak API tersedia, tetapi belum ada Laravel runtime untuk menghubungkan UI ke database.

## Checkpoint D — Demo

Status: simulasi statis tersedia.

Demo belum boleh disebut sebagai persisted production workflow.

## Checkpoint E — M3 readiness

Status: sebagian.

Koreksi, audit, verifikasi bank, dan payout memiliki kontrak backend konvensional, tetapi role authorization dan test runtime belum dapat dibuktikan tanpa scaffold autentikasi.

## Prioritas teknis

1. Tambahkan scaffold Laravel resmi yang kompatibel dengan PHP target.
2. Tambahkan model User, role, permission, policy, dan Sanctum.
3. Tambahkan filesystem config dengan disk private dan endpoint download terotorisasi.
4. Tambahkan importer spreadsheet berdasarkan mapping workbook yang sudah direkonsiliasi, termasuk flag `1OK`, harga historis, dan baris partner kosong.
5. Integrasikan vertical slice inbound → opname → closing → settlement → payout.
6. Tambahkan PHPUnit feature tests dengan database terisolasi.

