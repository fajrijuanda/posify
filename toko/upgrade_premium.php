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

// ✅ Validasi token JWT
$authResult = validateToken(); 
if (!is_array($authResult) || !isset($authResult['user_id'], $authResult['id_toko'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

// Ambil user_id & id_toko dari token JWT
$user_id = $authResult['user_id'];
$id_toko = $authResult['id_toko'];

if (!$user_id || !$id_toko) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token tidak valid atau sesi berakhir'
    ]);
    exit;
}

// ✅ Pastikan metode HTTP adalah POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data input dalam format JSON
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);

    // Validasi input JSON
    if (!isset($input['id_paket'])) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Paket diperlukan dalam format JSON'
        ]);
        exit;
    }

    // Pastikan id_paket bisa berupa satu ID atau array ID
    $id_paket = is_array($input['id_paket']) ? $input['id_paket'] : [$input['id_paket']];

    try {
        // Mulai transaksi
        $pdo->beginTransaction();

        foreach ($id_paket as $paket) {
            // Periksa apakah toko sudah memiliki langganan aktif untuk paket ini
            $queryCheck = "SELECT id FROM langganantoko WHERE id_toko = ? AND id_langganan = ? AND tanggal_berakhir > CURDATE()";
            $stmtCheck = $pdo->prepare($queryCheck);
            $stmtCheck->execute([$id_toko, $paket]);

            if ($stmtCheck->rowCount() > 0) {
                echo json_encode([
                    'success' => false,
                    'error' => "Toko sudah memiliki langganan aktif untuk paket ID $paket"
                ]);
                $pdo->rollBack();
                exit;
            }

            // Ambil informasi paket berdasarkan ID paket
            $queryPaket = "SELECT nama, durasi, harga FROM paketlangganan WHERE id = ? LIMIT 1";
            $stmtPaket = $pdo->prepare($queryPaket);
            $stmtPaket->execute([$paket]);

            if ($stmtPaket->rowCount() === 0) {
                echo json_encode([
                    'success' => false,
                    'error' => "Paket langganan ID $paket tidak ditemukan"
                ]);
                $pdo->rollBack();
                exit;
            }

            $paketData = $stmtPaket->fetch(PDO::FETCH_ASSOC);
            $nama_paket = $paketData['nama'];
            $durasi = (int)$paketData['durasi']; // Pastikan durasi adalah integer

            // Validasi durasi sebelum digunakan
            if ($durasi <= 0) {
                echo json_encode([
                    'success' => false,
                    'error' => "Durasi paket ID $paket tidak valid"
                ]);
                $pdo->rollBack();
                exit;
            }

            // Hitung tanggal mulai dan tanggal berakhir
            $tanggal_mulai = date('Y-m-d');

            // Gunakan DateTime untuk perhitungan tanggal yang lebih akurat
            $datetime = new DateTime($tanggal_mulai);
            $datetime->modify("+{$durasi} months");
            $tanggal_berakhir = $datetime->format('Y-m-d');

            // Masukkan langganan ke database
            $queryInsert = "
                INSERT INTO langganantoko (id_toko, id_langganan, tanggal_mulai, tanggal_berakhir)
                VALUES (?, ?, ?, ?)";
            $stmtInsert = $pdo->prepare($queryInsert);
            $stmtInsert->execute([$id_toko, $paket, $tanggal_mulai, $tanggal_berakhir]);

            $responseData[] = [
                'id_toko' => $id_toko,
                'id_paket' => $paket,
                'paket_nama' => $nama_paket,
                'tanggal_mulai' => $tanggal_mulai,
                'tanggal_berakhir' => $tanggal_berakhir,
            ];
        }

        // Commit transaksi setelah semua paket berhasil dimasukkan
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Upgrade berhasil',
            'data' => $responseData
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
