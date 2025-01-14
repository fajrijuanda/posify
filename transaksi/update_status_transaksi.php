<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pembayaran = $_POST['id_pembayaran'] ?? null;
    $status_midtrans = $_POST['status_midtrans'] ?? null;
    $nomor_order = $_POST['nomor_order'] ?? null;

    if (empty($id_pembayaran) || empty($status_midtrans) || empty($nomor_order)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Pembayaran, Status Midtrans, dan Nomor Order diperlukan'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update status pembayaran
        $queryUpdatePembayaran = "
            UPDATE pembayaran 
            SET status = ? 
            WHERE id = ?";
        $stmtUpdatePembayaran = $pdo->prepare($queryUpdatePembayaran);
        $stmtUpdatePembayaran->execute([$status_midtrans, $id_pembayaran]);

        // Update status transaksi
        $queryUpdateTransaksi = "
            UPDATE transaksi 
            SET status = ? 
            WHERE nomor_order = ?";
        $stmtUpdateTransaksi = $pdo->prepare($queryUpdateTransaksi);
        $stmtUpdateTransaksi->execute(['Terkonfirmasi', $nomor_order]);

        // Ambil detail laporan keuangan
        $queryLaporan = "
            SELECT 
                t.id AS id_transaksi, 
                t.id_pembayaran, 
                c.total_harga, 
                p.id_checkout, 
                l.id AS id_laporan, 
                t.nomor_order
            FROM transaksi t
            JOIN pembayaran p ON t.id_pembayaran = p.id
            JOIN checkout c ON p.id_checkout = c.id
            JOIN laporan_keuangan l ON l.id_toko = c.id_keranjang
            WHERE t.nomor_order = ?";
        $stmtLaporan = $pdo->prepare($queryLaporan);
        $stmtLaporan->execute([$nomor_order]);
        $dataLaporan = $stmtLaporan->fetch(PDO::FETCH_ASSOC);

        if (!$dataLaporan) {
            throw new Exception('Data transaksi tidak ditemukan untuk laporan keuangan');
        }

        // Update laporan keuangan
        $total_harga = $dataLaporan['total_harga'];
        $komisi_aplikasi = 0.025 * $total_harga; // Komisi 2.5%
        $total_bersih = $total_harga - $komisi_aplikasi;

        $queryUpdateLaporan = "
            UPDATE laporan_keuangan 
            SET total_diskon = total_diskon + 0, 
                biaya_komisi = biaya_komisi + ?, 
                total_bersih = total_bersih + ? 
            WHERE id = ?";
        $stmtUpdateLaporan = $pdo->prepare($queryUpdateLaporan);
        $stmtUpdateLaporan->execute([$komisi_aplikasi, $total_bersih, $dataLaporan['id_laporan']]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Status transaksi berhasil diperbarui',
            'data' => [
                'id_transaksi' => $dataLaporan['id_transaksi'],
                'nomor_order' => $dataLaporan['nomor_order'],
                'total_harga' => $total_harga,
                'komisi_aplikasi' => $komisi_aplikasi,
                'total_bersih' => $total_bersih
            ]
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?>
