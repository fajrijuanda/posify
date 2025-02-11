<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Load .env jika menggunakan PHP dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

// Validasi token untuk otentikasi
$userData = validateToken(); // Dapatkan data user dari token

if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$user_id = $userData['user_id'];
$id_toko = $userData['id_toko'];

error_log("DEBUG: User ID -> " . $user_id);
error_log("DEBUG: ID Toko -> " . $id_toko);

if (!$id_toko) {
    echo json_encode([
        'success' => false,
        'error' => 'Toko tidak ditemukan untuk user ini'
    ]);
    exit;
}

// METHOD POST: Simpan data checkout sementara
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);

    if (!is_array($input)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON format'
        ]);
        exit;
    }

    $id_keranjang = $input['id_keranjang'] ?? null;
    $id_pelanggan = $input['id_pelanggan'] ?? null;
    $subtotal = $input['subtotal'] ?? null;
    $total_harga = $input['total_harga'] ?? null;
    $metode_pengiriman = $input['metode_pengiriman'] ?? 'Bungkus';
    $id_voucher = $input['id_voucher'] ?? null;

    error_log("DEBUG: ID Keranjang -> " . $id_keranjang);
    error_log("DEBUG: ID Pelanggan -> " . $id_pelanggan);

    if (empty($id_keranjang) || empty($subtotal) || empty($total_harga) || empty($id_pelanggan)) {
        echo json_encode([
            'success' => false,
            'error' => 'Data keranjang, subtotal, total harga, dan ID pelanggan diperlukan'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Periksa apakah `id_keranjang` valid
        $queryCheckKeranjang = "SELECT id FROM keranjang WHERE id = ? AND id_toko = ?";
        $stmtCheckKeranjang = $pdo->prepare($queryCheckKeranjang);
        $stmtCheckKeranjang->execute([$id_keranjang, $id_toko]);
        $resultCheckKeranjang = $stmtCheckKeranjang->fetch(PDO::FETCH_ASSOC);

        if (!$resultCheckKeranjang) {
            echo json_encode([
                'success' => false,
                'error' => 'Keranjang tidak ditemukan atau bukan milik toko ini'
            ]);
            exit;
        }

        // Periksa apakah `id_pelanggan` valid
        $queryCheckPelanggan = "SELECT id FROM pelanggan WHERE id = ? AND id_toko = ?";
        $stmtCheckPelanggan = $pdo->prepare($queryCheckPelanggan);
        $stmtCheckPelanggan->execute([$id_pelanggan, $id_toko]);
        $resultCheckPelanggan = $stmtCheckPelanggan->fetch(PDO::FETCH_ASSOC);

        if (!$resultCheckPelanggan) {
            echo json_encode([
                'success' => false,
                'error' => 'Pelanggan tidak ditemukan atau bukan milik toko ini'
            ]);
            exit;
        }

        // Periksa apakah data checkout sementara sudah ada
        $queryCheck = "SELECT id FROM checkout WHERE id_keranjang = ? AND status = 'sementara'";
        $stmtCheck = $pdo->prepare($queryCheck);
        $stmtCheck->execute([$id_keranjang]);
        $checkout = $stmtCheck->fetch(PDO::FETCH_ASSOC);

       if ($checkout) {
            // Update data checkout jika sudah ada
            $queryUpdate = "
                UPDATE checkout 
                SET subtotal = ?, total_harga = ?, metode_pengiriman = ?, id_voucher = ?, id_pelanggan = ?
                WHERE id = ?";
            $stmtUpdate = $pdo ->prepare($queryUpdate);
            $stmtUpdate->execute([$subtotal, $total_harga, $metode_pengiriman, $id_voucher, $id_pelanggan, $checkout['id']]);

            $id_checkout = $checkout['id']; // Gunakan ID checkout yang sudah ada
            $message = "Data checkout diperbarui";
        } else {
            // Insert data baru jika belum ada
            $queryInsert = "
                INSERT INTO checkout (id_keranjang, id_pelanggan, subtotal, total_harga, metode_pengiriman, id_voucher, status,created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'sementara',NOW())";
            $stmtInsert = $pdo->prepare($queryInsert);
            $stmtInsert->execute([$id_keranjang, $id_pelanggan, $subtotal, $total_harga, $metode_pengiriman, $id_voucher]);

            $id_checkout = $pdo->lastInsertId(); // Ambil ID checkout yang baru dibuat
            $message = "Data checkout disimpan";
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => [
                'id_checkout' => $id_checkout, // Tambahkan ID checkout ke response
                'id_keranjang' => $id_keranjang,
                'id_pelanggan' => $id_pelanggan,
                'subtotal' => $subtotal,
                'total_harga' => $total_harga,
                'metode_pengiriman' => $metode_pengiriman,
                'id_voucher' => $id_voucher
            ]
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
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
