<?php
header('Content-Type: application/json');
include('../config/dbconnection.php'); // Pastikan path sesuai
include('../config/cors.php'); // Include konfigurasi CORS
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str(file_get_contents('php://input'), $data); // Mendapatkan data dari body request

    $id_produk = $data['id_produk'] ?? null;
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
