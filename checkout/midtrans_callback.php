<?php
header('Content-Type: application/json');
require_once '../config/dbconnection.php';
require_once '../config/midtrans_config.php';
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
$raw_body = file_get_contents("php://input");
$body = json_decode($raw_body, true);

$order_id = $body['order_id'] ?? null;
$transaction_status = $body['transaction_status'] ?? null;
$payment_type = $body['payment_type'] ?? null;
$gross_amount = $body['gross_amount'] ?? null;

if (empty($order_id) || empty($transaction_status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid callback data']);
    exit;
}

try {
    // Update status pembayaran di tabel pembayaran
    $query = "UPDATE pembayaran SET status = ?, metode = ?, waktu_pembayaran = NOW() WHERE id_checkout = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$transaction_status, $payment_type, $order_id]);

    // Jika pembayaran berhasil (status settlement)
    if ($transaction_status === 'settlement') {
        // Simpan data ke tabel transaksi
        $queryTransaction = "
            INSERT INTO transaksi (id_pembayaran, nomor_order, metode_pembayaran, waktu_transaksi, status)
            VALUES ((SELECT id FROM pembayaran WHERE id_checkout = ?), ?, ?, NOW(), 'Selesai')";
        $stmtTransaction = $pdo->prepare($queryTransaction);
        $stmtTransaction->execute([$order_id, $order_id, $payment_type]);

        $id_transaksi = $pdo->lastInsertId();

        // Ambil data checkout dan toko terkait
        $queryCheckout = "
            SELECT c.id_toko, c.total_harga, c.subtotal, (c.subtotal - c.total_harga) AS total_diskon
            FROM checkout c
            WHERE c.id = ?";
        $stmtCheckout = $pdo->prepare($queryCheckout);
        $stmtCheckout->execute([$order_id]);
        $checkout = $stmtCheckout->fetch(PDO::FETCH_ASSOC);

        $id_toko = $checkout['id_toko'];
        $total_harga = $checkout['total_harga'];
        $subtotal = $checkout['subtotal'];
        $total_diskon = $checkout['total_diskon'];
        $biaya_komisi = 0.025 * $total_harga; // Komisi 2.5%

        // Update laporan keuangan
        $queryLaporan = "
            INSERT INTO laporan_keuangan (id_toko, biaya_komisi, total_diskon, total_bersih, omset_penjualan)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                biaya_komisi = biaya_komisi + VALUES(biaya_komisi),
                total_diskon = total_diskon + VALUES(total_diskon),
                total_bersih = total_bersih + VALUES(total_bersih),
                omset_penjualan = omset_penjualan + VALUES(omset_penjualan)";
        $stmtLaporan = $pdo->prepare($queryLaporan);
        $stmtLaporan->execute([$id_toko, $biaya_komisi, $total_diskon, $total_harga - $biaya_komisi, $subtotal]);

        // Simpan data ke tabel TransaksiLaporan
        $queryTransaksiLaporan = "
            INSERT INTO transaksilaporan (id_transaksi, id_laporan)
            VALUES (?, (SELECT id FROM laporan_keuangan WHERE id_toko = ?))";
        $stmtTransaksiLaporan = $pdo->prepare($queryTransaksiLaporan);
        $stmtTransaksiLaporan->execute([$id_transaksi, $id_toko]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
