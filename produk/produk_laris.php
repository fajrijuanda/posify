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
        // Query untuk mendapatkan produk terlaris berdasarkan jumlah checkout
        $query = "
            SELECT 
                p.id, p.nama_produk, p.harga_modal, p.harga_jual, p.stok, p.deskripsi, p.gambar,
                COALESCE(SUM(t.kuantitas), 0) AS total_terjual
            FROM produk p
            LEFT JOIN produkkeranjang t ON p.id = t.id_produk
            WHERE p.id_toko = ? AND p.stok > 0
            GROUP BY p.id, p.nama_produk, p.harga_modal, p.harga_jual, p.stok, p.deskripsi, p.gambar
            ORDER BY total_terjual DESC
            LIMIT 10";  // Ambil 10 produk terlaris
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
