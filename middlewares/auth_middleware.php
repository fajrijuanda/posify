<?php
use \Firebase\JWT\JWT; // Import JWT class

$secretKey = "your-secret-key"; // Ganti dengan key rahasia Anda

function validateToken($pdo) {
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
        $decoded = JWT::decode($token, $secretKey, ['HS256']); // Decode token with secretKey

        // Kembalikan user_id dari token yang sudah didecode
        return $decoded->user_id; // Kembalikan user_id yang dapat digunakan untuk validasi lebih lanjut
    } catch (Exception $e) {
        respondJSON(['success' => false, 'error' => 'Token tidak valid atau kedaluwarsa'], 401);
    }
}

function respondJSON($data, $status = 200) {
    header("Content-Type: application/json");
    http_response_code($status);
    echo json_encode($data);
    exit;
}
?>
