<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$userData = validateToken(); // Dapatkan data user dari token

if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$user_id = $userData['user_id'];
$id_toko = $userData['id_toko'];

// Debugging: Pastikan id_toko ada sebelum insert
error_log("User ID: " . $user_id);
error_log("ID Toko: " . $id_toko);

if (!$id_toko) {
    echo json_encode([
        'success' => false,
        'error' => 'Toko tidak ditemukan untuk user ini'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_produk = $_POST['nama_produk'] ?? null;
    $harga_modal = $_POST['harga_modal'] ?? null;
    $harga_jual = $_POST['harga_jual'] ?? null;
    $stok = $_POST['stok'] ?? null;
    $deskripsi = $_POST['deskripsi'] ?? null;

    if (empty($nama_produk) || empty($harga_modal) || empty($harga_jual) || empty($stok)) {
        echo json_encode([
            'success' => false,
            'error' => 'Semua field kecuali deskripsi wajib diisi'
        ]);
        exit;
    }

    try {
        $uploadDir = __DIR__ . '/../uploads/produk/';

        // Perbaiki Penamaan File
        $fileInfo = pathinfo($_FILES['gambar']['name']);
        $extension = strtolower($fileInfo['extension']); // Ambil ekstensi file
        $fileName = uniqid() . '.' . $extension; // Gunakan nama unik tanpa prefix tambahan

        $targetFilePath = $uploadDir . $fileName;

        // Periksa folder
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $targetFilePath)) {
            $gambar = 'uploads/produk/' . $fileName;
        } else {
            throw new Exception('Gagal mengupload gambar. Path tujuan: ' . $targetFilePath);
        }

        // Simpan data produk ke database dengan id unik
        $query = "
            INSERT INTO produk (id_toko, nama_produk, harga_modal, harga_jual, stok, deskripsi, gambar)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko, $nama_produk, $harga_modal, $harga_jual, $stok, $deskripsi, $gambar]);

        $id_produk = $pdo->lastInsertId(); // Ambil ID produk yang baru saja dibuat

        echo json_encode([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'id_produk' => $id_produk,
            'image_url' => $_ENV['APP_URL'] . '/' . $gambar // Gunakan APP_URL dari .env jika tersedia
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
