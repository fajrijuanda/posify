<?php
header('Content-Type: application/json');
require_once '../config/dbconnection.php';
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pelanggan = $_POST['id_pelanggan'] ?? null;
    $nama_pelanggan = $_POST['nama_pelanggan'] ?? null;
    $no_telepon = $_POST['no_telepon'] ?? null;
    $email = $_POST['email'] ?? null;

    if (empty($id_pelanggan) || empty($nama_pelanggan) || empty($no_telepon)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID pelanggan, nama pelanggan, dan nomor telepon wajib diisi'
        ]);
        exit;
    }

    try {
        $query = "UPDATE pelanggan SET nama = ?, nomor_telepon = ?, email = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$nama_pelanggan, $no_telepon, $email, $id_pelanggan]);

        echo json_encode([
            'success' => true,
            'message' => 'Data pelanggan berhasil diperbarui'
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
