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
$authResult = validateToken(); // Tidak perlu parameter
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

// Ambil input JSON
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);
$nama_produk = $data['nama_produk'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Query untuk mendapatkan produk berdasarkan ID Toko dan optional nama produk (pencarian fleksibel)
        $query = "
            SELECT id, nama_produk, harga_modal, harga_jual, stok, deskripsi, gambar
            FROM produk
            WHERE id_toko = ?";

        if (!empty($nama_produk)) {
            $query .= " AND nama_produk LIKE ?";
        }

        $stmt = $pdo->prepare($query);

        if (!empty($nama_produk)) {
            $stmt->execute([$id_toko, "%$nama_produk%"]); // Mencari produk yang mengandung karakter inputan
        } else {
            $stmt->execute([$id_toko]);
        }

        $produk = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Jika tidak ada data ditemukan
        if (empty($produk)) {
            echo json_encode([
                'success' => true,
                'message' => 'Produk tidak ditemukan',
                'data' => []
            ]);
            exit;
        }

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