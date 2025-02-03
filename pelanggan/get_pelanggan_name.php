<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$userData = validateToken();

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

// SHOW ALL PELANGGAN BERDASARKAN ID TOKO & FILTER NAMA DENGAN JSON
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

    // Ambil kata kunci pencarian
    $search = $input['nama_pelanggan'] ?? '';

    try {
        // Query dengan pencarian fleksibel
        $query = "SELECT id, id_toko, nomor_telepon, nama_pelanggan 
                  FROM pelanggan 
                  WHERE id_toko = ? AND nama_pelanggan LIKE ?";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko, "%$search%"]); // Mencari nama yang mengandung kata kunci

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