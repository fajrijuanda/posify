<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

$userData = validateToken();

if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$id_toko = $userData['id_toko']; // Ambil id_toko dari token JWT

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id_transaksi = $_GET['id_transaksi'] ?? null;

    if (empty($id_transaksi)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Transaksi diperlukan'
        ]);
        exit;
    }

    try {
        // Ambil detail transaksi
        $queryTransaksi = "
            SELECT 
                t.nomor_order,
                t.waktu_transaksi,
                c.id AS id_checkout,
                c.subtotal,
                c.total_harga,
                c.metode_pengiriman
            FROM transaksi t
            JOIN pembayaran p ON t.id_pembayaran = p.id
            JOIN checkout c ON p.id_checkout = c.id
            WHERE t.id = ?";
        $stmtTransaksi = $pdo->prepare($queryTransaksi);
        $stmtTransaksi->execute([$id_transaksi]);
        $transaksi = $stmtTransaksi->fetch(PDO::FETCH_ASSOC);

        if (!$transaksi) {
            echo json_encode([
                'success' => false,
                'error' => 'Transaksi tidak ditemukan'
            ]);
            exit;
        }

        // Ambil detail produk dari keranjang
        $queryProduk = "
            SELECT 
                p.nama_produk,
                pk.kuantitas,
                p.harga AS harga_produk,
                (pk.kuantitas * p.harga) AS total_harga_produk,
                p.gambar
            FROM produkkeranjang pk
            JOIN produk p ON pk.id_produk = p.id
            WHERE pk.id_keranjang = ?";
        $stmtProduk = $pdo->prepare($queryProduk);
        $stmtProduk->execute([$transaksi['id_checkout']]);
        $produk = $stmtProduk->fetchAll(PDO::FETCH_ASSOC);

        // Ambil informasi laporan keuangan
        $queryLaporan = "
            SELECT 
                l.total_bersih,
                l.biaya_komisi,
                (l.biaya_komisi / 100) * c.total_harga AS komisi_aplikasi,
                c.total_harga AS omset_penjualan
            FROM laporan_keuangan l
            JOIN checkout c ON l.id_toko = c.id_keranjang
            WHERE c.id = ?";
        $stmtLaporan = $pdo->prepare($queryLaporan);
        $stmtLaporan->execute([$transaksi['id_checkout']]);
        $laporan = $stmtLaporan->fetch(PDO::FETCH_ASSOC);

        // Hasilkan respon
        echo json_encode([
            'success' => true,
            'data' => [
                'transaksi' => $transaksi,
                'produk' => $produk,
                'laporan' => $laporan
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
