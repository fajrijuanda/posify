<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); 
include('../config/helpers.php');  

// Load .env jika menggunakan PHP dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

// Validasi token untuk otentikasi
$authResult = validateToken(); // Tidak perlu parameter
if (!is_array($authResult) || !isset($authResult['user_id'], $authResult['id_toko'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

// Ambil user_id & id_toko dari token JWT
$user_id = $authResult['user_id'];
$id_toko = $authResult['id_toko'];

// Debugging: Pastikan id_toko ada sebelum query
error_log("User ID: " . $user_id);
error_log("ID Toko: " . $id_toko);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tangani input form-data
    $nama_toko = $_POST['nama_toko'] ?? null;
    $nomor_telepon = $_POST['nomor_telepon'] ?? null;
    $nomor_rekening = $_POST['nomor_rekening'] ?? null;
    $alamat = $_POST['alamat'] ?? null;
    
    // Tangani unggah gambar logo
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
        // Simpan perubahan toko
        $query = "UPDATE toko SET nama_toko = ?, nomor_telepon = ?, nomor_rekening = ?, alamat = ?, logo = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $nama_toko,
            $nomor_telepon,
            $nomor_rekening,
            $alamat,
            $logo,
            $id_toko
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Pengaturan toko berhasil diperbarui',
            'data' => [
                'id' => $id_toko,
                'nama_toko' => $nama_toko,
                'nomor_telepon' => $nomor_telepon,
                'nomor_rekening' => $nomor_rekening,
                'alamat' => $alamat,
                'logo' => $logo ? $baseURL . '/' . $logo : null
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Ambil konfigurasi toko beserta email dari tabel users
        $query = "SELECT t.id, t.nama_toko, t.nomor_telepon, t.nomor_rekening, t.alamat, t.logo, u.email 
                  FROM toko t 
                  JOIN users u ON t.id_user = u.id 
                  WHERE t.id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($config) {
            // Tambahkan URL gambar logo
            $config['logo_url'] = !empty($config['logo']) ? $baseURL . '/' . $config['logo'] : null;

            // Format ulang respons agar email berada di dalam id_user
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
                    'email' => $config['email']
        
                ]
            ];
        }

        echo json_encode($formattedResponse);
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