<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id_toko = $_GET['id_toko'] ?? null;

    if (empty($id_toko)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Toko diperlukan'
        ]);
        exit;
    }

    try {
        // Ambil produk dari keranjang berdasarkan ID Toko
        $query = "
            SELECT k.id, p.nama_produk, k.jumlah, p.harga, p.gambar
            FROM keranjang k
            JOIN produk p ON k.id_produk = p.id
            WHERE k.id_toko = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko]);
        $keranjang = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $keranjang
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
