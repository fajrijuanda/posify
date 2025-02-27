<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

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

// SHOW ALL PELANGGAN BERDASARKAN ID TOKO
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Query yang benar dengan filter berdasarkan id_toko
        $query = "SELECT id, id_toko, nomor_telepon, nama_pelanggan FROM pelanggan WHERE id_toko = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko]); // Menggunakan id_toko dari token
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'total_pelanggan' => count($result),
            'data' => $result
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
