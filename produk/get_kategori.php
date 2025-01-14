<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
$sql = "SELECT id_kategori, nama_kategori FROM kategori";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $kategori = [
        ["id_kategori" => 1, "nama_kategori" => "makanan"],
        ["id_kategori" => 2, "nama_kategori" => "minuman"]
    ];
    echo json_encode(["success" => true, "kategori" => $kategori]);
} else {
    echo json_encode(["failed" => false, "message" => "Tidak ada kategori tersedia!"]);
}

$conn->close();
?>

