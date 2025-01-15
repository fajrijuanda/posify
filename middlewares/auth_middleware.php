<?php 
use \Firebase\JWT\JWT; 
require_once '../vendor/autoload.php'; 

// Muat file .env menggunakan vlucas/phpdotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Fungsi untuk validasi token
function validateToken($pdo) {
    // Ambil secret key dari .env
    $secretKey = $_ENV['JWT_SECRET'] ?? null;

    // Pastikan $secretKey diinisialisasi sebelum digunakan
    if (empty($secretKey)) {
        respondJSON(['success' => false, 'error' => 'Secret key tidak ditemukan'], 500);
    }

    // Ambil token dari header Authorization
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        respondJSON(['success' => false, 'error' => 'Token tidak ditemukan'], 401);
    }

    $authHeader = $headers['Authorization'];
    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        respondJSON(['success' => false, 'error' => 'Format token tidak valid'], 401);
    }

    $token = $matches[1];

    try {
        // Decode token JWT
        $decoded = JWT::decode($token, $secretKey, ['HS256']);

        // Kembalikan user_id dari token yang sudah didecode
        return $decoded->user_id;
    } catch (Exception $e) {
        respondJSON(['success' => false, 'error' => 'Token tidak valid atau kedaluwarsa'], 401);
    }
}

// Fungsi untuk mengirimkan respons JSON
function respondJSON($data, $status = 200) {
    header("Content-Type: application/json");
    http_response_code($status);
    echo json_encode($data);
    exit;
}
