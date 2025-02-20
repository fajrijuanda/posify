<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Load .env jika menggunakan PHP dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

// Validasi token untuk otentikasi
$userData = validateToken();

if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$user_id = $userData['user_id'];
$id_toko = $userData['id_toko'];

if (!$id_toko) {
    echo json_encode([
        'success' => false,
        'error' => 'Toko tidak ditemukan untuk user ini'
    ]);
    exit;
}

// METHOD GET: Mengambil daftar produk dalam keranjang berdasarkan id_toko
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Ambil `id_keranjang` berdasarkan `id_toko`
        $queryKeranjang = "SELECT id, total_produk FROM keranjang WHERE id_toko = ?";
        $stmtKeranjang = $pdo->prepare($queryKeranjang);
        $stmtKeranjang->execute([$id_toko]);
        $resultKeranjang = $stmtKeranjang->fetch(PDO::FETCH_ASSOC);

        if (!$resultKeranjang) {
            echo json_encode([
                'success' => true,
                'id_keranjang' => null,
                'total_produk' => 0,
                'produk_keranjang' => [],
                'bundling_keranjang' => [],
                'message' => 'Keranjang kosong'
            ]);
            exit;
        }

        $id_keranjang = $resultKeranjang['id'];
        $total_produk = intval($resultKeranjang['total_produk']);

        // Ambil produk biasa dalam keranjang (tanpa bundling)
        $queryProduk = "
            SELECT pk.id_produk, p.nama_produk, CAST(pk.kuantitas AS UNSIGNED) AS kuantitas, 
                   pk.harga_produk, p.gambar
            FROM produkkeranjang pk
            JOIN produk p ON pk.id_produk = p.id
            WHERE pk.id_keranjang = ? AND pk.id_bundling IS NULL
        ";
        $stmtProduk = $pdo->prepare($queryProduk);
        $stmtProduk->execute([$id_keranjang]);
        $produk_keranjang = $stmtProduk->fetchAll(PDO::FETCH_ASSOC);

        foreach ($produk_keranjang as &$produk) {
            $produk['kuantitas'] = intval($produk['kuantitas']);
            $produk['harga_produk'] = floatval($produk['harga_produk']);
            $produk['gambar_url'] = !empty($produk['gambar']) ? rtrim($baseURL, '/') . '/' . ltrim($produk['gambar'], '/') : null;
        }

        // Ambil produk yang termasuk dalam bundling
        $queryProdukBundling = "
            SELECT pk.id_bundling, pk.id_produk, p.nama_produk, pk.kuantitas, pk.harga_produk, p.gambar
            FROM produkkeranjang pk
            JOIN produk p ON pk.id_produk = p.id
            WHERE pk.id_keranjang = ? AND pk.id_bundling IS NOT NULL
        ";
        $stmtProdukBundling = $pdo->prepare($queryProdukBundling);
        $stmtProdukBundling->execute([$id_keranjang]);
        $produk_bundling = $stmtProdukBundling->fetchAll(PDO::FETCH_ASSOC);

        foreach ($produk_bundling as &$produk) {
            $produk['kuantitas'] = intval($produk['kuantitas']);
            $produk['harga_produk'] = floatval($produk['harga_produk']);
            $produk['gambar_url'] = !empty($produk['gambar']) ? rtrim($baseURL, '/') . '/' . ltrim($produk['gambar'], '/') : null;
        }

        // Ambil bundling hanya satu per id_bundling dengan harga jual tertinggi
        $queryBundling = "
            SELECT pk.id_bundling, b.nama_bundling, MAX(p.harga_jual) AS harga_jual_tertinggi, 
                   (SELECT p.nama_produk FROM produk p 
                    JOIN bundling_produk bp ON p.id = bp.id_produk 
                    WHERE bp.id_bundling = b.id 
                    ORDER BY p.harga_jual DESC LIMIT 1) AS nama_produk_tertinggi,
                   (SELECT p.gambar FROM produk p 
                    JOIN bundling_produk bp ON p.id = bp.id_produk 
                    WHERE bp.id_bundling = b.id 
                    ORDER BY p.harga_jual DESC LIMIT 1) AS gambar_tertinggi
            FROM produkkeranjang pk
            JOIN bundling b ON pk.id_bundling = b.id
            JOIN bundling_produk bp ON b.id = bp.id_bundling
            JOIN produk p ON bp.id_produk = p.id
            WHERE pk.id_keranjang = ?
            GROUP BY pk.id_bundling, b.nama_bundling
        ";
        $stmtBundling = $pdo->prepare($queryBundling);
        $stmtBundling->execute([$id_keranjang]);
        $bundlingData = $stmtBundling->fetchAll(PDO::FETCH_ASSOC);

        foreach ($bundlingData as &$bundling) {
            $bundling['harga_jual_tertinggi'] = floatval($bundling['harga_jual_tertinggi']);
            $bundling['gambar_url'] = !empty($bundling['gambar_tertinggi']) ? rtrim($baseURL, '/') . '/' . ltrim($bundling['gambar_tertinggi'], '/') : null;
        }

        echo json_encode([
            'success' => true,
            'id_keranjang' => $id_keranjang,
            'total_produk' => $total_produk,
            'produk_keranjang' => $produk_keranjang,
            // 'produk_dalam_bundling' => $produk_bundling,
            'bundling_keranjang' => $bundlingData
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
