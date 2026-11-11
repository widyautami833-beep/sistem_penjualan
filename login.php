
<?php
session_start();

/*
|--------------------------------------------------------------------------
| KONEKSI DATABASE
|--------------------------------------------------------------------------
*/

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
    die("Koneksi database gagal.");
}

/*
|--------------------------------------------------------------------------
| REDIRECT BERDASARKAN ROLE
|--------------------------------------------------------------------------
*/

function redirectRole($role)
{
    switch ($role) {
        case 'admin':
            header("Location: index.php");
            exit;

        case 'kasir':
            header("Location: kasir/index.php");
            exit;

        case 'pelanggan':
            header("Location: pelanggan/index.php");
            exit;

        default:
            session_unset();
            session_destroy();
            header("Location: login.php");
            exit;
    }
}

/*
|--------------------------------------------------------------------------
| JIKA SUDAH LOGIN
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['user_id'])) {
    redirectRole($_SESSION['role'] ?? '');
}

$error = "";

/*
|--------------------------------------------------------------------------
| PROSES LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = "Username dan password wajib diisi!";

    } else {

        $stmt = $pdo->prepare("
            SELECT id, username, password, nama, role
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            $role = strtolower(trim($user['role']));

            if (in_array($role, ['admin', 'kasir', 'pelanggan'], true)) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role'] = $role;

                redirectRole($role);

            } else {

                $error = "Role akun tidak ditemukan!";

            }

        } else {

            $error = "Username atau password salah!";

        }
    }
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | TamtoyMart</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: Inter, Arial, sans-serif;
    background: linear-gradient(135deg,#eaf2ff,#dceaff,#f5f8fc);
    padding: 20px;
}

.login-container {
    width: 100%;
    max-width: 420px;
    background: white;
    padding: 40px;
    border-radius: 18px;
    box-shadow: 0 15px 45px rgba(35,76,130,.12);
}

.logo {
    text-align: center;
    font-size: 28px;
    font-weight: 800;
    color: #263e61;
    margin-bottom: 10px;
}

.logo span {
    color: #4788df;
}

.subtitle {
    text-align: center;
    color: #8794a7;
    font-size: 12px;
    margin-bottom: 32px;
}

h2 {
    color: #26364b;
    font-size: 21px;
    margin-bottom: 8px;
}

.description {
    color: #8794a7;
    font-size: 12px;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #53647a;
    margin-bottom: 8px;
}

input {
    width: 100%;
    padding: 13px;
    border: 1px solid #e1e8f1;
    border-radius: 8px;
    outline: none;
    font-size: 13px;
}

input:focus {
    border-color: #4788df;
    box-shadow: 0 0 0 3px #4788df18;
}

button {
    width: 100%;
    padding: 14px;
    background: #3978d5;
    border: none;
    border-radius: 8px;
    color: white;
    font-weight: 700;
    cursor: pointer;
    font-size: 13px;
}

button:hover {
    background: #2865bd;
}

.error {
    background: #fff0f0;
    color: #c33c3c;
    padding: 12px;
    border-radius: 8px;
    font-size: 12px;
    margin-bottom: 18px;
}

.footer {
    text-align: center;
    color: #a0aaba;
    font-size: 10px;
    margin-top: 30px;
}
</style>
</head>

<body>

<div class="login-container">

    <div class="logo">Store<span>Flow</span></div>

    <div class="subtitle">Sistem Informasi Penjualan Toko</div>

    <h2>Selamat Datang!</h2>

    <p class="description">
        Silakan login untuk mengakses dashboard TamtoyMart.
    </p>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label>Username</label>
            <input
                type="text"
                name="username"
                placeholder="Masukkan username"
                autocomplete="username"
                required
            >
        </div>

        <div class="form-group">
            <label>Password</label>
            <input
                type="password"
                name="password"
                placeholder="Masukkan password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit">Login ke Dashboard</button>

    </form>

    <div class="footer">
        © 2026 TamtoyMart. All rights reserved.
    </div>

</div>

</body>
</html>