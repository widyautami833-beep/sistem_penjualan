
<?php

$pdo = new PDO(
    "mysql:host=localhost;dbname=db_toko;charset=utf8mb4",
    "root",
    "",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]
);

// Password baru akun pelanggan
$passwordBaru = 'Pelanggan123';

$hash = password_hash($passwordBaru, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    UPDATE users
    SET password = ?, role = 'pelanggan'
    WHERE username = 'pelanggan01'
");

$stmt->execute([$hash]);

if ($stmt->rowCount() > 0) {
    echo "Password pelanggan berhasil diperbarui.";
} else {
    echo "Tidak ada perubahan. Periksa apakah username pelanggan01 tersedia.";
}
