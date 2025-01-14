<?php
header('Content-Type: application/json');
include("../config/dbconnection.php"); // Sesuaikan path jika diperlukan
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id_toko = $_GET['id_toko'] ?? null;

    if (empty($id_toko)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Toko diperlukan'
        ]);
        exit;
    }

    try {
        // 1. Ambil informasi toko
        $queryToko = "SELECT nama_toko, nomor_telepon, email, logo FROM toko WHERE id = ?";
        $stmtToko = $pdo->prepare($queryToko);
        $stmtToko->execute([$id_toko]);
        $toko = $stmtToko->fetch(PDO::FETCH_ASSOC);

        if (!$toko) {
            echo json_encode([
                'success' => false,
                'error' => 'Toko tidak ditemukan'
            ]);
            exit;
        }

        // 2. Ambil total produk terjual dan pendapatan
        $queryStatistik = "
            SELECT COUNT(p.id) AS produk_terjual, SUM(p.harga_jual * pk.kuantitas) AS pendapatan
            FROM produk p
            JOIN produkkeranjang pk ON p.id = pk.id_produk
            JOIN checkout c ON pk.id_keranjang = c.id_keranjang
            WHERE p.id_toko = ? AND c.status = 'selesai'";
        $stmtStatistik = $pdo->prepare($queryStatistik);
        $stmtStatistik->execute([$id_toko]);
        $statistik = $stmtStatistik->fetch(PDO::FETCH_ASSOC);

        // 3. Ambil produk terlaris (limit 3)
        $queryProdukTerlaris = "
            SELECT p.nama_produk, p.gambar, SUM(pk.kuantitas) AS total_terjual
            FROM produk p
            JOIN produkkeranjang pk ON p.id = pk.id_produk
            JOIN checkout c ON pk.id_keranjang = c.id_keranjang
            WHERE p.id_toko = ? AND c.status = 'selesai'
            GROUP BY p.id
            ORDER BY total_terjual DESC
            LIMIT 3";
        $stmtProdukTerlaris = $pdo->prepare($queryProdukTerlaris);
        $stmtProdukTerlaris->execute([$id_toko]);
        $produkTerlaris = $stmtProdukTerlaris->fetchAll(PDO::FETCH_ASSOC);

        // 4. Ambil paket bundling (limit 2)
        $queryBundling = "
            SELECT pb.nama_bundling, pb.harga, pb.stok, pb.gambar
            FROM paket_bundling pb
            WHERE pb.id_toko = ?
            LIMIT 2";
        $stmtBundling = $pdo->prepare($queryBundling);
        $stmtBundling->execute([$id_toko]);
        $bundling = $stmtBundling->fetchAll(PDO::FETCH_ASSOC);

        // Gabungkan semua data
        $response = [
            'success' => true,
            'toko' => $toko,
            'statistik' => [
                'produk_terjual' => $statistik['produk_terjual'] ?? 0,
                'pendapatan' => $statistik['pendapatan'] ?? 0
            ],
            'produk_terlaris' => $produkTerlaris,
            'bundling' => $bundling
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
