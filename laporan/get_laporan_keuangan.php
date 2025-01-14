<?php
header('Content-Type: application/json');
require_once '../config/dbconnection.php';
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id_toko = $_GET['id_toko'] ?? null;
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;

    if (empty($id_toko) || empty($start_date) || empty($end_date)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Toko, Start Date, dan End Date diperlukan'
        ]);
        exit;
    }

    try {
        $query = "
            SELECT l.omset_penjualan, l.biaya_komisi, l.total_diskon, l.total_bersih, t.nomor_order, t.waktu_transaksi
            FROM laporan_keuangan l
            JOIN transaksilaporan tl ON l.id = tl.id_laporan
            JOIN transaksi t ON tl.id_transaksi = t.id
            WHERE l.id_toko = ? AND t.waktu_transaksi BETWEEN ? AND ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko, $start_date, $end_date]);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    } catch (PDOException $e) {
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
