<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

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
    $nominal_tunai = $input['nominal_tunai'] ?? null;

    if (empty($id_checkout) || empty($metode_pembayaran)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID checkout dan metode pembayaran diperlukan'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

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

        $nomor_order = "ORD-" . date("dmY") . "-" . strtoupper(substr(md5(time()), 0, 8));

        if ($metode_pembayaran === 'tunai') {
            $nominal_tunai = floatval($nominal_tunai);

            if ($nominal_tunai < $total_harga) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Nominal tunai tidak boleh kurang dari total harga'
                ]);
                exit;
            }

            $uang_kembalian = $nominal_tunai - $total_harga;

        } elseif ($metode_pembayaran === 'debit') {
            $nominal_tunai = $total_harga;
            $uang_kembalian = 0; // Karena pembayaran harus pas

        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Metode pembayaran tidak valid'
            ]);
            exit;
        }

        // **🔹 Insert Pembayaran**
        $queryPembayaran = "
            INSERT INTO pembayaran (id_checkout, metode_pembayaran, nominal, status, waktu_pembayaran)
            VALUES (?, ?, ?, 'completed', NOW())";
        $stmtPembayaran = $pdo->prepare($queryPembayaran);
        $stmtPembayaran->execute([$id_checkout, $metode_pembayaran, $total_harga]);
        $id_pembayaran = $pdo->lastInsertId();

        // **🔹 Insert Transaksi**
        $queryTransaksi = "
            INSERT INTO transaksi (id_pembayaran, nomor_order, waktu_transaksi, status,created_at)
            VALUES (?, ?, NOW(), 'completed',NOW())";
        $stmtTransaksi = $pdo->prepare($queryTransaksi);
        $stmtTransaksi->execute([$id_pembayaran, $nomor_order]);
        $id_transaksi = $pdo->lastInsertId();

        // **🔹 Laporan Keuangan**
        $biaya_komisi = $total_harga * 0.025;
        $total_bersih = $total_harga - $biaya_komisi;

        $queryLaporanKeuangan = "
            INSERT INTO laporankeuangan (id_toko, omset_penjualan, biaya_komisi, total_bersih, tanggal)
            VALUES (?, ?, ?, ?, NOW())";
        $stmtLaporanKeuangan = $pdo->prepare($queryLaporanKeuangan);
        $stmtLaporanKeuangan->execute([$id_toko, $total_harga, $biaya_komisi, $total_bersih]);
        $id_laporan = $pdo->lastInsertId();

        // **🔹 Insert Transaksi Laporan**
        $queryTransaksiLaporan = "
            INSERT INTO transaksilaporan (id_transaksi, id_laporan)
            VALUES (?, ?)";
        $stmtTransaksiLaporan = $pdo->prepare($queryTransaksiLaporan);
        $stmtTransaksiLaporan->execute([$id_transaksi, $id_laporan]);

        // **🔹 Kurangi stok di produk & produkkeranjang**
        $queryProdukKeranjang = "
            SELECT pk.id, pk.id_produk, pk.kuantitas, p.stok
            FROM produkkeranjang pk
            JOIN produk p ON pk.id_produk = p.id
            WHERE pk.id_keranjang = ?";
        $stmtProdukKeranjang = $pdo->prepare($queryProdukKeranjang);
        $stmtProdukKeranjang->execute([$id_keranjang]);
        $produkList = $stmtProdukKeranjang->fetchAll(PDO::FETCH_ASSOC);

        // Update stok dan produk terjual
        foreach ($produkList as $produk) {
            $id_produk = $produk['id_produk'];
            $kuantitas = $produk['kuantitas'];
            $stok = $produk['stok'];

            // Pastikan stok mencukupi
            if ($stok >= $kuantitas) {
                // Kurangi stok produk
                $queryUpdateStok = "UPDATE produk SET stok = stok - ? WHERE id = ?";
                $stmtUpdateStok = $pdo->prepare($queryUpdateStok);
                $stmtUpdateStok->execute([$kuantitas, $id_produk]);

                // Update produk terjual di produkkeranjang
                $queryUpdateTerjual = "UPDATE produkkeranjang SET produk_terjual = produk_terjual + ? WHERE id = ?";
                $stmtUpdateTerjual = $pdo->prepare($queryUpdateTerjual);
                $stmtUpdateTerjual->execute([$kuantitas, $produk['id']]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Stok tidak mencukupi untuk produk ID ' . $id_produk
                ]);
                $pdo->rollBack();
                exit;
            }
        }
        $stokUpdateList = [];
        $totalProdukSebelum = 0;


       // **🔹 Update total produk di keranjang**
$queryUpdateKeranjang = "UPDATE keranjang SET total_produk = total_produk - ? WHERE id = ?";
$stmtUpdateKeranjang = $pdo->prepare($queryUpdateKeranjang);
$stmtUpdateKeranjang->execute([$totalProdukSebelum, $id_keranjang]);

// **Hapus keranjang jika total_produk akhirnya 0**
$queryGetTotalProduk = "SELECT total_produk FROM keranjang WHERE id = ?";
$stmtGetTotalProduk = $pdo->prepare($queryGetTotalProduk);
$stmtGetTotalProduk->execute([$id_keranjang]);
$totalProdukSesudah = $stmtGetTotalProduk->fetchColumn();

if ($totalProdukSesudah == 0) {
    // **Periksa apakah ada referensi di checkout terlebih dahulu**
    $queryCheckCheckout = "SELECT COUNT(*) FROM checkout WHERE id_keranjang = ?";
    $stmtCheckCheckout = $pdo->prepare($queryCheckCheckout);
    $stmtCheckCheckout->execute([$id_keranjang]);
    $checkoutCount = $stmtCheckCheckout->fetchColumn();

    // Jika tidak ada referensi di checkout, baru bisa hapus keranjang
    if ($checkoutCount == 0) {
        $queryDeleteKeranjang = "DELETE FROM keranjang WHERE id = ?";
        $stmtDeleteKeranjang = $pdo->prepare($queryDeleteKeranjang);
        $stmtDeleteKeranjang->execute([$id_keranjang]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Tidak bisa menghapus keranjang yang masih memiliki referensi di checkout.'
        ]);
        $pdo->rollBack();
        exit;
    }
}


        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Pembayaran berhasil dan stok terupdate',
            'id_transaksi' => $id_transaksi,
            'nomor_order' => $nomor_order,
            'id_laporan' => $id_laporan,
            'id_toko' => $id_toko,
            'omset_penjualan' => $total_harga,
            'biaya_komisi' => $biaya_komisi,
            'total_bersih' => $total_bersih,
            'nominal_tunai' => $nominal_tunai,
            'uang_kembalian' => $uang_kembalian
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
