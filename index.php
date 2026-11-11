
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../auth.php';
wajibRole('kasir');

/* =========================================
   KONEKSI DATABASE
========================================= */

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=db_toko;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . htmlspecialchars($e->getMessage()));
}

/* =========================================
   FUNGSI
========================================= */

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function redirectKasir($page = 'dashboard')
{
    header("Location: index.php?page=" . urlencode($page));
    exit;
}

function tanggalIndonesia($tanggal)
{
    return date('d-m-Y H:i', strtotime($tanggal));
}

if (empty($_SESSION['csrf_kasir'])) {
    $_SESSION['csrf_kasir'] = bin2hex(random_bytes(32));
}

$namaKasir = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Kasir';

$page = $_GET['page'] ?? 'dashboard';

$halamanValid = [
    'dashboard',
    'produk',
    'transaksi',
    'riwayat',
    'detail'
];

if (!in_array($page, $halamanValid, true)) {
    $page = 'dashboard';
}

/* =========================================
   NOTIFIKASI
========================================= */

$pesan = $_SESSION['pesan_kasir'] ?? '';
$jenisPesan = $_SESSION['jenis_pesan_kasir'] ?? 'success';

unset($_SESSION['pesan_kasir'], $_SESSION['jenis_pesan_kasir']);

/* =========================================
   PROSES TRANSAKSI MULTI PRODUK
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buat_transaksi'])) {

    try {
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_kasir'], $_POST['csrf_token'])
        ) {
            throw new Exception('Permintaan tidak valid. Silakan muat ulang halaman.');
        }

        $idPelanggan = (int)($_POST['id_pelanggan'] ?? 0);
        $metode = $_POST['metode_pembayaran'] ?? '';
        $uangDiterima = (float)($_POST['uang_diterima'] ?? 0);

        $idProduk = $_POST['id_produk'] ?? [];
        $jumlahProduk = $_POST['jumlah'] ?? [];

        $metodeValid = [
            'Tunai',
            'Transfer Bank',
            'QRIS',
            'E-Wallet'
        ];

        if ($idPelanggan <= 0) {
            throw new Exception('Silakan pilih pelanggan.');
        }

        if (!in_array($metode, $metodeValid, true)) {
            throw new Exception('Metode pembayaran tidak valid.');
        }

        if (!is_array($idProduk) || !is_array($jumlahProduk)) {
            throw new Exception('Data produk tidak valid.');
        }

        if (count($idProduk) === 0) {
            throw new Exception('Tambahkan minimal satu produk.');
        }

        /*
         * Gabungkan produk yang sama jika dipilih lebih dari sekali.
         */
        $keranjang = [];

        foreach ($idProduk as $index => $id) {
            $id = (int)$id;
            $jumlah = (int)($jumlahProduk[$index] ?? 0);

            if ($id <= 0 || $jumlah <= 0) {
                throw new Exception('Produk dan jumlah pembelian harus diisi dengan benar.');
            }

            if (!isset($keranjang[$id])) {
                $keranjang[$id] = 0;
            }

            $keranjang[$id] += $jumlah;
        }

        $pdo->beginTransaction();

        /* Periksa pelanggan */

        $stmt = $pdo->prepare("
            SELECT id_pelanggan
            FROM tbl_pelanggan
            WHERE id_pelanggan = ?
        ");

        $stmt->execute([$idPelanggan]);

        if (!$stmt->fetch()) {
            throw new Exception('Pelanggan tidak ditemukan.');
        }

        /* Ambil dan kunci semua produk */

        $daftarDetail = [];
        $totalBelanja = 0;

        $stmtProduk = $pdo->prepare("
            SELECT id_produk, nama_produk, harga, stok
            FROM tbl_produk
            WHERE id_produk = ?
            FOR UPDATE
        ");

        foreach ($keranjang as $id => $jumlah) {

            $stmtProduk->execute([$id]);
            $item = $stmtProduk->fetch();

            if (!$item) {
                throw new Exception('Salah satu produk tidak ditemukan.');
            }

            if ((int)$item['stok'] < $jumlah) {
                throw new Exception(
                    'Stok ' . $item['nama_produk'] .
                    ' tidak mencukupi. Stok tersedia: ' . $item['stok']
                );
            }

            $harga = (float)$item['harga'];
            $subtotal = $harga * $jumlah;

            $totalBelanja += $subtotal;

            $daftarDetail[] = [
                'id_produk' => (int)$item['id_produk'],
                'nama_produk' => $item['nama_produk'],
                'jumlah' => $jumlah,
                'harga' => $harga,
                'subtotal' => $subtotal
            ];
        }

        /* Perhitungan pembayaran */

        if ($metode === 'Tunai') {

            if ($uangDiterima < $totalBelanja) {
                throw new Exception('Uang yang diterima masih kurang.');
            }

            $kembalian = $uangDiterima - $totalBelanja;

        } else {
            // Pembayaran non-tunai dikonfirmasi secara manual oleh kasir.
            $uangDiterima = 0;
            $kembalian = 0;
        }

        /* Simpan transaksi utama */

        $stmt = $pdo->prepare("
            INSERT INTO tbl_transaksi
            (
                tanggal,
                id_pelanggan,
                total_belanja,
                metode_pembayaran,
                uang_diterima,
                kembalian
            )
            VALUES (NOW(), ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $idPelanggan,
            $totalBelanja,
            $metode,
            $uangDiterima,
            $kembalian
        ]);

        $idTransaksi = $pdo->lastInsertId();

        /* Simpan semua detail produk */

        $stmtDetail = $pdo->prepare("
            INSERT INTO tbl_detail_transaksi
            (
                id_transaksi,
                id_produk,
                nama_produk,
                jumlah,
                harga
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmtStok = $pdo->prepare("
            UPDATE tbl_produk
            SET stok = stok - ?
            WHERE id_produk = ?
              AND stok >= ?
        ");

        foreach ($daftarDetail as $detail) {

            $stmtDetail->execute([
                $idTransaksi,
                $detail['id_produk'],
                $detail['nama_produk'],
                $detail['jumlah'],
                $detail['harga']
            ]);

            $stmtStok->execute([
                $detail['jumlah'],
                $detail['id_produk'],
                $detail['jumlah']
            ]);

            if ($stmtStok->rowCount() !== 1) {
                throw new Exception('Gagal memperbarui stok salah satu produk.');
            }
        }

        $pdo->commit();

        $_SESSION['pesan_kasir'] = 'Transaksi berhasil disimpan!';
        $_SESSION['jenis_pesan_kasir'] = 'success';

        header("Location: index.php?page=detail&id=" . $idTransaksi);
        exit;

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['pesan_kasir'] = $e->getMessage();
        $_SESSION['jenis_pesan_kasir'] = 'error';

        redirectKasir('transaksi');
    }
}

/* =========================================
   DATA DASHBOARD
========================================= */

$totalProduk = (int)$pdo->query("
    SELECT COUNT(*) FROM tbl_produk
")->fetchColumn();

$totalStok = (int)$pdo->query("
    SELECT COALESCE(SUM(stok), 0) FROM tbl_produk
")->fetchColumn();

$totalPelanggan = (int)$pdo->query("
    SELECT COUNT(*) FROM tbl_pelanggan
")->fetchColumn();

$totalTransaksi = (int)$pdo->query("
    SELECT COUNT(*) FROM tbl_transaksi
")->fetchColumn();

$totalPenjualan = (float)$pdo->query("
    SELECT COALESCE(SUM(total_belanja), 0)
    FROM tbl_transaksi
")->fetchColumn();

/* =========================================
   DATA PRODUK
========================================= */

$produk = $pdo->query("
    SELECT id_produk, nama_produk, harga, stok, gambar
    FROM tbl_produk
    ORDER BY nama_produk ASC
")->fetchAll();

/* =========================================
   DATA PELANGGAN
========================================= */

$pelanggan = $pdo->query("
    SELECT id_pelanggan, nama_pelanggan
    FROM tbl_pelanggan
    ORDER BY nama_pelanggan ASC
")->fetchAll();

/* =========================================
   RIWAYAT TRANSAKSI
========================================= */

$riwayat = $pdo->query("
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        t.metode_pembayaran,
        p.nama_pelanggan
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    ORDER BY t.id_transaksi DESC
")->fetchAll();

/* =========================================
   DETAIL STRUK
========================================= */

$detailTransaksi = null;
$detailProduk = [];

if ($page === 'detail') {

    $idDetail = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT
            t.*,
            p.nama_pelanggan
        FROM tbl_transaksi t
        LEFT JOIN tbl_pelanggan p
            ON t.id_pelanggan = p.id_pelanggan
        WHERE t.id_transaksi = ?
    ");

    $stmt->execute([$idDetail]);
    $detailTransaksi = $stmt->fetch();

    if ($detailTransaksi) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM tbl_detail_transaksi
            WHERE id_transaksi = ?
        ");

        $stmt->execute([$idDetail]);
        $detailProduk = $stmt->fetchAll();
    }
}

/* =========================================
   GRAFIK PENJUALAN 7 HARI
========================================= */

$grafik = $pdo->query("
    SELECT
        DATE(tanggal) AS tanggal,
        SUM(total_belanja) AS total
    FROM tbl_transaksi
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(tanggal)
    ORDER BY tanggal ASC
")->fetchAll();

$labelGrafik = [];
$dataGrafik = [];

foreach ($grafik as $g) {
    $labelGrafik[] = date('d/m', strtotime($g['tanggal']));
    $dataGrafik[] = (float)$g['total'];
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TamtoyMart | Kasir</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root {
    --primary: #3978d7;
    --primary-dark: #245eb7;
    --blue-light: #edf4ff;
    --background: #f5f8fd;
    --white: #fff;
    --text: #26364d;
    --muted: #8190a5;
    --border: #e8edf5;
    --green: #1eaa78;
    --red: #df5b65;
    --shadow: 0 5px 22px rgba(40,77,130,.06);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Inter, sans-serif;
    background: var(--background);
    color: var(--text);
}

a {
    text-decoration: none;
    color: inherit;
}

.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: white;
    border-right: 1px solid var(--border);
    padding: 27px 18px;
    display: flex;
    flex-direction: column;
    z-index: 10;
}

.brand {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 0 10px;
    margin-bottom: 42px;
}

.brand-mark {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: var(--primary);
    color: white;
    display: grid;
    place-items: center;
    font-size: 19px;
    font-weight: 800;
}

.brand-name {
    font-size: 18px;
    font-weight: 800;
}

.brand-name span {
    color: var(--primary);
}

.menu-label {
    color: #a1adbd;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.2px;
    padding: 0 12px;
    margin-bottom: 12px;
}

.menu {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.menu a {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 14px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    color: #78879b;
    transition: .2s;
}

.menu a:hover,
.menu a.active {
    background: var(--blue-light);
    color: var(--primary);
}

.menu-icon {
    width: 21px;
    text-align: center;
    font-size: 16px;
}

.sidebar-bottom {
    margin-top: auto;
    border-top: 1px solid var(--border);
    padding-top: 18px;
}

.profile-mini {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    margin-bottom: 10px;
}

.avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #e5efff;
    color: var(--primary);
    display: grid;
    place-items: center;
    font-weight: 800;
}

.profile-mini strong {
    font-size: 12px;
    display: block;
}

.profile-mini small {
    font-size: 10px;
    color: var(--muted);
}

.logout {
    display: block;
    padding: 12px;
    color: #d65a66;
    font-size: 13px;
    font-weight: 600;
    border-radius: 9px;
}

.logout:hover {
    background: #fff1f2;
}

.main {
    margin-left: 250px;
    padding: 30px 35px;
    min-height: 100vh;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 30px;
    gap: 15px;
}

.topbar h1 {
    font-size: 23px;
    font-weight: 800;
}

.topbar p {
    font-size: 12px;
    color: var(--muted);
    margin-top: 6px;
}

.top-date {
    padding: 11px 15px;
    border: 1px solid var(--border);
    background: white;
    border-radius: 10px;
    font-size: 12px;
    color: #64748b;
}

.welcome {
    background: linear-gradient(115deg, #3978d7, #6699ed);
    color: white;
    padding: 28px 30px;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 10px 25px rgba(57,120,215,.16);
}

.welcome h2 {
    font-size: 22px;
    margin-bottom: 8px;
}

.welcome p {
    font-size: 12px;
    opacity: .9;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 17px;
    margin-bottom: 25px;
}

.stat-card,
.panel,
.form-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: var(--shadow);
}

.stat-card {
    padding: 21px;
}

.stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 19px;
}

.stat-icon {
    width: 39px;
    height: 39px;
    display: grid;
    place-items: center;
    border-radius: 11px;
    background: var(--blue-light);
    color: var(--primary);
    font-size: 17px;
}

.stat-card small {
    color: var(--muted);
    font-size: 11px;
}

.stat-card h2 {
    font-size: 24px;
    margin-top: 5px;
}

.panel {
    padding: 23px;
    margin-bottom: 22px;
}

.panel-header,
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.panel-header h3,
.section-header h3 {
    font-size: 15px;
    font-weight: 800;
}

.panel-header p,
.section-header span {
    color: var(--muted);
    font-size: 11px;
    margin-top: 5px;
    display: block;
}

.btn {
    border: none;
    border-radius: 9px;
    padding: 11px 16px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: .2s;
}

.btn:disabled {
    opacity: .55;
    cursor: not-allowed;
}

.btn-primary {
    color: white;
    background: var(--primary);
}

.btn-primary:hover:not(:disabled) {
    background: var(--primary-dark);
}

.btn-light {
    background: var(--blue-light);
    color: var(--primary);
}

.btn-success {
    background: var(--green);
    color: white;
}

.btn-danger {
    background: #fff0f1;
    color: var(--red);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th {
    text-align: left;
    padding: 13px 12px;
    background: #f7f9fc;
    color: #8390a2;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .5px;
}

td {
    padding: 15px 12px;
    border-bottom: 1px solid #f0f2f6;
    font-size: 12px;
}

tbody tr:last-child td {
    border-bottom: none;
}

.table-wrap {
    overflow-x: auto;
}

.badge {
    padding: 6px 9px;
    border-radius: 20px;
    background: #eaf8f1;
    color: #20966d;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.badge-warning {
    background: #fff5e6;
    color: #d28a25;
}

.badge-blue {
    background: var(--blue-light);
    color: var(--primary);
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.product-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    transition: .25s;
    min-width: 0;
}

.product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(40,77,130,.10);
    border-color: #cbdcf8;
}

.product-image {
    width: 100%;
    height: 190px;
    background: #f0f5fc;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 9px;
    color: #91a1b7;
    font-size: 12px;
    background: linear-gradient(135deg, #edf4ff, #f8faff);
}

.image-placeholder span {
    font-size: 32px;
    color: #7da5e5;
}

.product-content {
    padding: 17px;
}

.product-name {
    font-size: 14px;
    font-weight: 700;
    min-height: 38px;
    line-height: 1.5;
    margin-bottom: 8px;
}

.product-price {
    font-size: 17px;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 15px;
}

.product-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-top: 13px;
    border-top: 1px solid var(--border);
    margin-bottom: 15px;
}

.product-stock {
    font-size: 11px;
    color: #74849a;
    font-weight: 600;
}

.product-button {
    width: 100%;
    padding: 11px;
}

.product-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 45px 20px;
    background: #f8faff;
    border: 1px dashed #d5dfed;
    border-radius: 12px;
    color: var(--muted);
    font-size: 13px;
}

.form-card {
    padding: 25px;
    max-width: 1050px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.form-group {
    margin-bottom: 19px;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 9px;
    color: #40516a;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #dfe6ef;
    border-radius: 9px;
    outline: none;
    background: white;
    font-family: inherit;
    font-size: 12px;
    color: var(--text);
}

.form-group input:focus,
.form-group select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(57,120,215,.08);
}

.form-group input[readonly] {
    background: #f7f9fc;
}

.form-help {
    color: var(--muted);
    font-size: 10px;
    margin-top: 7px;
}

.total-box {
    padding: 20px;
    border-radius: 12px;
    background: #f0f6ff;
    border: 1px solid #dceaff;
    margin: 20px 0;
}

.total-box small {
    color: #7185a2;
    font-size: 11px;
    font-weight: 600;
}

.total-box h2 {
    color: var(--primary);
    font-size: 27px;
    margin-top: 8px;
}

.payment-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.payment-option {
    display: flex !important;
    align-items: center;
    gap: 12px;
    padding: 15px;
    border: 1px solid var(--border);
    border-radius: 11px;
    cursor: pointer;
    margin: 0 !important;
}

.payment-option:has(input:checked) {
    border-color: var(--primary);
    background: #f2f7ff;
}

.payment-option input {
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
}

.payment-option strong {
    display: block;
    font-size: 12px;
}

.payment-option small {
    display: block;
    color: var(--muted);
    font-size: 10px;
    margin-top: 5px;
    font-weight: 400;
}

.change-box {
    background: #effaf5;
    border: 1px solid #d4f1e2;
    padding: 17px;
    border-radius: 10px;
    margin-bottom: 18px;
}

.change-box small {
    color: #42866c;
    font-size: 11px;
}

.change-box h3 {
    color: #159568;
    font-size: 22px;
    margin-top: 5px;
}

.notice {
    padding: 13px 15px;
    border-radius: 9px;
    font-size: 12px;
    margin-bottom: 18px;
}

.notice-success {
    color: #16835d;
    background: #eaf8f1;
}

.notice-error {
    color: #c33d4b;
    background: #fff0f1;
}

/* KERANJANG TRANSAKSI */

.cart-table {
    min-width: 720px;
}

.cart-table select,
.cart-table input {
    width: 100%;
    padding: 10px;
    border: 1px solid #dfe6ef;
    border-radius: 8px;
    font-family: inherit;
    font-size: 11px;
    outline: none;
}

.cart-table input[type="number"] {
    min-width: 75px;
}

.cart-table select:focus,
.cart-table input:focus {
    border-color: var(--primary);
}

.cart-table .subtotal-cell {
    font-weight: 700;
    color: var(--primary);
    white-space: nowrap;
}

.cart-table .remove-row {
    padding: 8px 11px;
}

.cart-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.cart-info {
    font-size: 11px;
    color: var(--muted);
    margin-top: 10px;
}

.receipt {
    width: 100%;
    max-width: 620px;
    margin: auto;
    background: white;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 30px;
    box-shadow: var(--shadow);
}

.receipt-head {
    text-align: center;
    padding-bottom: 18px;
    border-bottom: 1px dashed #999;
    margin-bottom: 15px;
}

.receipt-head h2 {
    color: #000;
    font-size: 22px;
    font-weight: 800;
}

.receipt-head p {
    color: #333;
    font-size: 11px;
    margin-top: 5px;
}

.receipt-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 15px;
}

.receipt-info small {
    display: block;
    color: #666;
    font-size: 10px;
    margin-bottom: 4px;
}

.receipt-info strong {
    font-size: 11px;
    color: #000;
    overflow-wrap: anywhere;
}

.receipt table {
    width: 100%;
    min-width: 0 !important;
    border-collapse: collapse;
    table-layout: fixed;
}

.receipt th {
    background: #f2f2f2;
    color: #000;
    font-size: 9px;
    padding: 7px 3px;
    text-transform: none;
    letter-spacing: 0;
}

.receipt td {
    color: #000;
    font-size: 9px;
    padding: 7px 3px;
    overflow-wrap: anywhere;
    vertical-align: top;
}

.receipt-total {
    border-top: 1px dashed #999;
    padding-top: 12px;
    margin-top: 12px;
}

.receipt-total div {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 9px;
    font-size: 11px;
    color: #000;
}

.receipt-total .grand-total {
    color: #000;
    font-weight: 800;
    font-size: 14px;
    padding-top: 10px;
    border-top: 1px solid #999;
}

.receipt-footer {
    text-align: center;
    margin-top: 20px;
    color: #333;
    font-size: 10px;
    line-height: 1.6;
}

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.empty {
    text-align: center;
    padding: 35px;
    color: var(--muted);
    font-size: 12px;
}

.chart-container {
    height: 280px;
    position: relative;
}

@media(max-width:1100px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .product-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width:750px) {
    .sidebar {
        width: 70px;
        padding: 20px 9px;
    }

    .brand {
        justify-content: center;
        padding: 0;
    }

    .brand-name,
    .menu-label,
    .menu a span:not(.menu-icon),
    .profile-mini div:not(.avatar) {
        display: none;
    }

    .menu a {
        justify-content: center;
        padding: 14px 5px;
    }

    .menu-icon {
        font-size: 18px;
    }

    .profile-mini {
        justify-content: center;
        padding: 5px 0;
    }

    .logout {
        text-align: center;
        font-size: 0;
    }

    .logout::first-letter {
        font-size: 15px;
    }

    .main {
        margin-left: 70px;
        padding: 22px 15px;
    }

    .form-grid,
    .payment-options {
        grid-template-columns: 1fr;
    }

    .top-date {
        display: none;
    }

    .welcome {
        padding: 23px;
    }
}

@media(max-width:520px) {
    .product-grid {
        grid-template-columns: 1fr;
    }

    .product-image {
        height: 210px;
    }
}

@media(max-width:450px) {
    .stats {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-card h2 {
        font-size: 19px;
    }

    .panel,
    .form-card {
        padding: 17px;
    }

    .topbar h1 {
        font-size: 19px;
    }

    .receipt {
        padding: 18px;
    }
}

/* CETAK STRUK */

@media print {
    @page {
        size: 57mm auto;
        margin: 0;
    }

    html,
    body {
        width: 57mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    .sidebar,
    .topbar,
    .no-print,
    .notice {
        display: none !important;
    }

    .main {
        width: 57mm !important;
        max-width: 57mm !important;
        margin: 0 !important;
        padding: 0 !important;
        min-height: 0 !important;
    }

    .receipt {
        width: 57mm !important;
        max-width: 57mm !important;
        min-width: 57mm !important;
        margin: 0 !important;
        padding: 3mm !important;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        box-sizing: border-box !important;
        font-family: Arial, sans-serif !important;
        color: black !important;
        page-break-inside: avoid;
    }

    .receipt-head {
        padding-bottom: 10px;
        margin-bottom: 10px;
    }

    .receipt-head h2 {
        font-size: 14px !important;
    }

    .receipt-head p {
        font-size: 8px !important;
    }

    .receipt-info {
        gap: 7px 5px;
        margin-bottom: 10px;
    }

    .receipt-info small {
        font-size: 7px !important;
    }

    .receipt-info strong {
        font-size: 8px !important;
    }

    .receipt table {
        width: 100% !important;
        min-width: 0 !important;
        table-layout: fixed !important;
    }

    .receipt th,
    .receipt td {
        font-size: 7px !important;
        padding: 4px 1px !important;
        color: black !important;
    }

    .receipt-total {
        padding-top: 8px;
        margin-top: 8px;
    }

    .receipt-total div {
        font-size: 8px !important;
        margin-bottom: 6px;
    }

    .receipt-total .grand-total {
        font-size: 10px !important;
    }

    .receipt-footer {
        margin-top: 12px;
        font-size: 8px !important;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
</head>

<body>

<aside class="sidebar">

    <div class="brand">
        <div class="brand-mark">T</div>
        <div class="brand-name">Tamtoy<span>Mart</span></div>
    </div>

    <div class="menu-label">MENU KASIR</div>

    <nav class="menu">

        <a href="index.php" class="<?= $page === 'dashboard' ? 'active' : ''; ?>">
            <span class="menu-icon">⌂</span>
            <span>Dashboard</span>
        </a>

        <a href="index.php?page=produk" class="<?= $page === 'produk' ? 'active' : ''; ?>">
            <span class="menu-icon">▤</span>
            <span>Data Produk</span>
        </a>

        <a href="index.php?page=transaksi" class="<?= $page === 'transaksi' ? 'active' : ''; ?>">
            <span class="menu-icon">＋</span>
            <span>Transaksi</span>
        </a>

        <a href="index.php?page=riwayat" class="<?= $page === 'riwayat' ? 'active' : ''; ?>">
            <span class="menu-icon">◷</span>
            <span>Riwayat Penjualan</span>
        </a>

    </nav>

    <div class="sidebar-bottom">

        <div class="profile-mini">
            <div class="avatar">
                <?= e(strtoupper(substr($namaKasir, 0, 1))); ?>
            </div>

            <div>
                <strong><?= e($namaKasir); ?></strong>
                <small>Kasir</small>
            </div>
        </div>

        <a href="../logout.php" class="logout">⇥ Keluar</a>

    </div>

</aside>

<main class="main">

    <header class="topbar">
        <div>
            <h1>
                <?php
                $judul = [
                    'dashboard' => 'Dashboard Kasir',
                    'produk' => 'Data Produk',
                    'transaksi' => 'Transaksi Penjualan',
                    'riwayat' => 'Riwayat Penjualan',
                    'detail' => 'Detail Pembayaran'
                ];

                echo e($judul[$page]);
                ?>
            </h1>

            <p>Kelola transaksi dan pembayaran pelanggan dengan mudah.</p>
        </div>

        <div class="top-date"><?= date('d F Y'); ?></div>
    </header>

    <?php if ($pesan): ?>
        <div class="notice <?= $jenisPesan === 'error' ? 'notice-error' : 'notice-success'; ?>">
            <?= e($pesan); ?>
        </div>
    <?php endif; ?>

    <!-- DASHBOARD -->

    <?php if ($page === 'dashboard'): ?>

        <section class="welcome">
            <h2>Selamat datang, <?= e($namaKasir); ?>!</h2>
            <p>Selamat bekerja. Berikut ringkasan aktivitas penjualan toko hari ini.</p>
        </section>

        <section class="stats">

            <div class="stat-card">
                <div class="stat-top">
                    <small>Total Produk</small>
                    <div class="stat-icon">▤</div>
                </div>
                <h2><?= number_format($totalProduk); ?></h2>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <small>Total Stok</small>
                    <div class="stat-icon">▦</div>
                </div>
                <h2><?= number_format($totalStok); ?></h2>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <small>Total Pelanggan</small>
                    <div class="stat-icon">♙</div>
                </div>
                <h2><?= number_format($totalPelanggan); ?></h2>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <small>Total Transaksi</small>
                    <div class="stat-icon">▧</div>
                </div>
                <h2><?= number_format($totalTransaksi); ?></h2>
            </div>

        </section>

        <section class="panel">

            <div class="panel-header">
                <div>
                    <h3>Grafik Penjualan</h3>
                    <p>Ringkasan penjualan selama 7 hari terakhir</p>
                </div>
            </div>

            <div class="chart-container">
                <canvas id="grafikPenjualan"></canvas>
            </div>

        </section>

        <section class="panel">

            <div class="panel-header">
                <div>
                    <h3>Produk Tersedia</h3>
                    <p>Daftar produk yang dapat dijual</p>
                </div>

                <a href="index.php?page=produk" class="btn btn-light">
                    Lihat Semua
                </a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Produk</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($produk)): ?>

                        <tr>
                            <td colspan="5" class="empty">Belum ada produk.</td>
                        </tr>

                    <?php else: ?>

                        <?php foreach (array_slice($produk, 0, 5) as $i => $item): ?>

                            <tr>
                                <td><?= $i + 1; ?></td>
                                <td><strong><?= e($item['nama_produk']); ?></strong></td>
                                <td><?= rupiah($item['harga']); ?></td>
                                <td><?= number_format($item['stok']); ?></td>
                                <td>
                                    <?php if ($item['stok'] > 0): ?>
                                        <span class="badge">Tersedia</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Habis</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Ringkasan Penjualan</h3>
                    <p>Total nilai transaksi yang tercatat</p>
                </div>
            </div>

            <h2 style="font-size:27px;color:#3978d7;">
                <?= rupiah($totalPenjualan); ?>
            </h2>
        </section>

    <?php endif; ?>

    <!-- DATA PRODUK -->

    <?php if ($page === 'produk'): ?>

        <section class="panel">

            <div class="panel-header">
                <div>
                    <h3>Katalog Produk</h3>
                    <p>Daftar produk beserta gambar, harga, dan ketersediaan stok.</p>
                </div>

                <a href="index.php?page=transaksi" class="btn btn-primary">
                    + Buat Transaksi
                </a>
            </div>

            <div class="product-grid">

                <?php if (empty($produk)): ?>

                    <div class="product-empty">
                        Belum ada produk yang tersedia.
                    </div>

                <?php else: ?>

                    <?php foreach ($produk as $item): ?>

                        <div class="product-card">

                            <div class="product-image">

                                <?php if (!empty($item['gambar'])): ?>

                                    <img
                                        src="../assets/img/produk/<?= rawurlencode(basename($item['gambar'])); ?>"
                                        alt="<?= e($item['nama_produk']); ?>"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                                    >

                                    <div class="image-placeholder" style="display:none;">
                                        <span>▧</span>
                                        Gambar tidak tersedia
                                    </div>

                                <?php else: ?>

                                    <div class="image-placeholder">
                                        <span>▧</span>
                                        Gambar belum tersedia
                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="product-content">

                                <div class="product-name">
                                    <?= e($item['nama_produk']); ?>
                                </div>

                                <div class="product-price">
                                    <?= rupiah($item['harga']); ?>
                                </div>

                                <div class="product-bottom">
                                    <span class="product-stock">
                                        Stok: <?= number_format($item['stok']); ?>
                                    </span>

                                    <?php if ((int)$item['stok'] > 0): ?>
                                        <span class="badge">Tersedia</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Habis</span>
                                    <?php endif; ?>
                                </div>

                                <a href="index.php?page=transaksi" class="btn btn-primary product-button">
                                    Pilih untuk Transaksi
                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </section>

    <?php endif; ?>

    <!-- FORM TRANSAKSI MULTI PRODUK -->

    <?php if ($page === 'transaksi'): ?>

        <section class="form-card">

            <div class="section-header">
                <div>
                    <h3>Form Transaksi Penjualan</h3>
                    <span>Pilih beberapa produk sekaligus dalam satu transaksi.</span>
                </div>
            </div>

            <?php if (empty($pelanggan)): ?>

                <div class="notice notice-error">
                    Data pelanggan belum tersedia. Tambahkan pelanggan terlebih dahulu melalui halaman Admin.
                </div>

            <?php elseif (empty(array_filter($produk, fn($p) => (int)$p['stok'] > 0))): ?>

                <div class="notice notice-error">
                    Belum ada produk dengan stok tersedia.
                </div>

            <?php else: ?>

                <form method="POST" id="formTransaksi">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_kasir']); ?>"
                    >

                    <div class="form-grid">

                        <div class="form-group">
                            <label>Nama Pelanggan</label>

                            <select name="id_pelanggan" required>
                                <option value="">-- Pilih Pelanggan --</option>

                                <?php foreach ($pelanggan as $p): ?>
                                    <option value="<?= e($p['id_pelanggan']); ?>">
                                        <?= e($p['nama_pelanggan']); ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div class="form-group">
                            <label>Tanggal Transaksi</label>
                            <input type="text" value="<?= date('d-m-Y H:i'); ?>" readonly>
                        </div>

                    </div>

                    <div class="panel-header" style="margin-top:15px;">
                        <div>
                            <h3>Daftar Produk Pembelian</h3>
                            <p>Tambahkan produk sesuai barang yang dibeli pelanggan.</p>
                        </div>
                    </div>

                    <div class="table-wrap">

                        <table class="cart-table">

                            <thead>
                                <tr>
                                    <th style="width:5%;">No</th>
                                    <th style="width:39%;">Nama Produk</th>
                                    <th style="width:15%;">Harga</th>
                                    <th style="width:12%;">Jumlah</th>
                                    <th style="width:19%;">Subtotal</th>
                                    <th style="width:10%;">Aksi</th>
                                </tr>
                            </thead>

                            <tbody id="keranjangBody">
                                <!-- Baris produk dibuat melalui JavaScript -->
                            </tbody>

                        </table>

                    </div>

                    <div class="cart-actions">
                        <button type="button" class="btn btn-light" id="tambahProduk">
                            + Tambah Produk
                        </button>

                        <span class="cart-info" id="jumlahItem">
                            0 jenis produk dipilih
                        </span>
                    </div>

                    <div class="total-box">
                        <small>Total Seluruh Pembayaran</small>
                        <h2 id="totalDisplay">Rp 0</h2>
                    </div>

                    <div class="form-group">

                        <label>Metode Pembayaran</label>

                        <div class="payment-options">

                            <label class="payment-option">
                                <input type="radio" name="metode_pembayaran" value="Tunai" checked>
                                <span>
                                    <strong>Tunai</strong>
                                    <small>Bayar menggunakan uang cash</small>
                                </span>
                            </label>

                            <label class="payment-option">
                                <input type="radio" name="metode_pembayaran" value="Transfer Bank">
                                <span>
                                    <strong>Transfer Bank</strong>
                                    <small>Pembayaran melalui rekening bank</small>
                                </span>
                            </label>

                            <label class="payment-option">
                                <input type="radio" name="metode_pembayaran" value="QRIS">
                                <span>
                                    <strong>QRIS</strong>
                                    <small>Pembayaran menggunakan QRIS</small>
                                </span>
                            </label>

                            <label class="payment-option">
                                <input type="radio" name="metode_pembayaran" value="E-Wallet">
                                <span>
                                    <strong>E-Wallet</strong>
                                    <small>Pembayaran melalui dompet digital</small>
                                </span>
                            </label>

                        </div>

                    </div>

                    <div id="bagianTunai">

                        <div class="form-group">
                            <label>Uang Diterima</label>

                            <input
                                type="number"
                                name="uang_diterima"
                                id="uangDiterima"
                                min="0"
                                step="1"
                                placeholder="Masukkan nominal uang"
                                required
                            >
                        </div>

                        <div class="change-box">
                            <small>Kembalian Pelanggan</small>
                            <h3 id="kembalianDisplay">Rp 0</h3>
                        </div>

                    </div>

                    <div id="bagianNonTunai" style="display:none;">
                        <div class="notice notice-success">
                            Pastikan pelanggan sudah menyelesaikan pembayaran melalui metode yang dipilih sebelum kasir menyimpan transaksi.
                        </div>

                        <div class="form-help" style="margin-bottom:18px;">
                            Pembayaran non-tunai dikonfirmasi secara manual oleh kasir.
                        </div>
                    </div>

                    <button
                        type="submit"
                        name="buat_transaksi"
                        class="btn btn-primary"
                        style="width:100%;padding:14px;"
                        id="btnSimpan"
                        disabled
                    >
                        Simpan Transaksi
                    </button>

                </form>

            <?php endif; ?>

        </section>

    <?php endif; ?>

    <!-- RIWAYAT TRANSAKSI -->

    <?php if ($page === 'riwayat'): ?>

        <section class="panel">

            <div class="panel-header">
                <div>
                    <h3>Riwayat Penjualan</h3>
                    <p>Daftar seluruh transaksi yang tercatat.</p>
                </div>

                <a href="index.php?page=transaksi" class="btn btn-primary">
                    + Transaksi Baru
                </a>
            </div>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>No. Transaksi</th>
                            <th>Tanggal</th>
                            <th>Pelanggan</th>
                            <th>Metode Pembayaran</th>
                            <th>Total</th>
                            <th>Detail</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($riwayat)): ?>

                        <tr>
                            <td colspan="6" class="empty">
                                Belum ada transaksi penjualan.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($riwayat as $t): ?>

                            <tr>
                                <td><strong>#TRX-<?= e($t['id_transaksi']); ?></strong></td>
                                <td><?= e(tanggalIndonesia($t['tanggal'])); ?></td>
                                <td><?= e($t['nama_pelanggan'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge badge-blue">
                                        <?= e($t['metode_pembayaran'] ?? 'Tunai'); ?>
                                    </span>
                                </td>
                                <td><strong><?= rupiah($t['total_belanja']); ?></strong></td>
                                <td>
                                    <a
                                        class="btn btn-light"
                                        href="index.php?page=detail&id=<?= e($t['id_transaksi']); ?>"
                                    >
                                        Lihat Struk
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>

    <!-- DETAIL DAN STRUK -->

    <?php if ($page === 'detail'): ?>

        <?php if (!$detailTransaksi): ?>

            <section class="panel">
                <div class="empty">
                    Transaksi tidak ditemukan.
                    <br><br>

                    <a href="index.php?page=riwayat" class="btn btn-primary">
                        Kembali ke Riwayat
                    </a>
                </div>
            </section>

        <?php else: ?>

            <section class="receipt">

                <div class="receipt-head">
                    <h2>TamtoyMart</h2>
                    <p>Bukti Transaksi Penjualan</p>
                    <p>Terima kasih telah berbelanja</p>
                </div>

                <div class="receipt-info">

                    <div>
                        <small>Nomor Transaksi</small>
                        <strong>#TRX-<?= e($detailTransaksi['id_transaksi']); ?></strong>
                    </div>

                    <div>
                        <small>Tanggal</small>
                        <strong><?= e(tanggalIndonesia($detailTransaksi['tanggal'])); ?></strong>
                    </div>

                    <div>
                        <small>Pelanggan</small>
                        <strong><?= e($detailTransaksi['nama_pelanggan'] ?? '-'); ?></strong>
                    </div>

                    <div>
                        <small>Kasir</small>
                        <strong><?= e($namaKasir); ?></strong>
                    </div>

                </div>

                <div class="table-wrap">

                    <table>

                        <colgroup>
                            <col style="width:36%;">
                            <col style="width:12%;">
                            <col style="width:24%;">
                            <col style="width:28%;">
                        </colgroup>

                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Jml</th>
                                <th>Harga</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($detailProduk as $d): ?>

                            <tr>
                                <td><?= e($d['nama_produk']); ?></td>
                                <td><?= number_format($d['jumlah']); ?></td>
                                <td><?= rupiah($d['harga']); ?></td>
                                <td><?= rupiah($d['jumlah'] * $d['harga']); ?></td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="receipt-total">

                    <div>
                        <span>Metode Pembayaran</span>
                        <strong><?= e($detailTransaksi['metode_pembayaran'] ?? 'Tunai'); ?></strong>
                    </div>

                    <div>
                        <span>Total Belanja</span>
                        <strong><?= rupiah($detailTransaksi['total_belanja']); ?></strong>
                    </div>

                    <?php if (($detailTransaksi['metode_pembayaran'] ?? 'Tunai') === 'Tunai'): ?>

                        <div>
                            <span>Uang Diterima</span>
                            <strong><?= rupiah($detailTransaksi['uang_diterima']); ?></strong>
                        </div>

                        <div>
                            <span>Kembalian</span>
                            <strong><?= rupiah($detailTransaksi['kembalian']); ?></strong>
                        </div>

                    <?php endif; ?>

                    <div class="grand-total">
                        <span>Total Pembayaran</span>
                        <strong><?= rupiah($detailTransaksi['total_belanja']); ?></strong>
                    </div>

                </div>

                <div class="receipt-footer">
                    <p>Transaksi berhasil dicatat.</p>
                    <p>Simpan struk ini sebagai bukti pembelian.</p>
                    <p>Terima kasih telah berbelanja!</p>
                </div>

            </section>

            <div class="action-buttons no-print">

                <button onclick="window.print()" class="btn btn-primary">
                    Cetak Struk
                </button>

                <a href="index.php?page=riwayat" class="btn btn-light">
                    Kembali ke Riwayat
                </a>

                <a href="index.php?page=transaksi" class="btn btn-success">
                    Transaksi Baru
                </a>

            </div>

        <?php endif; ?>

    <?php endif; ?>

</main>

<script>

/* =========================================
   DATA PRODUK DARI DATABASE
========================================= */

const daftarProduk = <?= json_encode(
    array_map(function ($item) {
        return [
            'id' => (int)$item['id_produk'],
            'nama' => $item['nama_produk'],
            'harga' => (float)$item['harga'],
            'stok' => (int)$item['stok']
        ];
    }, $produk),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
); ?>;

/* =========================================
   GRAFIK DASHBOARD
========================================= */

const grafikElement = document.getElementById('grafikPenjualan');

if (grafikElement) {

    const labelGrafik = <?= json_encode($labelGrafik); ?>;
    const dataGrafik = <?= json_encode($dataGrafik); ?>;

    new Chart(grafikElement, {
        type: 'line',
        data: {
            labels: labelGrafik,
            datasets: [{
                label: 'Penjualan',
                data: dataGrafik,
                borderColor: '#3978d7',
                backgroundColor: 'rgba(57,120,215,0.12)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: '#3978d7'
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
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + Number(value).toLocaleString('id-ID');
                        }
                    },
                    grid: {
                        color: '#f0f3f8'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

/* =========================================
   KERANJANG MULTI PRODUK
========================================= */

const keranjangBody = document.getElementById('keranjangBody');
const tambahProdukButton = document.getElementById('tambahProduk');
const totalDisplay = document.getElementById('totalDisplay');
const jumlahItemDisplay = document.getElementById('jumlahItem');

const uangDiterima = document.getElementById('uangDiterima');
const kembalianDisplay = document.getElementById('kembalianDisplay');

const bagianTunai = document.getElementById('bagianTunai');
const bagianNonTunai = document.getElementById('bagianNonTunai');

const btnSimpan = document.getElementById('btnSimpan');
const formTransaksi = document.getElementById('formTransaksi');

let totalTransaksi = 0;

function formatRupiah(angka) {
    return 'Rp ' + Number(angka).toLocaleString('id-ID');
}

function produkTersedia(id) {
    return daftarProduk.find(p => p.id === Number(id));
}

function buatPilihanProduk(nilai = '') {

    let html = '<option value="">-- Pilih Produk --</option>';

    daftarProduk.forEach(produk => {

        if (produk.stok <= 0) return;

        const selected = Number(nilai) === produk.id ? 'selected' : '';

        html += `
            <option value="${produk.id}" ${selected}>
                ${escapeHTML(produk.nama)} — ${formatRupiah(produk.harga)}
                (Stok: ${produk.stok})
            </option>
        `;
    });

    return html;
}

function escapeHTML(teks) {
    return String(teks).replace(/[&<>"']/g, function(karakter) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[karakter];
    });
}

function tambahBarisProduk() {

    if (!keranjangBody) return;

    const nomor = keranjangBody.querySelectorAll('tr').length + 1;

    const baris = document.createElement('tr');

    baris.innerHTML = `
        <td class="nomor-baris">${nomor}</td>

        <td>
            <select name="id_produk[]" class="pilih-produk" required>
                ${buatPilihanProduk()}
            </select>
        </td>

        <td class="harga-cell">Rp 0</td>

        <td>
            <input
                type="number"
                name="jumlah[]"
                class="jumlah-produk"
                value="1"
                min="1"
                required
            >
        </td>

        <td class="subtotal-cell">Rp 0</td>

        <td>
            <button
                type="button"
                class="btn btn-danger remove-row"
                title="Hapus produk"
            >
                Hapus
            </button>
        </td>
    `;

    keranjangBody.appendChild(baris);

    hitungSemua();
}

function perbaruiNomorBaris() {

    if (!keranjangBody) return;

    keranjangBody.querySelectorAll('tr').forEach((baris, index) => {
        baris.querySelector('.nomor-baris').textContent = index + 1;
    });
}

function hitungSemua() {

    if (!keranjangBody) return;

    let total = 0;
    let jenisProduk = 0;

    const barisSemua = keranjangBody.querySelectorAll('tr');

    barisSemua.forEach(baris => {

        const select = baris.querySelector('.pilih-produk');
        const inputJumlah = baris.querySelector('.jumlah-produk');

        const id = Number(select.value);
        const jumlah = Number(inputJumlah.value || 0);

        const produk = produkTersedia(id);

        let harga = 0;
        let subtotal = 0;

        if (produk) {

            harga = produk.harga;

            inputJumlah.max = produk.stok;

            if (jumlah > produk.stok) {
                inputJumlah.value = produk.stok;
            }

            const jumlahAkhir = Number(inputJumlah.value || 0);

            subtotal = harga * jumlahAkhir;

            if (jumlahAkhir > 0) {
                jenisProduk++;
            }
        }

        baris.querySelector('.harga-cell').textContent = formatRupiah(harga);
        baris.querySelector('.subtotal-cell').textContent = formatRupiah(subtotal);

        total += subtotal;
    });

    totalTransaksi = total;

    totalDisplay.textContent = formatRupiah(totalTransaksi);

    jumlahItemDisplay.textContent =
        jenisProduk + ' jenis produk dipilih';

    hitungKembalian();
}

function hitungKembalian() {

    if (!uangDiterima) return;

    const uang = Number(uangDiterima.value || 0);
    const kembalian = uang - totalTransaksi;

    if (kembalian >= 0) {
        kembalianDisplay.textContent = formatRupiah(kembalian);
    } else {
        kembalianDisplay.textContent =
            'Kurang ' + formatRupiah(Math.abs(kembalian));
    }

    validasiPembayaran();
}

function metodePembayaran() {

    const metode = document.querySelector(
        'input[name="metode_pembayaran"]:checked'
    );

    if (!metode || !bagianTunai) return;

    if (metode.value === 'Tunai') {

        bagianTunai.style.display = 'block';
        bagianNonTunai.style.display = 'none';

        uangDiterima.required = true;

    } else {

        bagianTunai.style.display = 'none';
        bagianNonTunai.style.display = 'block';

        uangDiterima.required = false;
        uangDiterima.value = '';

        kembalianDisplay.textContent = 'Rp 0';
    }

    validasiPembayaran();
}

function validasiPembayaran() {

    if (!btnSimpan) return;

    const metode = document.querySelector(
        'input[name="metode_pembayaran"]:checked'
    );

    const semuaBaris = keranjangBody.querySelectorAll('tr');

    let adaProduk = false;
    let produkValid = true;
    const produkDipilih = [];

    semuaBaris.forEach(baris => {

        const select = baris.querySelector('.pilih-produk');
        const inputJumlah = baris.querySelector('.jumlah-produk');

        const id = Number(select.value);
        const jumlah = Number(inputJumlah.value || 0);

        if (id > 0) {

            adaProduk = true;

            const produk = produkTersedia(id);

            if (
                !produk ||
                jumlah <= 0 ||
                jumlah > produk.stok
            ) {
                produkValid = false;
            }

            if (produkDipilih.includes(id)) {
                produkValid = false;
            }

            produkDipilih.push(id);
        } else {
            produkValid = false;
        }
    });

    if (!metode || !adaProduk || !produkValid || totalTransaksi <= 0) {
        btnSimpan.disabled = true;
        return;
    }

    if (metode.value === 'Tunai') {

        const uang = Number(uangDiterima.value || 0);

        btnSimpan.disabled = uang < totalTransaksi;

    } else {
        btnSimpan.disabled = false;
    }
}

/* =========================================
   EVENT KERANJANG
========================================= */

if (tambahProdukButton) {

    tambahProdukButton.addEventListener('click', function() {
        tambahBarisProduk();
    });
}

if (keranjangBody) {

    keranjangBody.addEventListener('change', function(event) {

        if (
            event.target.classList.contains('pilih-produk') ||
            event.target.classList.contains('jumlah-produk')
        ) {
            hitungSemua();
        }
    });

    keranjangBody.addEventListener('input', function(event) {

        if (event.target.classList.contains('jumlah-produk')) {
            hitungSemua();
        }
    });

    keranjangBody.addEventListener('click', function(event) {

        if (event.target.classList.contains('remove-row')) {

            const baris = event.target.closest('tr');

            if (keranjangBody.querySelectorAll('tr').length > 1) {
                baris.remove();
            } else {
                baris.querySelector('.pilih-produk').value = '';
                baris.querySelector('.jumlah-produk').value = 1;
            }

            perbaruiNomorBaris();
            hitungSemua();
        }
    });

    // Baris pertama otomatis ditampilkan.
    tambahBarisProduk();
}

if (uangDiterima) {
    uangDiterima.addEventListener('input', hitungKembalian);
}

document.querySelectorAll('input[name="metode_pembayaran"]').forEach(input => {
    input.addEventListener('change', metodePembayaran);
});

if (formTransaksi) {

    formTransaksi.addEventListener('submit', function(event) {

        if (btnSimpan.disabled) {
            event.preventDefault();
            alert('Periksa kembali produk, jumlah, dan pembayaran.');
            return;
        }

        if (!confirm('Simpan transaksi pembelian ini?')) {
            event.preventDefault();
        }
    });
}

if (keranjangBody) {
    metodePembayaran();
    hitungSemua();
}

</script>

</body>
</html>
