<?php
header('Content-Type: application/json');
require_once '../config/dbconnection.php';
include('../config/cors.php');
require_once __DIR__ . '/../middlewares/auth_middleware.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$authResult = validateToken($authHeader);
if (isset($authResult['error'])) {
    http_response_code(401);
    echo json_encode($authResult);
    exit;
}
$user_id = $authResult;  // Ambil user_id dari hasil validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_toko = $_POST['id_toko'] ?? null;
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? null; // Misal: 'tunai', 'kartu'
    $simpan_pesanan = $_POST['simpan_pesanan'] ?? null; // Boolean: true/false
    $refund_kasir = $_POST['refund_kasir'] ?? null; // Boolean: true/false
    $tampilkan_logo = $_POST['tampilkan_logo'] ?? null; // Boolean: true/false
    $tampilkan_alamat = $_POST['tampilkan_alamat'] ?? null; // Boolean: true/false

    if (empty($id_toko)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Toko diperlukan'
        ]);
        exit;
    }

    try {
        // Simpan konfigurasi toko
        $query = "
            UPDATE toko 
            SET metode_pembayaran = ?, simpan_pesanan = ?, refund_kasir = ?, tampilkan_logo = ?, tampilkan_alamat = ?
            WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $metode_pembayaran,
            $simpan_pesanan,
            $refund_kasir,
            $tampilkan_logo,
            $tampilkan_alamat,
            $id_toko
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Pengaturan toko berhasil diperbarui'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id_toko = $_GET['id_toko'] ?? null;

    if (empty($id_toko)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Toko diperlukan'
        ]);
        exit;
    }

    try {
        // Ambil konfigurasi toko
        $query = "SELECT metode_pembayaran, simpan_pesanan, refund_kasir, tampilkan_logo, tampilkan_alamat FROM toko WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $config
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
