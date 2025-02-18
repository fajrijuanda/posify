<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php');

$userData = validateToken();
if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid']);
    exit;
}

$id_toko = $userData['id_toko']; // Ambil ID toko dari token JWT

// Load .env jika menggunakan PHP dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

try {
    // Ambil data bundling
    $query = "SELECT * FROM bundling WHERE id_toko = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id_toko]);
    $bundlingData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Jika tidak ada data bundling
    if (!$bundlingData) {
        echo json_encode(['success' => true, 'data' => [], 'message' => 'Tidak ada bundling']);
        exit;
    }

    // Ambil produk dari setiap bundling, termasuk gambar
    foreach ($bundlingData as &$bundling) {
        $id_bundling = $bundling['id'];

        $query = "SELECT bp.id_produk, p.nama_produk, bp.jumlah, p.harga_jual, p.harga_modal, p.gambar 
                  FROM bundling_produk bp
                  JOIN produk p ON bp.id_produk = p.id
                  WHERE bp.id_bundling = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_bundling]);
        $produkData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tambahkan URL gambar ke setiap produk
        foreach ($produkData as &$produk) {
            if (!empty($produk['gambar'])) {
                $produk['gambar_url'] = $baseURL . $produk['gambar'];
            } else {
                $produk['gambar_url'] = null; // Atau gunakan gambar default
            }
        }

        $bundling['produk_bundling'] = $produkData;
    }

    echo json_encode([
        'success' => true,
        'data' => $bundlingData
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
