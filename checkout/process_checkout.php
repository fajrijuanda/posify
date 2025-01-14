<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_keranjang = $_POST['id_keranjang'] ?? null;
    $id_toko = $_POST['id_toko'] ?? null;
    $id_voucher = $_POST['id_voucher'] ?? null; // Opsional
    $metode_pengiriman = $_POST['metode_pengiriman'] ?? 'Bungkus'; // Default: Bungkus

    if (empty($id_keranjang) || empty($id_toko)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Keranjang dan ID Toko diperlukan'
        ]);
        exit;
    }

    try {
        // Periksa apakah voucher sudah digunakan
        if ($id_voucher) {
            $queryVoucherCheck = "
                SELECT id FROM checkout 
                WHERE id_voucher = ? AND status = 'checkout'";
            $stmtVoucherCheck = $pdo->prepare($queryVoucherCheck);
            $stmtVoucherCheck->execute([$id_voucher]);
            if ($stmtVoucherCheck->rowCount() > 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Voucher sudah digunakan di checkout sebelumnya'
                ]);
                exit;
            }
        }

        // Hitung subtotal
        $querySubtotal = "
            SELECT SUM(k.jumlah * p.harga) AS subtotal
            FROM keranjang k
            JOIN produk p ON k.id_produk = p.id
            WHERE k.id_toko = ?";
        $stmtSubtotal = $pdo->prepare($querySubtotal);
        $stmtSubtotal->execute([$id_toko]);
        $subtotal = $stmtSubtotal->fetchColumn();

        // Hitung total harga setelah voucher
        $diskon = 0;
        if (!empty($id_voucher)) {
            $queryVoucher = "SELECT nilai_diskon FROM voucher WHERE id = ? AND kuota > 0";
            $stmtVoucher = $pdo->prepare($queryVoucher);
            $stmtVoucher->execute([$id_voucher]);
            $voucher = $stmtVoucher->fetch(PDO::FETCH_ASSOC);

            if ($voucher) {
                $diskon = $voucher['nilai_diskon'];
            }
        }

        $total_harga = max(0, $subtotal - $diskon);

        // Periksa apakah data checkout sementara sudah ada
        $queryCheck = "
            SELECT id 
            FROM checkout 
            WHERE id_keranjang = ? AND status = 'sementara'";
        $stmtCheck = $pdo->prepare($queryCheck);
        $stmtCheck->execute([$id_keranjang]);
        $checkout = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($checkout) {
            // Perbarui data checkout menjadi status 'checkout'
            $queryUpdate = "
                UPDATE checkout 
                SET id_voucher = ?, metode_pengiriman = ?, subtotal = ?, total_harga = ?, status = 'checkout'
                WHERE id = ?";
            $stmtUpdate = $pdo->prepare($queryUpdate);
            $stmtUpdate->execute([$id_voucher, $metode_pengiriman, $subtotal, $total_harga, $checkout['id']]);

            $id_checkout = $checkout['id'];
        } else {
            // Simpan data checkout baru
            $queryInsert = "
                INSERT INTO checkout (id_keranjang, id_voucher, metode_pengiriman, subtotal, total_harga, status)
                VALUES (?, ?, ?, ?, ?, 'checkout')";
            $stmtInsert = $pdo->prepare($queryInsert);
            $stmtInsert->execute([$id_keranjang, $id_voucher, $metode_pengiriman, $subtotal, $total_harga]);

            $id_checkout = $pdo->lastInsertId();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Checkout berhasil',
            'data' => [
                'id_checkout' => $id_checkout,
                'subtotal' => $subtotal,
                'diskon' => $diskon,
                'total_harga' => $total_harga
            ]
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
