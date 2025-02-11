<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php');
include('../config/helpers.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

// ✅ Validasi token JWT
$authResult = validateToken();
if (!is_array($authResult) || !isset($authResult['user_id'], $authResult['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

// ✅ Cek apakah pengguna adalah admin
if ($authResult['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Akses ditolak']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // ✅ Ambil daftar toko yang memiliki paket langganan "Premium"
        $queryTokoStandard = "
            SELECT 
                t.id AS id_toko,
                t.nama_toko,
                t.logo,
                t.alamat,
                l.id_langganan,
                p.nama AS paket_nama,
                l.tanggal_berakhir
            FROM toko t
            JOIN langganantoko l ON t.id = l.id_toko
            JOIN paketlangganan p ON l.id_langganan = p.id
            WHERE p.nama = 'standard' AND l.tanggal_berakhir > CURDATE()";

        $stmtTokoStandard = $pdo->query($queryTokoStandard);
        $tokoStandard = $stmtTokoStandard->fetchAll(PDO::FETCH_ASSOC);

        // Format URL gambar logo toko
        foreach ($tokoStandard as &$toko) {
            if (!empty($toko['logo'])) {
                $toko['logo_url'] = $baseURL . '/' . $toko['logo'];
            } else {
                $toko['logo_url'] = null; // Jika tidak ada logo
            }
        }

        // ✅ Format response JSON
        echo json_encode([
            'success' => true,
            'data' => [
                'standard_stores' => $tokoStandard // Menampilkan semua toko Standard dengan paket "Standard"
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