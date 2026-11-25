# TamtoyMart – Sistem Manajemen Toko

TamtoyMart adalah aplikasi berbasis web yang dikembangkan untuk membantu mengelola aktivitas operasional toko secara lebih mudah, terstruktur, dan efisien. Aplikasi ini mencakup pengelolaan produk, kategori, pelanggan, transaksi penjualan, stok barang, laporan, hingga riwayat aktivitas toko.

## Fitur Utama

- **Dashboard** – Menampilkan ringkasan informasi dan data toko.
- **Data Produk** – Mengelola informasi produk, harga, dan stok barang.
- **Data Kategori** – Mengelola kategori produk.
- **Data Pelanggan** – Mengelola informasi dan data pelanggan.
- **Transaksi** – Mencatat dan mengelola transaksi penjualan.
- **Manajemen Stok** – Memantau dan mengelola ketersediaan stok produk.
- **Laporan Penjualan** – Menampilkan laporan pendapatan berdasarkan periode dan kasir.
- **Riwayat Aktivitas** – Mencatat aktivitas yang terjadi dalam sistem.
- **Cetak Struk** – Mencetak bukti transaksi penjualan.
- **Simpan PDF** – Menyimpan struk transaksi dalam format PDF melalui fitur cetak browser.
- **Manajemen Pengguna** – Mengelola akun dan hak akses pengguna sesuai peran.

## Teknologi yang Digunakan

- PHP Native
- MySQL / MariaDB
- PDO (PHP Data Objects)
- HTML
- CSS
- JavaScript
- Bootstrap (jika digunakan dalam project)

## Persyaratan Sistem

Sebelum menjalankan aplikasi, pastikan perangkat telah memiliki:

- XAMPP
- PHP
- MySQL atau MariaDB
- Web Browser
- Visual Studio Code (opsional)

## Cara Instalasi

1. Clone repository berikut:

   ```bash
   git clone https://github.com/USERNAME/tamtoymart.git
   ```

2. Pindahkan folder project ke direktori:

   ```text
   C:\xampp\htdocs\
   ```

3. Buka XAMPP Control Panel, kemudian jalankan **Apache** dan **MySQL**.

4. Buka phpMyAdmin melalui browser:

   ```text
   http://localhost/phpmyadmin
   ```

5. Buat database dengan nama:

   ```text
   db_toko_pulih
   ```

6. Import file database SQL yang tersedia pada folder `database`.

7. Sesuaikan konfigurasi koneksi database pada file PHP:

   ```php
   $host = "localhost";
   $dbname = "db_toko_pulih";
   $username = "root";
   $password = "";
   ```

8. Jalankan aplikasi melalui browser:

   ```text
   http://localhost/tamtoymart
   ```

## Struktur Project

```text
tamtoymart/
│
├── index.php
├── login.php
├── logout.php
├── auth.php
├── README.md
│
├── admin/
│   └── index.php
│
├── kasir/
│   └── index.php
│
├── pelanggan/
│   └── index.php
│
├── uploads/
│   └── bukti_pembayaran/
│
└── database/
    └── db_toko_pulih.sql
```

*Struktur folder dapat disesuaikan dengan susunan file yang digunakan dalam repository.*

## Database

Aplikasi TamtoyMart menggunakan database **`db_toko_pulih`** dengan beberapa tabel utama, antara lain:

- `users` – Menyimpan data akun pengguna dan hak akses.
- `tbl_produk` – Menyimpan data produk toko.
- `tbl_kategori` – Menyimpan data kategori produk.
- `tbl_pelanggan` – Menyimpan informasi pelanggan.
- `tbl_transaksi` – Menyimpan data transaksi penjualan.
- `tbl_detail_transaksi` – Menyimpan detail produk dalam transaksi.
- `tbl_bukti_pembayaran` – Menyimpan informasi bukti pembayaran pelanggan.
- `log_transaksi` – Mencatat riwayat aktivitas transaksi.
- `log_hapus_pelanggan` – Mencatat riwayat penghapusan data pelanggan.
- `tbl_cabang` – Menyimpan informasi cabang toko.

## Tujuan Pembuatan

TamtoyMart dikembangkan sebagai project pembelajaran Rekayasa Perangkat Lunak (RPL) untuk menerapkan pengetahuan dan keterampilan dalam pengembangan aplikasi web menggunakan PHP Native dan MySQL.

Melalui project ini, diharapkan dapat memahami penerapan CRUD, relasi database, PDO, autentikasi dan hak akses pengguna, pengelolaan transaksi, manajemen stok, serta pembuatan laporan penjualan.

## Pengembang

**TamtoyMart – Sistem Manajemen Toko**

Dikembangkan sebagai project pembelajaran Rekayasa Perangkat Lunak (RPL).

---

© 2026 TamtoyMart. All Rights Reserved.
