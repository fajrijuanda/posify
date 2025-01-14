<?php
header('Content-Type: application/json');
require_once '../config/dbconnection.php';
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_keranjang = $_POST['id_keranjang'] ?? null;
    $subtotal = $_POST['subtotal'] ?? null;
    $total_harga = $_POST['total_harga'] ?? null;
    $metode_pengiriman = $_POST['metode_pengiriman'] ?? 'Bungkus'; // Default
    $id_voucher = $_POST['id_voucher'] ?? null; // Opsional

    if (empty($id_keranjang) || empty($subtotal) || empty($total_harga)) {
        echo json_encode([
            'success' => false,
            'error' => 'Data keranjang, subtotal, dan total harga diperlukan'
        ]);
        exit;
    }

    try {
        // Periksa apakah data checkout sementara sudah ada
        $queryCheck = "SELECT id FROM checkout WHERE id_keranjang = ? AND status = 'sementara'";
        $stmtCheck = $pdo->prepare($queryCheck);
        $stmtCheck->execute([$id_keranjang]);
        $checkout = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($checkout) {
            // Update data checkout sementara
            $queryUpdate = "
                UPDATE checkout 
                SET subtotal = ?, total_harga = ?, metode_pengiriman = ?, id_voucher = ?
                WHERE id = ?";
            $stmtUpdate = $pdo->prepare($queryUpdate);
            $stmtUpdate->execute([$subtotal, $total_harga, $metode_pengiriman, $id_voucher, $checkout['id']]);
        } else {
            // Insert data baru ke tabel checkout
            $queryInsert = "
                INSERT INTO checkout (id_keranjang, subtotal, total_harga, metode_pengiriman, id_voucher, status)
                VALUES (?, ?, ?, ?, ?, 'sementara')";
            $stmtInsert = $pdo->prepare($queryInsert);
            $stmtInsert->execute([$id_keranjang, $subtotal, $total_harga, $metode_pengiriman, $id_voucher]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Data berhasil disimpan sementara'
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
