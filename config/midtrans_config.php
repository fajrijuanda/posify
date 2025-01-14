<?php
// Pastikan Anda sudah mengunduh dan menginstal Midtrans PHP Library dari https://github.com/Midtrans/midtrans-php
require_once '../vendor/midtrans/Midtrans.php';

use Midtrans\Config;

// Konfigurasi Midtrans
Config::$serverKey = 'YOUR_SERVER_KEY'; // Ganti dengan Server Key dari akun Midtrans Anda
Config::$isProduction = false;         // Ubah ke true jika sudah menggunakan mode production
Config::$isSanitized = true;           // Pastikan data pembayaran disanitasi
Config::$is3ds = true;                 // Mengaktifkan 3DSecure untuk pembayaran kartu kredit
?>
