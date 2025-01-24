<?php
header('Content-Type: application/json');
require_once '../config/dbconnection.php';
require_once '../vendor/autoload.php';
require_once '../config/helpers.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Muat secret key dari .env
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

// Ambil semua header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader) {
    respondJSON(['success' => false, 'error' => 'Token tidak ditemukan'], 401);
}

// Validasi format token (Bearer {token})
if (!preg_match('/^Bearer\s+(\S+)$/', $authHeader, $matches)) {
    respondJSON(['success' => false, 'error' => 'Format token salah'], 401);
}

// Ambil token dari Authorization header
$token = $matches[1];

try {
    // Decode token JWT untuk validasi
    $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

    // Pastikan token memiliki user_id
    if (!isset($decoded->user_id)) {
        respondJSON(['success' => false, 'error' => 'Token tidak memiliki informasi pengguna'], 401);
    }

    $userId = $decoded->user_id;

    // Periksa apakah token ada di tabel sessions
    $query = "SELECT id FROM sessions WHERE token = :token";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        respondJSON(['success' => false, 'error' => 'Token tidak ditemukan dalam sesi'], 404);
    }

    // Hapus token dari tabel sessions
    $deleteQuery = "DELETE FROM sessions WHERE token = :token";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->bindParam(':token', $token);
    $deleteStmt->execute();

    if ($deleteStmt->rowCount() > 0) {
        respondJSON(['success' => true, 'message' => 'Logout berhasil']);
    } else {
        respondJSON(['success' => false, 'error' => 'Gagal menghapus token dari sesi'], 500);
    }

} catch (\Firebase\JWT\ExpiredException $e) {
    respondJSON(['success' => false, 'error' => 'Token expired'], 401);
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    respondJSON(['success' => false, 'error' => 'Invalid token signature'], 401);
} catch (Exception $e) {
    respondJSON(['success' => false, 'error' => 'Token tidak valid: ' . $e->getMessage()], 401);
}
?>
