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
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$authResult = validateToken($authHeader);
if (isset($authResult['error'])) {
    http_response_code(401);
    echo json_encode($authResult);
    exit;
}

// Ambil user_id jika valid
$user_id = $authResult;

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
} else {
    parse_str(file_get_contents('php://input'), $data);
}


    // Ambil id_produk dari query parameter jika tidak ada di body request
    $id_produk = $_GET['id_produk'] ?? ($data['id_produk'] ?? null);
    $nama_produk = $data['nama_produk'] ?? null;
    $harga_modal = $data['harga_modal'] ?? null;
    $harga_jual = $data['harga_jual'] ?? null;
    $stok = $data['stok'] ?? null;
    $deskripsi = $data['deskripsi'] ?? null;

    // Validasi input
    if (empty($id_produk)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Produk diperlukan'
        ]);
        exit;
    }

    try {
        // Query update produk
        $query = "
            UPDATE produk 
            SET nama_produk = COALESCE(?, nama_produk),
                harga_modal = COALESCE(?, harga_modal),
                harga_jual = COALESCE(?, harga_jual),
                stok = COALESCE(?, stok),
                deskripsi = COALESCE(?, deskripsi)
            WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$nama_produk, $harga_modal, $harga_jual, $stok, $deskripsi, $id_produk]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Produk berhasil diperbarui'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Produk tidak ditemukan atau tidak ada perubahan'
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
