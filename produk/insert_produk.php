<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_toko = $_POST['id_toko'] ?? null;
    $nama_produk = $_POST['nama_produk'] ?? null;
    $harga_modal = $_POST['harga_modal'] ?? null;
    $harga_jual = $_POST['harga_jual'] ?? null;
    $stok = $_POST['stok'] ?? null;
    $deskripsi = $_POST['deskripsi'] ?? null;

    if (empty($id_toko) || empty($nama_produk) || empty($harga_modal) || empty($harga_jual) || empty($stok)) {
        echo json_encode([
            'success' => false,
            'error' => 'Semua field kecuali deskripsi wajib diisi'
        ]);
        exit;
    }

    try {
        // Upload gambar jika ada
        $gambar = null;
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/produk/';
            $fileName = time() . '_' . basename($_FILES['gambar']['name']);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $filePath)) {
                $gambar = $fileName;
            } else {
                throw new Exception('Gagal mengupload gambar');
            }
        }

        // Simpan data produk
        $query = "
            INSERT INTO produk (id_toko, nama_produk, harga_modal, harga_jual, stok, deskripsi, gambar)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko, $nama_produk, $harga_modal, $harga_jual, $stok, $deskripsi, $gambar]);

        echo json_encode([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?>
