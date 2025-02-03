<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token JWT
$userData = validateToken();

if (!$userData) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$user_id = $userData['user_id'];
$id_toko = $userData['id_toko'];

// Debugging: Pastikan ID Toko ada
error_log("User ID: " . ($user_id ?? 'NULL'));
error_log("ID Toko: " . ($id_toko ?? 'NULL'));

if (!$id_toko) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Toko tidak ditemukan untuk user ini'
    ]);
    exit;
}

// SHOW ALL VOUCHER BERDASARKAN ID TOKO & FILTER NAMA DENGAN JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Ambil input JSON
        $inputJSON = file_get_contents("php://input");
        $input = json_decode($inputJSON, true);

        if (!is_array($input)) {
            error_log("Invalid JSON format: " . $inputJSON);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON format'
            ]);
            exit;
        }

        // Ambil kata kunci pencarian
        $search = isset($input['nama']) ? "%" . $input['nama'] . "%" : "%";

        // Debugging input pencarian
        error_log("Search Parameter: " . $search);

        // Query dengan pencarian fleksibel
        $query = "SELECT id, id_toko, nama, nilai_diskon, minimal_belanja 
                  FROM voucher 
                  WHERE id_toko = ? AND nama LIKE ?";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko, $search]); // Menggunakan array yang sesuai dengan jumlah placeholder

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'total_voucher' => count($result),
            'data' => $result
        ]);
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}

?>
