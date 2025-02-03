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

// METHOD POST: Menambahkan produk ke keranjang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true); // Ambil input JSON

    $id_produk = $input['id_produk'] ?? null;
    $jumlah = $input['jumlah'] ?? 1; // Default jumlah 1 jika tidak diinputkan

    if (empty($id_produk) || empty($id_toko)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Produk dan ID Toko diperlukan'
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
            // Jika belum ada `keranjang` untuk `id_toko`, buat dulu
            $queryInsertKeranjang = "INSERT INTO keranjang (id_toko) VALUES (?)";
            $stmtInsertKeranjang = $pdo->prepare($queryInsertKeranjang);
            $stmtInsertKeranjang->execute([$id_toko]);

            $id_keranjang = $pdo->lastInsertId(); // Ambil ID keranjang yang baru
        } else {
            $id_keranjang = $resultKeranjang['id'];
        }

        // Ambil harga produk dari `produk`
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

        // Cek apakah produk sudah ada di `produkkeranjang`
        $queryCheck = "SELECT id, kuantitas FROM produkkeranjang WHERE id_keranjang = ? AND id_produk = ?";
        $stmtCheck = $pdo->prepare($queryCheck);
        $stmtCheck->execute([$id_keranjang, $id_produk]);
        $resultCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($resultCheck) {
            // Jika produk sudah ada, update kuantitasnya
            $newQuantity = $resultCheck['kuantitas'] + $jumlah;
            $queryUpdate = "UPDATE produkkeranjang SET kuantitas = ? WHERE id = ?";
            $stmtUpdate = $pdo->prepare($queryUpdate);
            $stmtUpdate->execute([$newQuantity, $resultCheck['id']]);
        } else {
            // Jika produk belum ada, tambahkan ke `produkkeranjang`
            $queryInsert = "INSERT INTO produkkeranjang (id_keranjang, id_produk, kuantitas, harga_produk) VALUES (?, ?, ?, ?)";
            $stmtInsert = $pdo->prepare($queryInsert);
            $stmtInsert->execute([$id_keranjang, $id_produk, $jumlah, $harga_produk]);
        }

        // **Hitung total produk dalam keranjang berdasarkan id_keranjang**
        $queryTotalProduk = "SELECT SUM(kuantitas) AS total_produk FROM produkkeranjang WHERE id_keranjang = ?";
        $stmtTotalProduk = $pdo->prepare($queryTotalProduk);
        $stmtTotalProduk->execute([$id_keranjang]);
        $resultTotalProduk = $stmtTotalProduk->fetch(PDO::FETCH_ASSOC);
        $total_produk = $resultTotalProduk['total_produk'] ?? 0;

        // **Update total produk di tabel `keranjang`**
        $queryUpdateTotal = "UPDATE keranjang SET total_produk = ? WHERE id = ?";
        $stmtUpdateTotal = $pdo->prepare($queryUpdateTotal);
        $stmtUpdateTotal->execute([$total_produk, $id_keranjang]);

        // Commit transaksi
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
