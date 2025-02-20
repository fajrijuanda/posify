<?php
header("Content-Type: application/json");
include("../config/cors.php");
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php');
require_once('../vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

\Midtrans\Config::$serverKey = $_ENV['MIDTRANS_SERVER_KEY'];
\Midtrans\Config::$isProduction = false;

// ✅ Validasi Token JWT
$userData = validateToken();
if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$inputJSON = file_get_contents("php://input");
$notification = json_decode($inputJSON, true);

$id_checkout = $notification['id_checkout'] ?? null;
$transaction_status = $notification['transaction_status'] ?? null;

if (!$id_checkout) {
    echo json_encode(["success" => false, "error" => "ID Checkout tidak ditemukan dalam webhook request."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // **1️⃣ Ambil data checkout & id_keranjang berdasarkan id_checkout**
    $queryCheckout = "
        SELECT c.id_keranjang, k.id_toko, c.total_harga
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

    // **Cek apakah masih ada produk yang belum dihapus dalam keranjang**
    $queryCekKeranjang = "
      SELECT DISTINCT id_keranjang 
      FROM produkkeranjang 
      WHERE id_keranjang = ? AND deleted_at IS NULL";
    $stmtCekKeranjang = $pdo->prepare($queryCekKeranjang);
    $stmtCekKeranjang->execute([$id_keranjang]);
    $id_keranjang_valid = $stmtCekKeranjang->fetchColumn();

    if (!$id_keranjang_valid) {
        echo json_encode([
            'success' => false,
            'error' => 'Tidak ada produk aktif dalam keranjang ini'
        ]);
        exit;
    }
    $id_keranjang = $checkout['id_keranjang'];
    $id_toko = $checkout['id_toko'];
    $total_harga = floatval($checkout['total_harga']);

    if ($transaction_status === 'capture' || $transaction_status === 'settlement') {
        $status = 'completed';

        // **2️⃣ Ambil data transaksi berdasarkan id_checkout**
        $queryTransaksi = "
            SELECT t.id, p.id AS id_pembayaran
            FROM transaksi t
            JOIN pembayaran p ON t.id_pembayaran = p.id
            WHERE p.id_checkout = ?";
        $stmtTransaksi = $pdo->prepare($queryTransaksi);
        $stmtTransaksi->execute([$id_checkout]);
        $transaksi = $stmtTransaksi->fetch(PDO::FETCH_ASSOC);

        if (!$transaksi) {
            throw new Exception("Transaksi tidak ditemukan untuk id_checkout: $id_checkout");
        }

        $id_transaksi = $transaksi['id'];
        $id_pembayaran = $transaksi['id_pembayaran'];

         // ðŸ”¹ Hitung total harga modal untuk produk biasa
         $queryTotalModalProduk = "
         SELECT SUM(p.harga_modal * pk.kuantitas) AS total_harga_modal_produk
         FROM produkkeranjang pk
         JOIN produk p ON pk.id_produk = p.id
         WHERE pk.id_keranjang = ? AND pk.id_bundling IS NULL";
                 $stmtTotalModalProduk = $pdo->prepare($queryTotalModalProduk);
                 $stmtTotalModalProduk->execute([$id_keranjang]);
                 $total_harga_modal_produk = $stmtTotalModalProduk->fetchColumn() ?? 0;
         
                 // ðŸ”¹ Hitung total harga modal untuk produk bundling (langsung dari tabel bundling)
                 $queryTotalModalBundling = "
         SELECT SUM(bp.harga_modal * pk.kuantitas) AS total_harga_modal_bundling
         FROM produkkeranjang pk
         JOIN bundling_produk bp ON pk.id_bundling = bp.id
         WHERE pk.id_keranjang = ? AND pk.id_bundling IS NOT NULL";
                 $stmtTotalModalBundling = $pdo->prepare($queryTotalModalBundling);
                 $stmtTotalModalBundling->execute([$id_keranjang]);
                 $total_harga_modal_bundling = $stmtTotalModalBundling->fetchColumn() ?? 0;
         
                 // ðŸ”¹ Total harga modal dihitung ulang
                 $total_harga_modal = $total_harga_modal_produk + $total_harga_modal_bundling;
         
                 // ðŸ”¹ Hitung Keuntungan & Biaya Komisi (2.5%)
                 $keuntungan = $total_harga - $total_harga_modal;
                 $biaya_komisi = max(0, $keuntungan * 0.025);
                 $total_bersih = $total_harga - $total_harga_modal - $biaya_komisi - $total_harga_modal;

        // 🔹 Insert Laporan Keuangan
        $queryLaporanKeuangan = "
         INSERT INTO laporankeuangan (id_toko, omset_penjualan, biaya_komisi, total_bersih, tanggal)
         VALUES (?, ?, ?, ?, NOW())";
        $stmtLaporanKeuangan = $pdo->prepare($queryLaporanKeuangan);
        $stmtLaporanKeuangan->execute([$id_toko, $total_harga, $biaya_komisi, $total_bersih]);
        $id_laporan = $pdo->lastInsertId();


        // **7️⃣ Simpan id laporan ke tabel transaksilaporan**
        $queryTransaksiLaporan = "
            INSERT INTO transaksilaporan (id_transaksi, id_laporan)
            VALUES (?, ?)";
        $stmtTransaksiLaporan = $pdo->prepare($queryTransaksiLaporan);
        $stmtTransaksiLaporan->execute([$id_transaksi, $id_laporan]);

        // **8️⃣ Update status transaksi menjadi completed**
        $queryUpdateTransaksi = "UPDATE transaksi SET status = 'completed' WHERE id = ?";
        $stmtUpdateTransaksi = $pdo->prepare($queryUpdateTransaksi);
        $stmtUpdateTransaksi->execute([$id_transaksi]);

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

        // **🔹 Respon JSON**
        echo json_encode([
            "success" => true,
            "message" => "Webhook processed successfully",
            "status" => $status,
            "id_transaksi" => $id_transaksi,
            "id_laporan" => $id_laporan,
            "id_toko" => $id_toko,
            "omset_penjualan" => $total_harga,
            "biaya_komisi" => $biaya_komisi,
            "total_bersih" => $total_bersih,
            "stok_update" => $stokUpdateList,
            "total_produk_sebelum" => $totalProdukSebelum,
            "total_produk_sesudah" => $totalProdukSesudah
        ]);
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["error" => $e->getMessage()]);
}
?>