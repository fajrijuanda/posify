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
    $id_bundling = $input['id_bundling'] ?? null;
    $jumlah = $input['jumlah'] ?? 1;

    // **Validasi: Pastikan salah satu dari `id_produk` atau `id_bundling` harus ada**
    if (empty($id_produk) && empty($id_bundling)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Produk atau ID Bundling diperlukan'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 🔹 **Ambil ID keranjang berdasarkan ID toko**
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

        // **Jika yang ditambahkan adalah produk satuan**
        if ($id_produk) {
            // 🔹 **Ambil harga produk dan stok**
            $queryHarga = "SELECT harga_jual, stok FROM produk WHERE id = ?";
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
            $stok_produk = $resultHarga['stok'];

            // 🔹 **Periksa stok**
            if ($stok_produk < $jumlah) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Stok produk tidak mencukupi'
                ]);
                $pdo->rollBack();
                exit;
            }

            // 🔹 **Cek apakah produk sudah ada di keranjang**
            $queryCheck = "SELECT id, kuantitas FROM produkkeranjang WHERE id_keranjang = ? AND id_produk = ?";
            $stmtCheck = $pdo->prepare($queryCheck);
            $stmtCheck->execute([$id_keranjang, $id_produk]);
            $resultCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($resultCheck) {
                // 🔹 **Jika produk sudah ada, update kuantitas**
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
        }

        // **Jika yang ditambahkan adalah bundling**
        if ($id_bundling) {
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
                // 🔹 **Jika bundling belum ada, tambahkan ke produkkeranjang**
                $queryInsert = "INSERT INTO produkkeranjang (id_keranjang, id_bundling, kuantitas, harga_produk, deleted_at) VALUES (?, ?, ?, ?, NULL)";
                $stmtInsert = $pdo->prepare($queryInsert);
                $stmtInsert->execute([$id_keranjang, $id_bundling, $jumlah, $harga_bundling]);
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Produk/Bundling berhasil ditambahkan ke keranjang'
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
