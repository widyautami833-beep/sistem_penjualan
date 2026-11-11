<?php
require_once __DIR__ . '/../auth.php';

wajibRole('admin');

/* =========================================
   KONEKSI DATABASE
========================================= */

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
    die('Koneksi database gagal: ' . $e->getMessage());
}

/* =========================================
   HELPER
========================================= */

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

$pesan = '';
$error = '';

/* =========================================
   PASTIKAN TABEL PELANGGAN ADA
========================================= */

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tbl_pelanggan (
            id_pelanggan INT AUTO_INCREMENT PRIMARY KEY,
            nama_pelanggan VARCHAR(100) NOT NULL,
            no_hp VARCHAR(20) DEFAULT NULL,
            alamat TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    die('Gagal memeriksa tabel pelanggan: ' . $e->getMessage());
}

/* =========================================
   TAMBAH PELANGGAN
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $nama = trim($_POST['nama_pelanggan'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if ($nama === '') {
        $error = 'Nama pelanggan wajib diisi.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tbl_pelanggan
                (nama_pelanggan, no_hp, alamat)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([$nama, $no_hp, $alamat]);

            header('Location: index.php?status=tambah');
            exit;
        } catch (PDOException $e) {
            $error = 'Gagal menambahkan pelanggan.';
        }
    }
}

/* =========================================
   EDIT PELANGGAN
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $id = (int) ($_POST['id_pelanggan'] ?? 0);
    $nama = trim($_POST['nama_pelanggan'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if ($id <= 0 || $nama === '') {
        $error = 'Data pelanggan tidak valid.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE tbl_pelanggan
                SET nama_pelanggan = ?,
                    no_hp = ?,
                    alamat = ?
                WHERE id_pelanggan = ?
            ");

            $stmt->execute([$nama, $no_hp, $alamat, $id]);

            header('Location: index.php?status=edit');
            exit;
        } catch (PDOException $e) {
            $error = 'Gagal memperbarui pelanggan.';
        }
    }
}

/* =========================================
   HAPUS PELANGGAN
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $id = (int) ($_POST['id_pelanggan'] ?? 0);

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM tbl_transaksi
            WHERE id_pelanggan = ?
        ");
        $stmt->execute([$id]);

        if ((int) $stmt->fetchColumn() > 0) {
            $error = 'Pelanggan tidak dapat dihapus karena sudah memiliki transaksi.';
        } else {
            $stmt = $pdo->prepare("
                DELETE FROM tbl_pelanggan
                WHERE id_pelanggan = ?
            ");
            $stmt->execute([$id]);

            header('Location: index.php?status=hapus');
            exit;
        }
    } catch (PDOException $e) {
        $error = 'Gagal menghapus pelanggan. Periksa data transaksi terkait.';
    }
}

/* =========================================
   PESAN NOTIFIKASI
========================================= */

if (isset($_GET['status'])) {
    $status = $_GET['status'];

    if ($status === 'tambah') {
        $pesan = 'Pelanggan berhasil ditambahkan.';
    } elseif ($status === 'edit') {
        $pesan = 'Data pelanggan berhasil diperbarui.';
    } elseif ($status === 'hapus') {
        $pesan = 'Pelanggan berhasil dihapus.';
    }
}

/* =========================================
   PENCARIAN PELANGGAN
========================================= */

$keyword = trim($_GET['cari'] ?? '');

if ($keyword !== '') {
    $stmt = $pdo->prepare("
        SELECT *
        FROM tbl_pelanggan
        WHERE nama_pelanggan LIKE ?
           OR no_hp LIKE ?
           OR alamat LIKE ?
        ORDER BY id_pelanggan DESC
    ");

    $cari = "%$keyword%";
    $stmt->execute([$cari, $cari, $cari]);
} else {
    $stmt = $pdo->query("
        SELECT *
        FROM tbl_pelanggan
        ORDER BY id_pelanggan DESC
    ");
}

$pelanggan = $stmt->fetchAll();

/* =========================================
   DATA STATISTIK
========================================= */

$totalPelanggan = (int) $pdo->query("
    SELECT COUNT(*) FROM tbl_pelanggan
")->fetchColumn();

$totalTransaksi = (int) $pdo->query("
    SELECT COUNT(*) FROM tbl_transaksi
")->fetchColumn();

$pelangganBaru = (int) $pdo->query("
    SELECT COUNT(*)
    FROM tbl_pelanggan
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
      AND YEAR(created_at) = YEAR(CURRENT_DATE())
")->fetchColumn();

/* =========================================
   DATA EDIT
========================================= */

$dataEdit = null;

if (isset($_GET['edit'])) {
    $idEdit = (int) $_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM tbl_pelanggan
        WHERE id_pelanggan = ?
    ");

    $stmt->execute([$idEdit]);
    $dataEdit = $stmt->fetch();
}

$modeEdit = $dataEdit !== false && $dataEdit !== null;

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Pelanggan | TamtoyMart</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #3978d7;
            --primary-dark: #2861ba;
            --blue-light: #eaf2ff;
            --background: #f5f8fd;
            --white: #ffffff;
            --text: #24334b;
            --muted: #8794a9;
            --border: #e8edf5;
            --green: #19a879;
            --red: #e35b68;
            --shadow: 0 5px 20px rgba(40, 76, 130, .06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
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
            background: var(--white);
            border-right: 1px solid var(--border);
            padding: 28px 18px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 10;
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
            color: var(--text);
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

        .sidebar-bottom {
            position: absolute;
            bottom: 25px;
            left: 18px;
            right: 18px;
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

        .admin-info {
            display: flex;
            align-items: center;
            gap: 11px;
            background: white;
            padding: 9px 15px;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .admin-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: var(--primary);
            background: var(--blue-light);
            font-weight: 700;
        }

        .admin-info strong {
            font-size: 12px;
        }

        .admin-info small {
            display: block;
            font-size: 10px;
            color: var(--muted);
            margin-top: 3px;
        }

        /* STATISTIK */

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 21px;
            box-shadow: var(--shadow);
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-title {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .stat-icon {
            width: 39px;
            height: 39px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: var(--blue-light);
            color: var(--primary);
            font-size: 18px;
        }

        .stat-card h2 {
            font-size: 25px;
            margin-top: 13px;
        }

        .stat-card small {
            font-size: 11px;
            color: var(--muted);
        }

        /* CONTENT */

        .content-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 24px;
            margin-bottom: 22px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 22px;
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

        .btn {
            border: none;
            border-radius: 9px;
            padding: 11px 16px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-light {
            background: #eef3fa;
            color: #61718a;
        }

        .btn-edit {
            background: #eaf2ff;
            color: var(--primary);
            padding: 8px 11px;
        }

        .btn-delete {
            background: #fff0f1;
            color: var(--red);
            padding: 8px 11px;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 12px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #e9f9f2;
            color: #13865f;
        }

        .alert-error {
            background: #fff0f1;
            color: #c94351;
        }

        /* FORM */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 17px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 700;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            outline: none;
            font-family: inherit;
            font-size: 12px;
            color: var(--text);
            background: #fbfcff;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(57, 120, 215, .08);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 85px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        /* SEARCH */

        .search-form {
            display: flex;
            gap: 9px;
            width: min(100%, 390px);
        }

        .search-form input {
            flex: 1;
            min-width: 0;
            border: 1px solid var(--border);
            background: #fbfcff;
            padding: 11px 13px;
            border-radius: 9px;
            font-family: inherit;
            font-size: 12px;
            outline: none;
        }

        /* TABLE */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th {
            text-align: left;
            padding: 14px 13px;
            background: #f7f9fd;
            color: #8290a5;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        td {
            padding: 15px 13px;
            border-bottom: 1px solid #edf0f5;
            font-size: 12px;
            color: #59677d;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .customer {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .customer-avatar {
            width: 37px;
            height: 37px;
            border-radius: 11px;
            background: var(--blue-light);
            color: var(--primary);
            display: grid;
            place-items: center;
            font-weight: 800;
            flex-shrink: 0;
        }

        .customer-name {
            font-weight: 700;
            color: var(--text);
        }

        .customer-id {
            font-size: 10px;
            color: var(--muted);
            margin-top: 4px;
        }

        .action-buttons {
            display: flex;
            gap: 7px;
        }

        .empty {
            text-align: center;
            padding: 45px 15px;
            color: var(--muted);
        }

        .empty-icon {
            font-size: 30px;
            margin-bottom: 12px;
        }

        .empty h3 {
            font-size: 14px;
            color: var(--text);
            margin-bottom: 6px;
        }

        .empty p {
            font-size: 11px;
        }

        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 18px;
            font-size: 11px;
            color: var(--muted);
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {
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

            .sidebar-bottom {
                left: 10px;
                right: 10px;
            }

            .sidebar-bottom a {
                text-align: center;
                font-size: 0;
            }

            .sidebar-bottom a::first-letter {
                font-size: 16px;
            }

            .main {
                margin-left: 75px;
                width: calc(100% - 75px);
                padding: 25px 20px;
            }
        }

        @media (max-width: 600px) {
            .main {
                padding: 20px 13px;
            }

            .topbar {
                align-items: flex-start;
            }

            .topbar h1 {
                font-size: 19px;
            }

            .admin-info {
                padding: 7px;
            }

            .admin-info div:last-child {
                display: none;
            }

            .stats {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .stat-card {
                padding: 16px;
            }

            .content-card {
                padding: 17px;
            }

            .section-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .section-heading .btn {
                width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .search-form {
                width: 100%;
            }

            .table-footer {
                align-items: flex-start;
                flex-direction: column;
                gap: 8px;
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
                <span>Management System</span>
            </div>
        </div>

        <div class="menu-label">MENU UTAMA</div>

        <nav class="menu">

            <a href="../index.php">
                <span class="menu-icon">⌂</span>
                <span class="menu-text">Dashboard</span>
            </a>

            <a href="../index.php?page=produk">
                <span class="menu-icon">▦</span>
                <span class="menu-text">Produk</span>
            </a>

            <a href="index.php" class="active">
                <span class="menu-icon">♙</span>
                <span class="menu-text">Pelanggan</span>
            </a>

            <a href="../index.php?page=transaksi">
                <span class="menu-icon">▤</span>
                <span class="menu-text">Transaksi</span>
            </a>

            <a href="../index.php?page=kasir">
                <span class="menu-icon">▣</span>
                <span class="menu-text">Kasir</span>
            </a>

            <a href="../index.php?page=laporan">
                <span class="menu-icon">▥</span>
                <span class="menu-text">Laporan</span>
            </a>

        </nav>

        <div class="sidebar-bottom">
            <a href="../logout.php">↪ Keluar</a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">

        <header class="topbar">

            <div>
                <h1>Data Pelanggan</h1>
                <p>Kelola informasi dan data pelanggan TamtoyMart.</p>
            </div>

            <div class="admin-info">
                <div class="admin-avatar">A</div>

                <div>
                    <strong>Administrator</strong>
                    <small>Admin Sistem</small>
                </div>
            </div>

        </header>

        <!-- NOTIFIKASI -->
        <?php if ($pesan): ?>
            <div class="alert alert-success">
                ✓ <?= e($pesan) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- STATISTIK -->
        <section class="stats">

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-title">Total Pelanggan</span>
                    <div class="stat-icon">♙</div>
                </div>

                <h2><?= number_format($totalPelanggan) ?></h2>
                <small>Seluruh pelanggan terdaftar</small>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-title">Pelanggan Baru</span>
                    <div class="stat-icon">＋</div>
                </div>

                <h2><?= number_format($pelangganBaru) ?></h2>
                <small>Pelanggan bulan ini</small>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-title">Total Transaksi</span>
                    <div class="stat-icon">▤</div>
                </div>

                <h2><?= number_format($totalTransaksi) ?></h2>
                <small>Transaksi yang tercatat</small>
            </div>

        </section>

        <!-- FORM TAMBAH / EDIT -->
        <section class="content-card" id="form-pelanggan">

            <div class="section-heading">

                <div>
                    <h2><?= $modeEdit ? 'Edit Pelanggan' : 'Tambah Pelanggan' ?></h2>
                    <p>
                        <?= $modeEdit
                            ? 'Perbarui informasi pelanggan.'
                            : 'Masukkan informasi pelanggan baru.' ?>
                    </p>
                </div>

                <?php if ($modeEdit): ?>
                    <a href="index.php" class="btn btn-light">Batal Edit</a>
                <?php endif; ?>

            </div>

            <form method="POST">

                <?php if ($modeEdit): ?>
                    <input
                        type="hidden"
                        name="id_pelanggan"
                        value="<?= e($dataEdit['id_pelanggan']) ?>"
                    >
                <?php endif; ?>

                <div class="form-grid">

                    <div class="form-group">
                        <label>Nama Pelanggan *</label>

                        <input
                            type="text"
                            name="nama_pelanggan"
                            placeholder="Masukkan nama pelanggan"
                            value="<?= $modeEdit ? e($dataEdit['nama_pelanggan']) : '' ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Nomor HP</label>

                        <input
                            type="tel"
                            name="no_hp"
                            placeholder="Contoh: 081234567890"
                            value="<?= $modeEdit ? e($dataEdit['no_hp']) : '' ?>"
                            maxlength="20"
                        >
                    </div>

                    <div class="form-group full">
                        <label>Alamat</label>

                        <textarea
                            name="alamat"
                            placeholder="Masukkan alamat pelanggan..."
                        ><?= $modeEdit ? e($dataEdit['alamat']) : '' ?></textarea>
                    </div>

                </div>

                <div class="form-actions">

                    <?php if ($modeEdit): ?>

                        <button type="submit" name="edit" class="btn btn-primary">
                            Simpan Perubahan
                        </button>

                    <?php else: ?>

                        <button type="submit" name="tambah" class="btn btn-primary">
                            + Tambah Pelanggan
                        </button>

                    <?php endif; ?>

                    <a href="index.php" class="btn btn-light">
                        Reset
                    </a>

                </div>

            </form>

        </section>

        <!-- DAFTAR PELANGGAN -->
        <section class="content-card">

            <div class="section-heading">

                <div>
                    <h2>Daftar Pelanggan</h2>
                    <p>Informasi seluruh pelanggan yang terdaftar.</p>
                </div>

                <form method="GET" class="search-form">

                    <input
                        type="text"
                        name="cari"
                        placeholder="Cari nama, nomor HP, atau alamat..."
                        value="<?= e($keyword) ?>"
                    >

                    <button type="submit" class="btn btn-primary">
                        Cari
                    </button>

                    <?php if ($keyword !== ''): ?>
                        <a href="index.php" class="btn btn-light">Reset</a>
                    <?php endif; ?>

                </form>

            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Pelanggan</th>
                            <th>Nomor HP</th>
                            <th>Alamat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($pelanggan) > 0): ?>

                        <?php $no = 1; ?>

                        <?php foreach ($pelanggan as $row): ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td>
                                    <div class="customer">

                                        <div class="customer-avatar">
                                            <?= strtoupper(substr($row['nama_pelanggan'], 0, 1)) ?>
                                        </div>

                                        <div>
                                            <div class="customer-name">
                                                <?= e($row['nama_pelanggan']) ?>
                                            </div>

                                            <div class="customer-id">
                                                ID: #<?= e($row['id_pelanggan']) ?>
                                            </div>
                                        </div>

                                    </div>
                                </td>

                                <td>
                                    <?= $row['no_hp']
                                        ? e($row['no_hp'])
                                        : '<span style="color:#a0aabd">Belum diisi</span>' ?>
                                </td>

                                <td>
                                    <?= $row['alamat']
                                        ? e($row['alamat'])
                                        : '<span style="color:#a0aabd">Belum diisi</span>' ?>
                                </td>

                                <td>

                                    <div class="action-buttons">

                                        <a
                                            href="index.php?edit=<?= (int) $row['id_pelanggan'] ?>#form-pelanggan"
                                            class="btn btn-edit"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus pelanggan ini?')"
                                        >

                                            <input
                                                type="hidden"
                                                name="id_pelanggan"
                                                value="<?= (int) $row['id_pelanggan'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="hapus"
                                                class="btn btn-delete"
                                            >
                                                Hapus
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5">

                                <div class="empty">

                                    <div class="empty-icon">♙</div>

                                    <h3>
                                        <?= $keyword !== ''
                                            ? 'Pelanggan tidak ditemukan'
                                            : 'Belum Ada Data Pelanggan' ?>
                                    </h3>

                                    <p>
                                        <?= $keyword !== ''
                                            ? 'Coba gunakan kata kunci pencarian lainnya.'
                                            : 'Tambahkan pelanggan pertama melalui formulir di atas.' ?>
                                    </p>

                                </div>

                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <div class="table-footer">
                <span>Menampilkan <?= count($pelanggan) ?> data pelanggan</span>
                <span>Total keseluruhan: <?= number_format($totalPelanggan) ?> pelanggan</span>
            </div>

        </section>

        <div style="text-align:center;color:#a0aabd;font-size:10px;padding:10px;">
            © <?= date('Y') ?> TamtoyMart Management System
        </div>

    </main>

</div>

</body>
</html>
