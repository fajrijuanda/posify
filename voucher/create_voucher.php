<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_toko = $_POST['id_toko'] ?? null;
    $nama = $_POST['nama'] ?? null;
    $nilai_diskon = $_POST['nilai_diskon'] ?? null;
    $minimal_belanja = $_POST['minimal_belanja'] ?? null;
    $deskripsi = $_POST['deskripsi'] ?? null;
    $tanggal_mulai = date('Y-m-d H:i:s');
    $tanggal_berakhir = $_POST['tanggal_berakhir'] ?? null; // Pastikan dikirim dalam format 'Y-m-d H:i:s'
    $kuota = $_POST['kuota'] ?? 1; // Default kuota adalah 1

    // Validasi input
    if (empty($id_toko) || empty($nama) || empty($nilai_diskon) || empty($minimal_belanja) || empty($tanggal_berakhir)) {
        echo json_encode([
            'success' => false,
            'error' => 'Semua field wajib diisi'
        ]);
        exit;
    }

    try {
        // Tambahkan voucher baru
        $query = "
            INSERT INTO voucher (id_toko, nama, nilai_diskon, minimal_belanja, deskripsi, tanggal_mulai, tanggal_berakhir, kuota)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko, $nama, $nilai_diskon, $minimal_belanja, $deskripsi, $tanggal_mulai, $tanggal_berakhir, $kuota]);

        echo json_encode([
            'success' => true,
            'message' => 'Voucher berhasil dibuat'
        ]);
    } catch (PDOException $e) {
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
