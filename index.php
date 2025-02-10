<?php
header('Content-Type: application/json');
// Autoloader Composer
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$secretKey = $_ENV['JWT_SECRET'];

// Menambahkan Middleware untuk validasi token
// include('../middlewares/auth_middleware.php'); 
// Include koneksi database dan konfigurasi
include("./config/dbconnection.php");
include("./config/helpers.php");
// Ambil route dari URL
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Parsing URL
$parsed_url = parse_url($request);
$path = $parsed_url['path'];



// Routing berdasarkan struktur folder
switch (true) {
    // Auth
    case preg_match('/^\/auth\/login$/', $path):
        include('./auth/login.php');
        break;
    case preg_match('/^\/auth\/register$/', $path):
        include('./auth/register.php');
        break;
    case preg_match('/^\/auth\/logout$/', $path):
        include('./auth/logout.php');
        break;
    case preg_match('/^\/auth\/reset_password$/', $path):
        include('./auth/reset_password.php');
        break;
    case preg_match('/^\/auth\/request_reset$/', $path):
        include('./auth/request_reset.php');
        break;

    // Checkout
    case preg_match('/^\/checkout\/generate_midtrans$/', $path):
        include('./checkout/generate_midtrans.php');
        break;
    case preg_match('/^\/checkout\/midtrans_webhook$/', $path):
        include('./checkout/midtrans_webhook.php');
        break;
    case preg_match('/^\/checkout\/get_checkout$/', $path):
        include('./checkout/get_checkout.php');
        break;
    case preg_match('/^\/checkout\/process_checkout$/', $path):
        include('./checkout/process_checkout.php');
        break;
    case preg_match('/^\/checkout\/update_checkout$/', $path):
        include('./checkout/update_checkout.php');
        break;
    case preg_match('/^\/checkout\/save_checkout$/', $path):
        include('./checkout/save_checkout.php');
        break;

    // Dashboard
    case preg_match('/^\/dashboard\/detail_produk$/', $path):
        include('./dashboard/detail_produk.php');
        break;
    case preg_match('/^\/dashboard$/', $path):
        include('./dashboard/dashboard.php');
        break;

    // Kasir
    case preg_match('/^\/kasir\/add_to_cart$/', $path):
        include('./kasir/add_to_cart.php');
        break;
    case preg_match('/^\/kasir\/delete_to_cart$/', $path):
        include('./kasir/delete_to_cart.php');
        break;
    case preg_match('/^\/kasir\/delete_produk$/', $path):
        include('./kasir/delete_produk.php');
        break;
    case preg_match('/^\/kasir\/delete_keranjang$/', $path):
        include('./kasir/delete_keranjang.php');
        break;
    case preg_match('/^\/kasir\/get_cart$/', $path):
        include('./kasir/get_cart.php');
        break;
    case preg_match('/^\/kasir\/update_cart$/', $path):
        include('./kasir/update_cart.php');
        break;
    case preg_match('/^\/kasir\/checkout$/', $path):
        include('./kasir/checkout.php');
        break;

    // Laporan
    case preg_match('/^\/laporan\/get_laporan_keuangan$/', $path):
        include('./laporan/get_laporan_keuangan.php');
        break;
    case preg_match('/^\/laporan\/get_invoice_detail$/', $path):
        include('./laporan/get_invoice_detail.php');
        break;

    // Pelanggan
    case preg_match('/^\/pelanggan\/create_pelanggan$/', $path):
        include('./pelanggan/create_pelanggan.php');
        break;
    case preg_match('/^\/pelanggan\/get_pelanggan$/', $path):
        include('./pelanggan/get_pelanggan.php');
        break;
    case preg_match('/^\/pelanggan\/get_pelanggan_name$/', $path):
        include('./pelanggan/get_pelanggan_name.php');
        break;
    case preg_match('/^\/pelanggan\/update_pelanggan$/', $path):
        include('./pelanggan/update_pelanggan.php');
        break;

    // Produk
    case preg_match('/^\/produk\/insert_produk$/', $path):
        include('./produk/insert_produk.php');
    case preg_match('/^\/produk\/search_produk$/', $path):
        include('./produk/search_produk.php');
        break;
    case preg_match('/^\/produk\/update_produk$/', $path):
        include('./produk/update_produk.php');
        break;
    case preg_match('/^\/produk\/delete_produk$/', $path):
        include('./produk/delete_produk.php');
        break;
    case preg_match('/^\/produk\/get_produk$/', $path):
        include('./produk/get_produk.php');
        break;
    case preg_match('/^\/produk\/produk_laris$/', $path):
        include('./produk/produk_laris.php');
        break;

    // Toko
    case preg_match('/^\/toko\/atur_akun$/', $path):
        include('./toko/atur_akun.php');
        break;
    case preg_match('/^\/toko\/kelola_toko$/', $path):
        include('./toko/kelola_toko.php');
        break;
    case preg_match('/^\/toko\/update_toko$/', $path):
        include('./toko/update_toko.php');
        break;
    case preg_match('/^\/toko\/upgrade_premium$/', $path):
        include('./toko/upgrade_premium.php');
        break;

    // Transaksi
    case preg_match('/^\/transaksi\/print_struk$/', $path):
        include('./transaksi/print_struk.php');
        break;
    case preg_match('/^\/transaksi\/update_status_transaksi$/', $path):
        include('./transaksi/update_status_transaksi.php');
        break;

    // Voucher
    case preg_match('/^\/voucher\/create_voucher$/', $path):
        include('./voucher/create_voucher.php');
        break;
    case preg_match('/^\/voucher\/get_voucher$/', $path):
        include('./voucher/get_voucher.php');
        break;
    case preg_match('/^\/voucher\/vocher_name$/', $path):
        include('./voucher/vocher_name.php');
        break;

    default:
        echo json_encode([
            'success' => false,
            'error' => 'Endpoint tidak ditemukan'
        ]);
        break;
}
?>