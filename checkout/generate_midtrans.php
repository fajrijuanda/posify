<?php
header('Content-Type: application/json');
require_once '../config/dbconnection.php';
require_once '../config/midtrans_config.php'; // File konfigurasi Midtrans
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
use Midtrans\Snap;
use Midtrans\Config;

$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_checkout = $_POST['id_checkout'] ?? null;

    if (empty($id_checkout)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Checkout diperlukan'
        ]);
        exit;
    }

    try {
        // Ambil data checkout
        $query = "SELECT total_harga FROM checkout WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_checkout]);
        $checkout = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$checkout) {
            echo json_encode([
                'success' => false,
                'error' => 'Data checkout tidak ditemukan'
            ]);
            exit;
        }

        $total_harga = $checkout['total_harga'];

        // Konfigurasi Midtrans
        Config::$serverKey = 'YOUR_SERVER_KEY';
        Config::$isProduction = false; // Ubah ke true jika di production
        Config::$isSanitized = true;
        Config::$is3ds = true;

        // Data transaksi
        $transaction_details = [
            'order_id' => uniqid('ORDER-'),
            'gross_amount' => $total_harga
        ];

        $item_details = [
            [
                'id' => $id_checkout,
                'price' => $total_harga,
                'quantity' => 1,
                'name' => "Pembayaran Order #$id_checkout"
            ]
        ];

        $customer_details = [
            'first_name' => 'Nama Pelanggan',
            'email' => 'email@pelanggan.com',
            'phone' => '08123456789'
        ];

        $params = [
            'transaction_details' => $transaction_details,
            'item_details' => $item_details,
            'customer_details' => $customer_details
        ];

        // Dapatkan Snap Token
        $snapToken = Snap::getSnapToken($params);

        // Simpan data pembayaran ke tabel pembayaran
        $queryPayment = "
            INSERT INTO pembayaran (id_checkout, metode, detail, status, waktu_pembayaran)
            VALUES (?, 'nontunai', ?, 'pending', NOW())";
        $stmtPayment = $pdo->prepare($queryPayment);
        $stmtPayment->execute([$id_checkout, json_encode($transaction_details)]);

        echo json_encode([
            'success' => true,
            'snap_token' => $snapToken
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?>
