<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token JWT
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

// METHOD DELETE: Menghapus seluruh isi keranjang berdasarkan id_keranjang
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents("php://input"), true);
    
    $id_keranjang = $input['id_keranjang'] ?? null;

    if (empty($id_keranjang)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Keranjang diperlukan'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Cek apakah `id_keranjang` valid dan milik toko yang sesuai
        $queryCheckKeranjang = "SELECT id FROM keranjang WHERE id = ? AND id_toko = ?";
        $stmtCheckKeranjang = $pdo->prepare($queryCheckKeranjang);
        $stmtCheckKeranjang->execute([$id_keranjang, $id_toko]);
        $resultCheckKeranjang = $stmtCheckKeranjang->fetch(PDO::FETCH_ASSOC);

        if (!$resultCheckKeranjang) {
            echo json_encode([
                'success' => false,
                'error' => 'Keranjang tidak ditemukan atau bukan milik toko ini'
            ]);
            exit;
        }

        // Hapus semua produk yang ada dalam keranjang berdasarkan id_keranjang
        $queryDeleteProduk = "DELETE FROM produkkeranjang WHERE id_keranjang = ?";
        $stmtDeleteProduk = $pdo->prepare($queryDeleteProduk);
        $stmtDeleteProduk->execute([$id_keranjang]);

        // Update total_produk di `keranjang` menjadi nol
        $queryUpdateKeranjang = "UPDATE keranjang SET total_produk = 0 WHERE id = ?";
        $stmtUpdateKeranjang = $pdo->prepare($queryUpdateKeranjang);
        $stmtUpdateKeranjang->execute([$id_keranjang]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Semua produk berhasil dihapus dari keranjang",
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
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
