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

        // 🔹 **Hitung jumlah produk dalam bundling berdasarkan id_bundling**
        $queryTotalProdukBundling = "
            SELECT COUNT(*) AS total_produk
            FROM bundling_produk
            WHERE id_bundling = ?";
        $stmtTotalProdukBundling = $pdo->prepare($queryTotalProdukBundling);
        $stmtTotalProdukBundling->execute([$id_bundling]);
        $resultTotalProdukBundling = $stmtTotalProdukBundling->fetch(PDO::FETCH_ASSOC);
        $total_produk_in_bundling = $resultTotalProdukBundling['total_produk'] ?? 1; // Hindari pembagian nol

        // 🔹 **Hitung total harga produk dalam bundling**
        $queryHargaBundling = "
            SELECT SUM(p.harga_jual * bp.jumlah) AS total_harga
            FROM bundling_produk bp
            JOIN produk p ON bp.id_produk = p.id
            WHERE bp.id_bundling = ?";
        $stmtHargaBundling = $pdo->prepare($queryHargaBundling);
        $stmtHargaBundling->execute([$id_bundling]);
        $resultHargaBundling = $stmtHargaBundling->fetch(PDO::FETCH_ASSOC);

        if (!$resultHargaBundling || $resultHargaBundling['total_harga'] === null) {
            echo json_encode([
                'success' => false,
                'error' => 'Harga bundling tidak ditemukan'
            ]);
            $pdo->rollBack();
            exit;
        }

        $harga_bundling = $resultHargaBundling['total_harga'];

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
            // 🔹 **Jika bundling belum ada, tambahkan ke produkkeranjang**
            $queryInsert = "INSERT INTO produkkeranjang (id_keranjang, id_bundling, kuantitas, harga_produk, deleted_at) VALUES (?, ?, ?, ?, NULL)";
            $stmtInsert = $pdo->prepare($queryInsert);
            $stmtInsert->execute([$id_keranjang, $id_bundling, $jumlah, $harga_bundling]);
        }

        // 🔹 **Kurangi jumlah di `bundling_produk` secara proporsional**
        $jumlah_per_produk = floor($jumlah / $total_produk_in_bundling);
        $sisa = $jumlah % $total_produk_in_bundling;

        foreach ($produkBundling as $index => $produk) {
            $id_produk = $produk['id_produk'];
            $jumlah_kurang = $jumlah_per_produk;

            if ($sisa > 0) {
                $jumlah_kurang += 1;
                $sisa--;
            }

            $queryUpdateBundlingProduk = "UPDATE bundling_produk SET jumlah = jumlah - ? WHERE id_bundling = ? AND id_produk = ?";
            $stmtUpdateBundlingProduk = $pdo->prepare($queryUpdateBundlingProduk);
            $stmtUpdateBundlingProduk->execute([$jumlah_kurang, $id_bundling, $id_produk]);

            $queryDeleteZero = "DELETE FROM bundling_produk WHERE id_bundling = ? AND id_produk = ? AND jumlah <= 0";
            $stmtDeleteZero = $pdo->prepare($queryDeleteZero);
            $stmtDeleteZero->execute([$id_bundling, $id_produk]);
        }

        // 🔹 **Kurangi `total_jumlah` di tabel `bundling`**
        $queryUpdateBundling = "UPDATE bundling SET total_jumlah = total_jumlah - ? WHERE id = ?";
        $stmtUpdateBundling = $pdo->prepare($queryUpdateBundling);
        $stmtUpdateBundling->execute([$jumlah, $id_bundling]);

        // 🔹 **Update total produk di keranjang**
        $queryUpdateTotal = "UPDATE keranjang SET total_produk = ? WHERE id = ?";
        $stmtUpdateTotal = $pdo->prepare($queryUpdateTotal);
        $stmtUpdateTotal->execute([$total_produk_in_bundling, $id_keranjang]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Bundling berhasil ditambahkan ke keranjang', 'total_produk' => $total_produk_in_bundling]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
