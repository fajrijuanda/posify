<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$userData = validateToken();
if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$user_id = $userData['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input JSON
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);

    if (!is_array($input)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON format'
        ]);
        exit;
    }

    // Ambil `id_pelanggan` dari input JSON
    $id_pelanggan = $input['id_pelanggan'] ?? null;

    if (empty($id_pelanggan)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Pelanggan diperlukan'
        ]);
        exit;
    }

    try {
        // Ambil data checkout yang sudah berstatus 'checkout' berdasarkan id_pelanggan
        $query = "
            SELECT 
                c.id AS id_checkout,
                c.id_keranjang,
                c.subtotal,
                c.total_harga,
                c.metode_pengiriman,
                c.status,
                v.nama AS nama_voucher,
                p.nama_pelanggan
            FROM checkout c
            LEFT JOIN voucher v ON c.id_voucher = v.id
            LEFT JOIN pelanggan p ON c.id_pelanggan = p.id
            WHERE c.id_pelanggan = ? AND c.status = 'checkout'
            ORDER BY c.id DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_pelanggan]);
        $checkouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($checkouts)) {
            echo json_encode([
                'success' => false,
                'error' => 'Tidak ada data checkout yang sudah diproses untuk pelanggan ini'
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'total_checkout' => count($checkouts),
            'data' => $checkouts
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
