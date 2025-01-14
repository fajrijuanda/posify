<?php
header('Content-Type: application/json');
require_once '../config/dbconnection.php';
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
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
        // Ambil data transaksi utama
        $queryTransaksi = "
            SELECT 
                t.id AS id_transaksi,
                t.nomor_order,
                t.metode_pembayaran,
                t.waktu_transaksi,
                t.status,
                c.metode_pengiriman,
                c.total_harga,
                p.nama AS nama_pelanggan,
                p.nomor_telepon,
                p.email
            FROM transaksi t
            JOIN pembayaran pm ON t.id_pembayaran = pm.id
            JOIN checkout c ON pm.id_checkout = c.id
            LEFT JOIN pelanggan p ON c.id_keranjang = p.id
            WHERE t.id = ?";
        $stmtTransaksi = $pdo->prepare($queryTransaksi);
        $stmtTransaksi->execute([$id_transaksi]);
        $transaksi = $stmtTransaksi->fetch(PDO::FETCH_ASSOC);

        if (!$transaksi) {
            echo json_encode([
                'success' => false,
                'error' => 'Data transaksi tidak ditemukan'
            ]);
            exit;
        }

        // Ambil detail produk terkait transaksi
        $queryProduk = "
            SELECT 
                pr.id AS id_produk,
                pr.nama_produk,
                pr.harga_jual,
                pr.gambar,
                pk.kuantitas
            FROM produkkeranjang pk
            JOIN produk pr ON pk.id_produk = pr.id
            WHERE pk.id_keranjang = (
                SELECT id_keranjang FROM checkout WHERE id = ?
            )";
        $stmtProduk = $pdo->prepare($queryProduk);
        $stmtProduk->execute([$transaksi['id_transaksi']]);
        $produk = $stmtProduk->fetchAll(PDO::FETCH_ASSOC);

        // Struktur respon
        $response = [
            'success' => true,
            'data' => [
                'transaksi' => [
                    'id_transaksi' => $transaksi['id_transaksi'],
                    'nomor_order' => $transaksi['nomor_order'],
                    'metode_pembayaran' => $transaksi['metode_pembayaran'],
                    'waktu_transaksi' => $transaksi['waktu_transaksi'],
                    'status' => $transaksi['status'],
                    'metode_pengiriman' => $transaksi['metode_pengiriman'],
                    'total_harga' => $transaksi['total_harga']
                ],
                'pelanggan' => [
                    'nama' => $transaksi['nama_pelanggan'],
                    'nomor_telepon' => $transaksi['nomor_telepon'],
                    'email' => $transaksi['email']
                ],
                'produk' => $produk
            ]
        ];

        echo json_encode($response);
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
