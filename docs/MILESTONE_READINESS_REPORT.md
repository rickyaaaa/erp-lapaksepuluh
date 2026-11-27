# Milestone Readiness Review — ERP Lapak Sepuluh

Tanggal review: 27 November 2026

## Kesimpulan eksekutif

M1 dan M2 **belum terintegrasi dan belum teruji**. Checkout berisi prototype UI statis, route/controller/service bergaya Laravel, migration, dan kontrak API, tetapi tidak memiliki `composer.json`, `artisan`, `bootstrap/app.php`, `config/`, model User, provider, database runtime, atau autentikasi yang dapat dijalankan.

M3 dan M4 memiliki sebagian implementasi backend dan UI, tetapi belum memenuhi acceptance criteria produksi karena RBAC maker-checker, storage runtime, persistence frontend, dan automated feature tests belum tersedia.

Rekomendasi: **jangan mulai UAT dan jangan menyatakan M1–M2 selesai**. Kerjakan foundation Laravel dan authentication terlebih dahulu, lalu buktikan satu vertical slice persisten sebelum melanjutkan hardening M3 dan M4.

## Ketersediaan sumber

Tiga sumber asli kini sudah tersedia dan disalin ke `docs/references/`. Rekonsiliasi sebelumnya telah diperbarui menggunakan isi faktual sumber tersebut.

- `docs/references/SYSTEM REQUIREMENT SPECIFICATION LAPAK10.docx`
- `docs/references/CONTOH SPREADSHEET L10.xlsx`
- `docs/references/prototype_appsheet_google_sheets_l10_10_cabang_mvp.html`

Penilaian terhadap SRS, spreadsheet, dan prototype asli dibatasi pada requirement yang sudah direkonsiliasi di [REQUIREMENT_RECONCILIATION.md](REQUIREMENT_RECONCILIATION.md) dan PRD yang tersedia. Tidak ada isi file yang diasumsikan.

## Status per milestone

| Milestone | Status | Bukti | Kesimpulan |
|---|---|---|---|
| M1 Fondasi dan master data | BACKEND PARTIAL | Migration transaksi, branch middleware, controller options | NOT READY FOR UAT |
| M2 Operasional harian | BACKEND PARTIAL | Inbound, opname, calculator, closing controller, static UI | NOT READY FOR UAT |
| M3 Verifikasi dan koreksi | BACKEND PARTIAL | Correction, audit, book closing, bank mutation, verification endpoints | NOT READY FOR UAT |
| M4 Payout dan reporting | BACKEND PARTIAL | Payout schema, aggregation, export, payment endpoint, payout UI | NOT READY FOR UAT |

Tidak ada milestone yang dapat diklasifikasikan `INTEGRATED`, `TESTED`, atau `READY FOR UAT`.

## M1 — Authentication, RBAC, branch access, master data, historical mapping

**Classification: BACKEND PARTIAL**

### Existing implementation

- `EnsureBranchAccess` memeriksa `home_branch_id` dan `user_branches`.
- Route API memakai `auth:sanctum` secara deklaratif.
- Controller inbound membaca partner/product aktif dan branch assignment.
- Migration transaksi merujuk tabel `users`, `branches`, `partners`, dan `products`.

### Missing functionality

- Laravel application scaffold dan Composer dependencies.
- Model User, authentication provider, Sanctum setup, role, permission, policy, dan gate.
- 10 cashier accounts, 2 SPV clusters, Manager/Owner, Admin Pusat, dan configurable assignments.
- Partner/SKU master data persistence dan admin workflow.
- Historical spreadsheet importer, source row tracking, validation status, normalization notes, dan import report.
- Tests untuk authentication, RBAC, branch isolation, dan historical mapping.

### Technical blockers

- `composer.json`, `artisan`, `bootstrap/app.php`, `config/`, dan runtime database tidak tersedia.
- Tabel yang dirujuk migration belum memiliki migration master data pada checkout.
- Middleware belum dapat dieksekusi atau diuji tanpa Laravel runtime dan authenticated User.

### Business decisions required

- Sumber spreadsheet asli dan aturan data cleansing.
- Bank final dan mapping rekening.
- Assignment final SPV cluster dan permission granular.

### Priority and dependencies

**Priority: CRITICAL.** Dependensi pertama untuk semua milestone berikutnya.

### Acceptance criteria

- User dapat login dengan credential yang aman.
- Cashier hanya melihat satu branch.
- SPV hanya melihat branch assignment.
- Manager/Owner dan Admin Pusat memiliki permission lintas branch yang eksplisit.
- User tanpa permission menerima 401 atau 403 sesuai kondisi.
- Import report mempertahankan partner code, SKU, source row, harga historis, dan flag data quality.

## M2 — Inbound, stock opname, calculation, blind closing, evidence

**Classification: BACKEND PARTIAL**

### Existing implementation

- Inbound schema dan controller tersedia.
- Stock opname menghitung qty sold dan amount menggunakan snapshot.
- `DailyClosingCalculator` mengimplementasikan KLOP/MINUS/PLUS.
- Closing menyimpan cash, QRIS aggregate, expenses, attachments, dan submit state.
- Beberapa evidence endpoint melakukan branch check.
- Static pages tersedia untuk inbound, opname, dan closing.

### Missing functionality

- End-to-end persistence melalui aplikasi Laravel yang dapat dijalankan.
- Authenticated branch-aware UI integration.
- Full evidence download endpoints untuk expense proof dan payout proof.
- Private filesystem configuration and migration for existing public files.
- Blind closing verification that never reveals system gross before submit.
- Automated integration tests for inbound → opname → closing.
- Explicit lock behavior and approved override rules for the morning time gate.

### Technical blockers

- No Laravel runtime, database configuration, or storage configuration.
- Frontend uses static sample data and does not call API endpoints.
- Source evidence files and spreadsheet fixtures are missing.

### Business decisions required

- Whether inbound evidence is always mandatory.
- Exact 07.00 and 11.30 time gate behavior and SPV override policy.
- MINUS/PLUS tolerance and settlement policy.

### Priority and dependencies

**Priority: CRITICAL.** Depends on M1 foundation and is the prerequisite for M3 settlement and M4 payout.

### Acceptance criteria

- Cashier creates a persisted inbound for an assigned branch.
- Finalized inbound is the only source for stock opname.
- Backend rejects remaining stock above finalized incoming stock.
- Backend calculates sold, gross, commission, and net from snapshots.
- Cashier cannot see system gross before blind closing submission.
- Closing requires required evidence and stores it privately.
- Closing summary and variance are retrievable after submit.

## M3 — Supervisor verification, Manager bank confirmation, corrections, audit

**Classification: BACKEND PARTIAL**

### Existing implementation

- Verification comparison endpoint exists.
- Correction request, manager decision, controlled apply, payout impact, and audit routes exist.
- Audit trail has an immutable PostgreSQL trigger migration.
- Book closing state machine and bank mutation confirmation endpoints exist.
- Static verification, audit, and approval pages exist.

### Missing functionality

- Enforced role-based maker-checker authorization.
- Explicit prevention of requester self-approval.
- Permission separation for request, approve, apply, verify, and confirm bank mutation.
- Full evidence review/download across inbound, closing, expense, bank, and payout records.
- Runtime state transition tests and authorization tests.
- Controlled adjustment workflow after a paid payout based on approved business policy.

### Technical blockers

- No User model, role system, policies, gates, or Sanctum runtime.
- No Laravel feature test environment.
- Original SRS and prototype are missing, so verification details cannot be compared against primary sources.

### Business decisions required

- Post-paid correction adjustment/reversal policy.
- Bank confirmation authority and required evidence.
- MINUS/PLUS resolution policy.

### Priority and dependencies

**Priority: CRITICAL after M1–M2.** Must not be declared ready until M1–M2 vertical slice passes.

### Acceptance criteria

- SPV can request only for assigned branches.
- Manager can approve or reject only with explicit permission.
- Requester cannot approve the same request.
- Approved corrections apply atomically and create immutable audit records.
- Settled or paid records cannot be edited silently.
- Bank confirmation is a separate authorized transition to `LOCKED_SETTLED`.

## M4 — Payout cycles, aggregation, export, reporting, multi-branch dashboards

**Classification: BACKEND PARTIAL**

### Existing implementation

- Payout cycle, item, and payment migrations exist.
- Aggregation filters `LOCKED_SETTLED` stock opname records.
- Official Monday–Wednesday and Thursday–Saturday periods are validated.
- Partner, product, branch detail, generic CSV export, paid marking, payment history, and duplicate-payment constraint exist.
- Static payout and audit pages exist.

### Missing functionality

- User-permission-aware cross-branch aggregation in a real authenticated runtime.
- Payout cycle creation and approval state machine.
- Payment proof authenticated download endpoint.
- Dashboard/reporting endpoints for Cashier, SPV, Manager/Owner, and Admin Pusat.
- Official bank template validation. Current export is generic CSV only.
- Feature tests for settled-only aggregation, duplicate payments, cross-branch access, and export totals.

### Technical blockers

- No Laravel runtime or database migration execution.
- No official bank template.
- Frontend payout actions are still mock actions and do not persist through API.

### Business decisions required

- Final bank and export format.
- Payout approval state machine and authorized payment actor.
- Post-payment adjustment policy.

### Priority and dependencies

**Priority: HIGH after M1–M3.** Depends on settled closing, RBAC, correction policy, and bank confirmation.

### Acceptance criteria

- Only `LOCKED_SETTLED` records enter payable totals.
- A partner is paid at most once per cycle.
- Export totals equal eligible payout records.
- Cross-branch totals respect user permissions.
- Paid records store amount, reference, proof, actor, and timestamp.
- Generic export is labeled generic until the official bank template is validated.

## Automated test results

### Commands executed

- `phpunit --testdox` — **FAILED before test discovery**. The globally installed legacy PHPUnit calls removed PHP function `each()` under the current PHP runtime.
- `vendor/bin/phpunit --testdox` — **NOT AVAILABLE**. No `vendor/bin/phpunit` exists.
- `php artisan test` — **NOT AVAILABLE**. No `artisan` exists.
- `php -l` across 31 PHP files — **PASSED**, zero syntax errors.
- `node --check` across 8 JavaScript files — **PASSED**, zero syntax errors.

The four calculator tests are present in `tests/Unit/DailyClosingCalculatorTest.php`, but they were not executed successfully because the project has no compatible PHPUnit/Laravel test runtime.

## Recommended execution order

1. Obtain the three missing original reference files and confirm open business decisions.
2. Establish a minimal Laravel foundation with Composer, `artisan`, config, PostgreSQL connection, migrations, and Sanctum.
3. Implement User, roles, permissions, branch assignments, policies, and isolated authorization tests.
4. Add partner/product/SKU master data and spreadsheet import with an import report.
5. Integrate the inbound → stock opname → blind closing vertical slice with private evidence storage.
6. Add feature tests for M1–M2 and run them against an isolated test database.
7. Implement M3 maker-checker, verification, bank confirmation, and evidence review with authorization tests.
8. Implement M4 payout cycle approval, settled-only aggregation, generic export, payment proof access, and reporting.
9. Replace static mock actions with API calls and explicit loading, error, and persisted-success states.
10. Run UAT readiness review only after the M1–M2 acceptance criteria pass.

## UAT recommendation

M1–M2 do not pass readiness. The minimum corrective work before M3 UAT is Laravel bootstrapping, authentication/RBAC, master-data persistence, private storage configuration, one persisted inbound → opname → closing vertical slice, and executable automated tests for those flows.

No code was modified for this review.

