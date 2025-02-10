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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Query untuk menghitung total produk yang telah terjual
        $query = "
            SELECT 
                p.id AS id_produk, 
                p.nama_produk, 
                COALESCE(SUM(t.qty), 0) AS total_terjual
            FROM produk p
            LEFT JOIN transaksi t ON p.id = t.id_produk
            WHERE p.id_toko = ?
            GROUP BY p.id, p.nama_produk
            ORDER BY total_terjual DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko]);
        $produkTerjual = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $produkTerjual
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
