<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Load autoload composer

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Ambil variabel dari .env
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbname = $_ENV['DB_DATABASE'] ?? 'db_posify';
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

try {
    // Koneksi PDO menggunakan variabel dari .env
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Debug: Tampilkan pesan jika koneksi berhasil
    // echo "Connected successfully to database!";
} catch (PDOException $e) {
    die(json_encode([
        "success" => false,
        "error" => "Database connection failed: " . $e->getMessage()
    ]));
}
?>
