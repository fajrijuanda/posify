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

        // **3️⃣ Hitung biaya komisi dan total bersih**
        $biaya_komisi = $total_harga * 0.025;

        // **4️⃣ Ambil harga modal dari semua produk yang ada dalam transaksi ini**
        $queryHargaModal = "
            SELECT SUM(p.harga_modal) AS total_modal
            FROM produkkeranjang pk
            JOIN produk p ON pk.id_produk = p.id
            WHERE pk.id_keranjang = ?";
        $stmtHargaModal = $pdo->prepare($queryHargaModal);
        $stmtHargaModal->execute([$id_keranjang]);
        $harga_modal = floatval($stmtHargaModal->fetchColumn());

        // **5️⃣ Hitung total bersih**
        $total_bersih = $total_harga - $biaya_komisi - $harga_modal;

        // **6️⃣ Simpan data laporan keuangan**
        $queryLaporanKeuangan = "
            INSERT INTO laporankeuangan (id_toko, omset_penjualan, biaya_komisi, total_bersih)
            VALUES (?, ?, ?, ?)";
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

        $stokUpdateList = [];
        $totalProdukSebelum = 0;

        // foreach ($produkList as $produk) {
        //     $id_produk = $produk['id_produk'];
        //     $id_produkkeranjang = $produk['id'];
        //     $kuantitas = $produk['kuantitas'];
        //     $stok_sebelumnya = $produk['stok'];
        //     $stok_setelah = max(0, $stok_sebelumnya - $kuantitas);

        //     // Update stok di tabel produk
        //     $queryUpdateStok = "UPDATE produk SET stok = ? WHERE id = ?";
        //     $stmtUpdateStok = $pdo->prepare($queryUpdateStok);
        //     $stmtUpdateStok->execute([$stok_setelah, $id_produk]);

        //     // **2️⃣ Update deleted_at di tabel produkkeranjang berdasarkan id_keranjang**
        //     $queryUpdateProdukKeranjang = "UPDATE produkkeranjang SET deleted_at = NOW() WHERE id_keranjang = ?";
        //     $stmtUpdateProdukKeranjang = $pdo->prepare($queryUpdateProdukKeranjang);
        //     $stmtUpdateProdukKeranjang->execute([$id_keranjang]);

        //     // Hapus dari produkkeranjang jika kuantitas 0
        //     if ($kuantitas > 0) {
        //         $queryDeleteProdukKeranjang = "UPDATE produkkeranjang SET kuantitas = 0, deleted_at = NOW() WHERE id = ?";
        //         $stmtDeleteProdukKeranjang = $pdo->prepare($queryDeleteProdukKeranjang);
        //         $stmtDeleteProdukKeranjang->execute([$id_produkkeranjang]);
        //     }

        //     $stokUpdateList[] = [
        //         'id_produk' => $id_produk,
        //         'stok_sebelumnya' => $stok_sebelumnya,
        //         'dikurangi' => $kuantitas,
        //         'stok_sekarang' => $stok_setelah
        //     ];

        //     $totalProdukSebelum += $kuantitas;
        // }

        // **🔹 Update total produk di keranjang**
        $queryUpdateKeranjang = "UPDATE keranjang SET total_produk = total_produk - ? WHERE id = ?";
        $stmtUpdateKeranjang = $pdo->prepare($queryUpdateKeranjang);
        $stmtUpdateKeranjang->execute([$totalProdukSebelum, $id_keranjang]);

        // **🔹 Dapatkan total produk setelah transaksi**
        $queryGetTotalProduk = "SELECT total_produk FROM keranjang WHERE id = ?";
        $stmtGetTotalProduk = $pdo->prepare($queryGetTotalProduk);
        $stmtGetTotalProduk->execute([$id_keranjang]);
        $totalProdukSesudah = $stmtGetTotalProduk->fetchColumn();

        // Hapus keranjang jika total_produk akhirnya 0
        if ($totalProdukSesudah == 0) {
            $queryDeleteKeranjang = "DELETE FROM keranjang WHERE id = ?";
            $stmtDeleteKeranjang = $pdo->prepare($queryDeleteKeranjang);
            $stmtDeleteKeranjang->execute([$id_keranjang]);
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