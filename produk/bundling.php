<?php 
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php');

$userData = validateToken();
if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid']);
    exit;
}

$id_toko = $userData['id_toko']; // Ambil ID toko dari token JWT

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input JSON
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);

    if (!is_array($input)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON format'
        ]);
        exit;
    }

    // Ambil data dari input JSON
    $nama_bundling = $input['nama_bundling'] ?? null;
    $produk_list = $input['produk_list'] ?? null; // Array ID produk
    // $jumlah_list = $input['jumlah_list'] ?? null; // Array jumlah produk

    // Validasi input
    if (empty($produk_list) ) {
        echo json_encode(['success' => false, 'error' => 'Nama bundling, produk_list, dan jumlah_list wajib diisi dengan format yang benar']);
        exit;
    }

    try {

        // Simpan bundling ke database tanpa harga_jual dan harga_modal
        $query = "INSERT INTO bundling (id_toko, nama_bundling) VALUES (?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko, $nama_bundling]);

        // Ambil ID bundling yang baru dibuat
        $id_bundling = $pdo->lastInsertId();

        // Ambil stok dari produk yang dipilih
        $placeholders = implode(',', array_fill(0, count($produk_list), '?'));
        $query = "SELECT id, stok, harga_jual, harga_modal FROM produk WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($query);
        $stmt->execute($produk_list);
        $produk_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buat array stok dan harga produk
        $stok_produk = [];
        $harga_jual_tertinggi = 0;
        $harga_modal_terendah = PHP_INT_MAX;

        foreach ($produk_data as $data) {
            $stok_produk[$data['id']] = $data['stok'];
            if ($data['harga_jual'] > $harga_jual_tertinggi) {
                $harga_jual_tertinggi = $data['harga_jual'];
            }
            if ($data['harga_modal'] < $harga_modal_terendah) {
                $harga_modal_terendah = $data['harga_modal'];
            }
        }

        // Cek stok sebelum memasukkan ke bundling
        foreach ($produk_list as $index => $id_produk) {
            $jumlah = $jumlah_list[$index];

            if (!isset($stok_produk[$id_produk]) || $stok_produk[$id_produk] < $jumlah) {
                echo json_encode(['success' => false, 'error' => "Produk dengan ID $id_produk stok tidak mencukupi"]);
                exit;
            }
        }

        // Simpan produk ke dalam bundling dan kurangi stoknya
        foreach ($produk_list as $index => $id_produk) {
            // Ambil harga jual & modal dari produk
            $query = "SELECT harga_jual, harga_modal FROM produk WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$id_produk]);
            $harga_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $harga_jual = $harga_data['harga_jual'] ?? 0;
            $harga_modal = $harga_data['harga_modal'] ?? 0;

            // Simpan ke tabel `bundling_produk`
            $query = "INSERT INTO bundling_produk (id_bundling, id_produk, harga_jual, harga_modal) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$id_bundling, $id_produk, $harga_jual, $harga_modal]);
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Bundling berhasil dibuat', 
            'id_bundling' => $id_bundling, 
        ]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
