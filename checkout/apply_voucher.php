<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_toko = $_POST['id_toko'] ?? null;
    $kode_voucher = $_POST['kode_voucher'] ?? null;

    if (empty($id_toko) || empty($kode_voucher)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Toko dan Kode Voucher diperlukan'
        ]);
        exit;
    }

    try {
        // Cek validitas voucher
        $queryVoucher = "SELECT id, nilai_diskon FROM voucher WHERE kode = ? AND kuota > 0";
        $stmtVoucher = $pdo->prepare($queryVoucher);
        $stmtVoucher->execute([$kode_voucher]);

        if ($stmtVoucher->rowCount() === 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Voucher tidak valid atau sudah habis kuota'
            ]);
            exit;
        }

        $voucher = $stmtVoucher->fetch(PDO::FETCH_ASSOC);
        $diskon = $voucher['nilai_diskon'];

        // Ambil subtotal
        $querySubtotal = "
            SELECT SUM(k.jumlah * p.harga) AS subtotal
            FROM keranjang k
            JOIN produk p ON k.id_produk = p.id
            WHERE k.id_toko = ?";
        $stmtSubtotal = $pdo->prepare($querySubtotal);
        $stmtSubtotal->execute([$id_toko]);
        $subtotal = $stmtSubtotal->fetchColumn();

        // Hitung total setelah diskon
        $total = max(0, $subtotal - $diskon);

        echo json_encode([
            'success' => true,
            'subtotal' => $subtotal,
            'diskon' => $diskon,
            'total' => $total
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
