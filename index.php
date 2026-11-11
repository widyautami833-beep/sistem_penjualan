
<?php
require_once __DIR__ . '/../auth.php';
wajibRole('pelanggan');

/* =====================================================
   1. KONEKSI DATABASE
===================================================== */

$host = 'localhost';
$dbname = 'db_toko';
$username = 'root';
$password = '';

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
    die('Koneksi database gagal.');
}

/* =====================================================
   2. HELPER
===================================================== */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function redirectHalaman($halaman = 'dashboard')
{
    header("Location: index.php?halaman=" . urlencode($halaman));
    exit;
}

function flash($pesan, $jenis = 'success')
{
    $_SESSION['flash_pelanggan'] = [
        'pesan' => $pesan,
        'jenis' => $jenis
    ];
}

function getFlash()
{
    $data = $_SESSION['flash_pelanggan'] ?? null;
    unset($_SESSION['flash_pelanggan']);
    return $data;
}

/* =====================================================
   3. CSRF TOKEN
===================================================== */

if (empty($_SESSION['csrf_pelanggan'])) {
    $_SESSION['csrf_pelanggan'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_pelanggan'];

/* =====================================================
   4. DATA AKUN PELANGGAN
===================================================== */

$idUser = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.username,
        u.nama,
        u.id_pelanggan,
        p.nama_pelanggan,
        p.kota
    FROM users u
    LEFT JOIN tbl_pelanggan p
        ON u.id_pelanggan = p.id_pelanggan
    WHERE u.id = ?
      AND u.role = 'pelanggan'
    LIMIT 1
");

$stmt->execute([$idUser]);
$akun = $stmt->fetch();

if (!$akun || empty($akun['id_pelanggan'])) {
    die('Data pelanggan belum terhubung dengan akun.');
}

$idPelanggan = (int)$akun['id_pelanggan'];
$namaPelanggan = $akun['nama_pelanggan'] ?: $akun['nama'];
$inisial = strtoupper(substr($namaPelanggan, 0, 1));

/* =====================================================
   5. DETEKSI KOLOM DATABASE
===================================================== */

$kolomTransaksi = $pdo->query("
    SHOW COLUMNS FROM tbl_transaksi
")->fetchAll(PDO::FETCH_COLUMN);

$kolomProduk = $pdo->query("
    SHOW COLUMNS FROM tbl_produk
")->fetchAll(PDO::FETCH_COLUMN);

$kolomTransaksi = array_flip($kolomTransaksi);
$kolomProduk = array_flip($kolomProduk);

/* =====================================================
   6. KERANJANG SESSION
===================================================== */

if (!isset($_SESSION['keranjang_pelanggan'])) {
    $_SESSION['keranjang_pelanggan'] = [];
}

/* =====================================================
   7. PROSES FORM
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals(
        $_SESSION['csrf_pelanggan'] ?? '',
        $_POST['csrf'] ?? ''
    )) {
        flash('Permintaan tidak valid. Silakan coba kembali.', 'error');
        redirectHalaman('dashboard');
    }

    $aksi = $_POST['aksi'] ?? '';

    /* TAMBAH KE KERANJANG */

    if ($aksi === 'tambah_keranjang') {

        $idProduk = (int)($_POST['id_produk'] ?? 0);
        $jumlah = (int)($_POST['jumlah'] ?? 1);

        if ($idProduk <= 0 || $jumlah <= 0) {
            flash('Jumlah produk tidak valid.', 'error');
            redirectHalaman('produk');
        }

        $stmt = $pdo->prepare("
            SELECT id_produk, nama_produk, harga, stok
            FROM tbl_produk
            WHERE id_produk = ?
            LIMIT 1
        ");

        $stmt->execute([$idProduk]);
        $item = $stmt->fetch();

        if (!$item) {
            flash('Produk tidak ditemukan.', 'error');
            redirectHalaman('produk');
        }

        if ($item['stok'] <= 0) {
            flash('Stok produk sedang habis.', 'error');
            redirectHalaman('produk');
        }

        $jumlahSekarang = $_SESSION['keranjang_pelanggan'][$idProduk] ?? 0;
        $jumlahBaru = $jumlahSekarang + $jumlah;

        if ($jumlahBaru > (int)$item['stok']) {
            flash('Jumlah melebihi stok yang tersedia.', 'error');
            redirectHalaman('produk');
        }

        $_SESSION['keranjang_pelanggan'][$idProduk] = $jumlahBaru;

        flash('Produk berhasil ditambahkan ke keranjang.');
        redirectHalaman('produk');
    }

    /* UPDATE JUMLAH KERANJANG */

    if ($aksi === 'update_keranjang') {

        foreach ($_POST['jumlah'] ?? [] as $id => $jumlah) {

            $id = (int)$id;
            $jumlah = (int)$jumlah;

            if ($id <= 0) {
                continue;
            }

            if ($jumlah <= 0) {
                unset($_SESSION['keranjang_pelanggan'][$id]);
                continue;
            }

            $stmt = $pdo->prepare("
                SELECT stok
                FROM tbl_produk
                WHERE id_produk = ?
            ");

            $stmt->execute([$id]);
            $produkCek = $stmt->fetch();

            if ($produkCek) {
                $_SESSION['keranjang_pelanggan'][$id] =
                    min($jumlah, (int)$produkCek['stok']);
            } else {
                unset($_SESSION['keranjang_pelanggan'][$id]);
            }
        }

        flash('Keranjang berhasil diperbarui.');
        redirectHalaman('keranjang');
    }

    /* HAPUS ITEM KERANJANG */

    if ($aksi === 'hapus_keranjang') {

        $idProduk = (int)($_POST['id_produk'] ?? 0);

        unset($_SESSION['keranjang_pelanggan'][$idProduk]);

        flash('Produk dihapus dari keranjang.');
        redirectHalaman('keranjang');
    }

    /* KOSONGKAN KERANJANG */

    if ($aksi === 'kosongkan_keranjang') {

        $_SESSION['keranjang_pelanggan'] = [];

        flash('Keranjang berhasil dikosongkan.');
        redirectHalaman('keranjang');
    }

    /* =================================================
       CHECKOUT
    ================================================= */

    if ($aksi === 'checkout') {

        $keranjang = $_SESSION['keranjang_pelanggan'];

        if (empty($keranjang)) {
            flash('Keranjang belanja masih kosong.', 'error');
            redirectHalaman('keranjang');
        }

        $metode = $_POST['metode_pembayaran'] ?? '';
        $metodeValid = [
            'Tunai',
            'Transfer Bank',
            'E-Wallet',
            'QRIS'
        ];

        if (!in_array($metode, $metodeValid, true)) {
            flash('Metode pembayaran tidak valid.', 'error');
            redirectHalaman('checkout');
        }

        $uangDiterima = (float)($_POST['uang_diterima'] ?? 0);

        try {

            $pdo->beginTransaction();

            $detailBelanja = [];
            $totalBelanja = 0;

            /* CEK STOK DAN KUNCI BARIS PRODUK */

            foreach ($keranjang as $idProduk => $jumlah) {

                $idProduk = (int)$idProduk;
                $jumlah = (int)$jumlah;

                if ($jumlah <= 0) {
                    throw new Exception('Jumlah produk tidak valid.');
                }

                $stmt = $pdo->prepare("
                    SELECT
                        id_produk,
                        nama_produk,
                        harga,
                        stok
                    FROM tbl_produk
                    WHERE id_produk = ?
                    FOR UPDATE
                ");

                $stmt->execute([$idProduk]);
                $produkCek = $stmt->fetch();

                if (!$produkCek) {
                    throw new Exception('Ada produk yang sudah tidak tersedia.');
                }

                if ((int)$produkCek['stok'] < $jumlah) {
                    throw new Exception(
                        'Stok ' . $produkCek['nama_produk'] .
                        ' tidak mencukupi. Stok tersedia: ' .
                        $produkCek['stok']
                    );
                }

                $subtotal = (float)$produkCek['harga'] * $jumlah;

                $totalBelanja += $subtotal;

                $detailBelanja[] = [
                    'id_produk' => $idProduk,
                    'nama_produk' => $produkCek['nama_produk'],
                    'jumlah' => $jumlah,
                    'harga' => $produkCek['harga'],
                    'subtotal' => $subtotal
                ];
            }

            /* VALIDASI PEMBAYARAN */

            if ($metode === 'Tunai') {

                if ($uangDiterima < $totalBelanja) {
                    throw new Exception('Uang yang dibayarkan belum mencukupi.');
                }

                $kembalian = $uangDiterima - $totalBelanja;

            } else {

                /*
                 * Untuk metode non-tunai, nominal transaksi
                 * dicatat sebesar total belanja.
                 * Pembayaran gateway belum terintegrasi.
                 */

                $uangDiterima = $totalBelanja;
                $kembalian = 0;
            }

            /* INSERT TRANSAKSI */

            $kolomInsert = [
                'tanggal',
                'id_pelanggan',
                'total_belanja'
            ];

            $nilaiInsert = [
                date('Y-m-d'),
                $idPelanggan,
                $totalBelanja
            ];

            if (isset($kolomTransaksi['metode_pembayaran'])) {
                $kolomInsert[] = 'metode_pembayaran';
                $nilaiInsert[] = $metode;
            }

            if (isset($kolomTransaksi['uang_diterima'])) {
                $kolomInsert[] = 'uang_diterima';
                $nilaiInsert[] = $uangDiterima;
            }

            if (isset($kolomTransaksi['kembalian'])) {
                $kolomInsert[] = 'kembalian';
                $nilaiInsert[] = $kembalian;
            }

            $namaKolom = implode(', ', $kolomInsert);
            $tandaTanya = implode(', ', array_fill(0, count($nilaiInsert), '?'));

            $sqlInsert = "
                INSERT INTO tbl_transaksi
                ($namaKolom)
                VALUES ($tandaTanya)
            ";

            $stmt = $pdo->prepare($sqlInsert);
            $stmt->execute($nilaiInsert);

            $idTransaksi = (int)$pdo->lastInsertId();

            /* INSERT DETAIL TRANSAKSI DAN KURANGI STOK */

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

            foreach ($detailBelanja as $detail) {

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
                    throw new Exception('Gagal memperbarui stok produk.');
                }
            }

            $pdo->commit();

            $_SESSION['keranjang_pelanggan'] = [];

            flash('Pembelian berhasil dicatat. Terima kasih sudah berbelanja!');

            header(
                'Location: index.php?halaman=struk&id=' .
                $idTransaksi
            );
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash($e->getMessage(), 'error');
            redirectHalaman('checkout');
        }
    }
}

/* =====================================================
   8. HALAMAN AKTIF
===================================================== */

$halaman = $_GET['halaman'] ?? 'dashboard';

$halamanValid = [
    'dashboard',
    'produk',
    'keranjang',
    'checkout',
    'riwayat',
    'profil',
    'struk'
];

if (!in_array($halaman, $halamanValid, true)) {
    $halaman = 'dashboard';
}

$pesanFlash = getFlash();

/* =====================================================
   9. STATISTIK
===================================================== */

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_transaksi,
        COALESCE(SUM(total_belanja), 0) AS total_belanja
    FROM tbl_transaksi
    WHERE id_pelanggan = ?
");

$stmt->execute([$idPelanggan]);
$statistik = $stmt->fetch();

$totalTransaksi = (int)$statistik['total_transaksi'];
$totalBelanja = (float)$statistik['total_belanja'];

/* =====================================================
   10. RIWAYAT TRANSAKSI
===================================================== */

$stmt = $pdo->prepare("
    SELECT *
    FROM tbl_transaksi
    WHERE id_pelanggan = ?
    ORDER BY tanggal DESC, id_transaksi DESC
");

$stmt->execute([$idPelanggan]);
$transaksi = $stmt->fetchAll();

/* =====================================================
   11. DATA PRODUK
===================================================== */

$stmt = $pdo->query("
    SELECT
        p.*,
        k.nama_kategori
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k
        ON p.id_kategori = k.id_kategori
    ORDER BY p.id_produk DESC
");

$produk = $stmt->fetchAll();

/* =====================================================
   12. DATA KERANJANG
===================================================== */

$keranjang = [];
$totalKeranjang = 0;
$totalItem = 0;

foreach ($_SESSION['keranjang_pelanggan'] as $id => $jumlah) {

    $stmt = $pdo->prepare("
        SELECT
            p.*,
            k.nama_kategori
        FROM tbl_produk p
        LEFT JOIN tbl_kategori k
            ON p.id_kategori = k.id_kategori
        WHERE p.id_produk = ?
    ");

    $stmt->execute([(int)$id]);
    $item = $stmt->fetch();

    if (!$item) {
        unset($_SESSION['keranjang_pelanggan'][$id]);
        continue;
    }

    $item['jumlah_beli'] = (int)$jumlah;
    $item['subtotal'] = (float)$item['harga'] * (int)$jumlah;

    $keranjang[] = $item;
    $totalKeranjang += $item['subtotal'];
    $totalItem += (int)$jumlah;
}

/* =====================================================
   13. DETAIL STRUK
===================================================== */

$dataStruk = null;
$detailStruk = [];

if ($halaman === 'struk') {

    $idStruk = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT *
        FROM tbl_transaksi
        WHERE id_transaksi = ?
          AND id_pelanggan = ?
        LIMIT 1
    ");

    $stmt->execute([$idStruk, $idPelanggan]);
    $dataStruk = $stmt->fetch();

    if ($dataStruk) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM tbl_detail_transaksi
            WHERE id_transaksi = ?
        ");

        $stmt->execute([$idStruk]);
        $detailStruk = $stmt->fetchAll();

    } else {

        flash('Transaksi tidak ditemukan.', 'error');
        redirectHalaman('riwayat');
    }
}

/* =====================================================
   14. HELPER MENU
===================================================== */

function menuAktif($nama, $halaman)
{
    return $nama === $halaman ? 'active' : '';
}

?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>TamtoyMart | Customer Portal</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

:root {
    --primary: #3978d7;
    --primary-dark: #2861ba;
    --blue-light: #eaf2ff;
    --background: #f5f8fd;
    --white: #fff;
    --text: #24334b;
    --muted: #8794a9;
    --border: #e8edf5;
    --green: #19a879;
    --red: #dc626d;
    --shadow: 0 5px 20px rgba(40,76,130,.06);
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

.layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.sidebar {
    width: 245px;
    background: white;
    border-right: 1px solid var(--border);
    padding: 28px 18px;
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 10;
    display: flex;
    flex-direction: column;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 10px;
    margin-bottom: 38px;
}

.brand-icon {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: var(--primary);
    color: white;
    display: grid;
    place-items: center;
    font-size: 21px;
    font-weight: 800;
}

.brand h2 {
    font-size: 18px;
    font-weight: 800;
}

.brand span {
    font-size: 11px;
    color: var(--muted);
}

.menu-label {
    font-size: 10px;
    color: #a0aabd;
    font-weight: 700;
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
    text-decoration: none;
    color: #77849a;
    font-size: 13px;
    font-weight: 600;
    transition: .2s;
}

.menu a:hover,
.menu a.active {
    background: var(--blue-light);
    color: var(--primary);
}

.menu-icon {
    width: 20px;
    text-align: center;
    font-size: 17px;
}

.cart-count {
    margin-left: auto;
    background: var(--primary);
    color: white;
    font-size: 10px;
    border-radius: 20px;
    padding: 3px 7px;
}

.sidebar-bottom {
    margin-top: auto;
    padding-top: 20px;
}

.sidebar-bottom a {
    display: block;
    padding: 12px;
    text-decoration: none;
    color: var(--red);
    font-size: 13px;
    font-weight: 600;
    border-radius: 10px;
    background: #fff1f2;
}

/* MAIN */

.main {
    margin-left: 245px;
    width: calc(100% - 245px);
    padding: 30px 35px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 28px;
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

.user-info {
    display: flex;
    align-items: center;
    gap: 11px;
    background: white;
    padding: 9px 15px;
    border-radius: 12px;
    border: 1px solid var(--border);
}

.avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: var(--primary);
    background: var(--blue-light);
    font-weight: 800;
}

.user-info strong {
    font-size: 12px;
}

.user-info small {
    display: block;
    font-size: 10px;
    color: var(--muted);
    margin-top: 3px;
}

/* WELCOME */

.welcome {
    background: linear-gradient(120deg, #3978d7, #679be8);
    color: white;
    border-radius: 17px;
    padding: 28px 30px;
    margin-bottom: 23px;
    position: relative;
    overflow: hidden;
}

.welcome small {
    font-size: 11px;
    opacity: .85;
}

.welcome h2 {
    font-size: 23px;
    margin: 10px 0;
}

.welcome p {
    font-size: 12px;
    opacity: .9;
}

/* CARD */

.content-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: var(--shadow);
    padding: 24px;
    margin-bottom: 22px;
}

.section-heading {
    margin-bottom: 20px;
}

.section-heading h2 {
    font-size: 16px;
    font-weight: 800;
}

.section-heading p {
    font-size: 11px;
    color: var(--muted);
    margin-top: 6px;
}

/* STATISTIK */

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 21px;
    box-shadow: var(--shadow);
}

.stat-title {
    font-size: 12px;
    color: var(--muted);
    font-weight: 600;
}

.stat-card h2 {
    font-size: 23px;
    margin-top: 13px;
}

.stat-card small {
    font-size: 11px;
    color: var(--muted);
}

/* TABEL */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 620px;
}

th {
    text-align: left;
    padding: 14px 13px;
    background: #f7f9fd;
    color: #8290a5;
    font-size: 10px;
    text-transform: uppercase;
}

td {
    padding: 15px 13px;
    border-bottom: 1px solid #edf0f5;
    font-size: 12px;
    color: #59677d;
}

.transaction-id {
    color: var(--primary);
    font-weight: 700;
}

.payment {
    display: inline-block;
    padding: 6px 10px;
    background: var(--blue-light);
    color: var(--primary);
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
}

/* PRODUK */

.search {
    width: 100%;
    max-width: 350px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 9px;
    outline: none;
    font-family: inherit;
    font-size: 12px;
    margin-bottom: 20px;
}

.search:focus,
.input:focus,
select:focus {
    border-color: var(--primary);
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 18px;
}

.product-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 13px;
    overflow: hidden;
    transition: .2s;
}

.product-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow);
}

.product-image {
    height: 165px;
    background: #edf3fc;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a2b7;
    font-size: 11px;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-info {
    padding: 16px;
}

.category {
    display: inline-block;
    padding: 5px 9px;
    background: var(--blue-light);
    color: var(--primary);
    border-radius: 20px;
    font-size: 10px;
}

.product-info h3 {
    font-size: 13px;
    margin: 12px 0 8px;
}

.product-price {
    color: var(--primary);
    font-size: 16px;
    font-weight: 800;
}

.product-stock {
    font-size: 11px;
    color: var(--muted);
    margin-top: 9px;
}

.stock-habis {
    color: var(--red);
}

.btn {
    display: inline-block;
    border: none;
    cursor: pointer;
    border-radius: 8px;
    padding: 10px 14px;
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    transition: .2s;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
}

.btn-light {
    background: var(--blue-light);
    color: var(--primary);
}

.btn-danger {
    background: #fff0f1;
    color: var(--red);
}

.btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.add-form {
    margin-top: 14px;
    display: flex;
    gap: 8px;
}

.add-form input {
    width: 58px;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px;
    text-align: center;
}

/* FORM */

.input-group {
    margin-bottom: 17px;
}

.input-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 8px;
}

.input,
select {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid var(--border);
    border-radius: 9px;
    outline: none;
    font-family: inherit;
    font-size: 12px;
    background: white;
}

.checkout-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 20px;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 12px;
    margin-bottom: 13px;
    color: #65738a;
}

.summary-total {
    border-top: 1px solid var(--border);
    padding-top: 15px;
    margin-top: 15px;
    font-size: 16px;
    font-weight: 800;
    color: var(--primary);
}

/* PROFIL */

.profile {
    display: flex;
    align-items: center;
    gap: 17px;
}

.profile-avatar {
    width: 65px;
    height: 65px;
    border-radius: 17px;
    background: var(--blue-light);
    color: var(--primary);
    display: grid;
    place-items: center;
    font-size: 25px;
    font-weight: 800;
}

.profile h3 {
    font-size: 15px;
    margin-bottom: 6px;
}

.profile p {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.9;
}

.account-badge {
    display: inline-block;
    margin-top: 7px;
    background: #e9f9f2;
    color: #13865f;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
}

/* PESAN */

.alert {
    padding: 14px 17px;
    border-radius: 10px;
    font-size: 12px;
    margin-bottom: 20px;
}

.alert.success {
    background: #e9f9f2;
    color: #13865f;
}

.alert.error {
    background: #fff0f1;
    color: #c34c5b;
}

.empty {
    text-align: center;
    padding: 40px 15px;
    color: var(--muted);
}

.empty h3 {
    font-size: 14px;
    color: var(--text);
    margin-bottom: 7px;
}

.empty p {
    font-size: 11px;
}

/* STRUK */

.receipt {
    max-width: 650px;
    margin: auto;
}

.receipt-header {
    text-align: center;
    border-bottom: 1px dashed #dce4ef;
    padding-bottom: 20px;
    margin-bottom: 20px;
}

.receipt-header h2 {
    color: var(--primary);
    margin-bottom: 7px;
}

.receipt-info {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    font-size: 12px;
    margin-bottom: 12px;
}

.receipt-total {
    border-top: 1px dashed #dce4ef;
    padding-top: 15px;
    margin-top: 15px;
}

.footer {
    text-align: center;
    color: #a0aabd;
    font-size: 10px;
    padding: 10px;
}

/* RESPONSIVE */

@media(max-width: 1050px) {
    .product-grid {
        grid-template-columns: repeat(2, minmax(0,1fr));
    }
}

@media(max-width: 900px) {
    .sidebar {
        width: 75px;
        padding: 22px 10px;
    }

    .brand {
        justify-content: center;
        padding: 0;
    }

    .brand-text,
    .menu-label,
    .menu-text {
        display: none;
    }

    .menu a {
        justify-content: center;
        padding: 14px 8px;
    }

    .cart-count {
        position: absolute;
        margin-left: 22px;
        margin-top: -22px;
    }

    .main {
        margin-left: 75px;
        width: calc(100% - 75px);
        padding: 25px 20px;
    }
}

@media(max-width: 650px) {
    .main {
        padding: 20px 13px;
    }

    .topbar {
        align-items: flex-start;
    }

    .topbar h1 {
        font-size: 19px;
    }

    .user-info {
        padding: 7px;
    }

    .user-info div:last-child {
        display: none;
    }

    .welcome {
        padding: 23px 20px;
    }

    .welcome h2 {
        font-size: 19px;
    }

    .stats {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .content-card {
        padding: 17px;
    }

    .product-grid {
        grid-template-columns: 1fr;
    }

    .checkout-grid {
        grid-template-columns: 1fr;
    }

    .profile {
        align-items: flex-start;
    }
}

@media print {
    body {
        background: white;
    }

    .sidebar,
    .topbar,
    .no-print,
    .footer {
        display: none !important;
    }

    .main {
        margin: 0;
        padding: 0;
        width: 100%;
    }

    .content-card {
        border: none;
        box-shadow: none;
    }
}

</style>
</head>

<body>

<div class="layout">

<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="brand">
        <div class="brand-icon">T</div>
        <div class="brand-text">
            <h2>TamtoyMart</h2>
            <span>Customer Portal</span>
        </div>
    </div>

    <div class="menu-label">MENU PELANGGAN</div>

    <nav class="menu">

        <a href="index.php?halaman=dashboard"
           class="<?= menuAktif('dashboard', $halaman) ?>">
            <span class="menu-icon">⌂</span>
            <span class="menu-text">Dashboard</span>
        </a>

        <a href="index.php?halaman=produk"
           class="<?= menuAktif('produk', $halaman) ?>">
            <span class="menu-icon">▦</span>
            <span class="menu-text">Produk</span>
        </a>

        <a href="index.php?halaman=keranjang"
           class="<?= menuAktif('keranjang', $halaman) ?>">
            <span class="menu-icon">🛒</span>
            <span class="menu-text">Keranjang</span>

            <?php if ($totalItem > 0): ?>
                <span class="cart-count"><?= $totalItem ?></span>
            <?php endif; ?>
        </a>

        <a href="index.php?halaman=riwayat"
           class="<?= menuAktif('riwayat', $halaman) ?>">
            <span class="menu-icon">▤</span>
            <span class="menu-text">Riwayat Transaksi</span>
        </a>

        <a href="index.php?halaman=profil"
           class="<?= menuAktif('profil', $halaman) ?>">
            <span class="menu-icon">♙</span>
            <span class="menu-text">Profil Saya</span>
        </a>

    </nav>

    <div class="sidebar-bottom">
        <a href="../logout.php">↪ Keluar</a>
    </div>

</aside>

<!-- MAIN -->

<main class="main">

<header class="topbar">

    <div>
        <h1>
            <?php
            $judul = [
                'dashboard' => 'Dashboard Pelanggan',
                'produk' => 'Katalog Produk',
                'keranjang' => 'Keranjang Belanja',
                'checkout' => 'Checkout',
                'riwayat' => 'Riwayat Transaksi',
                'profil' => 'Profil Saya',
                'struk' => 'Struk Pembelian'
            ];

            echo e($judul[$halaman]);
            ?>
        </h1>

        <p>Selamat datang di halaman pelanggan TamtoyMart.</p>
    </div>

    <div class="user-info">
        <div class="avatar"><?= e($inisial) ?></div>
        <div>
            <strong><?= e($namaPelanggan) ?></strong>
            <small>Pelanggan</small>
        </div>
    </div>

</header>

<?php if ($pesanFlash): ?>

    <div class="alert <?= e($pesanFlash['jenis']) ?>">
        <?= e($pesanFlash['pesan']) ?>
    </div>

<?php endif; ?>


<!-- =========================================
     DASHBOARD
========================================= -->

<?php if ($halaman === 'dashboard'): ?>

    <section class="welcome">
        <small>SELAMAT DATANG DI TAMTOYMART</small>

        <h2>Halo, <?= e($namaPelanggan) ?>!</h2>

        <p>
            Temukan produk favoritmu dan nikmati pengalaman
            berbelanja yang mudah di TamtoyMart.
        </p>

        <br>

        <a href="index.php?halaman=produk"
           class="btn btn-light">
            Mulai Belanja
        </a>
    </section>

    <section class="stats">

        <div class="stat-card">
            <span class="stat-title">Total Transaksi</span>
            <h2><?= number_format($totalTransaksi) ?></h2>
            <small>Seluruh pembelianmu</small>
        </div>

        <div class="stat-card">
            <span class="stat-title">Total Belanja</span>
            <h2><?= rupiah($totalBelanja) ?></h2>
            <small>Akumulasi pembelian</small>
        </div>

        <div class="stat-card">
            <span class="stat-title">Jumlah Produk</span>
            <h2><?= count($produk) ?></h2>
            <small>Produk terdaftar</small>
        </div>

    </section>

    <section class="content-card">

        <div class="section-heading">
            <h2>Transaksi Terbaru</h2>
            <p>Aktivitas pembelian terakhir.</p>
        </div>

        <div class="table-wrapper">

            <table>
                <thead>
                    <tr>
                        <th>ID Transaksi</th>
                        <th>Tanggal</th>
                        <th>Total Belanja</th>
                        <th>Pembayaran</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($transaksi): ?>

                    <?php foreach (array_slice($transaksi, 0, 5) as $row): ?>

                        <tr>
                            <td class="transaction-id">
                                #<?= e($row['id_transaksi']) ?>
                            </td>

                            <td>
                                <?= date('d-m-Y', strtotime($row['tanggal'])) ?>
                            </td>

                            <td>
                                <strong><?= rupiah($row['total_belanja']) ?></strong>
                            </td>

                            <td>
                                <span class="payment">
                                    <?= e($row['metode_pembayaran'] ?? 'Belum diisi') ?>
                                </span>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4">
                            <div class="empty">
                                <h3>Belum Ada Transaksi</h3>
                                <p>Mulai belanja untuk melihat riwayat pembelianmu.</p>
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </section>


<!-- =========================================
     PRODUK
========================================= -->

<?php elseif ($halaman === 'produk'): ?>

    <section class="welcome">
        <small>KOLEKSI PRODUK</small>
        <h2>Temukan Produk Favoritmu</h2>
        <p>Pilih produk yang ingin kamu beli dan masukkan ke keranjang.</p>
    </section>

    <section class="content-card">

        <div class="section-heading">
            <h2>Semua Produk</h2>
            <p><?= count($produk) ?> produk tersedia di TamtoyMart.</p>
        </div>

        <input
            type="search"
            id="cariProduk"
            class="search"
            placeholder="Cari nama produk..."
        >

        <div class="product-grid" id="productGrid">

            <?php if ($produk): ?>

                <?php foreach ($produk as $p): ?>

                    <article class="product-card"
                             data-nama="<?= e(strtolower($p['nama_produk'])) ?>">

                        <div class="product-image">

                            <?php
                            $fileGambar = $p['gambar'] ?? '';
                            $pathGambar = __DIR__ . '/../assets/img/produk/' . $fileGambar;
                            ?>

                            <?php if ($fileGambar && is_file($pathGambar)): ?>

                                <img
                                    src="../assets/img/produk/<?= rawurlencode($fileGambar) ?>"
                                    alt="<?= e($p['nama_produk']) ?>"
                                >

                            <?php else: ?>

                                Tidak ada gambar produk

                            <?php endif; ?>

                        </div>

                        <div class="product-info">

                            <span class="category">
                                <?= e($p['nama_kategori'] ?? 'Tanpa kategori') ?>
                            </span>

                            <h3><?= e($p['nama_produk']) ?></h3>

                            <div class="product-price">
                                <?= rupiah($p['harga']) ?>
                            </div>

                            <div class="product-stock <?= (int)$p['stok'] < 1 ? 'stock-habis' : '' ?>">
                                <?php if ((int)$p['stok'] > 0): ?>
                                    Stok tersedia: <?= (int)$p['stok'] ?>
                                <?php else: ?>
                                    Stok habis
                                <?php endif; ?>
                            </div>

                            <?php if ((int)$p['stok'] > 0): ?>

                                <form method="POST"
                                      action="index.php?halaman=produk"
                                      class="add-form">

                                    <input type="hidden" name="csrf"
                                           value="<?= e($csrf) ?>">

                                    <input type="hidden" name="aksi"
                                           value="tambah_keranjang">

                                    <input type="hidden" name="id_produk"
                                           value="<?= (int)$p['id_produk'] ?>">

                                    <input
                                        type="number"
                                        name="jumlah"
                                        value="1"
                                        min="1"
                                        max="<?= (int)$p['stok'] ?>"
                                        required
                                    >

                                    <button class="btn btn-primary" type="submit">
                                        + Keranjang
                                    </button>

                                </form>

                            <?php else: ?>

                                <button class="btn btn-danger" disabled>
                                    Stok Habis
                                </button>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty">
                    <h3>Belum Ada Produk</h3>
                    <p>Produk belum tersedia saat ini.</p>
                </div>

            <?php endif; ?>

        </div>

        <div class="empty" id="hasilKosong" style="display:none;">
            <h3>Produk Tidak Ditemukan</h3>
            <p>Coba gunakan kata pencarian lainnya.</p>
        </div>

    </section>


<!-- =========================================
     KERANJANG
========================================= -->

<?php elseif ($halaman === 'keranjang'): ?>

    <section class="content-card">

        <div class="section-heading">
            <h2>Keranjang Belanja</h2>
            <p>Periksa produk dan jumlah barang sebelum checkout.</p>
        </div>

        <?php if ($keranjang): ?>

            <form method="POST" action="index.php?halaman=keranjang">

                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="aksi" value="update_keranjang">

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th>Subtotal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($keranjang as $item): ?>

                            <tr>

                                <td>
                                    <strong><?= e($item['nama_produk']) ?></strong>
                                </td>

                                <td><?= rupiah($item['harga']) ?></td>

                                <td>
                                    <input
                                        type="number"
                                        name="jumlah[<?= (int)$item['id_produk'] ?>]"
                                        value="<?= (int)$item['jumlah_beli'] ?>"
                                        min="0"
                                        max="<?= (int)$item['stok'] ?>"
                                        style="width:65px;padding:7px;border:1px solid #e8edf5;border-radius:7px;"
                                    >
                                </td>

                                <td>
                                    <strong><?= rupiah($item['subtotal']) ?></strong>
                                </td>

                                <td>
                                    <form></form>
                                    <button
                                        type="button"
                                        class="btn btn-danger"
                                        onclick="hapusItem(<?= (int)$item['id_produk'] ?>)">
                                        Hapus
                                    </button>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <br>

                <button class="btn btn-light" type="submit">
                    Perbarui Keranjang
                </button>

            </form>

            <?php foreach ($keranjang as $item): ?>

                <form method="POST"
                      action="index.php?halaman=keranjang"
                      id="hapus-<?= (int)$item['id_produk'] ?>"
                      style="display:none;">

                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="aksi" value="hapus_keranjang">
                    <input type="hidden" name="id_produk"
                           value="<?= (int)$item['id_produk'] ?>">

                </form>

            <?php endforeach; ?>

            <div style="max-width:350px;margin:25px 0 0 auto;">

                <div class="summary-line">
                    <span>Total item</span>
                    <strong><?= $totalItem ?> barang</strong>
                </div>

                <div class="summary-line summary-total">
                    <span>Total Belanja</span>
                    <strong><?= rupiah($totalKeranjang) ?></strong>
                </div>

                <a href="index.php?halaman=checkout"
                   class="btn btn-primary"
                   style="display:block;text-align:center;margin-top:15px;">
                    Lanjutkan Checkout
                </a>

                <form method="POST" action="index.php?halaman=keranjang"
                      style="margin-top:10px;">

                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="aksi" value="kosongkan_keranjang">

                    <button class="btn btn-danger" type="submit"
                            style="width:100%;">
                        Kosongkan Keranjang
                    </button>

                </form>

            </div>

        <?php else: ?>

            <div class="empty">
                <h3>Keranjang Masih Kosong</h3>
                <p>Belum ada produk yang kamu pilih.</p>
                <br>
                <a href="index.php?halaman=produk" class="btn btn-primary">
                    Mulai Belanja
                </a>
            </div>

        <?php endif; ?>

    </section>


<!-- =========================================
     CHECKOUT
========================================= -->

<?php elseif ($halaman === 'checkout'): ?>

    <?php if (!$keranjang): ?>

        <section class="content-card">
            <div class="empty">
                <h3>Keranjang Masih Kosong</h3>
                <p>Tambahkan produk sebelum melanjutkan checkout.</p>
                <br>
                <a href="index.php?halaman=produk" class="btn btn-primary">
                    Pilih Produk
                </a>
            </div>
        </section>

    <?php else: ?>

        <div class="checkout-grid">

            <section class="content-card">

                <div class="section-heading">
                    <h2>Konfirmasi Pesanan</h2>
                    <p>Pastikan produk dan jumlah pesanan sudah benar.</p>
                </div>

                <?php foreach ($keranjang as $item): ?>

                    <div class="summary-line">
                        <span>
                            <?= e($item['nama_produk']) ?>
                            × <?= (int)$item['jumlah_beli'] ?>
                        </span>

                        <strong><?= rupiah($item['subtotal']) ?></strong>
                    </div>

                <?php endforeach; ?>

                <div class="summary-line summary-total">
                    <span>Total Pembayaran</span>
                    <strong><?= rupiah($totalKeranjang) ?></strong>
                </div>

            </section>

            <section class="content-card">

                <div class="section-heading">
                    <h2>Pembayaran</h2>
                    <p>Isi informasi pembayaran pesananmu.</p>
                </div>

                <form method="POST" action="index.php?halaman=checkout"
                      id="formCheckout">

                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="aksi" value="checkout">

                    <div class="input-group">
                        <label>Nama Pelanggan</label>
                        <input class="input"
                               value="<?= e($namaPelanggan) ?>"
                               readonly>
                    </div>

                    <div class="input-group">
                        <label>Metode Pembayaran</label>

                        <select name="metode_pembayaran"
                                id="metodePembayaran" required>

                            <option value="">Pilih pembayaran</option>
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer Bank">Transfer Bank</option>
                            <option value="E-Wallet">E-Wallet</option>
                            <option value="QRIS">QRIS</option>

                        </select>
                    </div>

                    <div class="input-group" id="uangGroup" style="display:none;">

                        <label>Uang Diterima</label>

                        <input
                            type="number"
                            name="uang_diterima"
                            id="uangDiterima"
                            class="input"
                            min="<?= (float)$totalKeranjang ?>"
                            placeholder="Masukkan jumlah uang"
                        >

                        <small style="color:#8794a9;font-size:10px;display:block;margin-top:7px;">
                            Minimal <?= rupiah($totalKeranjang) ?>
                        </small>

                    </div>

                    <div class="summary-line summary-total">
                        <span>Total</span>
                        <strong><?= rupiah($totalKeranjang) ?></strong>
                    </div>

                    <button type="submit"
                            class="btn btn-primary"
                            style="width:100%;margin-top:15px;">

                        Konfirmasi Pembelian

                    </button>

                    <a href="index.php?halaman=keranjang"
                       class="btn btn-light"
                       style="display:block;text-align:center;margin-top:10px;">

                        Kembali ke Keranjang

                    </a>

                </form>

            </section>

        </div>

    <?php endif; ?>


<!-- =========================================
     RIWAYAT
========================================= -->

<?php elseif ($halaman === 'riwayat'): ?>

    <section class="content-card">

        <div class="section-heading">
            <h2>Riwayat Transaksi</h2>
            <p>Semua pembelian yang tercatat pada akunmu.</p>
        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID Transaksi</th>
                        <th>Tanggal</th>
                        <th>Total Belanja</th>
                        <th>Metode Pembayaran</th>
                        <th>Struk</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($transaksi): ?>

                    <?php foreach ($transaksi as $row): ?>

                        <tr>

                            <td class="transaction-id">
                                #<?= e($row['id_transaksi']) ?>
                            </td>

                            <td>
                                <?= date('d-m-Y', strtotime($row['tanggal'])) ?>
                            </td>

                            <td>
                                <strong><?= rupiah($row['total_belanja']) ?></strong>
                            </td>

                            <td>
                                <span class="payment">
                                    <?= e($row['metode_pembayaran'] ?? 'Belum diisi') ?>
                                </span>
                            </td>

                            <td>
                                <a class="btn btn-light"
                                   href="index.php?halaman=struk&id=<?= (int)$row['id_transaksi'] ?>">
                                    Lihat Struk
                                </a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5">
                            <div class="empty">
                                <h3>Belum Ada Transaksi</h3>
                                <p>Riwayat pembelianmu akan muncul di sini.</p>
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>


<!-- =========================================
     PROFIL
========================================= -->

<?php elseif ($halaman === 'profil'): ?>

    <section class="content-card">

        <div class="section-heading">
            <h2>Informasi Profil</h2>
            <p>Informasi akun pelanggan yang sedang login.</p>
        </div>

        <div class="profile">

            <div class="profile-avatar">
                <?= e($inisial) ?>
            </div>

            <div>

                <h3><?= e($namaPelanggan) ?></h3>

                <p>
                    Username: <?= e($akun['username']) ?><br>
                    Nama: <?= e($namaPelanggan) ?><br>
                    Kota: <?= e($akun['kota'] ?: 'Belum diisi') ?><br>
                    ID Pelanggan: #<?= e($idPelanggan) ?>
                </p>

                <span class="account-badge">
                    Akun Pelanggan Aktif
                </span>

            </div>

        </div>

    </section>

    <section class="content-card">

        <div class="section-heading">
            <h2>Ringkasan Akun</h2>
            <p>Informasi aktivitas belanjamu.</p>
        </div>

        <div class="stats">

            <div class="stat-card">
                <span class="stat-title">Total Transaksi</span>
                <h2><?= number_format($totalTransaksi) ?></h2>
            </div>

            <div class="stat-card">
                <span class="stat-title">Total Belanja</span>
                <h2><?= rupiah($totalBelanja) ?></h2>
            </div>

            <div class="stat-card">
                <span class="stat-title">ID Pelanggan</span>
                <h2>#<?= e($idPelanggan) ?></h2>
            </div>

        </div>

    </section>


<!-- =========================================
     STRUK
========================================= -->

<?php elseif ($halaman === 'struk'): ?>

    <section class="content-card receipt">

        <div class="receipt-header">
            <h2>TamtoyMart</h2>
            <p style="font-size:12px;color:#8794a9;">
                Bukti Pembelian Pelanggan
            </p>
        </div>

        <div class="receipt-info">
            <span>Nomor Transaksi</span>
            <strong>#<?= e($dataStruk['id_transaksi']) ?></strong>
        </div>

        <div class="receipt-info">
            <span>Tanggal</span>
            <strong><?= date('d-m-Y', strtotime($dataStruk['tanggal'])) ?></strong>
        </div>

        <div class="receipt-info">
            <span>Pelanggan</span>
            <strong><?= e($namaPelanggan) ?></strong>
        </div>

        <div class="receipt-info">
            <span>Metode Pembayaran</span>
            <strong><?= e($dataStruk['metode_pembayaran'] ?? 'Belum diisi') ?></strong>
        </div>

        <br>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Harga</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($detailStruk as $detail): ?>

                    <tr>

                        <td><?= e($detail['nama_produk']) ?></td>

                        <td><?= rupiah($detail['harga']) ?></td>

                        <td><?= (int)$detail['jumlah'] ?></td>

                        <td>
                            <?= rupiah($detail['harga'] * $detail['jumlah']) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <div class="receipt-total">

            <div class="receipt-info">
                <span>Total Belanja</span>
                <strong><?= rupiah($dataStruk['total_belanja']) ?></strong>
            </div>

            <div class="receipt-info">
                <span>Uang Diterima</span>
                <strong><?= rupiah($dataStruk['uang_diterima'] ?? $dataStruk['total_belanja']) ?></strong>
            </div>

            <div class="receipt-info">
                <span>Kembalian</span>
                <strong><?= rupiah($dataStruk['kembalian'] ?? 0) ?></strong>
            </div>

        </div>

        <div style="text-align:center;margin-top:25px;color:#8794a9;font-size:11px;">
            Terima kasih telah berbelanja di TamtoyMart.
        </div>

        <div class="no-print" style="display:flex;gap:10px;margin-top:25px;">

            <button onclick="window.print()"
                    class="btn btn-primary"
                    style="flex:1;">
                Cetak Struk
            </button>

            <a href="index.php?halaman=riwayat"
               class="btn btn-light"
               style="flex:1;text-align:center;">
                Kembali
            </a>

        </div>

    </section>

<?php endif; ?>


<div class="footer">
    © <?= date('Y') ?> TamtoyMart Customer Portal
</div>

</main>
</div>

<script>

/* PENCARIAN PRODUK */

const cariProduk = document.getElementById('cariProduk');

if (cariProduk) {

    cariProduk.addEventListener('input', function() {

        const keyword = this.value.toLowerCase();
        const cards = document.querySelectorAll('.product-card');

        let jumlahTampil = 0;

        cards.forEach(card => {

            const nama = card.dataset.nama || '';
            const cocok = nama.includes(keyword);

            card.style.display = cocok ? '' : 'none';

            if (cocok) {
                jumlahTampil++;
            }

        });

        const kosong = document.getElementById('hasilKosong');

        if (kosong) {
            kosong.style.display = jumlahTampil === 0 ? 'block' : 'none';
        }

    });

}

/* HAPUS ITEM KERANJANG */

function hapusItem(id) {

    if (confirm('Hapus produk ini dari keranjang?')) {

        const form = document.getElementById('hapus-' + id);

        if (form) {
            form.submit();
        }

    }

}

/* METODE PEMBAYARAN */

const metode = document.getElementById('metodePembayaran');
const uangGroup = document.getElementById('uangGroup');
const uangInput = document.getElementById('uangDiterima');

function aturPembayaran() {

    if (!metode || !uangGroup || !uangInput) {
        return;
    }

    if (metode.value === 'Tunai') {

        uangGroup.style.display = 'block';
        uangInput.required = true;

    } else {

        uangGroup.style.display = 'none';
        uangInput.required = false;
        uangInput.value = '';

    }

}

if (metode) {
    metode.addEventListener('change', aturPembayaran);
}

/* VALIDASI CHECKOUT */

const formCheckout = document.getElementById('formCheckout');

if (formCheckout) {

    formCheckout.addEventListener('submit', function(event) {

        if (!metode.value) {
            event.preventDefault();
            alert('Silakan pilih metode pembayaran.');
            return;
        }

        if (metode.value === 'Tunai' && (!uangInput.value || Number(uangInput.value) < Number(uangInput.min))) {
            event.preventDefault();
            alert('Uang yang dibayarkan belum mencukupi.');
            return;
        }

        if (!confirm('Apakah kamu yakin ingin menyelesaikan pembelian?')) {
            event.preventDefault();
        }

    });

}

</script>

</body>
</html>
