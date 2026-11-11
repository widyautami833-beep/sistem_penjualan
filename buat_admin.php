
<?php
$host = "localhost";
$dbname = "db_toko";
$usernameDB = "root";
$passwordDB = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $usernameDB,
        $passwordDB,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Periksa apakah akun Admin sudah tersedia
    $stmt = $pdo->prepare("
        SELECT id FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->execute(["admin"]);
    $admin = $stmt->fetch();

    if ($admin) {
        echo "Akun Admin sudah tersedia. Tidak ada perubahan password.";
    } else {
        $password = password_hash("admin123", PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, nama, role)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            "admin",
            $password,
            "Administrator",
            "admin"
        ]);

        echo "Akun Admin berhasil dibuat!";
        echo "<br>Username: admin";
        echo "<br>Password: admin123";
    }

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
