<?php
header('Content-Type: application/json');
require_once '../config/dbconnection.php';
require_once '../vendor/autoload.php';
require_once '../config/helpers.php';
use \Firebase\JWT\JWT;

// Muat secret key dari .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$secretKey = $_ENV['JWT_SECRET'] ?? null;

// Validasi secret key
if (empty($secretKey)) {
    respondJSON(['success' => false, 'error' => 'Secret key tidak ditemukan'], 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil semua header
    $headers = getallheaders();

    // Periksa apakah header Authorization ada dan sesuai format
    $authHeader = $headers['Authorization'] ?? null;

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        respondJSON(['success' => false, 'error' => 'Token tidak ditemukan atau format salah'], 401);
    }

    // Ambil token dari header Authorization
    $token = $matches[1];

    try {
        // Decode token JWT untuk validasi
        $decoded = JWT::decode($token, new \Firebase\JWT\Key($secretKey, 'HS256')); // Gunakan \Firebase\JWT\Key untuk versi terbaru
    
        // Ambil `user_id` dari token jika diperlukan
        $userId = $decoded->user_id;
    
        // Periksa apakah token ada di tabel sessions
        $query = "SELECT id FROM sessions WHERE token = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$token]);
    
        if ($stmt->rowCount() === 0) {
            // Token tidak ditemukan di tabel sessions
            respondJSON(['success' => false, 'error' => 'Token tidak ditemukan di sesi'], 404);
        }
    
        // Hapus token dari tabel sessions
        $deleteQuery = "DELETE FROM sessions WHERE token = ?";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute([$token]);
    
        if ($deleteStmt->rowCount() > 0) {
            // Token berhasil dihapus
            respondJSON(['success' => true, 'message' => 'Logout berhasil']);
        } else {
            // Token tidak berhasil dihapus karena alasan yang tidak diketahui
            respondJSON(['success' => false, 'error' => 'Gagal menghapus token dari sesi'], 500);
        }
    } catch (\Firebase\JWT\ExpiredException $e) {
        respondJSON(['success' => false, 'error' => 'Token expired'], 401);
    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        respondJSON(['success' => false, 'error' => 'Invalid token signature'], 401);
    } catch (Exception $e) {
        // Token tidak valid atau expired
        respondJSON(['success' => false, 'error' => 'Token tidak valid: ' . $e->getMessage()], 401);
    }
    
} else {
    respondJSON(['success' => false, 'error' => 'Invalid request method'], 405);
}

