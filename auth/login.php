<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include("../config/helpers.php");
use \Firebase\JWT\JWT; // Import JWT class

$secretKey = "your-secret-key"; // Ganti dengan key rahasia Anda

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input dari user
    $nama_toko = sanitizeInput($_POST['nama_toko'] ?? null);
    $password = sanitizeInput($_POST['password'] ?? null);

    // Validasi input
    if (empty($nama_toko) || empty($password)) {
        respondJSON(['success' => false, 'error' => 'Nama Toko dan Password wajib diisi'], 400);
    }

    try {
        // Query toko berdasarkan nama_toko
        $query = "SELECT id, id_user, password FROM toko WHERE nama_toko = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$nama_toko]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Membuat payload untuk token JWT
            $payload = [
                'user_id' => $user['id_user'],
                'nama_toko' => $nama_toko,
                'exp' => time() + 3600 // Token berlaku selama 1 jam
            ];

            // Encode payload menjadi JWT
            $jwt = JWT::encode($payload, $secretKey, 'HS256');


            // Kirim token ke klien
            respondJSON([
                'success' => true,
                'message' => 'Login berhasil',
                'token' => $jwt,
                'nama_toko' => $nama_toko,
            ]);
        } else {
            // Jika password salah
            respondJSON(['success' => false, 'error' => 'Nama Toko atau Password salah'], 401);
        }
    } catch (PDOException $e) {
        respondJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    }
} else {
    // Jika metode request bukan POST
    respondJSON(['success' => false, 'error' => 'Invalid request method'], 405);
}
?>
