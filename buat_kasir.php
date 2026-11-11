<?php

$pdo = new PDO(
    "mysql:host=localhost;dbname=db_toko;charset=utf8mb4",
    "root",
    ""
);

$username = "kasir01";
$password = "kasir123";
$nama = "Kasir Toko";
$role = "kasir";

$cek = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$cek->execute([$username]);

if ($cek->fetch()) {
    die("Username kasir01 sudah digunakan.");
}

$stmt = $pdo->prepare("
    INSERT INTO users (username, password, nama, role)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $username,
    password_hash($password, PASSWORD_DEFAULT),
    $nama,
    $role
]);

echo "Akun kasir berhasil dibuat.";