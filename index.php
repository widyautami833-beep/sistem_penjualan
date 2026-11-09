
<?php
session_start();

/* =========================================
   1. KONEKSI DATABASE
========================================= */

$host = "localhost";
$dbname = "db_toko";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . htmlspecialchars($e->getMessage()));
}

/* =========================================
   2. MEMBUAT TABEL PRODUK DAN KATEGORI
========================================= */

$pdo->exec("
    CREATE TABLE IF NOT EXISTS tbl_kategori (
        id_kategori INT AUTO_INCREMENT PRIMARY KEY,
        nama_kategori VARCHAR(100) NOT NULL,
        deskripsi TEXT NULL
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS tbl_produk (
        id_produk INT AUTO_INCREMENT PRIMARY KEY,
        nama_produk VARCHAR(150) NOT NULL,
        id_kategori INT NOT NULL,
        harga DECIMAL(15,2) NOT NULL DEFAULT 0,
        stok INT NOT NULL DEFAULT 0,
        FOREIGN KEY (id_kategori)
            REFERENCES tbl_kategori(id_kategori)
            ON UPDATE CASCADE
    )
");

/* =========================================
   3. FUNGSI BANTU
========================================= */

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

function redirect($page = "dashboard") {
    header("Location: index.php?page=" . urlencode($page));
    exit;
}

function activity($pdo, $idTransaksi, $pesan) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_transaksi
            (id_transaksi, aktivitas, waktu)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$idTransaksi, $pesan]);
    } catch (PDOException $e) {
        // Log tidak menggagalkan transaksi utama.
    }
}

$page = $_GET['page'] ?? 'dashboard';
$message = "";

/* =========================================
   4. PROSES CETAK STRUK DAN PDF
========================================= */

if (isset($_GET['struk'])) {

    $idStruk = (int)$_GET['struk'];

    $stmt = $pdo->prepare("
        SELECT t.*, p.nama_pelanggan
        FROM tbl_transaksi t
        LEFT JOIN tbl_pelanggan p
            ON t.id_pelanggan = p.id_pelanggan
        WHERE t.id_transaksi = ?
    ");

    $stmt->execute([$idStruk]);
    $dataStruk = $stmt->fetch();

    if (!$dataStruk) {
        die("Transaksi tidak ditemukan.");
    }

    $stmt = $pdo->prepare("
        SELECT nama_produk, jumlah, harga
        FROM tbl_detail_transaksi
        WHERE id_transaksi = ?
    ");

    $stmt->execute([$idStruk]);
    $detailStruk = $stmt->fetchAll();

    $modePDF = isset($_GET['pdf']);
    ?>

    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Struk StoreFlow</title>

        <style>
            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                padding: 30px;
                background: #f1f5fb;
                font-family: Arial, sans-serif;
                color: #26364b;
            }

            .struk {
                max-width: 550px;
                margin: auto;
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 5px 25px #00000010;
            }

            .header {
                text-align: center;
                border-bottom: 2px dashed #ccd5e2;
                padding-bottom: 20px;
            }

            .header h1 {
                color: #3978d5;
                margin: 0 0 8px;
            }

            .informasi {
                margin: 20px 0;
                line-height: 2;
                font-size: 14px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th, td {
                padding: 12px 5px;
                text-align: left;
                border-bottom: 1px solid #eee;
                font-size: 13px;
            }

            .total {
                text-align: right;
                font-size: 20px;
                font-weight: bold;
                color: #3978d5;
                margin-top: 25px;
            }

            .footer {
                text-align: center;
                margin-top: 30px;
                color: #7c899b;
                font-size: 13px;
            }

            .tombol {
                max-width: 550px;
                margin: 20px auto;
                display: flex;
                gap: 10px;
            }

            .tombol button,
            .tombol a {
                flex: 1;
                padding: 12px;
                border: none;
                border-radius: 7px;
                text-align: center;
                text-decoration: none;
                cursor: pointer;
                font-size: 14px;
            }

            .cetak {
                background: #3978d5;
                color: white;
            }

            .kembali {
                background: #e5edf8;
                color: #26364b;
            }

            @media print {
                body {
                    padding: 0;
                    background: white;
                }

                .struk {
                    box-shadow: none;
                    max-width: 100%;
                }

                .tombol {
                    display: none;
                }
            }
        </style>
    </head>

    <body>

    <div class="struk">

        <div class="header">
            <h1>StoreFlow</h1>
            <p>STRUK PEMBELIAN</p>
            <small>Terima kasih telah berbelanja!</small>
        </div>

        <div class="informasi">
            <strong>Nomor Transaksi:</strong>
            #TRX-<?= e($dataStruk['id_transaksi']) ?><br>

            <strong>Tanggal:</strong>
            <?= e($dataStruk['tanggal']) ?><br>

            <strong>Pelanggan:</strong>
            <?= e($dataStruk['nama_pelanggan'] ?? '-') ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Jumlah</th>
                    <th>Harga</th>
                    <th>Subtotal</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($detailStruk as $d): ?>
                <tr>
                    <td><?= e($d['nama_produk']) ?></td>
                    <td><?= e($d['jumlah']) ?></td>
                    <td><?= rupiah($d['harga']) ?></td>
                    <td>
                        <?= rupiah($d['harga'] * $d['jumlah']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total">
            Total: <?= rupiah($dataStruk['total_belanja']) ?>
        </div>

        <div class="footer">
            <p>Terima kasih atas kunjungan Anda.</p>
            <strong>StoreFlow - Sistem Informasi Penjualan</strong>
        </div>

    </div>

    <div class="tombol">
        <button class="cetak" onclick="window.print()">
            Cetak / Simpan PDF
        </button>

        <a href="index.php?page=aktivitas" class="kembali">
            Kembali
        </a>
    </div>

    <?php if ($modePDF): ?>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
    <?php endif; ?>

    </body>
    </html>

    <?php
    exit;
}

/* =========================================
   5. PROSES TAMBAH / EDIT / HAPUS
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        /* TAMBAH PELANGGAN */
        if ($action === 'tambah_pelanggan') {

            $nama = trim($_POST['nama_pelanggan']);
            $kota = trim($_POST['kota']);

            if ($nama === '' || $kota === '') {
                throw new Exception("Nama dan kota harus diisi.");
            }

            $stmt = $pdo->prepare("
                INSERT INTO tbl_pelanggan
                (nama_pelanggan, kota)
                VALUES (?, ?)
            ");

            $stmt->execute([$nama, $kota]);

            $_SESSION['message'] = "Pelanggan berhasil ditambahkan.";
            redirect('pelanggan');
        }

        /* EDIT PELANGGAN */
        if ($action === 'edit_pelanggan') {

            $stmt = $pdo->prepare("
                UPDATE tbl_pelanggan
                SET nama_pelanggan = ?, kota = ?
                WHERE id_pelanggan = ?
            ");

            $stmt->execute([
                trim($_POST['nama_pelanggan']),
                trim($_POST['kota']),
                (int)$_POST['id_pelanggan']
            ]);

            $_SESSION['message'] = "Pelanggan berhasil diperbarui.";
            redirect('pelanggan');
        }

        /* HAPUS PELANGGAN */
        if ($action === 'hapus_pelanggan') {

            $id = (int)$_POST['id_pelanggan'];

            $stmt = $pdo->prepare("
                SELECT nama_pelanggan, kota
                FROM tbl_pelanggan
                WHERE id_pelanggan = ?
            ");

            $stmt->execute([$id]);
            $dataPelanggan = $stmt->fetch();

            if (!$dataPelanggan) {
                throw new Exception("Pelanggan tidak ditemukan.");
            }

            $cek = $pdo->prepare("
                SELECT COUNT(*)
                FROM tbl_transaksi
                WHERE id_pelanggan = ?
            ");

            $cek->execute([$id]);

            if ($cek->fetchColumn() > 0) {
                throw new Exception(
                    "Pelanggan memiliki riwayat transaksi dan tidak dapat dihapus."
                );
            }

            try {
                $log = $pdo->prepare("
                    INSERT INTO log_hapus_pelanggan
                    (id_pelanggan, nama_pelanggan, kota, waktu_hapus)
                    VALUES (?, ?, ?, NOW())
                ");

                $log->execute([
                    $id,
                    $dataPelanggan['nama_pelanggan'],
                    $dataPelanggan['kota']
                ]);
            } catch (PDOException $e) {
                // Tabel log boleh belum tersedia.
            }

            $stmt = $pdo->prepare("
                DELETE FROM tbl_pelanggan
                WHERE id_pelanggan = ?
            ");

            $stmt->execute([$id]);

            $_SESSION['message'] = "Pelanggan berhasil dihapus.";
            redirect('pelanggan');
        }

        /* TAMBAH KATEGORI */
        if ($action === 'tambah_kategori') {

            $nama = trim($_POST['nama_kategori']);
            $deskripsi = trim($_POST['deskripsi']);

            if ($nama === '') {
                throw new Exception("Nama kategori harus diisi.");
            }

            $stmt = $pdo->prepare("
                INSERT INTO tbl_kategori
                (nama_kategori, deskripsi)
                VALUES (?, ?)
            ");

            $stmt->execute([$nama, $deskripsi]);

            $_SESSION['message'] = "Kategori berhasil ditambahkan.";
            redirect('kategori');
        }

        /* EDIT KATEGORI */
        if ($action === 'edit_kategori') {

            $stmt = $pdo->prepare("
                UPDATE tbl_kategori
                SET nama_kategori = ?, deskripsi = ?
                WHERE id_kategori = ?
            ");

            $stmt->execute([
                trim($_POST['nama_kategori']),
                trim($_POST['deskripsi']),
                (int)$_POST['id_kategori']
            ]);

            $_SESSION['message'] = "Kategori berhasil diperbarui.";
            redirect('kategori');
        }

        /* HAPUS KATEGORI */
        if ($action === 'hapus_kategori') {

            $id = (int)$_POST['id_kategori'];

            $cek = $pdo->prepare("
                SELECT COUNT(*)
                FROM tbl_produk
                WHERE id_kategori = ?
            ");

            $cek->execute([$id]);

            if ($cek->fetchColumn() > 0) {
                throw new Exception(
                    "Kategori masih digunakan oleh produk."
                );
            }

            $stmt = $pdo->prepare("
                DELETE FROM tbl_kategori
                WHERE id_kategori = ?
            ");

            $stmt->execute([$id]);

            $_SESSION['message'] = "Kategori berhasil dihapus.";
            redirect('kategori');
        }

        /* TAMBAH PRODUK */
        if ($action === 'tambah_produk') {

            $nama = trim($_POST['nama_produk']);
            $kategoriID = (int)$_POST['id_kategori'];
            $harga = (float)$_POST['harga'];
            $stok = (int)$_POST['stok'];

            if ($nama === '' || $harga < 0 || $stok < 0) {
                throw new Exception("Data produk tidak valid.");
            }

            $stmt = $pdo->prepare("
                INSERT INTO tbl_produk
                (nama_produk, id_kategori, harga, stok)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $nama,
                $kategoriID,
                $harga,
                $stok
            ]);

            $_SESSION['message'] = "Produk berhasil ditambahkan.";
            redirect('produk');
        }

        /* EDIT PRODUK */
        if ($action === 'edit_produk') {

            $stmt = $pdo->prepare("
                UPDATE tbl_produk
                SET nama_produk = ?,
                    id_kategori = ?,
                    harga = ?,
                    stok = ?
                WHERE id_produk = ?
            ");

            $stmt->execute([
                trim($_POST['nama_produk']),
                (int)$_POST['id_kategori'],
                (float)$_POST['harga'],
                (int)$_POST['stok'],
                (int)$_POST['id_produk']
            ]);

            $_SESSION['message'] = "Produk berhasil diperbarui.";
            redirect('produk');
        }

        /* HAPUS PRODUK */
        if ($action === 'hapus_produk') {

            $id = (int)$_POST['id_produk'];

            $stmt = $pdo->prepare("
                DELETE FROM tbl_produk
                WHERE id_produk = ?
            ");

            $stmt->execute([$id]);

            $_SESSION['message'] = "Produk berhasil dihapus.";
            redirect('produk');
        }

        /* TAMBAH TRANSAKSI */
        if ($action === 'tambah_transaksi') {

            $idPelanggan = (int)$_POST['id_pelanggan'];
            $idProduk = (int)$_POST['id_produk'];
            $jumlah = (int)$_POST['jumlah'];

            if ($jumlah < 1) {
                throw new Exception("Jumlah produk minimal 1.");
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT nama_produk, harga, stok
                FROM tbl_produk
                WHERE id_produk = ?
                FOR UPDATE
            ");

            $stmt->execute([$idProduk]);
            $dataProduk = $stmt->fetch();

            if (!$dataProduk) {
                throw new Exception("Produk tidak ditemukan.");
            }

            if ($dataProduk['stok'] < $jumlah) {
                throw new Exception("Stok produk tidak mencukupi.");
            }

            $cekPelanggan = $pdo->prepare("
                SELECT COUNT(*)
                FROM tbl_pelanggan
                WHERE id_pelanggan = ?
            ");

            $cekPelanggan->execute([$idPelanggan]);

            if (!$cekPelanggan->fetchColumn()) {
                throw new Exception("Pelanggan tidak ditemukan.");
            }

            $total = $dataProduk['harga'] * $jumlah;

            $stmt = $pdo->prepare("
                INSERT INTO tbl_transaksi
                (tanggal, id_pelanggan, total_belanja)
                VALUES (CURDATE(), ?, ?)
            ");

            $stmt->execute([
                $idPelanggan,
                $total
            ]);

            $idTransaksi = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO tbl_detail_transaksi
                (id_transaksi, nama_produk, jumlah, harga)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $idTransaksi,
                $dataProduk['nama_produk'],
                $jumlah,
                $dataProduk['harga']
            ]);

            $stmt = $pdo->prepare("
                UPDATE tbl_produk
                SET stok = stok - ?
                WHERE id_produk = ?
            ");

            $stmt->execute([$jumlah, $idProduk]);

            activity(
                $pdo,
                $idTransaksi,
                "Transaksi berhasil dibuat."
            );

            $pdo->commit();

            $_SESSION['message'] = "Transaksi berhasil disimpan.";
            redirect('transaksi');
        }

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['message'] = $e->getMessage();
        redirect($page);
    }
}

/* =========================================
   6. PESAN NOTIFIKASI
========================================= */

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

/* =========================================
   7. MENGAMBIL DATA DATABASE
========================================= */

$totalPenjualan = $pdo->query("
    SELECT COALESCE(SUM(total_belanja), 0)
    FROM tbl_transaksi
")->fetchColumn();

$totalTransaksi = $pdo->query("
    SELECT COUNT(*) FROM tbl_transaksi
")->fetchColumn();

$totalProduk = $pdo->query("
    SELECT COUNT(*) FROM tbl_produk
")->fetchColumn();

$totalPelanggan = $pdo->query("
    SELECT COUNT(*) FROM tbl_pelanggan
")->fetchColumn();

$totalKategori = $pdo->query("
    SELECT COUNT(*) FROM tbl_kategori
")->fetchColumn();

$rataTransaksi = $totalTransaksi
    ? $totalPenjualan / $totalTransaksi
    : 0;

$pelanggan = $pdo->query("
    SELECT * FROM tbl_pelanggan
    ORDER BY id_pelanggan DESC
")->fetchAll();

$kategori = $pdo->query("
    SELECT * FROM tbl_kategori
    ORDER BY id_kategori DESC
")->fetchAll();

$produk = $pdo->query("
    SELECT p.*, k.nama_kategori
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k
        ON p.id_kategori = k.id_kategori
    ORDER BY p.id_produk DESC
")->fetchAll();

$transaksi = $pdo->query("
    SELECT t.*, p.nama_pelanggan
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    ORDER BY t.id_transaksi DESC
")->fetchAll();

/* DATA AKTIVITAS BESERTA INFORMASI TRANSAKSI */

$aktivitas = [];

try {
    $aktivitas = $pdo->query("
        SELECT
            l.id_transaksi,
            l.aktivitas,
            l.waktu,
            t.tanggal,
            t.total_belanja,
            p.nama_pelanggan
        FROM log_transaksi l
        LEFT JOIN tbl_transaksi t
            ON l.id_transaksi = t.id_transaksi
        LEFT JOIN tbl_pelanggan p
            ON t.id_pelanggan = p.id_pelanggan
        ORDER BY l.waktu DESC
    ")->fetchAll();

} catch (PDOException $e) {
    $aktivitas = [];
}

/* DATA GRAFIK */

$chartData = $pdo->query("
    SELECT MONTH(tanggal) AS bulan,
           SUM(total_belanja) AS total
    FROM tbl_transaksi
    GROUP BY MONTH(tanggal)
")->fetchAll();

$monthly = array_fill(0, 12, 0);

foreach ($chartData as $item) {
    $monthly[(int)$item['bulan'] - 1] = (float)$item['total'];
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard | StoreFlow</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f5f8fc;
    font-family: Inter, Arial, sans-serif;
    color: #26364b;
    font-size: 15px;
}

.sidebar {
    width: 245px;
    background: #172b4d;
    color: white;
    position: fixed;
    inset: 0 auto 0 0;
    padding: 25px 16px;
    z-index: 10;
}

.brand {
    font-size: 22px;
    font-weight: 800;
    padding: 10px;
    margin-bottom: 35px;
}

.brand span {
    color: #83b6ff;
}

.menu-label {
    color: #8fa3bf;
    font-size: 11px;
    margin: 20px 12px 12px;
    letter-spacing: 1px;
}

.nav-link {
    display: block;
    color: #c2cee0;
    text-decoration: none;
    padding: 13px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 5px;
}

.nav-link:hover,
.nav-link.active {
    background: #29466f;
    color: white;
}

.main {
    margin-left: 245px;
}

.topbar {
    min-height: 75px;
    background: white;
    border-bottom: 1px solid #e8edf4;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 30px;
    gap: 15px;
}

.topbar h3 {
    margin: 0 0 5px;
    font-size: 19px;
}

.topbar p {
    margin: 0;
    font-size: 13px;
    color: #8794a7;
}

.content {
    padding: 25px 30px;
}

.welcome {
    background: linear-gradient(110deg,#2e67b7,#4c8fe6);
    color: white;
    border-radius: 13px;
    padding: 25px;
    margin-bottom: 18px;
}

.welcome h2 {
    margin: 0 0 8px;
    font-size: 25px;
}

.welcome p {
    font-size: 14px;
    margin: 0;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 14px;
    margin-bottom: 15px;
}

.card,
.panel {
    background: white;
    border: 1px solid #e8edf4;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 8px 28px #1b375a0a;
}

.card small {
    color: #8794a7;
    font-size: 13px;
}

.card h2 {
    font-size: 22px;
    margin: 12px 0 5px;
}

.card p {
    color: #9aa6b5;
    font-size: 12px;
    margin: 0;
}

.grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.panel {
    margin-bottom: 15px;
    scroll-margin-top: 20px;
}

.panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.panel-header h3 {
    font-size: 18px;
    margin: 0 0 5px;
}

.panel-header p {
    font-size: 13px;
    color: #8794a7;
    margin: 0;
}

.btn {
    display: inline-block;
    border: 0;
    border-radius: 7px;
    padding: 10px 13px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}

.primary {
    background: #3978d5;
    color: white;
}

.light {
    background: #edf4ff;
    color: #3978d5;
}

.danger {
    background: #fff0f0;
    color: #d34b4b;
}

input,
select {
    padding: 11px;
    border: 1px solid #e8edf4;
    border-radius: 7px;
    font-size: 14px;
    width: 100%;
    background: white;
}

label {
    display: block;
    font-size: 13px;
    color: #65758b;
    margin-bottom: 6px;
    font-weight: 600;
}

.form-group {
    margin-bottom: 12px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

.table-wrap {
    overflow-x: auto;
}

th {
    text-align: left;
    color: #8794a7;
    font-size: 12px;
    padding: 13px 9px;
    border-bottom: 1px solid #e8edf4;
}

td {
    font-size: 13px;
    padding: 14px 9px;
    border-bottom: 1px solid #f0f2f6;
}

.status {
    background: #e7f7ef;
    color: #26945c;
    border-radius: 20px;
    padding: 5px 9px;
    font-size: 12px;
}

.alert {
    background: #e7f7ef;
    color: #247b50;
    padding: 13px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 14px;
}

.alert.error {
    background: #fff0f0;
    color: #b63838;
}

.footer {
    color: #9aa6b5;
    font-size: 12px;
    padding: 15px 0;
}

@media(max-width:1000px) {
    .stats {
        grid-template-columns: repeat(2,1fr);
    }
}

@media(max-width:750px) {
    .sidebar {
        position: static;
        width: 100%;
        padding: 12px;
    }

    .brand {
        margin: 0;
    }

    .menu-label {
        display: none;
    }

    .sidebar nav {
        display: flex;
        flex-wrap: wrap;
    }

    .nav-link {
        padding: 9px;
        margin: 2px;
        font-size: 12px;
    }

    .main {
        margin-left: 0;
    }

    .topbar {
        padding: 15px;
    }

    .content {
        padding: 15px;
    }

    .grid {
        grid-template-columns: 1fr;
    }

    .stats {
        grid-template-columns: 1fr 1fr;
    }
}

@media(max-width:480px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

    .stats {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<aside class="sidebar">

    <div class="brand">Store<span>Flow</span></div>

    <div class="menu-label">MENU UTAMA</div>

    <nav>
        <a href="index.php?page=dashboard"
           class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>">
           ▦ Dashboard
        </a>

        <a href="index.php?page=produk"
           class="nav-link <?= $page === 'produk' ? 'active' : '' ?>">
           ▣ Produk
        </a>

        <a href="index.php?page=kategori"
           class="nav-link <?= $page === 'kategori' ? 'active' : '' ?>">
           ▤ Kategori
        </a>

        <a href="index.php?page=pelanggan"
           class="nav-link <?= $page === 'pelanggan' ? 'active' : '' ?>">
           ♙ Pelanggan
        </a>

        <a href="index.php?page=transaksi"
           class="nav-link <?= $page === 'transaksi' ? 'active' : '' ?>">
           ▧ Transaksi
        </a>

        <a href="index.php?page=laporan"
           class="nav-link <?= $page === 'laporan' ? 'active' : '' ?>">
           ▥ Laporan Penjualan
        </a>

        <a href="index.php?page=stok"
           class="nav-link <?= $page === 'stok' ? 'active' : '' ?>">
           ◷ Stok Produk
        </a>

        <a href="index.php?page=aktivitas"
           class="nav-link <?= $page === 'aktivitas' ? 'active' : '' ?>">
           ◉ Aktivitas
        </a>
    </nav>

</aside>

<main class="main">

<header class="topbar">

    <div>
        <h3>Dashboard</h3>
        <p>Ringkasan aktivitas toko</p>
    </div>

    <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap">
        <small>
            <?= date('d F Y') ?> |
            <?= e($_SESSION['nama'] ?? 'Admin') ?>
        </small>

        <a href="logout.php"
           class="btn danger"
           onclick="return confirm('Yakin ingin logout?')">
            Logout
        </a>
    </div>

</header>

<section class="content">

<?php if ($message): ?>
    <div class="alert <?= strpos($message, 'gagal') !== false || strpos($message, 'tidak') !== false || strpos($message, 'belum') !== false ? 'error' : '' ?>">
        <?= e($message) ?>
    </div>
<?php endif; ?>


<!-- =====================================
     DASHBOARD
===================================== -->

<?php if ($page === 'dashboard'): ?>

    <div class="welcome">
        <h2>Selamat datang, Admin!</h2>
        <p>Berikut adalah ringkasan informasi penjualan tokomu.</p>
    </div>

    <div class="stats">

        <div class="card">
            <small>Total Penjualan</small>
            <h2><?= rupiah($totalPenjualan) ?></h2>
            <p>Akumulasi seluruh transaksi</p>
        </div>

        <div class="card">
            <small>Total Transaksi</small>
            <h2><?= $totalTransaksi ?></h2>
            <p>Transaksi tercatat</p>
        </div>

        <div class="card">
            <small>Total Produk</small>
            <h2><?= $totalProduk ?></h2>
            <p>Produk tersedia</p>
        </div>

        <div class="card">
            <small>Total Pelanggan</small>
            <h2><?= $totalPelanggan ?></h2>
            <p>Pelanggan terdaftar</p>
        </div>

    </div>

    <div class="stats">

        <div class="card">
            <small>Total Kategori</small>
            <h2><?= $totalKategori ?></h2>
        </div>

        <div class="card">
            <small>Rata-rata Transaksi</small>
            <h2><?= rupiah($rataTransaksi) ?></h2>
        </div>

    </div>

    <div class="panel">

        <div class="panel-header">
            <div>
                <h3>Grafik Penjualan</h3>
                <p>Statistik penjualan per bulan</p>
            </div>
        </div>

        <div style="height:260px">
            <canvas id="salesChart"></canvas>
        </div>

    </div>

    <div class="panel">

        <div class="panel-header">
            <div>
                <h3>Transaksi Terbaru</h3>
                <p>Daftar transaksi penjualan toko</p>
            </div>

            <a href="index.php?page=transaksi" class="btn primary">
                Lihat Transaksi
            </a>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID TRANSAKSI</th>
                        <th>TANGGAL</th>
                        <th>PELANGGAN</th>
                        <th>TOTAL BELANJA</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach (array_slice($transaksi, 0, 5) as $t): ?>
                    <tr>
                        <td>#TRX-<?= e($t['id_transaksi']) ?></td>
                        <td><?= e($t['tanggal']) ?></td>
                        <td><?= e($t['nama_pelanggan']) ?></td>
                        <td><?= rupiah($t['total_belanja']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$transaksi): ?>
                    <tr>
                        <td colspan="4">Belum ada transaksi.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>


<!-- =====================================
     PELANGGAN
===================================== -->

<?php elseif ($page === 'pelanggan'): ?>

    <div class="panel">

        <div class="panel-header">
            <div>
                <h3>Data Pelanggan</h3>
                <p>Kelola data pelanggan toko</p>
            </div>

            <button class="btn primary"
                onclick="showForm('formTambahPelanggan')">
                + Tambah Pelanggan
            </button>
        </div>

        <form id="formTambahPelanggan"
              method="POST"
              style="display:none"
              class="panel">

            <h3>Tambah Pelanggan</h3>

            <input type="hidden" name="action" value="tambah_pelanggan">

            <div class="form-grid">
                <div class="form-group">
                    <label>Nama Pelanggan</label>
                    <input type="text" name="nama_pelanggan" required>
                </div>

                <div class="form-group">
                    <label>Kota</label>
                    <input type="text" name="kota" required>
                </div>
            </div>

            <button class="btn primary">Simpan</button>

            <button type="button" class="btn light"
                onclick="showForm('formTambahPelanggan')">
                Batal
            </button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>NAMA PELANGGAN</th>
                        <th>KOTA</th>
                        <th>AKSI</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($pelanggan as $p): ?>
                    <tr>
                        <td><?= e($p['id_pelanggan']) ?></td>
                        <td><?= e($p['nama_pelanggan']) ?></td>
                        <td><?= e($p['kota']) ?></td>
                        <td>

                            <button class="btn light"
                                onclick='editPelanggan(
                                    <?= (int)$p['id_pelanggan'] ?>,
                                    <?= json_encode($p['nama_pelanggan']) ?>,
                                    <?= json_encode($p['kota']) ?>
                                )'>
                                Edit
                            </button>

                            <form method="POST" style="display:inline"
                                onsubmit="return confirm('Hapus pelanggan ini?')">

                                <input type="hidden" name="action" value="hapus_pelanggan">

                                <input type="hidden"
                                    name="id_pelanggan"
                                    value="<?= e($p['id_pelanggan']) ?>">

                                <button class="btn danger">Hapus</button>
                            </form>

                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$pelanggan): ?>
                    <tr>
                        <td colspan="4">Belum ada pelanggan.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="panel" id="editPelanggan" style="display:none">

        <h3>Edit Pelanggan</h3>

        <form method="POST">

            <input type="hidden" name="action" value="edit_pelanggan">
            <input type="hidden" name="id_pelanggan" id="edit_id">

            <div class="form-grid">
                <div class="form-group">
                    <label>Nama Pelanggan</label>
                    <input type="text" name="nama_pelanggan" id="edit_nama" required>
                </div>

                <div class="form-group">
                    <label>Kota</label>
                    <input type="text" name="kota" id="edit_kota" required>
                </div>
            </div>

            <button class="btn primary">Simpan Perubahan</button>

        </form>

    </div>


<!-- =====================================
     KATEGORI
===================================== -->

<?php elseif ($page === 'kategori'): ?>

    <div class="panel">

        <div class="panel-header">
            <div>
                <h3>Data Kategori</h3>
                <p>Kelola kategori produk</p>
            </div>

            <button class="btn primary"
                onclick="showForm('formTambahKategori')">
                + Tambah Kategori
            </button>
        </div>

        <form id="formTambahKategori"
              method="POST"
              style="display:none"
              class="panel">

            <h3>Tambah Kategori</h3>

            <input type="hidden" name="action" value="tambah_kategori">

            <div class="form-grid">
                <div class="form-group">
                    <label>Nama Kategori</label>
                    <input type="text" name="nama_kategori" required>
                </div>

                <div class="form-group">
                    <label>Deskripsi</label>
                    <input type="text" name="deskripsi">
                </div>
            </div>

            <button class="btn primary">Simpan</button>

            <button type="button" class="btn light"
                onclick="showForm('formTambahKategori')">
                Batal
            </button>

        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>NAMA KATEGORI</th>
                        <th>DESKRIPSI</th>
                        <th>AKSI</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($kategori as $k): ?>
                    <tr>
                        <td><?= e($k['id_kategori']) ?></td>
                        <td><?= e($k['nama_kategori']) ?></td>
                        <td><?= e($k['deskripsi']) ?></td>
                        <td>

                            <button class="btn light"
                                onclick='editKategori(
                                    <?= (int)$k['id_kategori'] ?>,
                                    <?= json_encode($k['nama_kategori']) ?>,
                                    <?= json_encode($k['deskripsi']) ?>
                                )'>
                                Edit
                            </button>

                            <form method="POST" style="display:inline"
                                onsubmit="return confirm('Hapus kategori ini?')">

                                <input type="hidden" name="action" value="hapus_kategori">

                                <input type="hidden"
                                    name="id_kategori"
                                    value="<?= e($k['id_kategori']) ?>">

                                <button class="btn danger">Hapus</button>
                            </form>

                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$kategori): ?>
                    <tr>
                        <td colspan="4">Belum ada kategori.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="panel" id="editKategori" style="display:none">

        <h3>Edit Kategori</h3>

        <form method="POST">

            <input type="hidden" name="action" value="edit_kategori">
            <input type="hidden" name="id_kategori" id="edit_kategori_id">

            <div class="form-grid">
                <div class="form-group">
                    <label>Nama Kategori</label>
                    <input type="text" name="nama_kategori"
                        id="edit_kategori_nama" required>
                </div>

                <div class="form-group">
                    <label>Deskripsi</label>
                    <input type="text" name="deskripsi"
                        id="edit_kategori_deskripsi">
                </div>
            </div>

            <button class="btn primary">Simpan Perubahan</button>

        </form>

    </div>


<!-- =====================================
     PRODUK
===================================== -->

<?php elseif ($page === 'produk'): ?>

    <div class="panel">

        <div class="panel-header">
            <div>
                <h3>Data Produk</h3>
                <p>Kelola produk dan stok toko</p>
            </div>

            <button class="btn primary"
                onclick="showForm('formTambahProduk')">
                + Tambah Produk
            </button>
        </div>

        <form id="formTambahProduk"
              method="POST"
              style="display:none"
              class="panel">

            <h3>Tambah Produk</h3>

            <input type="hidden" name="action" value="tambah_produk">

            <div class="form-grid">

                <div class="form-group">
                    <label>Nama Produk</label>
                    <input type="text" name="nama_produk" required>
                </div>

                <div class="form-group">
                    <label>Kategori</label>
                    <select name="id_kategori" required>
                        <?php foreach ($kategori as $k): ?>
                            <option value="<?= e($k['id_kategori']) ?>">
                                <?= e($k['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Harga</label>
                    <input type="number" name="harga" min="0" required>
                </div>

                <div class="form-group">
                    <label>Stok</label>
                    <input type="number" name="stok" min="0" required>
                </div>

            </div>

            <button class="btn primary">Simpan</button>

            <button type="button" class="btn light"
                onclick="showForm('formTambahProduk')">
                Batal
            </button>

        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>NAMA PRODUK</th>
                        <th>KATEGORI</th>
                        <th>HARGA</th>
                        <th>STOK</th>
                        <th>AKSI</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($produk as $p): ?>
                    <tr>
                        <td><?= e($p['id_produk']) ?></td>
                        <td><?= e($p['nama_produk']) ?></td>
                        <td><?= e($p['nama_kategori']) ?></td>
                        <td><?= rupiah($p['harga']) ?></td>
                        <td><?= e($p['stok']) ?></td>
                        <td>

                            <button class="btn light"
                                onclick='editProduk(
                                    <?= (int)$p['id_produk'] ?>,
                                    <?= json_encode($p['nama_produk']) ?>,
                                    <?= (int)$p['id_kategori'] ?>,
                                    <?= (float)$p['harga'] ?>,
                                    <?= (int)$p['stok'] ?>
                                )'>
                                Edit
                            </button>

                            <form method="POST" style="display:inline"
                                onsubmit="return confirm('Hapus produk ini?')">

                                <input type="hidden" name="action" value="hapus_produk">

                                <input type="hidden"
                                    name="id_produk"
                                    value="<?= e($p['id_produk']) ?>">

                                <button class="btn danger">Hapus</button>
                            </form>

                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$produk): ?>
                    <tr>
                        <td colspan="6">Belum ada produk.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="panel" id="editProduk" style="display:none">

        <h3>Edit Produk</h3>

        <form method="POST">

            <input type="hidden" name="action" value="edit_produk">
            <input type="hidden" name="id_produk" id="edit_produk_id">

            <div class="form-grid">

                <div class="form-group">
                    <label>Nama Produk</label>
                    <input type="text" name="nama_produk"
                        id="edit_produk_nama" required>
                </div>

                <div class="form-group">
                    <label>Kategori</label>
                    <select name="id_kategori" id="edit_produk_kategori">
                        <?php foreach ($kategori as $k): ?>
                            <option value="<?= e($k['id_kategori']) ?>">
                                <?= e($k['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Harga</label>
                    <input type="number" name="harga"
                        id="edit_produk_harga" min="0" required>
                </div>

                <div class="form-group">
                    <label>Stok</label>
                    <input type="number" name="stok"
                        id="edit_produk_stok" min="0" required>
                </div>

            </div>

            <button class="btn primary">Simpan Perubahan</button>

        </form>

    </div>


<!-- =====================================
     TRANSAKSI
===================================== -->

<?php elseif ($page === 'transaksi'): ?>

    <div class="panel">

        <div class="panel-header">
            <div>
                <h3>Data Transaksi</h3>
                <p>Kelola transaksi penjualan</p>
            </div>

            <button class="btn primary"
                onclick="showForm('formTransaksi')">
                + Buat Transaksi
            </button>
        </div>

        <form id="formTransaksi"
              method="POST"
              style="display:none"
              class="panel">

            <h3>Transaksi Baru</h3>

            <input type="hidden" name="action" value="tambah_transaksi">

            <div class="form-grid">

                <div class="form-group">
                    <label>Pelanggan</label>
                    <select name="id_pelanggan" required>
                        <?php foreach ($pelanggan as $p): ?>
                            <option value="<?= e($p['id_pelanggan']) ?>">
                                <?= e($p['nama_pelanggan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Produk</label>
                    <select name="id_produk" required>
                        <?php foreach ($produk as $p): ?>
                            <?php if ($p['stok'] > 0): ?>
                                <option value="<?= e($p['id_produk']) ?>">
                                    <?= e($p['nama_produk']) ?>
                                    - <?= rupiah($p['harga']) ?>
                                    (Stok <?= e($p['stok']) ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Jumlah</label>
                    <input type="number" name="jumlah"
                        min="1" value="1" required>
                </div>

            </div>

            <button class="btn primary">Simpan Transaksi</button>

            <button type="button" class="btn light"
                onclick="showForm('formTransaksi')">
                Batal
            </button>

        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID TRANSAKSI</th>
                        <th>TANGGAL</th>
                        <th>PELANGGAN</th>
                        <th>TOTAL BELANJA</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($transaksi as $t): ?>
                    <tr>
                        <td>#TRX-<?= e($t['id_transaksi']) ?></td>
                        <td><?= e($t['tanggal']) ?></td>
                        <td><?= e($t['nama_pelanggan']) ?></td>
                        <td><?= rupiah($t['total_belanja']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$transaksi): ?>
                    <tr>
                        <td colspan="4">Belum ada transaksi.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>


<!-- =====================================
     STOK PRODUK
===================================== -->

<?php elseif ($page === 'stok'): ?>

    <div class="panel">

        <div class="panel-header">
            <div>
                <h3>Stok Produk</h3>
                <p>Informasi ketersediaan produk</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>PRODUK</th>
                        <th>KATEGORI</th>
                        <th>STOK</th>
                        <th>STATUS</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($produk as $p): ?>
                    <tr>
                        <td><?= e($p['nama_produk']) ?></td>
                        <td><?= e($p['nama_kategori']) ?></td>
                        <td><?= e($p['stok']) ?></td>
                        <td>
                            <?php if ($p['stok'] <= 5): ?>
                                <span style="color:#d34b4b">
                                    Stok Rendah
                                </span>
                            <?php else: ?>
                                <span style="color:#26945c">
                                    Tersedia
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$produk): ?>
                    <tr>
                        <td colspan="4">Belum ada produk.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>


<!-- =====================================
     LAPORAN PENJUALAN
===================================== -->

<?php elseif ($page === 'laporan'): ?>

    <div class="panel">

        <div class="panel-header">
            <div>
                <h3>Laporan Penjualan</h3>
                <p>Rekap seluruh transaksi penjualan</p>
            </div>

            <button class="btn primary" onclick="window.print()">
                Cetak Laporan
            </button>
        </div>

        <h2><?= rupiah($totalPenjualan) ?></h2>
        <p>Total penjualan keseluruhan</p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID TRANSAKSI</th>
                        <th>TANGGAL</th>
                        <th>PELANGGAN</th>
                        <th>TOTAL</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($transaksi as $t): ?>
                    <tr>
                        <td><?= e($t['id_transaksi']) ?></td>
                        <td><?= e($t['tanggal']) ?></td>
                        <td><?= e($t['nama_pelanggan']) ?></td>
                        <td><?= rupiah($t['total_belanja']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$transaksi): ?>
                    <tr>
                        <td colspan="4">Belum ada transaksi.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>


<!-- =====================================
     AKTIVITAS + CETAK STRUK + PDF
===================================== -->

<?php elseif ($page === 'aktivitas'): ?>

    <div class="panel">

        <div class="panel-header">
            <div>
                <h3>Aktivitas Sistem</h3>
                <p>Riwayat aktivitas transaksi dan bukti pembelian</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>

                <thead>
                    <tr>
                        <th>ID TRANSAKSI</th>
                        <th>TANGGAL</th>
                        <th>PELANGGAN</th>
                        <th>AKTIVITAS</th>
                        <th>TOTAL</th>
                        <th>AKSI</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($aktivitas as $a): ?>

                    <tr>

                        <td>
                            #TRX-<?= e($a['id_transaksi']) ?>
                        </td>

                        <td>
                            <?= e($a['tanggal'] ?? $a['waktu']) ?>
                        </td>

                        <td>
                            <?= e($a['nama_pelanggan'] ?? '-') ?>
                        </td>

                        <td>
                            <?= e($a['aktivitas']) ?>
                        </td>

                        <td>
                            <?= rupiah($a['total_belanja'] ?? 0) ?>
                        </td>

                        <td>

                            <?php if (!empty($a['id_transaksi']) && $a['total_belanja'] !== null): ?>

                                <a
                                    href="index.php?struk=<?= (int)$a['id_transaksi'] ?>"
                                    target="_blank"
                                    class="btn light"
                                    style="margin-bottom:5px"
                                >
                                    Cetak Struk
                                </a>

                                <a
                                    href="index.php?struk=<?= (int)$a['id_transaksi'] ?>&pdf=1"
                                    target="_blank"
                                    class="btn primary"
                                >
                                    Download PDF
                                </a>

                            <?php else: ?>

                                <span style="color:#9aa6b5">
                                    Tidak tersedia
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$aktivitas): ?>
                    <tr>
                        <td colspan="6" style="text-align:center">
                            Belum ada aktivitas transaksi.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>
        </div>

    </div>

<?php endif; ?>


<div class="footer">
    © 2026 StoreFlow - Sistem Informasi Penjualan Toko
</div>

</section>
</main>


<!-- =====================================
     JAVASCRIPT
===================================== -->

<script>

function showForm(id) {

    const element = document.getElementById(id);

    if (element) {
        element.style.display =
            element.style.display === 'none' ? 'block' : 'none';
    }

}

function editPelanggan(id, nama, kota) {

    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_kota').value = kota;

    const form = document.getElementById('editPelanggan');

    form.style.display = 'block';
    form.scrollIntoView({behavior:'smooth'});

}

function editKategori(id, nama, deskripsi) {

    document.getElementById('edit_kategori_id').value = id;
    document.getElementById('edit_kategori_nama').value = nama;
    document.getElementById('edit_kategori_deskripsi').value = deskripsi;

    const form = document.getElementById('editKategori');

    form.style.display = 'block';
    form.scrollIntoView({behavior:'smooth'});

}

function editProduk(id, nama, kategori, harga, stok) {

    document.getElementById('edit_produk_id').value = id;
    document.getElementById('edit_produk_nama').value = nama;
    document.getElementById('edit_produk_kategori').value = kategori;
    document.getElementById('edit_produk_harga').value = harga;
    document.getElementById('edit_produk_stok').value = stok;

    const form = document.getElementById('editProduk');

    form.style.display = 'block';
    form.scrollIntoView({behavior:'smooth'});

}


/* GRAFIK DASHBOARD */

<?php if ($page === 'dashboard'): ?>

const chartData = <?= json_encode($monthly) ?>;

new Chart(document.getElementById('salesChart'), {

    type: 'line',

    data: {

        labels: [
            'Jan','Feb','Mar','Apr','Mei','Jun',
            'Jul','Agu','Sep','Okt','Nov','Des'
        ],

        datasets: [{

            label: 'Penjualan',

            data: chartData,

            borderColor: '#3978d5',

            backgroundColor: 'rgba(57,120,213,.12)',

            fill: true,

            tension: .35

        }]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        plugins: {
            legend: {
                display: false
            }
        },

        scales: {
            y: {
                beginAtZero: true
            }
        }

    }

});

<?php endif; ?>

</script>

</body>
</html>
