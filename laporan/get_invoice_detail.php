<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php');
include('../config/helpers.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'https://posifyapi.muhsalfazi.my.id';

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

// ✅ Fungsi untuk mengisi data bulan yang kosong dengan nilai 0
function fillMissingMonths($data, $key)
{
    $allMonths = [];
    for ($i = 1; $i <= 12; $i++) {
        $month = date('Y-m', strtotime(date('Y') . "-$i-01"));
        $allMonths[$month] = ["bulan" => $month, $key => 0];
    }

    foreach ($data as $row) {
        $allMonths[$row['bulan']] = $row;
    }

    return array_values($allMonths); // Konversi kembali ke array numerik
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // ✅ Ambil total pendapatan semua toko
        $queryPendapatan = "SELECT COALESCE(SUM(biaya_komisi), 0) AS total_pendapatan FROM laporankeuangan";
        $stmtPendapatan = $pdo->query($queryPendapatan);
        $totalPendapatan = $stmtPendapatan->fetchColumn();

        // ✅ Ambil total produk yang terjual dari tabel checkout per bulan
        $queryTotalProdukTerjual = "
            SELECT COUNT(DISTINCT pk.id_produk) AS total_produk_terjual,
                   DATE_FORMAT(c.created_at, '%Y-%m') AS bulan
            FROM checkout c
            JOIN produkkeranjang pk ON c.id_keranjang = pk.id_keranjang
            WHERE c.status = 'checkout'
            GROUP BY bulan
            ORDER BY bulan DESC";
        $stmtTotalProdukTerjual = $pdo->query($queryTotalProdukTerjual);
        $totalProdukTerjual = fillMissingMonths($stmtTotalProdukTerjual->fetchAll(PDO::FETCH_ASSOC), "total_produk_terjual");

        // ✅ Ambil total pesanan (orders) tanpa format bulanan
        $queryTotalOrders = "SELECT COUNT(*) AS total_orders FROM transaksi WHERE status = 'completed'";
        $stmtTotalOrders = $pdo->query($queryTotalOrders);
        $totalOrders = $stmtTotalOrders->fetchColumn(); // Langsung ambil angka total

        // ✅ Ambil total sales per bulan
        $querySales = "
            SELECT COUNT(*) AS total_sales, 
                   DATE_FORMAT(created_at, '%Y-%m') AS bulan
            FROM transaksi
            WHERE status = 'completed'
            GROUP BY bulan
            ORDER BY bulan DESC";
        $stmtSales = $pdo->query($querySales);
        $totalSales = fillMissingMonths($stmtSales->fetchAll(PDO::FETCH_ASSOC), "total_sales");

        // ✅ Ambil total visitors (pelanggan) per bulan
        $queryVisitors = "
            SELECT COUNT(*) AS total_visitors, 
                   DATE_FORMAT(created_at, '%Y-%m') AS bulan
            FROM pelanggan
            GROUP BY bulan
            ORDER BY bulan DESC";
        $stmtVisitors = $pdo->query($queryVisitors);
        $totalVisitors = fillMissingMonths($stmtVisitors->fetchAll(PDO::FETCH_ASSOC), "total_visitors");

        // ✅ Ambil total products
        $queryProducts = "SELECT COUNT(*) AS total_products FROM produk";
        $stmtProducts = $pdo->query($queryProducts);
        $totalProducts = $stmtProducts->fetchColumn();

        // ✅ Ambil Toko Paling Laris berdasarkan total omset tertinggi
        $queryTokoLaris = "
            SELECT 
                t.id AS id_toko,
                t.nama_toko, 
                t.logo, 
                t.alamat,
                COALESCE(SUM(lk.omset_penjualan), 0) AS total_omset
            FROM toko t
            JOIN laporankeuangan lk ON t.id = lk.id_toko
            GROUP BY t.id, t.nama_toko, t.logo, t.alamat
            ORDER BY total_omset DESC
            LIMIT 3"; // Ambil 3 toko terlaris

        $stmtTokoLaris = $pdo->query($queryTokoLaris);
        $tokoLaris = $stmtTokoLaris->fetchAll(PDO::FETCH_ASSOC); // Mengambil semua hasilnya

        // Format URL gambar logo toko
        foreach ($tokoLaris as &$toko) {
            if (!empty($toko['logo'])) {
                $toko['logo_url'] = $baseURL . '/' . $toko['logo'];
            } else {
                $toko['logo_url'] = null; // Jika tidak ada logo
            }
        }
        
    // ✅ Ambil penghasilan bulanan, hanya yang memiliki tanggal valid
        $queryPendapatanBulanan = "
            SELECT DATE_FORMAT(tanggal, '%Y-%m') AS bulan, COALESCE(SUM(biaya_komisi), 0) AS total_pendapatan
            FROM laporankeuangan
            WHERE tanggal IS NOT NULL
            GROUP BY bulan
            ORDER BY bulan ASC";
        $stmtPendapatanBulanan = $pdo->query($queryPendapatanBulanan);
        $pendapatanBulanan = $stmtPendapatanBulanan->fetchAll(PDO::FETCH_ASSOC);

        // ✅ Format response JSON
        echo json_encode([
            'success' => true,
            'data' => [
                'revenue' => $totalPendapatan,
                'orders' => $totalOrders, 
                'total_products' => $totalProducts,
               'monthly_earning' => !empty($pendapatanBulanan) ? (float) $pendapatanBulanan[0]['total_pendapatan'] : 0.0,
                'total_sales' => $totalSales,
                'total_visitors' => $totalVisitors,
                'sold_products' => $totalProdukTerjual,
                'top_selling_store' => $tokoLaris
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
