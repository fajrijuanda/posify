<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php');
include('../config/helpers.php');

// Load .env jika menggunakan PHP dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

// Validasi token untuk otentikasi
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$authResult = validateToken($authHeader);
if (isset($authResult['error'])) {
    http_response_code(401);
    echo json_encode($authResult);
    exit;
}

// Ambil user_id jika valid
$user_id = $authResult;

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
    } else {
        parse_str(file_get_contents('php://input'), $data);
    }
    // Mendapatkan data dari body request
    // $id_produk = $data['id_produk'] ?? null;
    $id_produk = $_GET['id_produk'] ?? ($data['id_produk'] ?? null);

    if (empty($id_produk)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Produk diperlukan'
        ]);
        exit;
    }

    try {
        // Hapus produk berdasarkan ID
        $query = "DELETE FROM produk WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_produk]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Produk berhasil dihapus'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Produk tidak ditemukan atau gagal dihapus'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?>