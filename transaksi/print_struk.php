<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id_checkout = $_GET['id_checkout'] ?? null;

    if (empty($id_checkout)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Checkout diperlukan'
        ]);
        exit;
    }

    try {
        // Ambil detail checkout
        $query = "
            SELECT c.id AS id_checkout, c.subtotal, c.total_harga, p.nama_produk, pk.kuantitas, p.harga_jual
            FROM checkout c
            JOIN produkkeranjang pk ON c.id_keranjang = pk.id_keranjang
            JOIN produk p ON pk.id_produk = p.id
            WHERE c.id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_checkout]);
        $checkout = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$checkout) {
            echo json_encode([
                'success' => false,
                'error' => 'Checkout tidak ditemukan'
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data' => $checkout
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
