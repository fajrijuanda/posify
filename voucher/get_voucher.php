<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
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
        // Ambil semua voucher milik toko
        $query = "SELECT id, nama, nilai_diskon, minimal_belanja, deskripsi FROM voucher WHERE id_toko = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko]);
        $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
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
