<?php
header('Content-Type: application/json');
require_once '../config/dbconnection.php';
require_once '../vendor/autoload.php';
require_once '../config/helpers.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$secretKey = $_ENV['JWT_SECRET'] ?? null;

if (empty($secretKey)) {
    respondJSON(['success' => false, 'error' => 'Secret key tidak ditemukan'], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJSON(['success' => false, 'error' => 'Invalid request method'], 405);
}

$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

if (!is_array($data)) {
    respondJSON(['success' => false, 'error' => 'Invalid JSON format'], 400);
}

$email = sanitizeInput($data['name'] ?? '');
$password = sanitizeInput($data['password'] ?? '');

if (empty($email) || empty($password)) {
    respondJSON(['success' => false, 'error' => 'Nama toko dan Password wajib diisi'], 400);
}

try {
    // ✅ 1. Ambil user berdasarkan name termasuk `role`
    $query = "SELECT id, name, password, role FROM users WHERE name = :name";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':name', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // ✅ 2. Ambil id_toko berdasarkan id_user
        $stmt = $pdo->prepare("SELECT id FROM toko WHERE id_user = ?");
        $stmt->execute([$user['id']]);
        $toko = $stmt->fetch(PDO::FETCH_ASSOC);
        $id_toko = $toko['id'] ?? null;

        // ✅ 3. Ambil id_langganan jika pengguna memiliki langganan premium
        $stmt = $pdo->prepare("
            SELECT id_langganan 
            FROM langganantoko 
            WHERE id_toko = ? 
            AND tanggal_berakhir > CURDATE()
            ORDER BY tanggal_berakhir DESC 
            LIMIT 1
        ");
        $stmt->execute([$id_toko]);
        $langganan = $stmt->fetch(PDO::FETCH_ASSOC);
        $id_langganan = $langganan['id_langganan'] ?? null;

        // ✅ 4. Buat payload JWT
        $payload = [
            'user_id' => $user['id'],
            'nama_toko' => $user['name'],
            'id_toko' => $id_toko,
            'id_langganan' => $id_langganan, 
            'role' => $user['role'], // Tambahkan role ke JWT
            'exp' => time() + 86400 // Token berlaku selama 1 hari
        ];

        // ✅ 5. Encode JWT Token
        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        // ✅ 6. Simpan token ke tabel sessions
        $insertQuery = "INSERT INTO sessions (user_id, token, expired_at, created_at) VALUES (?, ?, ?, ?)";
        $insertStmt = $pdo->prepare($insertQuery);
        $expiredAt = date('Y-m-d H:i:s', time() + 86400);
        $createdAt = date('Y-m-d H:i:s');
        $insertStmt->execute([$user['id'], $jwt, $expiredAt, $createdAt]);

        // ✅ 7. Kirim respons JSON
        respondJSON([
            'success' => true,
            'message' => 'Login berhasil',
            'token' => $jwt,
            'user' => [
                'id_user' => $user['id'],
                'nama_toko' => $user['name'],
                'id_toko' => $id_toko,
                'id_langganan' => $id_langganan, 
                'role' => $user['role'] 
            ]
        ]);
    } else {
        respondJSON(['success' => false, 'error' => 'Nama atau Password salah'], 401);
    }
} catch (PDOException $e) {
    respondJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
?>
