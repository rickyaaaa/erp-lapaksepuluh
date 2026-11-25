# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

delegated: mock statis mandiri untuk task frontend fase 1, dengan HTML, CSS, dan JavaScript tanpa dependensi build karena checkout belum memiliki scaffold aplikasi.

## Users

Kasir cabang Lapak Sepuluh yang mencatat barang titipan mitra pada jam operasional pagi melalui tablet atau layar sentuh.

## Product Purpose

ERP Lapak Sepuluh memusatkan pencatatan barang masuk, stok opname, closing, koreksi, payout, dan laporan untuk bisnis konsinyasi makanan. Keberhasilan layar ini berarti kasir dapat memilih mitra, mengisi jumlah produk dengan cepat, melampirkan bukti serah terima, lalu melihat ringkasan input sebelum menyimpan.

## Positioning

Sistem operasional terpusat yang menjaga snapshot harga dan komisi per transaksi, menghitung data finansial secara konsisten, dan membatasi akses berdasarkan cabang.

## Operating Context

Inbound dilakukan sekitar pukul 05.00–07.00 WIB di cabang. Kasir menerima barang, mencatat jumlah per produk, dan mengunggah foto logbook atau surat serah terima. Setelah pukul 07.00 input pagi terkunci dan data menjadi Masuk Final.

## Capabilities and Constraints

MVP mendukung multi-cabang hingga sepuluh cabang, satu mitra di beberapa cabang, harga resmi Rp10.000 dan Rp15.000, komisi yang dapat dikonfigurasi Admin Pusat, snapshot harga dan komisi, serta bukti foto. Layar operator harus ramah tablet, cepat dipindai, dan memakai bahasa Indonesia. Nilai pada mock diberi label data contoh.

## Brand Commitments

Nama produk adalah ERP Lapak Sepuluh atau L10. Bahasa antarmuka Indonesia. Istilah bisnis utama mencakup Barang Masuk, Mitra, SKU, Masuk Final, dan cabang.

## Evidence on Hand

PRD proyek tersedia di erp-lapak-sepuluh-PRD.md. Belum ada aplikasi, aset visual, logo, atau data produksi di checkout.

## Product Principles

- Input operasional harus cepat dan mudah dipindai.
- Perhitungan dan status harus terlihat sebelum data disimpan.
- Bukti dan histori menjadi bagian dari alur utama.
- Akses cabang membatasi data operator.
- Data contoh tidak boleh terlihat seperti data produksi.

## Accessibility & Inclusion

Tombol dan field dibuat besar untuk layar sentuh, fokus keyboard terlihat, kontras teks dijaga, dan status disampaikan dengan teks selain warna.
