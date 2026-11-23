
<?php
$pdo = new PDO(
    "mysql:host=localhost;dbname=db_toko_pulih;charset=utf8mb4",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$akun = [
    ['admin', 'admin123'],
    ['kasir', 'kasir123'],
    ['pelanggan', 'pelanggan123']
];

foreach ($akun as $data) {
    $passwordHash = password_hash($data[1], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE users
        SET password = ?
        WHERE username = ?
    ");

    $stmt->execute([$passwordHash, $data[0]]);

    echo "Akun " . htmlspecialchars($data[0]) .
         ": " . ($stmt->rowCount() > 0 ? "Password diperbarui" : "Periksa akun") .
         "<br>";
}
?>
