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

// METHOD POST: Mengurangi jumlah produk dalam keranjang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    $id_produk = $input['id_produk'] ?? null;
    $jumlah = $input['jumlah'] ?? 1; // Default pengurangan 1 jika tidak diinputkan

    if (empty($id_produk)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Produk diperlukan'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Ambil `id_keranjang` berdasarkan `id_toko`
        $queryKeranjang = "SELECT id FROM keranjang WHERE id_toko = ?";
        $stmtKeranjang = $pdo->prepare($queryKeranjang);
        $stmtKeranjang->execute([$id_toko]);
        $resultKeranjang = $stmtKeranjang->fetch(PDO::FETCH_ASSOC);

        if (!$resultKeranjang) {
            echo json_encode([
                'success' => false,
                'error' => 'Keranjang tidak ditemukan untuk toko ini'
            ]);
            exit;
        }

        $id_keranjang = $resultKeranjang['id'];

        // Cek apakah produk ada di `produkkeranjang`
        $queryCheck = "SELECT id, kuantitas FROM produkkeranjang WHERE id_keranjang = ? AND id_produk = ?";
        $stmtCheck = $pdo->prepare($queryCheck);
        $stmtCheck->execute([$id_keranjang, $id_produk]);
        $resultCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$resultCheck) {
            echo json_encode([
                'success' => false,
                'error' => 'Produk tidak ditemukan dalam keranjang'
            ]);
            exit;
        }

        $currentQuantity = $resultCheck['kuantitas'];
        $newQuantity = max(0, $currentQuantity - $jumlah);

        if ($newQuantity > 0) {
            // Jika kuantitas masih lebih dari 0, update jumlah produk
            $queryUpdate = "UPDATE produkkeranjang SET kuantitas = ? WHERE id = ?";
            $stmtUpdate = $pdo->prepare($queryUpdate);
            $stmtUpdate->execute([$newQuantity, $resultCheck['id']]);
        } else {
            // Jika kuantitas mencapai 0 atau kurang, soft delete produk
            $querySoftDelete = "UPDATE produkkeranjang SET kuantitas = 0, deleted_at = NOW() WHERE id = ?";
            $stmtSoftDelete = $pdo->prepare($querySoftDelete);
            $stmtSoftDelete->execute([$resultCheck['id']]);

            // Kurangi jumlah produk di `keranjang`
            $queryUpdateTotal = "UPDATE keranjang SET total_produk = total_produk - 1 WHERE id = ?";
            $stmtUpdateTotal = $pdo->prepare($queryUpdateTotal);
            $stmtUpdateTotal->execute([$id_keranjang]);

        }

        // **Hitung ulang total produk dalam keranjang berdasarkan id_keranjang**
        $queryTotalProduk = "SELECT SUM(kuantitas) AS total_produk FROM produkkeranjang WHERE id_keranjang = ? AND deleted_at IS NULL";
        $stmtTotalProduk = $pdo->prepare($queryTotalProduk);
        $stmtTotalProduk->execute([$id_keranjang]);
        $resultTotalProduk = $stmtTotalProduk->fetch(PDO::FETCH_ASSOC);
        $total_produk = $resultTotalProduk['total_produk'] ?? 0;

        // **Update total produk di tabel `keranjang`**
        $queryUpdateKeranjang = "UPDATE keranjang SET total_produk = ? WHERE id = ?";
        $stmtUpdateKeranjang = $pdo->prepare($queryUpdateKeranjang);
        $stmtUpdateKeranjang->execute([$total_produk, $id_keranjang]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => $newQuantity > 0 ? 'Jumlah produk dalam keranjang dikurangi' : 'Produk ditandai sebagai dihapus (soft delete)',
            'id_produk' => $id_produk,
            'id_keranjang' => $id_keranjang,
            'new_quantity' => $newQuantity,
            'total_produk' => $total_produk
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