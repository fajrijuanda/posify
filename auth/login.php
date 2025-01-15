<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
require_once '../vendor/autoload.php';
require_once("../config/helpers.php"); // Pastikan hanya ini yang digunakan
use \Firebase\JWT\JWT;

// Muat secret key dari .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$secretKey = $_ENV['JWT_SECRET'] ?? null;

if (empty($secretKey)) {
    respondJSON(['success' => false, 'error' => 'Secret key tidak ditemukan'], 500);
}


if (empty($secretKey)) {
    respondJSON(['success' => false, 'error' => 'Secret key tidak ditemukan'], 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input dari user
    $nama_toko = sanitizeInput($_POST['nama_toko'] ?? '');
    $password = sanitizeInput($_POST['password'] ?? '');

    // Validasi input
    if (empty($nama_toko) || empty($password)) {
        respondJSON(['success' => false, 'error' => 'Nama Toko dan Password wajib diisi'], 400);
    }

    try {
        // Query toko berdasarkan nama_toko
        $query = "SELECT u.id AS id_user, t.id AS id_toko, u.password 
                  FROM users u
                  JOIN toko t ON t.id_user = u.id
                  WHERE t.nama_toko = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$nama_toko]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Membuat payload untuk token JWT
            $payload = [
                'user_id' => $user['id_user'],
                'nama_toko' => $nama_toko,
                'exp' => time() + 3600 // Token berlaku selama 1 jam
            ];

            // Encode payload menjadi JWT
            $jwt = JWT::encode($payload, $secretKey, 'HS256');

            // Waktu sekarang (created_at) dan waktu kedaluwarsa (expired_at)
            $createdAt = date('Y-m-d H:i:s');
            $expiredAt = date('Y-m-d H:i:s', time() + 3600);

            // Simpan token ke tabel sessions
            $insertQuery = "INSERT INTO sessions (user_id, token, expired_at, created_at) VALUES (?, ?, ?, ?)";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->execute([$user['id_user'], $jwt, $expiredAt, $createdAt]);

            // Kirim token ke klien
            respondJSON([
                'success' => true,
                'message' => 'Login berhasil',
                'token' => $jwt,
                'nama_toko' => $nama_toko,
            ]);
        } else {
            // Jika password salah
            respondJSON(['success' => false, 'error' => 'Nama Toko atau Password salah'], 401);
        }
    } catch (PDOException $e) {
        respondJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    }
} else {
    // Jika metode request bukan POST
    respondJSON(['success' => false, 'error' => 'Invalid request method'], 405);
}

?>
