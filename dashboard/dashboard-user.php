<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); 
include('../config/helpers.php');  

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'https://posifyapi.muhsalfazi.my.id';

// ✅ Validasi token JWT
$authResult = validateToken(); 
if (!is_array($authResult) || !isset($authResult['user_id'], $authResult['id_toko'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$user_id = $authResult['user_id'];
$id_toko = $authResult['id_toko'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // ✅ 1. Ambil jumlah total jenis produk yang terjual
        $queryTotalProdukTerjual = "
            SELECT COUNT(DISTINCT pk.id_produk) AS total_produk_terjual
            FROM produkkeranjang pk
            JOIN produk p ON pk.id_produk = p.id
            WHERE p.id_toko = ? AND pk.produk_terjual > 0";
        $stmtTotalProdukTerjual = $pdo->prepare($queryTotalProdukTerjual);
        $stmtTotalProdukTerjual->execute([$id_toko]);
        $totalProdukTerjual = $stmtTotalProdukTerjual->fetchColumn();

        // ✅ 2. Ambil detail produk yang terjual
        $queryDetailProdukTerjual = "
            SELECT 
                p.id AS id_produk, 
                p.nama_produk, 
                COALESCE(SUM(pk.produk_terjual), 0) AS total_terjual
            FROM produk p
            JOIN produkkeranjang pk ON p.id = pk.id_produk
            WHERE p.id_toko = ?
            GROUP BY p.id, p.nama_produk
            HAVING total_terjual > 0
            ORDER BY total_terjual DESC";
        $stmtDetailProdukTerjual = $pdo->prepare($queryDetailProdukTerjual);
        $stmtDetailProdukTerjual->execute([$id_toko]);
        $produkTerjual = $stmtDetailProdukTerjual->fetchAll(PDO::FETCH_ASSOC);

        // ✅ 3. Ambil produk terlaris berdasarkan checkout (top 3)
        $queryProdukTerlaris = "
            SELECT 
                p.id, 
                p.nama_produk, 
                p.harga_jual, 
                p.gambar,
                COALESCE(SUM(pk.produk_terjual), 0) AS total_terjual
            FROM produk p
            JOIN produkkeranjang pk ON p.id = pk.id_produk
            JOIN checkout c ON pk.id_keranjang = c.id_keranjang
            WHERE p.id_toko = ?
            GROUP BY p.id, p.nama_produk, p.harga_jual, p.gambar
            HAVING total_terjual > 0
            ORDER BY total_terjual DESC
            LIMIT 3";
        $stmtProdukTerlaris = $pdo->prepare($queryProdukTerlaris);
        $stmtProdukTerlaris->execute([$id_toko]);
        $produkTerlaris = $stmtProdukTerlaris->fetchAll(PDO::FETCH_ASSOC);

        // ✅ 4. Ambil total pendapatan (SUM dari `omset_penjualan` di `laporankeuangan`)
        $queryPendapatan = "
            SELECT COALESCE(SUM(omset_penjualan), 0) AS total_pendapatan
            FROM laporankeuangan
            WHERE id_toko = ?";
        $stmtPendapatan = $pdo->prepare($queryPendapatan);
        $stmtPendapatan->execute([$id_toko]);
        $totalPendapatan = $stmtPendapatan->fetchColumn();

        // ✅ Tambahkan URL gambar produk terlaris
        foreach ($produkTerlaris as &$item) {
            $item['gambar_url'] = !empty($item['gambar']) ? $baseURL . '/' . $item['gambar'] : null;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'produk_terjual' => $totalProdukTerjual,
                'detail_produk_terjual' => $produkTerjual,
                'produk_terlaris' => $produkTerlaris,
                'pendapatan' => $totalPendapatan
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
