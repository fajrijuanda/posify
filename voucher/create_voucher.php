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

// Debugging: Pastikan ID Toko ada
error_log("User ID: " . $user_id);
error_log("ID Toko: " . $id_toko);

if (!$id_toko) {
    echo json_encode([
        'success' => false,
        'error' => 'Toko tidak ditemukan untuk user ini'
    ]);
    exit;
}

// METHOD POST: Buat Voucher Baru
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

    // Ambil data dari input JSON
    $nama = $input['nama'] ?? null;
    $nilai_diskon = $input['nilai_diskon'] ?? 0;
    $minimal_belanja = $input['minimal_belanja'] ?? 0;

    // Validasi input
    if (empty($nama)) {
        echo json_encode([
            'success' => false,
            'error' => 'Nama voucher diperlukan'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // **Simpan voucher ke database**
        $queryInsert = "INSERT INTO voucher (id_toko, nama, nilai_diskon, minimal_belanja) VALUES (?, ?, ?, ?)";
        $stmtInsert = $pdo->prepare($queryInsert);
        $stmtInsert->execute([$id_toko, $nama, $nilai_diskon, $minimal_belanja]);

        // Ambil ID voucher yang baru saja dibuat
        $voucher_id = $pdo->lastInsertId();

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Voucher berhasil dibuat',
            'voucher' => [
                'id' => $voucher_id,
                'nama' => $nama,
                'nilai_diskon' => $nilai_diskon,
                'minimal_belanja' => $minimal_belanja,
                'id_toko' => $id_toko
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
