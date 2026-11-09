<?php
$pdo = new PDO(
    "mysql:host=localhost;dbname=db_toko;charset=utf8mb4",
    "root",
    "",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

$username = "admin";
$password = password_hash("admin123", PASSWORD_DEFAULT);
$nama = "Administrator";

$stmt = $pdo->prepare("
    INSERT INTO users (username, password, nama, role)
    VALUES (?, ?, ?, 'admin')
    ON DUPLICATE KEY UPDATE
        password = VALUES(password),
        nama = VALUES(nama)
");

$stmt->execute([$username, $password, $nama]);

echo "Akun admin berhasil dibuat!";