<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_toko = $_POST['id_toko'] ?? null;

    if (empty($id_toko)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Toko diperlukan'
        ]);
        exit;
    }

    try {
        // Ambil total belanja dari keranjang
        $queryTotal = "
            SELECT SUM(k.jumlah * p.harga) AS total
            FROM keranjang k
            JOIN produk p ON k.id_produk = p.id
            WHERE k.id_toko = ?";
        $stmtTotal = $pdo->prepare($queryTotal);
        $stmtTotal->execute([$id_toko]);
        $total = $stmtTotal->fetchColumn();

        if ($total > 0) {
            // Masukkan transaksi ke tabel checkout
            $queryCheckout = "INSERT INTO checkout (id_toko, total_harga) VALUES (?, ?)";
            $stmtCheckout = $pdo->prepare($queryCheckout);
            $stmtCheckout->execute([$id_toko, $total]);

            // Kosongkan keranjang
            $queryClear = "DELETE FROM keranjang WHERE id_toko = ?";
            $stmtClear = $pdo->prepare($queryClear);
            $stmtClear->execute([$id_toko]);

            echo json_encode([
                'success' => true,
                'message' => 'Checkout berhasil',
                'total' => $total
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Keranjang kosong'
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
