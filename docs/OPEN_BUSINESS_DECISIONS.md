# Open Business Decisions — ERP Lapak Sepuluh

Sumber asli sudah tersedia dan diverifikasi. Keputusan berikut tetap perlu persetujuan bisnis karena sumber berbeda atau belum memberikan keputusan final.

| Keputusan | Evidence sumber | Dampak | Status |
|---|---|---|---|
| Waktu operasional final | SRS 05.00–07.00 and 11.00–13.30. Prototype simulator shows 06.00, 11.15, 12.45, 14.15 | Scheduler, editable windows, UAT | OPEN |
| Bukti inbound wajib atau opsional | SRS meminta foto logbook TTD. Prototype menandai foto sebagai opsional | Validation and evidence policy | OPEN |
| Koreksi sebelum closing versus after closing | SRS memberi SPV koreksi sebelum 13.30. PRD menetapkan Manager approval untuk after closing | State machine and permissions | OPEN |
| Bank pengelola dan format export | SRS menyebut BRI/BNI, tetapi template resmi belum tersedia | Export schema and validation | OPEN |
| Complaint/refund deduction | SRS punya complaint and refund deduction, PRD belum mengonfirmasi MVP | Payout adjustment and liability | OPEN |
| Cash shortage settlement | SRS menyebut cash payment atau salary deduction. PRD belum menyetujui payroll policy | Closing resolution and HR scope | OPEN |
| Harga historis dan label `1OK` | Spreadsheet berisi `1OK`, `12K`, `15K`; PRD menetapkan harga baru 10K/15K | Import normalization and active price policy | OPEN DATA QUALITY |
| 10 relational tabs versus 11 visual tabs | SRS meminta 10 tabs, prototype menampilkan 11 tabs | Mapping only, not table count | OPEN |
| Partner code blank continuation rows | Spreadsheet visually groups rows under prior partner | Import relationship preservation | CONFIRMED TECHNICAL RULE |
