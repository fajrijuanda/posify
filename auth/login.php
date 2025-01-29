<?php
header('Content-Type: application/json');
require_once '../config/dbconnection.php';
require_once '../vendor/autoload.php';
require_once '../config/helpers.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$secretKey = $_ENV['JWT_SECRET'] ?? null;

// Validasi secret key
if (empty($secretKey)) {
    respondJSON(['success' => false, 'error' => 'Secret key tidak ditemukan'], 500);
}

// Pastikan metode HTTP adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJSON(['success' => false, 'error' => 'Invalid request method'], 405);
}

// Ambil JSON input dari user
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

if (!is_array($data)) {
    respondJSON(['success' => false, 'error' => 'Invalid JSON format'], 400);
}

$email = sanitizeInput($data['name'] ?? '');
$password = sanitizeInput($data['password'] ?? '');

// Validasi input kosong
if (empty($email) || empty($password)) {
    respondJSON(['success' => false, 'error' => 'nama toko dan Password wajib diisi'], 400);
}

try {
    // Query untuk mendapatkan user berdasarkan email
    $query = "SELECT id, name, password FROM users WHERE name = :name";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':name', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Ambil id_toko berdasarkan id_user dari tabel toko
        $stmt = $pdo->prepare("SELECT id FROM toko WHERE id_user = ?");
        $stmt->execute([$user['id']]);
        $toko = $stmt->fetch(PDO::FETCH_ASSOC);

        $id_toko = $toko['id'] ?? null; // Jika tidak ada toko, tetap null

        // Membuat payload untuk token JWT
        $payload = [
            'user_id' => $user['id'],
            'nama_toko' => $user['name'],
            'id_toko' => $id_toko, // Tambahkan id_toko ke token
            'exp' => time() + 86400  // Token berlaku selama 1 hari (86400 detik)
        ];

        // Encode payload menjadi JWT
        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        // Simpan token ke tabel sessions
        $insertQuery = "INSERT INTO sessions (user_id, token, expired_at, created_at) VALUES (?, ?, ?, ?)";
        $insertStmt = $pdo->prepare($insertQuery);
        $expiredAt = date('Y-m-d H:i:s', time() + 86400);
        $createdAt = date('Y-m-d H:i:s');
        $insertStmt->execute([$user['id'], $jwt, $expiredAt, $createdAt]);

        // Kirim token ke klien
        respondJSON([
            'success' => true,
            'message' => 'Login berhasil',
            'token' => $jwt,
            'user' => [
                'id_user' => $user['id'],
                'nama_toko' => $user['name'],
                'id_toko' => $id_toko
            ]
        ]);
    } else {
        // Jika password salah atau user tidak ditemukan
        respondJSON(['success' => false, 'error' => 'name atau Password salah'], 401);
    }
} catch (PDOException $e) {
    respondJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
?>
