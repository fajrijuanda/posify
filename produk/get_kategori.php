<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

$authResult = validateToken($authHeader);
if (isset($authResult['error'])) {
    http_response_code(401);
    echo json_encode($authResult);
    exit;
}
$user_id = $authResult; // Ambil user_id dari hasil validasi token

$sql = "SELECT id_kategori, nama_kategori FROM kategori";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $kategori = [];
    while ($row = $result->fetch_assoc()) {
        $kategori[] = $row;
    }
    echo json_encode(["success" => true, "kategori" => $kategori]);
} else {
    echo json_encode(["failed" => false, "message" => "Tidak ada kategori tersedia!"]);
}

$conn->close();
