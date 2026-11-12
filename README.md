# TamtoyMart – Sistem Manajemen Toko

TamtoyMart adalah aplikasi berbasis web untuk membantu mengelola aktivitas toko, mulai dari pengelolaan produk, kategori, pelanggan, transaksi, stok barang, hingga laporan dan riwayat aktivitas.

Aplikasi ini dikembangkan menggunakan PHP Native dan MySQL dengan menerapkan konsep CRUD, relasi database, PDO, serta pengelolaan transaksi database.

## Fitur Utama

- **Dashboard** – Menampilkan ringkasan data dan informasi toko.
- **Data Produk** – Mengelola informasi produk, harga, kategori, dan stok.
- **Data Kategori** – Mengelola kategori produk.
- **Data Pelanggan** – Mengelola informasi pelanggan.
- **Transaksi** – Mencatat transaksi penjualan.
- **Manajemen Stok** – Memantau jumlah stok produk.
- **Laporan** – Menampilkan informasi transaksi.
- **Riwayat Aktivitas** – Mencatat aktivitas transaksi.
- **Cetak Struk** – Mencetak bukti transaksi.
- **Simpan PDF** – Menyimpan struk transaksi sebagai PDF melalui fitur cetak browser.
- **Autentikasi** – Mengelola proses login dan logout.
- **Manajemen Hak Akses** – Membatasi akses berdasarkan role pengguna.

## Role dan Hak Akses Pengguna

TamtoyMart memiliki tiga jenis role pengguna dengan hak akses yang berbeda.

### 1. Admin

Admin merupakan pengguna yang memiliki hak akses penuh untuk mengelola sistem toko.

Hak akses:
- Melihat dashboard admin.
- Mengelola data produk.
- Mengelola data kategori.
- Mengelola data pelanggan.
- Mengelola data transaksi.
- Mengelola stok produk.
- Melihat laporan penjualan.
- Melihat riwayat aktivitas.
- Mengelola akun pengguna sesuai fitur yang tersedia.

### 2. Kasir

Kasir merupakan pengguna yang bertugas melayani transaksi penjualan.

Hak akses:
- Mengakses dashboard kasir.
- Melihat informasi produk.
- Melakukan transaksi penjualan.
- Memproses pembayaran.
- Melihat rincian transaksi.
- Mencetak struk transaksi.
- Menyimpan struk dalam format PDF.

### 3. Pelanggan

Pelanggan merupakan pengguna yang dapat mengakses fitur yang disediakan untuk pelanggan.

Hak akses:
- Login ke akun pelanggan.
- Melihat informasi akun.
- Mengakses data pelanggan.
- Menggunakan fitur yang tersedia sesuai hak akses pelanggan.

## Teknologi yang Digunakan

- PHP Native
- MySQL / MariaDB
- PDO
- HTML
- CSS
- JavaScript
- Bootstrap (jika digunakan)

## Persyaratan Sistem

Pastikan perangkat sudah memiliki:

- XAMPP
- PHP
- MySQL atau MariaDB
- Browser
- Visual Studio Code (opsional)
- Git (opsional)

## Cara Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/USERNAME/sistem-penjualan.git
```

### 2. Pindahkan Folder Project

Pindahkan folder project ke:

```text
C:\xampp\htdocs\
```

### 3. Jalankan XAMPP

Aktifkan Apache dan MySQL melalui XAMPP Control Panel.

### 4. Buat Database

Buka phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Buat database dengan nama:

```text
db_toko
```

### 5. Import Database

1. Pilih database `db_toko`.
2. Klik menu Import.
3. Pilih file database SQL.
4. Klik Import dan tunggu hingga proses selesai.

### 6. Konfigurasi Database

Sesuaikan konfigurasi koneksi database pada file PHP:

```php
$host = "localhost";
$dbname = "db_toko";
$username = "root";
$password = "";
```

### 7. Jalankan Aplikasi

Buka browser dan akses:

```text
http://localhost/sistem-penjualan
```

## Struktur Project

```text
sistem-penjualan/
│
├── assets/
│   └── img/
│       └── produk/
│           ├── aqua-600ml.jpg
│           ├── aqua.jpg
│           ├── baterai-abc.jpg
│           ├── beras.jpg
│           ├── buku-sidu.jpg
│           ├── chitato.jpg
│           ├── fanta.jpg
│           ├── goodday.jpg
│           ├── indomie-goreng.jpg
│           ├── iphone.jpg
│           ├── le-minerale.jpg
│           ├── lifebuoy.jpg
│           ├── mie-sedaap.jpg
│           ├── oreo.jpg
│           ├── sari-roti.jpg
│           ├── silverqueen.jpg
│           ├── sunlight.jpg
│           ├── teh-pucuk.jpg
│           └── ultra-milk.jpg
│
├── kasir/
│   └── index.php
│
├── pelanggan/
│   ├── data.php
│   └── index.php
│
├── auth.php
├── buat_admin.php
├── buat_kasir.php
├── index.php
├── login.php
├── logout.php
├── reset_password.php
├── script.js
├── style.css
└── README.md
```

## Database

Database yang digunakan bernama `db_toko`, dengan tabel utama:

- `tbl_produk`
- `tbl_kategori`
- `tbl_pelanggan`
- `tbl_transaksi`
- `tbl_detail_transaksi`
- `log_transaksi`
- `log_hapus_pelanggan`
- `tbl_cabang`

## Keamanan Aplikasi

- Menggunakan PDO Prepared Statements untuk membantu mencegah SQL Injection.
- Menggunakan session untuk autentikasi pengguna.
- Menerapkan pembatasan hak akses berdasarkan role.
- Menggunakan password hashing.
- Melakukan validasi input sebelum data diproses.
- Menggunakan transaksi database untuk menjaga konsistensi data penjualan.

## Tujuan Pembuatan

TamtoyMart dibuat sebagai sarana pembelajaran pengembangan aplikasi web menggunakan PHP dan MySQL, khususnya dalam penerapan:

1. Create, Read, Update, Delete (CRUD).
2. Relasi antar tabel database.
3. Pemrograman PHP Native.
4. Koneksi database menggunakan PDO.
5. Autentikasi dan manajemen session.
6. Pengelolaan hak akses pengguna.
7. Transaksi database.
8. Pengelolaan stok produk.
9. Pembuatan laporan dan pencatatan aktivitas.
10. Pengembangan antarmuka aplikasi web.

## Pengembang

**TamtoyMart – Sistem Manajemen Toko**

Dikembangkan sebagai project pembelajaran Rekayasa Perangkat Lunak (RPL).

---

© 2026 TamtoyMart. All Rights Reserved.
