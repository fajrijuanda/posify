<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_keranjang = $_POST['id_keranjang'] ?? null;
    $jumlah = $_POST['jumlah'] ?? null;

    if (empty($id_keranjang) || empty($jumlah)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Keranjang dan Jumlah diperlukan'
        ]);
        exit;
    }

    try {
        $query = "UPDATE keranjang SET jumlah = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$jumlah, $id_keranjang]);

        echo json_encode([
            'success' => true,
            'message' => 'Jumlah produk berhasil diperbarui'
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
