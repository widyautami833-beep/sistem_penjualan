# StoreFlow – Sistem Manajemen Toko

StoreFlow adalah aplikasi berbasis web untuk membantu mengelola aktivitas toko, mulai dari pengelolaan produk, kategori, pelanggan, transaksi, stok barang, hingga laporan dan riwayat aktivitas.

## Fitur Utama

- **Dashboard** – Menampilkan ringkasan data toko.
- **Data Produk** – Mengelola informasi produk, harga, dan stok.
- **Data Kategori** – Mengelola kategori produk.
- **Data Pelanggan** – Mengelola informasi pelanggan.
- **Transaksi** – Mencatat transaksi penjualan.
- **Manajemen Stok** – Memantau jumlah stok produk.
- **Laporan** – Menampilkan informasi transaksi.
- **Riwayat Aktivitas** – Mencatat aktivitas transaksi.
- **Cetak Struk** – Mencetak bukti transaksi.
- **Simpan PDF** – Menyimpan struk transaksi sebagai PDF melalui fitur cetak browser.

## Teknologi yang Digunakan

- PHP Native
- MySQL
- PDO
- HTML
- CSS
- JavaScript
- Bootstrap (jika digunakan pada project)

## Persyaratan

Pastikan perangkat sudah memiliki:

- XAMPP
- PHP
- MySQL atau MariaDB
- Browser
- Visual Studio Code (opsional)

## Cara Instalasi

1. Clone repository ini:

   ```bash
   git clone https://github.com/USERNAME/storeflow.git
   ```

2. Pindahkan folder project ke:

   ```text
   C:\xampp\htdocs\
   ```

3. Jalankan Apache dan MySQL melalui XAMPP Control Panel.

4. Buka phpMyAdmin:

   ```text
   http://localhost/phpmyadmin
   ```

5. Buat database dengan nama:

   ```text
   db_toko
   ```

6. Import file database SQL yang tersedia di repository.

7. Sesuaikan konfigurasi koneksi database pada file `index.php`:

   ```php
   $host = "localhost";
   $dbname = "db_toko";
   $username = "root";
   $password = "";
   ```

8. Jalankan aplikasi melalui browser:

   ```text
   http://localhost/storeflow
   ```

## Struktur Project

```text
storeflow/
│
├── index.php
├── login.php
├── logout.php
├── auth.php
├── README.md
└── database/
    └── db_toko.sql
```

*Struktur dapat disesuaikan dengan file yang tersedia di repository.*

## Database

Database yang digunakan bernama `db_toko`, dengan tabel utama seperti:

- `tbl_produk`
- `tbl_kategori`
- `tbl_pelanggan`
- `tbl_transaksi`
- `tbl_detail_transaksi`
- `log_transaksi`
- `log_hapus_pelanggan`
- `tbl_cabang`

## Tujuan Pembuatan

Project ini dibuat sebagai sarana pembelajaran pengembangan aplikasi web menggunakan PHP dan MySQL, khususnya dalam penerapan CRUD, relasi tabel, transaksi database, serta pengelolaan data toko.

## Pengembang

**StoreFlow – Sistem Manajemen Toko**

Dikembangkan sebagai project pembelajaran Rekayasa Perangkat Lunak (RPL).

---

© 2026 StoreFlow. All Rights Reserved.