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
$authResult = validateToken(); 
if (!is_array($authResult) || !isset($authResult['user_id'], $authResult['id_toko'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

// Ambil user_id & id_toko dari token JWT
$user_id = $authResult['user_id'];
$id_toko = $authResult['id_toko'];

// Debugging: Pastikan id_toko ada sebelum query
error_log("User ID: " . $user_id);
error_log("ID Toko: " . $id_toko);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Ambil semua produk yang memiliki stok lebih dari 0 berdasarkan ID Toko
        $query = "
            SELECT id, nama_produk, harga_modal, harga_jual, stok, deskripsi, gambar
            FROM produk
            WHERE id_toko = ? AND stok > 0";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko]);
        $produk = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tambahkan URL lengkap ke gambar produk
        foreach ($produk as &$item) {
            $item['gambar_url'] = !empty($item['gambar']) ? $baseURL . '/' . $item['gambar'] : null;
        }

        echo json_encode([
            'success' => true,
            'data' => $produk
        ]);
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
