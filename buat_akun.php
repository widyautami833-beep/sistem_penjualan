
<?php
$pdo = new PDO(
    "mysql:host=localhost;dbname=db_toko_pulih;charset=utf8mb4",
    "root",
    ""
);

$akun = [
    ['kasir', 'kasir123', 'Petugas Kasir', 'kasir'],
    ['pelanggan', 'pelanggan123', 'Pelanggan Toko', 'pelanggan']
];

foreach ($akun as $data) {
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, nama, role)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $data[0],
        password_hash($data[1], PASSWORD_DEFAULT),
        $data[2],
        $data[3]
    ]);
}

echo "Akun kasir dan pelanggan berhasil dibuat!";
