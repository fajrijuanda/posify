<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id_toko = $_GET['id_toko'] ?? null;

    // Validasi input id_toko
    if (empty($id_toko)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Toko diperlukan'
        ]);
        exit;
    }

    // Sanitasi ID Toko untuk mencegah SQL Injection
    $id_toko = sanitizeInput($id_toko);

    try {
        // Cek apakah toko tersebut milik user yang sedang login
        $queryCheckUser = "SELECT id_user FROM toko WHERE id = ?";
        $stmtCheckUser = $pdo->prepare($queryCheckUser);
        $stmtCheckUser->execute([$id_toko]);
        $toko = $stmtCheckUser->fetch(PDO::FETCH_ASSOC);

        if (!$toko || $toko['id_user'] !== $user_id) {
            // Jika toko tidak ditemukan atau tidak milik user
            echo json_encode([
                'success' => false,
                'error' => 'Toko tidak ditemukan atau tidak terasosiasi dengan pengguna saat ini'
            ]);
            exit;
        }

        // Ambil semua produk berdasarkan ID Toko
        $query = "
            SELECT id, nama_produk, harga_modal, harga_jual, stok, deskripsi, gambar
            FROM produk
            WHERE id_toko = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko]);
        $produk = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $produk
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
