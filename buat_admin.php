
<?php
$pdo = new PDO(
    "mysql:host=localhost;dbname=db_toko_pulih;charset=utf8mb4",
    "root",
    ""
);

$username = "admin";
$password = password_hash("admin123", PASSWORD_DEFAULT);
$nama = "Administrator";
$role = "admin";

$stmt = $pdo->prepare("
    INSERT INTO users (username, password, nama, role)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([$username, $password, $nama, $role]);

echo "Akun admin berhasil dibuat!";
