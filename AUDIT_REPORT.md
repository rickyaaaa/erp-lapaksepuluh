# Audit Report — ERP Lapak Sepuluh

Tanggal audit 27 November 2026

## Assessment

Checkout ini belum merupakan aplikasi Laravel lengkap. Tidak tersedia `composer.json`, `artisan`, `bootstrap/app.php`, `config/`, provider aplikasi, atau konfigurasi autentikasi. Controller, migration, route, dan service mengikuti konvensi Laravel, tetapi belum dapat dijalankan end-to-end tanpa scaffold dan dependency Laravel.

## Perbaikan diterapkan

### CRITICAL

- Payout hanya mengagregasi `stock_opnames.status = LOCKED_SETTLED`.
- Bukti inbound, closing, dan payout dipindahkan ke disk `local` dan tidak lagi dikembalikan sebagai URL publik.
- Endpoint unduh inbound dan lampiran closing tetap melakukan pemeriksaan cabang sebelum mengirim file.
- Payout tetap menolak pembayaran ganda lewat pemeriksaan aplikasi dan unique constraint database.
- Nominal pembayaran divalidasi terhadap total item payout di server.
- Payout transfer menyimpan pelaku dan waktu pembayaran dalam audit trail.

### HIGH

- JavaScript payout memakai status default `READY` saat status baris tidak tersedia.
- Listener payout yang terduplikasi saat memilih siklus dihapus.
- Semua file JavaScript dan PHP lolos pemeriksaan sintaks.

## Temuan yang masih terbuka

### CRITICAL

- Maker–checker belum dapat diverifikasi penuh karena tidak ada model User, role, permission, policy, gate, atau bootstrap autentikasi.
- Bukti lama yang sudah tersimpan pada disk `public` belum dimigrasikan. Tidak ada penghapusan otomatis.
- Endpoint bukti pengeluaran dan bukti payout khusus belum lengkap. Payload kini tidak mengekspos URL publik, tetapi akses unduh terautentikasi masih perlu ditambahkan untuk kedua jenis bukti.

### HIGH

- Frontend masih memakai data contoh dan belum terhubung ke API Laravel.
- Penyimpanan status sukses di frontend masih berupa mock.
- PHPUnit dan migration test belum dapat dijalankan karena dependency Laravel belum tersedia.
- Server demo masih menyajikan root repository lewat `server.js`, sehingga belum layak untuk deployment publik.

## Validasi yang dijalankan

- `php -l` untuk seluruh file PHP pada `app`, `database`, dan `routes`.
- `node --check` untuk seluruh file JavaScript.
- `npx ngodingpakeai sync --if-changed --plan` setelah perubahan task sebelumnya.
- PHPUnit dan migration runtime belum dijalankan karena scaffold Laravel tidak tersedia.

## Checklist QA manual setelah scaffold tersedia

- Payout dengan closing `SUBMITTED`, `VERIFIED`, `REJECTED`, dan `LOCKED_SETTLED`.
- Percobaan pembayaran ganda dan referensi bank duplikat.
- Akses bukti antar-cabang.
- Self-approval koreksi dan approval lintas role.
- Alur inbound → stok opname → closing → settlement → payout.
- Export CSV dibandingkan dengan total payout eligible.
