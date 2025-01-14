<?php
header('Content-Type: application/json');
include('../config/dbconnection.php'); // Pastikan path sesuai
include('../config/cors.php'); // Include konfigurasi CORS
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents('php://input'), $data); // Mendapatkan data dari body request
    $id_produk = $data['id_produk'] ?? null;

    if (empty($id_produk)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Produk diperlukan'
        ]);
        exit;
    }

    try {
        // Hapus produk berdasarkan ID
        $query = "DELETE FROM produk WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_produk]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Produk berhasil dihapus'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Produk tidak ditemukan atau gagal dihapus'
            ]);
        }
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
