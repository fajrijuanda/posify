<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Load .env jika menggunakan PHP dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test/uploads'; // Ganti dengan direktori gambar yang benar

// Validasi token untuk otentikasi
$userData = validateToken(); // Dapatkan data user dari token

if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$user_id = $userData['user_id'];
$id_toko = $userData['id_toko'];

// Debugging untuk memastikan ID Toko ada
error_log("User ID: " . $user_id);
error_log("ID Toko: " . $id_toko);

if (!$id_toko) {
    echo json_encode([
        'success' => false,
        'error' => 'Toko tidak ditemukan untuk user ini'
    ]);
    exit;
}

// METHOD GET: Mengambil daftar bundling dalam keranjang berdasarkan id_toko
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
                'total_produk' => 0,
                'bundling_keranjang' => [],
                'message' => 'Keranjang kosong'
            ]);
            exit;
        }

        $id_keranjang = $resultKeranjang['id'];
        $total_produk = $resultKeranjang['total_produk'];

        // Ambil daftar bundling dalam keranjang
        $queryBundling = "
            SELECT pk.id_bundling, b.nama_bundling, pk.kuantitas, pk.harga_produk, MAX(p.harga_jual) AS harga_jual, p.gambar
            FROM produkkeranjang pk
            JOIN bundling b ON pk.id_bundling = b.id
            JOIN bundling_produk bp ON b.id = bp.id_bundling
            JOIN produk p ON bp.id_produk = p.id
            WHERE pk.id_keranjang = ?
            GROUP BY pk.id_bundling, b.nama_bundling, pk.kuantitas, pk.harga_produk, p.gambar
        ";
        $stmtBundling = $pdo->prepare($queryBundling);
        $stmtBundling->execute([$id_keranjang]);
        $bundling_keranjang = $stmtBundling->fetchAll(PDO::FETCH_ASSOC);

        // Tambahkan `gambar_url` untuk setiap bundling
        foreach ($bundling_keranjang as &$bundling) {
            $bundling['gambar_url'] = !empty($bundling['gambar']) ? $baseURL . '/' . $bundling['gambar'] : null;

            // Ambil daftar produk dalam setiap bundling
            $queryProdukBundling = "
                SELECT bp.id_produk, p.nama_produk, bp.jumlah, p.harga_jual, p.harga_modal, p.gambar 
                FROM bundling_produk bp
                JOIN produk p ON bp.id_produk = p.id
                WHERE bp.id_bundling = ?
            ";
            $stmtProdukBundling = $pdo->prepare($queryProdukBundling);
            $stmtProdukBundling->execute([$bundling['id_bundling']]);
            $produkBundling = $stmtProdukBundling->fetchAll(PDO::FETCH_ASSOC);

            // Tambahkan `gambar_url` untuk setiap produk di dalam bundling
            foreach ($produkBundling as &$produk) {
                $produk['gambar_url'] = !empty($produk['gambar']) ? $baseURL . '/' . $produk['gambar'] : null;
            }

            $bundling['produk_bundling'] = $produkBundling;
        }

        echo json_encode([
            'success' => true,
            'total_produk' => $total_produk,
            'bundling_keranjang' => $bundling_keranjang
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
