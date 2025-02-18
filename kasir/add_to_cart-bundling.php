<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// 🔹 **Validasi token untuk otentikasi**
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

    $id_bundling = $input['id_bundling'] ?? null;
    $jumlah = $input['jumlah'] ?? 1;

    if (empty($id_bundling) || empty($id_toko)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Bundling dan ID Toko diperlukan'
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

        // 🔹 **Ambil daftar produk dalam bundling**
        $queryProdukBundling = "SELECT id_produk, jumlah FROM bundling_produk WHERE id_bundling = ?";
        $stmtProdukBundling = $pdo->prepare($queryProdukBundling);
        $stmtProdukBundling->execute([$id_bundling]);
        $produkBundling = $stmtProdukBundling->fetchAll(PDO::FETCH_ASSOC);

        if (!$produkBundling) {
            echo json_encode([
                'success' => false,
                'error' => 'Bundling tidak ditemukan atau tidak memiliki produk'
            ]);
            exit;
        }

        // 🔹 **Ambil harga jual tertinggi dari produk dalam bundling**
        $queryHargaTertinggi = "
            SELECT MAX(p.harga_jual) AS harga_tertinggi
            FROM bundling_produk bp
            JOIN produk p ON bp.id_produk = p.id
            WHERE bp.id_bundling = ?";
        $stmtHargaTertinggi = $pdo->prepare($queryHargaTertinggi);
        $stmtHargaTertinggi->execute([$id_bundling]);
        $resultHargaTertinggi = $stmtHargaTertinggi->fetch(PDO::FETCH_ASSOC);

        if (!$resultHargaTertinggi || $resultHargaTertinggi['harga_tertinggi'] === null) {
            echo json_encode([
                'success' => false,
                'error' => 'Harga tertinggi tidak ditemukan'
            ]);
            $pdo->rollBack();
            exit;
        }

        $harga_bundling = $resultHargaTertinggi['harga_tertinggi'];

        // 🔹 **Cek apakah bundling sudah ada di produkkeranjang**
        $queryCheck = "SELECT id, kuantitas FROM produkkeranjang WHERE id_keranjang = ? AND id_bundling = ?";
        $stmtCheck = $pdo->prepare($queryCheck);
        $stmtCheck->execute([$id_keranjang, $id_bundling]);
        $resultCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($resultCheck) {
            // 🔹 **Jika bundling sudah ada, update kuantitas**
            $newQuantity = $resultCheck['kuantitas'] + $jumlah;
            $queryUpdate = "UPDATE produkkeranjang SET kuantitas = ?, deleted_at = NULL WHERE id = ?";
            $stmtUpdate = $pdo->prepare($queryUpdate);
            $stmtUpdate->execute([$newQuantity, $resultCheck['id']]);
        } else {
            // 🔹 **Jika bundling belum ada, tambahkan ke produkkeranjang dengan harga tertinggi**
            $queryInsert = "INSERT INTO produkkeranjang (id_keranjang, id_bundling, kuantitas, harga_produk, deleted_at) VALUES (?, ?, ?, ?, NULL)";
            $stmtInsert = $pdo->prepare($queryInsert);
            $stmtInsert->execute([$id_keranjang, $id_bundling, $jumlah, $harga_bundling]);
        }

        // 🔹 **Kurangi jumlah di `bundling_produk`**
        // foreach ($produkBundling as $produk) {
        //     $id_produk = $produk['id_produk'];
        //     $jumlah_kurang = $produk['jumlah'];

        //     // $queryUpdateBundlingProduk = "UPDATE bundling_produk SET jumlah = jumlah - ? WHERE id_bundling = ? AND id_produk = ?";
        //     // $stmtUpdateBundlingProduk = $pdo->prepare($queryUpdateBundlingProduk);
        //     // $stmtUpdateBundlingProduk->execute([$jumlah_kurang, $id_bundling, $id_produk]);

        //     // $queryDeleteZero = "DELETE FROM bundling_produk WHERE id_bundling = ? AND id_produk = ? AND jumlah <= 0";
        //     // $stmtDeleteZero = $pdo->prepare($queryDeleteZero);
        //     // $stmtDeleteZero->execute([$id_bundling, $id_produk]);
        // }

        // 🔹 **Kurangi `total_jumlah` di tabel `bundling`**
        // $queryUpdateBundling = "UPDATE bundling SET total_jumlah = total_jumlah - ? WHERE id = ?";
        // $stmtUpdateBundling = $pdo->prepare($queryUpdateBundling);
        // $stmtUpdateBundling->execute([$jumlah, $id_bundling]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Bundling berhasil ditambahkan ke keranjang']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
