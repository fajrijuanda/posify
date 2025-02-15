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

    // Debugging: Cek JSON yang diterima
    error_log("JSON Input: " . json_encode($input));

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
    $jumlah_list = $input['jumlah_list'] ?? null; // Array jumlah produk

    // Validasi input
    if (empty($nama_bundling) || empty($produk_list) || empty($jumlah_list) || count($produk_list) !== count($jumlah_list)) {
        echo json_encode(['success' => false, 'error' => 'Nama bundling, produk_list, dan jumlah_list wajib diisi dengan format yang benar']);
        exit;
    }

    try {
        // Hitung total jumlah produk dalam bundling
        $total_jumlah = array_sum($jumlah_list);

        // Simpan bundling ke database
        $query = "INSERT INTO bundling (id_toko, nama_bundling, total_jumlah) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko, $nama_bundling, $total_jumlah]);

        // Ambil ID bundling yang baru dibuat
        $id_bundling = $pdo->lastInsertId();

        // Ambil stok dari produk yang dipilih
        $placeholders = implode(',', array_fill(0, count($produk_list), '?'));
        $query = "SELECT id, stok FROM produk WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($query);
        $stmt->execute($produk_list);
        $produk_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buat array stok produk
        $stok_produk = [];
        foreach ($produk_data as $data) {
            $stok_produk[$data['id']] = $data['stok'];
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
            $jumlah = $jumlah_list[$index];

            // Simpan ke tabel `bundling_produk`
            $query = "INSERT INTO bundling_produk (id_bundling, id_produk, jumlah) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$id_bundling, $id_produk, $jumlah]);

            // Kurangi stok produk
            $query = "UPDATE produk SET stok = stok - ? WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$jumlah, $id_produk]);
        }

        echo json_encode(['success' => true, 'message' => 'Bundling berhasil dibuat', 'id_bundling' => $id_bundling, 'total_jumlah' => $total_jumlah]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
