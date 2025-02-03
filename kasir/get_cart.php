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
                'total_produk' => 0,
                'produk_keranjang' => [],
                'message' => 'Keranjang kosong'
            ]);
            exit;
        }

        $id_keranjang = $resultKeranjang['id'];
        $total_produk = $resultKeranjang['total_produk'];

        // Ambil detail produk yang ada di dalam keranjang
        $queryProduk = "
            SELECT pk.id_produk, p.nama_produk, pk.kuantitas, pk.harga_produk, p.gambar
            FROM produkkeranjang pk
            JOIN produk p ON pk.id_produk = p.id
            WHERE pk.id_keranjang = ?
        ";
        $stmtProduk = $pdo->prepare($queryProduk);
        $stmtProduk->execute([$id_keranjang]);
        $produk_keranjang = $stmtProduk->fetchAll(PDO::FETCH_ASSOC);

        // Tambahkan `gambar_url` untuk setiap produk
        foreach ($produk_keranjang as &$produk) {
            $produk['gambar_url'] = !empty($produk['gambar']) ? $baseURL . '/' . $produk['gambar'] : null;
        }

        echo json_encode([
            'success' => true,
            'total_produk' => $total_produk,
            'produk_keranjang' => $produk_keranjang
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
