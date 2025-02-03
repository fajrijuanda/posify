<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token JWT
$userData = validateToken();

if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$user_id = $userData['user_id'];
$id_toko = $userData['id_toko'];

// Debugging: Pastikan ID Toko ada
error_log("User ID: " . $user_id);
error_log("ID Toko: " . $id_toko);

if (!$id_toko) {
    echo json_encode([
        'success' => false,
        'error' => 'Toko tidak ditemukan untuk user ini'
    ]);
    exit;
}

// METHOD GET: Ambil semua voucher berdasarkan id_toko dari token JWT
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Ambil semua voucher milik toko berdasarkan token JWT
        $query = "SELECT id, nama, nilai_diskon, minimal_belanja FROM voucher WHERE id_toko = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko]);
        $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($vouchers)) {
            echo json_encode([
                'success' => true,
                'message' => 'Tidak ada voucher tersedia untuk toko ini',
                'total_voucher' => 0,
                'data' => []
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'total_voucher' => count($vouchers),
            'data' => $vouchers
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
