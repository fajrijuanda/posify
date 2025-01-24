<?php
header('Content-Type: application/json');
require_once '../config/dbconnection.php';
include('../config/cors.php');
require_once __DIR__ . '/../middlewares/auth_middleware.php';

// Ambil Authorization header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

$authResult = validateToken($authHeader);
if (isset($authResult['error'])) {
    http_response_code(401);
    echo json_encode($authResult);
    exit;
}

// Ambil user_id jika valid
$user_id = $authResult;

// Pastikan metode adalah POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_toko = $_POST['id_toko'] ?? null;
    $no_telepon = $_POST['no_telepon'] ?? null;
    $alamat = $_POST['alamat'] ?? null;
    $avatar = $_FILES['avatar'] ?? null;  // File avatar

    // Validasi input yang wajib diisi
    if (empty($id_toko) || empty($no_telepon)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID toko dan nomor telepon wajib diisi'
        ]);
        exit;
    }

    // Ambil nama toko dan id_user berdasarkan id_toko
    $queryUser = "SELECT u.name AS nama_user, t.nama_toko 
       FROM users u 
       JOIN toko t ON t.id_user = u.id 
       WHERE t.id = :id_toko";
    $stmtUser = $pdo->prepare($queryUser);
    $stmtUser->bindParam(':id_toko', $id_toko, PDO::PARAM_INT);
    $stmtUser->execute();
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        echo json_encode([
            'success' => false,
            'error' => 'Data toko atau user tidak ditemukan'
        ]);
        exit;
    }

    // Ambil nama toko dan nama user
    $namaToko = str_replace(' ', '_', strtolower($userData['nama_toko']));  // Format nama toko
    $namaUser = str_replace(' ', '_', strtolower($userData['nama_user']));
   
    // Proses upload gambar jika ada
    $avatarPath = null;
    if ($avatar && $avatar['error'] == 0) {
        $uploadDir = __DIR__ . '/../uploads/avatar/';  // Path absolut ke folder uploads/avatar
        $fileExt = strtolower(pathinfo($avatar['name'], PATHINFO_EXTENSION));
           // Buat nama file dengan format timestamp_nama_toko_nama_user.ext
           $fileName = time() . '_' . $namaToko . '_' . $namaUser . '.' . $fileExt;
           $targetFilePath = $uploadDir . $fileName;
   
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        // Validasi tipe file
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode([
                'success' => false,
                'error' => 'Format file avatar tidak valid (hanya jpg, jpeg, png, gif)'
            ]);
            exit;
        }

        // Pastikan folder uploads/avatar ada, jika tidak buat
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Simpan file ke folder uploads/avatar
        if (move_uploaded_file($avatar['tmp_name'], $targetFilePath)) {
            $avatarPath = 'uploads/avatar/' . $fileName;  // Simpan relative path di database
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Gagal mengunggah gambar, periksa izin folder'
            ]);
            exit;
        }
    }

    try {
        // Query untuk menambahkan pelanggan dengan avatar
        $query = "INSERT INTO pelanggan (id_toko, nomor_telepon, alamat, avatar) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko, $no_telepon, $alamat, $avatarPath]);

        // $baseURL = 'http://posify.test/';
        $baseURL = $_ENV['APP_URL'] ?? 'http://localhost';

        echo json_encode([
            'success' => true,
            'message' => 'Pelanggan berhasil ditambahkan',
            'avatar_url' => $avatarPath ? $baseURL . $avatarPath : null
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