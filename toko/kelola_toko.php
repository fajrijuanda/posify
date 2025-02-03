<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); 
include('../config/helpers.php');  

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

$authResult = validateToken();
if (!is_array($authResult) || !isset($authResult['user_id'], $authResult['id_toko'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$user_id = $authResult['user_id'];
$id_toko = $authResult['id_toko'];

error_log("User ID: " . $user_id);
error_log("ID Toko: " . $id_toko);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_toko = $_POST['nama_toko'] ?? null;
    $nomor_telepon = $_POST['nomor_telepon'] ?? null;
    $nomor_rekening = $_POST['nomor_rekening'] ?? null;
    $alamat = $_POST['alamat'] ?? null;
    
    $uploadDir = __DIR__ . '/../uploads/toko/';
    $logo = null;
    if (!empty($_FILES['logo']['name'])) {
        $fileName = time() . '_' . basename($_FILES['logo']['name']);
        $targetFilePath = $uploadDir . $fileName;
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFilePath)) {
            $logo = 'uploads/toko/' . $fileName;
        } else {
            echo json_encode(['success' => false, 'error' => 'Gagal mengupload logo']);
            exit;
        }
    }
    
    if (empty($id_toko)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Toko diperlukan'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction(); // Memulai transaksi

        // **1️⃣ Dapatkan ID User yang terkait dengan toko**
        $queryUser = "SELECT id_user FROM toko WHERE id = ?";
        $stmtUser = $pdo->prepare($queryUser);
        $stmtUser->execute([$id_toko]);
        $resultUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$resultUser) {
            throw new Exception("Toko tidak ditemukan atau tidak memiliki user terkait.");
        }

        $id_user = $resultUser['id_user'];

        // **2️⃣ Update tabel toko**
        $queryUpdateToko = "UPDATE toko SET nama_toko = ?, nomor_telepon = ?, nomor_rekening = ?, alamat = ?, logo = ? WHERE id = ?";
        $stmtUpdateToko = $pdo->prepare($queryUpdateToko);
        $stmtUpdateToko->execute([
            $nama_toko,
            $nomor_telepon,
            $nomor_rekening,
            $alamat,
            $logo,
            $id_toko
        ]);

        // **3️⃣ Update nama pada tabel users**
        $queryUpdateUser = "UPDATE users SET name = ? WHERE id = ?";
        $stmtUpdateUser = $pdo->prepare($queryUpdateUser);
        $stmtUpdateUser->execute([$nama_toko, $id_user]);

        $pdo->commit(); // Simpan perubahan

        echo json_encode([
            'success' => true,
            'message' => 'Pengaturan toko dan pengguna berhasil diperbarui',
            'data' => [
                'id' => $id_toko,
                'nama_toko' => $nama_toko,
                'nomor_telepon' => $nomor_telepon,
                'nomor_rekening' => $nomor_rekening,
                'alamat' => $alamat,
                'logo' => $logo ? $baseURL . '/' . $logo : null,
                'id_user' => $id_user,
                'updated_user_name' => $nama_toko
            ]
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT t.id, t.nama_toko, t.nomor_telepon, t.nomor_rekening, t.alamat, t.logo, u.email, u.name 
                  FROM toko t 
                  JOIN users u ON t.id_user = u.id 
                  WHERE t.id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($config) {
            $config['logo_url'] = !empty($config['logo']) ? $baseURL . '/' . $config['logo'] : null;

            $formattedResponse = [
                'success' => true,
                'data' => [
                    'id' => $config['id'],
                    'nama_toko' => $config['nama_toko'],
                    'nomor_telepon' => $config['nomor_telepon'],
                    'nomor_rekening' => $config['nomor_rekening'],
                    'alamat' => $config['alamat'],
                    'logo' => $config['logo'],
                    'logo_url' => $config['logo_url'],
                    'email' => $config['email'],
                    'name' => $config['name']
                ]
            ];
        } else {
            throw new Exception("Data toko tidak ditemukan.");
        }

        echo json_encode($formattedResponse);
    } catch (Exception $e) {
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
