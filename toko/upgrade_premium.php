<?php
header('Content-Type: application/json');
include("../config/dbconnection.php"); // Sesuaikan path jika diperlukan
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data input
    $id_toko = $_POST['id_toko'] ?? null;
    $id_paket = $_POST['id_paket'] ?? null; // ID paket langganan yang dipilih

    // Validasi input
    if (empty($id_toko) || empty($id_paket)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Toko dan ID Paket diperlukan'
        ]);
        exit;
    }

    try {
        // Mulai transaksi
        $pdo->beginTransaction();

        // Cek apakah toko sudah memiliki langganan aktif
        $queryCheck = "SELECT id FROM langganantoko WHERE id_toko = ? AND tanggal_berakhir > NOW()";
        $stmtCheck = $pdo->prepare($queryCheck);
        $stmtCheck->execute([$id_toko]);

        if ($stmtCheck->rowCount() > 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Toko sudah memiliki langganan aktif'
            ]);
            $pdo->rollBack();
            exit;
        }

        // Ambil informasi paket dari tabel PaketLangganan
        $queryPaket = "SELECT durasi, harga FROM paketlangganan WHERE id = ? LIMIT 1";
        $stmtPaket = $pdo->prepare($queryPaket);
        $stmtPaket->execute([$id_paket]);

        if ($stmtPaket->rowCount() === 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Paket langganan tidak ditemukan'
            ]);
            $pdo->rollBack();
            exit;
        }

        $paket = $stmtPaket->fetch(PDO::FETCH_ASSOC);
        $durasi = $paket['durasi'];
        $biaya = $paket['harga'];

        // Hitung tanggal mulai dan tanggal berakhir berdasarkan durasi paket
        $tanggal_mulai = date('Y-m-d H:i:s');
        $tanggal_berakhir = date('Y-m-d H:i:s', strtotime("+$durasi months"));

        // Masukkan langganan ke database
        $queryInsert = "
            INSERT INTO langganantoko (id_toko, id_langganan, tanggal_mulai, tanggal_berakhir)
            VALUES (?, ?, ?, ?)";
        $stmtInsert = $pdo->prepare($queryInsert);
        $stmtInsert->execute([$id_toko, $id_paket, $tanggal_mulai, $tanggal_berakhir]);

        // Commit transaksi
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Upgrade ke premium berhasil',
            'data' => [
                'id_toko' => $id_toko,
                'id_paket' => $id_paket,
                'tanggal_mulai' => $tanggal_mulai,
                'tanggal_berakhir' => $tanggal_berakhir,
                'biaya' => $biaya
            ]
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
