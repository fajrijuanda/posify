<?php
// Konfigurasi CORS
header('Access-Control-Allow-Origin: *'); // Mengizinkan akses dari semua origin
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS'); // Metode HTTP yang diizinkan
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Header yang diizinkan

// Handling preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
?>
