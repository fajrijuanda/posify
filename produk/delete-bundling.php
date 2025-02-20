<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php');

$userData = validateToken();
if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid']);
    exit;
}

$id_toko = $userData['id_toko']; // Ambil ID toko dari token JWT

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

    // Ambil ID bundling dari input JSON
    $id_bundling = $input['id_bundling'] ?? null;

    if (empty($id_bundling)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Bundling wajib diisi'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // **🔹 Cek apakah bundling ada dan milik toko ini**
        $queryCheckBundling = "SELECT id FROM bundling WHERE id = ? AND id_toko = ?";
        $stmtCheckBundling = $pdo->prepare($queryCheckBundling);
        $stmtCheckBundling->execute([$id_bundling, $id_toko]);
        $bundlingExists = $stmtCheckBundling->fetch(PDO::FETCH_ASSOC);

        if (!$bundlingExists) {
            echo json_encode([
                'success' => false,
                'error' => 'Bundling tidak ditemukan atau tidak milik toko ini'
            ]);
            exit;
        }

        // **🔹 Hapus semua produk terkait dalam `bundling_produk`**
        $queryDeleteBundlingProduk = "DELETE FROM bundling_produk WHERE id_bundling = ?";
        $stmtDeleteBundlingProduk = $pdo->prepare($queryDeleteBundlingProduk);
        $stmtDeleteBundlingProduk->execute([$id_bundling]);

        // **🔹 Hapus data dari `bundling`**
        $queryDeleteBundling = "DELETE FROM bundling WHERE id = ?";
        $stmtDeleteBundling = $pdo->prepare($queryDeleteBundling);
        $stmtDeleteBundling->execute([$id_bundling]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Bundling berhasil dihapus',
            'id_bundling' => $id_bundling
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
