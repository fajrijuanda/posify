<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

$userData = validateToken();

if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$id_toko = $userData['id_toko']; // Ambil id_toko dari token JWT

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // **Menampilkan Semua Data Laporan Keuangan**
        $query = "
            SELECT 
                lk.id AS id_laporan,
                lk.id_toko,
                lk.omset_penjualan,
                lk.biaya_komisi,
                lk.total_bersih,
                DATE_FORMAT(t.waktu_transaksi, '%d-%m-%Y %H:%i:%s') AS waktu_transaksi
            FROM laporankeuangan lk
            JOIN transaksilaporan tl ON lk.id = tl.id_laporan
            JOIN transaksi t ON tl.id_transaksi = t.id
            WHERE lk.id_toko = ?
            ORDER BY t.waktu_transaksi DESC
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_toko]);
        $laporanKeuangan = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Data laporan keuangan berhasil diambil',
            'data' => $laporanKeuangan
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);

    $waktuTransaksi = $input['waktu_transaksi'] ?? null; // Ambil input waktu_transaksi

    try {
        // **1️⃣ Buat Query Dasar**
        $query = "
            SELECT 
                lk.id AS id_laporan,
                lk.id_toko,
                lk.omset_penjualan,
                lk.biaya_komisi,
                lk.total_bersih,
                DATE_FORMAT(t.waktu_transaksi, '%d-%m-%Y %H:%i:%s') AS waktu_transaksi
            FROM laporankeuangan lk
            JOIN transaksilaporan tl ON lk.id = tl.id_laporan
            JOIN transaksi t ON tl.id_transaksi = t.id
            WHERE lk.id_toko = ?
        ";

        // **2️⃣ Tambahkan Filter Waktu Fleksibel**
        $params = [$id_toko];

        if ($waktuTransaksi) {
            $dateParts = explode('-', $waktuTransaksi);

            if (count($dateParts) == 3) {
                // **Format Lengkap (dd-mm-yyyy)**
                $formattedDate = DateTime::createFromFormat('d-m-Y', $waktuTransaksi);
                if ($formattedDate) {
                    $query .= " AND DATE(t.waktu_transaksi) = ?";
                    array_push($params, $formattedDate->format('Y-m-d'));
                }
            } elseif (count($dateParts) == 2) {
                // **Format Bulan-Tahun (mm-yyyy)**
                $formattedDate = DateTime::createFromFormat('m-Y', $waktuTransaksi);
                if ($formattedDate) {
                    $query .= " AND MONTH(t.waktu_transaksi) = ? AND YEAR(t.waktu_transaksi) = ?";
                    array_push($params, $formattedDate->format('m'), $formattedDate->format('Y'));
                }
            } elseif (count($dateParts) == 1) {
                if (strlen($dateParts[0]) == 4) {
                    // **Tahun (yyyy)**
                    $query .= " AND YEAR(t.waktu_transaksi) = ?";
                    array_push($params, $dateParts[0]);
                } elseif (strlen($dateParts[0]) == 2) {
                    // **Hari (dd) dengan bulan & tahun saat ini**
                    $currentMonth = date('m');
                    $currentYear = date('Y');
                    $query .= " AND DAY(t.waktu_transaksi) = ? AND MONTH(t.waktu_transaksi) = ? AND YEAR(t.waktu_transaksi) = ?";
                    array_push($params, $dateParts[0], $currentMonth, $currentYear);
                }
            }
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $laporanKeuangan = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Data laporan keuangan berhasil diambil',
            'data' => $laporanKeuangan
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
