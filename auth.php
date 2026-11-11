
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function arahkanDashboard()
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        header("Location: /sistem-penjualan/login.php");
        exit;
    }

    switch (strtolower($_SESSION['role'])) {
        case 'admin':
            header("Location: /sistem-penjualan/index.php");
            exit;

        case 'kasir':
            header("Location: /sistem-penjualan/kasir/index.php");
            exit;

        case 'pelanggan':
            header("Location: /sistem-penjualan/pelanggan/index.php");
            exit;

        default:
            session_unset();
            session_destroy();
            header("Location: /sistem-penjualan/login.php");
            exit;
    }
}

function wajibLogin()
{
    if (empty($_SESSION['user_id'])) {
        header("Location: /sistem-penjualan/login.php");
        exit;
    }
}

function wajibRole($role)
{
    wajibLogin();

    $roleSekarang = strtolower($_SESSION['role'] ?? '');

    if ($roleSekarang !== strtolower($role)) {
        arahkanDashboard();
    }
}
