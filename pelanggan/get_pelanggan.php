<?php
// code middleware
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

// show all pelanggan
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT id, id_toko, nomor_telepon, alamat FROM pelanggan";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
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
