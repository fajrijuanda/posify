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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input dari user
    $id_toko = $_POST['id_toko'] ?? null;
    $logo = $_FILES['logo'] ?? null;
    $nomor_telepon = $_POST['nomor_telepon'] ?? null;
    $nomor_rekening = $_POST['nomor_rekening'] ?? null;
    $alamat = $_POST['alamat'] ?? null;

    // Validasi input wajib
    if (empty($id_toko) || empty($nomor_telepon) || empty($nomor_rekening)) {
        echo json_encode([
            'success' => false,
            'error' => 'Semua field wajib diisi'
        ]);
        exit;
    }

    try {
        // Ambil nama toko dan gambar logo yang sudah ada dari database
        $queryToko = "SELECT nama_toko, logo FROM toko WHERE id = ?";
        $stmtToko = $pdo->prepare($queryToko);
        $stmtToko->execute([$id_toko]);
        $toko = $stmtToko->fetch(PDO::FETCH_ASSOC);

        if (!$toko) {
            echo json_encode([
                'success' => false,
                'error' => 'Toko tidak ditemukan'
            ]);
            exit;
        }

        $nama_toko = str_replace(' ', '_', strtolower($toko['nama_toko']));
        $oldLogoPath = $toko['logo'];

        // Proses upload logo (jika ada)
        $logoPath = $oldLogoPath;  // Gunakan gambar lama jika tidak ada unggahan baru
        if (!empty($logo) && $logo['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/toko/';
            
            // Pastikan folder upload ada
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Hapus file gambar lama jika ada
            if (!empty($oldLogoPath) && file_exists(__DIR__ . "/.." . $oldLogoPath)) {
                unlink(__DIR__ . "/.." . $oldLogoPath);
            }

            // Ambil ekstensi file logo baru
            $ext = pathinfo($logo['name'], PATHINFO_EXTENSION);

            // Buat nama file dari nama toko + timestamp
            $newLogoName = $nama_toko . '_' . time() . '.' . $ext;
            $logoPath = '/uploads/toko/' . $newLogoName;

            // Pindahkan file yang diunggah ke folder tujuan
            if (!move_uploaded_file($logo['tmp_name'], __DIR__ . "/.." . $logoPath)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Gagal mengunggah logo'
                ]);
                exit;
            }
        }

        // Update informasi toko di database
        $query = "UPDATE toko SET logo = ?, nomor_telepon = ?, nomor_rekening = ?, alamat = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$logoPath, $nomor_telepon, $nomor_rekening, $alamat, $id_toko]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Informasi bisnis berhasil diperbarui',
                'logo_url' => $logoPath ? ($_ENV['APP_URL'] . $logoPath) : null
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Gagal memperbarui informasi bisnis atau tidak ada perubahan'
            ]);
        }
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
