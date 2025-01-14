<?php
header('Content-Type: application/json');
include("../config/dbconnection.php"); // Sesuaikan path jika diperlukan
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id_produk = $_GET['id_produk'] ?? null;

    if (empty($id_produk)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Produk diperlukan'
        ]);
        exit;
    }

    try {
        // Query untuk mendapatkan detail produk
        $query = "
            SELECT 
                p.id, 
                p.nama_produk, 
                p.deskripsi, 
                p.harga_jual, 
                p.stok, 
                p.gambar, 
                t.nama_toko, 
                t.nomor_telepon, 
                t.email 
            FROM produk p
            JOIN toko t ON p.id_toko = t.id
            WHERE p.id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_produk]);
        $produk = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($produk) {
            echo json_encode([
                'success' => true,
                'produk' => $produk
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Produk tidak ditemukan'
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
