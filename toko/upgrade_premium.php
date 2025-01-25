<?php
header('Content-Type: application/json');
include("../config/dbconnection.php"); // Sesuaikan path jika diperlukan
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Set timezone ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

// Validasi token untuk otentikasi
$user_id = validateToken($pdo);
if (!$user_id) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token tidak valid atau sesi berakhir'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data input
    $id_toko = $_POST['id_toko'] ?? null;
    $id_paket = $_POST['id_paket'] ?? null;

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

        // Periksa apakah toko sudah memiliki langganan aktif
        $queryCheck = "SELECT id FROM langganantoko WHERE id_toko = ? AND id_langganan = ? AND tanggal_berakhir > CURDATE()";
        $stmtCheck = $pdo->prepare($queryCheck);
        $stmtCheck->execute([$id_toko, $id_paket]);

        if ($stmtCheck->rowCount() > 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Toko sudah memiliki langganan aktif untuk paket ini'
            ]);
            $pdo->rollBack();
            exit;
        }

        // Ambil informasi paket berdasarkan ID paket
        $queryPaket = "SELECT nama, durasi, harga FROM paketlangganan WHERE id = ? LIMIT 1";
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
        $nama_paket = $paket['nama'];
        $durasi = (int)$paket['durasi']; // Pastikan durasi adalah integer
        
        // Pastikan paket yang dipilih adalah "premium"
        if (strtolower($nama_paket) !== 'premium') {
            echo json_encode([
                'success' => false,
                'error' => 'Paket yang dipilih bukan premium, silakan pilih paket premium'
            ]);
            $pdo->rollBack();
            exit;
        }
        
        // Validasi durasi sebelum digunakan
        if ($durasi <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Durasi paket tidak valid'
            ]);
            $pdo->rollBack();
            exit;
        }
        

        // Hitung tanggal mulai dan tanggal berakhir
        $tanggal_mulai = date('Y-m-d');

        // Gunakan DateTime untuk perhitungan tanggal yang lebih akurat
        $datetime = new DateTime($tanggal_mulai);
        $datetime->modify("+{$durasi} months");
        $tanggal_berakhir = $datetime->format('Y-m-d');

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
            'message' => 'Upgrade ke ' . ucfirst($nama_paket) . ' berhasil',
            'data' => [
                'id_toko' => $id_toko,
                'id_paket' => $id_paket,
                'paket_nama' => $nama_paket,
                'tanggal_mulai' => $tanggal_mulai,
                'tanggal_berakhir' => $tanggal_berakhir,
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
