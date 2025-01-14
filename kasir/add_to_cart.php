<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include('../config/cors.php');
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_produk = $_POST['id_produk'] ?? null;
    $id_toko = $_POST['id_toko'] ?? null;
    $jumlah = $_POST['jumlah'] ?? 1;

    if (empty($id_produk) || empty($id_toko)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Produk dan ID Toko diperlukan'
        ]);
        exit;
    }

    try {
        // Cek apakah produk sudah ada di keranjang
        $queryCheck = "SELECT id FROM keranjang WHERE id_produk = ? AND id_toko = ?";
        $stmtCheck = $pdo->prepare($queryCheck);
        $stmtCheck->execute([$id_produk, $id_toko]);

        if ($stmtCheck->rowCount() > 0) {
            // Jika produk sudah ada, update jumlahnya
            $queryUpdate = "UPDATE keranjang SET jumlah = jumlah + ? WHERE id_produk = ? AND id_toko = ?";
            $stmtUpdate = $pdo->prepare($queryUpdate);
            $stmtUpdate->execute([$jumlah, $id_produk, $id_toko]);
        } else {
            // Jika produk belum ada, tambahkan ke keranjang
            $queryInsert = "INSERT INTO keranjang (id_produk, id_toko, jumlah) VALUES (?, ?, ?)";
            $stmtInsert = $pdo->prepare($queryInsert);
            $stmtInsert->execute([$id_produk, $id_toko, $jumlah]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan ke keranjang'
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
