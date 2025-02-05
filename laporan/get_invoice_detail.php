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
        // 🔹 Ambil detail transaksi dan id_checkout
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

        $id_checkout = $transaksi['id_checkout'];

        // 🔹 Ambil produk yang sesuai dengan `id_checkout`
        $queryProduk = "
            SELECT 
                p.nama_produk,
                p.harga_modal,
                p.harga_jual,
                p.deskripsi,
                p.gambar
            FROM produk p
            JOIN produkkeranjang pk ON p.id = pk.id_produk
            WHERE pk.id_keranjang = (SELECT id_keranjang FROM checkout WHERE id = ?)";
        $stmtProduk = $pdo->prepare($queryProduk);
        $stmtProduk->execute([$id_checkout]);
        $produk = $stmtProduk->fetchAll(PDO::FETCH_ASSOC);

        // 🔹 Ambil informasi laporan keuangan berdasarkan id_checkout
        $queryLaporan = "
            SELECT 
                l.omset_penjualan,
                l.biaya_komisi,
                l.total_bersih
            FROM laporankeuangan l
            JOIN checkout c ON l.id_toko = c.id_keranjang
            WHERE c.id = ?";
        $stmtLaporan = $pdo->prepare($queryLaporan);
        $stmtLaporan->execute([$id_checkout]);
        $laporan = $stmtLaporan->fetch(PDO::FETCH_ASSOC);

        // 🔹 Hasilkan respon JSON
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
