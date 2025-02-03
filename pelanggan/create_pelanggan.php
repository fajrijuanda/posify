<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token JWT untuk mendapatkan data pengguna
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

// PASTIKAN METODE ADALAH POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input JSON
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);

    // Debugging: Cek JSON yang diterima
    error_log("JSON Input: " . json_encode($input));

    if (!is_array($input)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON format'
        ]);
        exit;
    }

    // Ambil data input dari JSON
    $nomor_telepon = $input['nomor_telepon'] ?? null;
    $nama_pelanggan = $input['nama_pelanggan'] ?? null;
    $email = $input['email'] ?? null;

    // Validasi input wajib
    if (empty($id_toko) || empty($nomor_telepon)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID toko dan nomor telepon wajib diisi'
        ]);
        exit;
    }

    try {
        // INSERT DATA PELANGGAN KE DATABASE
        $query = "INSERT INTO pelanggan (id_toko, nomor_telepon, nama_pelanggan, email) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko, $nomor_telepon, $nama_pelanggan, $email]);

        // Ambil ID pelanggan yang baru ditambahkan
        $id_pelanggan = $pdo->lastInsertId();

        // Kirim respons JSON dengan data pelanggan yang baru saja ditambahkan
        echo json_encode([
            'success' => true,
            'message' => 'Pelanggan berhasil ditambahkan',
            'data' => [
                'id_pelanggan' => $id_pelanggan,
                'id_toko' => $id_toko,
                'nomor_telepon' => $nomor_telepon,
                'nama_pelanggan' => $nama_pelanggan,
                'email' => $email
            ]
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