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

if (!$id_toko) {
    echo json_encode([
        'success' => false,
        'error' => 'Toko tidak ditemukan untuk user ini'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    $id_produk = $input['id_produk'] ?? null;
    $jumlah = $input['jumlah'] ?? 1;

    if (empty($id_produk) || empty($id_toko)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Produk dan ID Toko diperlukan'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 🔹 **Ambil id_keranjang berdasarkan id_toko**
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

        // 🔹 **Cek apakah ada transaksi yang masih pending berdasarkan id_keranjang**
        $queryCheckout = "SELECT id FROM checkout WHERE id_keranjang = ?";
        $stmtCheckout = $pdo->prepare($queryCheckout);
        $stmtCheckout->execute([$id_keranjang]);
        $checkout = $stmtCheckout->fetch(PDO::FETCH_ASSOC);

        if ($checkout) {
            $id_checkout = $checkout['id'];

            // 🔹 **Cek status pembayaran berdasarkan id_checkout**
            $queryPembayaran = "SELECT status FROM pembayaran WHERE id_checkout = ? ORDER BY id DESC LIMIT 1";
            $stmtPembayaran = $pdo->prepare($queryPembayaran);
            $stmtPembayaran->execute([$id_checkout]);
            $pembayaran = $stmtPembayaran->fetch(PDO::FETCH_ASSOC);

            if ($pembayaran && $pembayaran['status'] === 'pending') {
                echo json_encode([
                    'success' => false,
                    'error' => 'Transaksi belum selesai, masih dalam status pending.'
                ]);
                exit;
            }

            if ($pembayaran && $pembayaran['status'] === 'completed') {
                // 🔹 **Jika transaksi selesai, kembalikan deleted_at ke NULL di produkkeranjang**
                $queryRestoreProdukKeranjang = "UPDATE produkkeranjang SET deleted_at = NULL WHERE id_keranjang = ?";
                $stmtRestoreProdukKeranjang = $pdo->prepare($queryRestoreProdukKeranjang);
                $stmtRestoreProdukKeranjang->execute([$id_keranjang]);
            }
        }

        // 🔹 **Ambil harga produk**
        $queryHarga = "SELECT harga_jual FROM produk WHERE id = ?";
        $stmtHarga = $pdo->prepare($queryHarga);
        $stmtHarga->execute([$id_produk]);
        $resultHarga = $stmtHarga->fetch(PDO::FETCH_ASSOC);

        if (!$resultHarga) {
            echo json_encode([
                'success' => false,
                'error' => 'Produk tidak ditemukan dalam database'
            ]);
            $pdo->rollBack();
            exit;
        }

        $harga_produk = $resultHarga['harga_jual'];

        // 🔹 **Cek apakah produk sudah ada di produkkeranjang**
        $queryCheck = "SELECT id, kuantitas FROM produkkeranjang WHERE id_keranjang = ? AND id_produk = ?";
        $stmtCheck = $pdo->prepare($queryCheck);
        $stmtCheck->execute([$id_keranjang, $id_produk]);
        $resultCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($resultCheck) {
            // 🔹 **Jika produk sudah ada, update kuantitas dan deleted_at**
            $newQuantity = $resultCheck['kuantitas'] + $jumlah;
            $queryUpdate = "UPDATE produkkeranjang SET kuantitas = ?, deleted_at = NULL WHERE id = ?";
            $stmtUpdate = $pdo->prepare($queryUpdate);
            $stmtUpdate->execute([$newQuantity, $resultCheck['id']]);
        } else {
            // 🔹 **Jika produk belum ada, tambahkan ke produkkeranjang**
            $queryInsert = "INSERT INTO produkkeranjang (id_keranjang, id_produk, kuantitas, harga_produk, deleted_at) VALUES (?, ?, ?, ?, NULL)";
            $stmtInsert = $pdo->prepare($queryInsert);
            $stmtInsert->execute([$id_keranjang, $id_produk, $jumlah, $harga_produk]);
        }

        // 🔹 **Hitung total produk dalam keranjang**
        $queryTotalProduk = "SELECT SUM(kuantitas) AS total_produk FROM produkkeranjang WHERE id_keranjang = ?";
        $stmtTotalProduk = $pdo->prepare($queryTotalProduk);
        $stmtTotalProduk->execute([$id_keranjang]);
        $resultTotalProduk = $stmtTotalProduk->fetch(PDO::FETCH_ASSOC);
        $total_produk = $resultTotalProduk['total_produk'] ?? 0;

        // 🔹 **Update total produk di keranjang**
        $queryUpdateTotal = "UPDATE keranjang SET total_produk = ? WHERE id = ?";
        $stmtUpdateTotal = $pdo->prepare($queryUpdateTotal);
        $stmtUpdateTotal->execute([$total_produk, $id_keranjang]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan ke keranjang',
            'total_produk' => $total_produk
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
}
?>
