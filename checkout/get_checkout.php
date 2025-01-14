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
        // Ambil semua produk di keranjang berdasarkan toko
        $query = "
            SELECT 
                k.id AS id_keranjang, 
                p.nama_produk, 
                k.jumlah, 
                p.harga AS harga_produk, 
                p.gambar, 
                (k.jumlah * p.harga) AS total_harga_produk
            FROM keranjang k
            JOIN produk p ON k.id_produk = p.id
            WHERE k.id_toko = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko]);
        $keranjang = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cek apakah keranjang kosong
        if (empty($keranjang)) {
            echo json_encode([
                'success' => false,
                'error' => 'Keranjang kosong untuk toko ini'
            ]);
            exit;
        }

        // Hitung subtotal
        $subtotalQuery = "
            SELECT 
                SUM(k.jumlah * p.harga) AS subtotal
            FROM keranjang k
            JOIN produk p ON k.id_produk = p.id
            WHERE k.id_toko = ?";
        $stmtSubtotal = $pdo->prepare($subtotalQuery);
        $stmtSubtotal->execute([$id_toko]);
        $subtotal = $stmtSubtotal->fetchColumn();

        // Cek apakah ada data checkout sementara
        $queryCheckout = "
            SELECT 
                id AS id_checkout, 
                subtotal, 
                total_harga, 
                metode_pengiriman, 
                id_voucher
            FROM checkout 
            WHERE id_keranjang = ? AND status = 'sementara'";
        $stmtCheckout = $pdo->prepare($queryCheckout);
        $stmtCheckout->execute([$keranjang[0]['id_keranjang']]);
        $checkout = $stmtCheckout->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'keranjang' => $keranjang,
            'subtotal' => $subtotal,
            'checkout' => $checkout // Jika tidak ada data, akan bernilai null
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
