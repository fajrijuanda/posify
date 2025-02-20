<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

// 🔒 Ambil data dari token JWT
$userData = validateToken();

if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$id_toko = $userData['id_toko']; // Ambil ID toko langsung dari token JWT

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data input JSON
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);

    // Pastikan id_transaksi ada dalam input JSON
    $id_transaksi = $input['id_transaksi'] ?? null;

    if (empty($id_transaksi)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Transaksi diperlukan dalam JSON'
        ]);
        exit;
    }

    try {
        // 🔎 **Ambil detail transaksi dan id_checkout**
        $queryTransaksi = "
            SELECT 
                t.nomor_order,
                t.waktu_transaksi,
                c.id AS id_checkout,
                c.subtotal,
                c.total_harga,
                c.metode_pengiriman
            FROM transaksi t
            JOIN pembayaran p ON t.id_pembayaran = p.id
            JOIN checkout c ON p.id_checkout = c.id
            JOIN keranjang k ON c.id_keranjang = k.id
            WHERE t.id = ? AND k.id_toko = ?";
        $stmtTransaksi = $pdo->prepare($queryTransaksi);
        $stmtTransaksi->execute([$id_transaksi, $id_toko]);
        $transaksi = $stmtTransaksi->fetch(PDO::FETCH_ASSOC);

        // 🛑 **Pastikan transaksi ditemukan untuk toko dari token**
        if (!$transaksi) {
            echo json_encode([
                'success' => false,
                'error' => 'Transaksi tidak ditemukan atau tidak sesuai dengan toko ini'
            ]);
            exit;
        }

        $id_checkout = $transaksi['id_checkout'];

        // 📦 **Ambil produk biasa yang ada dalam transaksi**
      $queryProduk = "
    SELECT 
        p.nama_produk,
        p.harga_modal,
        p.harga_jual,
        p.deskripsi,
        pk.kuantitas,
        p.gambar,
        CONCAT(?, '/', p.gambar) AS gambar_url,
        (p.harga_modal * pk.kuantitas) AS total_harga_modal
    FROM produk p
    JOIN produkkeranjang pk ON p.id = pk.id_produk
    WHERE pk.id_keranjang = (SELECT id_keranjang FROM checkout WHERE id = ?) 
      AND pk.id_bundling IS NULL";
$stmtProduk = $pdo->prepare($queryProduk);
$stmtProduk->execute([$baseURL, $id_checkout]);
$produk = $stmtProduk->fetchAll(PDO::FETCH_ASSOC);

// **Hitung Total Harga Modal Produk Biasa**
$total_harga_modal_produk = array_sum(array_column($produk, 'total_harga_modal'));

// 🎁 **Ambil satu produk dengan harga jual tertinggi dalam bundling**
$queryBundling = "
    SELECT 
        b.id AS id_bundling,
        b.nama_bundling,
        MAX(p.harga_jual) AS harga_jual,
        (SELECT p.nama_produk FROM produk p 
         JOIN bundling_produk bp ON p.id = bp.id_produk 
         WHERE bp.id_bundling = b.id 
         ORDER BY p.harga_jual DESC LIMIT 1) AS nama_produk_tertinggi,
        (SELECT p.gambar FROM produk p 
         JOIN bundling_produk bp ON p.id = bp.id_produk 
         WHERE bp.id_bundling = b.id 
         ORDER BY p.harga_jual DESC LIMIT 1) AS gambar_tertinggi,
        SUM(p.harga_modal * pk.kuantitas) AS total_harga_modal_bundling
    FROM bundling b
    JOIN bundling_produk bp ON b.id = bp.id_bundling
    JOIN produk p ON bp.id_produk = p.id
    JOIN produkkeranjang pk ON pk.id_bundling = b.id
    WHERE pk.id_keranjang = (SELECT id_keranjang FROM checkout WHERE id = ?)
    GROUP BY b.id, b.nama_bundling
    LIMIT 1";
$stmtBundling = $pdo->prepare($queryBundling);
$stmtBundling->execute([$id_checkout]);
$bundling = $stmtBundling->fetch(PDO::FETCH_ASSOC);

// Jika tidak ada produk bundling, total harga modalnya 0
$total_harga_modal_bundling = $bundling['total_harga_modal_bundling'] ?? 0;

// **Total Harga Modal Keseluruhan**
$total_harga_modal = $total_harga_modal_produk + $total_harga_modal_bundling;


        if ($bundling) {
            $bundling['harga_jual'] = floatval($bundling['harga_jual']);
            $bundling['gambar_url'] = !empty($bundling['gambar_tertinggi']) ? rtrim($baseURL, '/') . '/' . ltrim($bundling['gambar_tertinggi'], '/') : null;
        }

        // 📊 **Ambil informasi laporan keuangan berdasarkan id_toko dari JWT**
        $queryLaporan = "
            SELECT 
                l.omset_penjualan,
                l.biaya_komisi,
                l.total_bersih
            FROM laporankeuangan l
            JOIN transaksilaporan tl ON l.id = tl.id_laporan
            WHERE tl.id_transaksi = ? AND l.id_toko = ?";
        $stmtLaporan = $pdo->prepare($queryLaporan);
        $stmtLaporan->execute([$id_transaksi, $id_toko]);
        $laporan = $stmtLaporan->fetch(PDO::FETCH_ASSOC);

        // **Pastikan laporan tidak kosong**
        if (!$laporan) {
            $laporan = [
                'omset_penjualan' => 0,
                'biaya_komisi' => 0,
                'total_bersih' => 0
            ];
        }

        // 🔥 **Hasilkan respon JSON**
        echo json_encode([
            'success' => true,
            'data' => [
                'transaksi' => $transaksi,
                'produk' => $produk,
                 'harga_modal' => $total_harga_modal,
                'produk_bundling' => $bundling ?: null, // Jika tidak ada bundling, kembalikan null
                'laporan' => $laporan
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
