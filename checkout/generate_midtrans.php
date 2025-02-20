<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

require_once('../vendor/autoload.php'); // Pastikan Midtrans SDK sudah diinstall via Composer

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

\Midtrans\Config::$serverKey = $_ENV['MIDTRANS_SERVER_KEY'];
\Midtrans\Config::$clientKey = $_ENV['MIDTRANS_CLIENT_KEY'];
\Midtrans\Config::$isProduction = false; // Ganti dengan true jika di production
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

$userData = validateToken();

if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$user_id = $userData['user_id'];

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

    $id_checkout = $input['id_checkout'] ?? null;
    $metode_pembayaran = $input['metode_pembayaran'] ?? null;

    if (empty($id_checkout) || empty($metode_pembayaran)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID checkout dan metode pembayaran diperlukan'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Ambil data checkout
        $queryCheckout = "
            SELECT c.id_keranjang, k.id_toko, c.total_harga, c.id_pelanggan
            FROM checkout c
            JOIN keranjang k ON c.id_keranjang = k.id
            WHERE c.id = ?";
        $stmtCheckout = $pdo->prepare($queryCheckout);
        $stmtCheckout->execute([$id_checkout]);
        $checkout = $stmtCheckout->fetch(PDO::FETCH_ASSOC);

        if (!$checkout) {
            echo json_encode([
                'success' => false,
                'error' => 'Checkout tidak ditemukan'
            ]);
            exit;
        }

        $id_keranjang = $checkout['id_keranjang'];
        $id_toko = $checkout['id_toko'];
        $total_harga = floatval($checkout['total_harga']);
        $id_pelanggan = $checkout['id_pelanggan'];
        // Ambil data pelanggan dari database
        $queryPelanggan = "SELECT nama_pelanggan, nomor_telepon, email FROM pelanggan WHERE id = ?";
        $stmtPelanggan = $pdo->prepare($queryPelanggan);
        $stmtPelanggan->execute([$id_pelanggan]);
        $pelanggan = $stmtPelanggan->fetch(PDO::FETCH_ASSOC);

        if (!$pelanggan) {
            echo json_encode([
                'success' => false,
                'error' => 'Data pelanggan tidak ditemukan'
            ]);
            exit;
        }

        $nomor_order = "ORD-" . date("dmY") . "-" . strtoupper(substr(md5(time()), 0, 8));

        if ($metode_pembayaran === 'non-tunai') {
            $transaction_details = [
                'order_id' => $nomor_order,
                'gross_amount' => $total_harga,
            ];

            $customer_details = [
                'id' => $id_pelanggan,
                'first_name' => $pelanggan['nama_pelanggan'],
                'phone' => $pelanggan['nomor_telepon'],
                'email' => $pelanggan['email']
            ];

            $params = [
                'transaction_details' => $transaction_details,
                'customer_details' => $customer_details,
                'enabled_payments' => ['bca_va', 'bni_va', 'permata_va', 'echannel'],
            ];

            // Generate Snap Token dari Midtrans
            $snapToken = \Midtrans\Snap::getSnapToken($params);

            // Insert ke tabel pembayaran
            $queryPembayaran = "
                INSERT INTO pembayaran (id_checkout, metode_pembayaran, nominal, status, waktu_pembayaran)
                VALUES (?, 'midtrans', ?, 'pending', NOW())";
            $stmtPembayaran = $pdo->prepare($queryPembayaran);
            $stmtPembayaran->execute([$id_checkout, $total_harga]);
            $id_pembayaran = $pdo->lastInsertId();

            // Insert ke tabel transaksi
            $queryTransaksi = "
                INSERT INTO transaksi (id_pembayaran, nomor_order, waktu_transaksi, status,created_at)
                VALUES (?, ?, NOW(), 'pending',NOW())";
            $stmtTransaksi = $pdo->prepare($queryTransaksi);
            $stmtTransaksi->execute([$id_pembayaran, $nomor_order]);
            $id_transaksi = $pdo->lastInsertId();

            // Simpan log transaksi di tabel midtrans_log
            $queryMidtransLog = "
                INSERT INTO midtrans_log (id_pembayaran, transaction_id, amount, status, raw_response)
                VALUES (?, ?, ?, ?, ?)";
            $stmtMidtransLog = $pdo->prepare($queryMidtransLog);
            $stmtMidtransLog->execute([
                $id_pembayaran,
                $nomor_order,
                $total_harga,
                'pending',
                json_encode(['snap_token' => $snapToken])
            ]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Transaksi Midtrans berhasil dibuat',
                'snap_token' => $snapToken,
                'id_transaksi' => $id_transaksi,
                'nomor_order' => $nomor_order,
                'metode_pembayaran' => $metode_pembayaran,
                'total_harga' => $total_harga,
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Metode pembayaran tidak valid'
            ]);
            exit;
        }

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