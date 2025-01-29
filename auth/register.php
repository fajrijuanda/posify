<?php
header('Content-Type: application/json');
include("../config/dbconnection.php"); // Sesuaikan path jika diperlukan
include('../config/cors.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil JSON input dari user
    $jsonInput = file_get_contents('php://input');
    $data = json_decode($jsonInput, true);
    
    if (!is_array($data)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON format'
        ]);
        exit;
    }
    
    $nama_toko = $data['nama_toko'] ?? null;
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    // Validasi input
    if (empty($nama_toko) || empty($email) || empty($password)) {
        echo json_encode([
            'success' => false,
            'error' => 'Semua field wajib diisi'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Cek apakah email sudah ada di tabel Users
        $checkEmailQuery = "SELECT id FROM users WHERE email = ?";
        $checkEmailStmt = $pdo->prepare($checkEmailQuery);
        $checkEmailStmt->execute([$email]);

        if ($checkEmailStmt->rowCount() > 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Email sudah terdaftar'
            ]);
            $pdo->rollBack();
            exit;
        }

        // Hash password dengan bcrypt
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Insert data ke tabel Users (name = nama_toko, role = 'User')
        $queryUser = "INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, 'User', 1)";
        $stmtUser = $pdo->prepare($queryUser);
        $stmtUser->execute([$nama_toko, $email, $hashed_password]);

        // Ambil ID User yang baru saja dibuat
        $id_user = $pdo->lastInsertId();

        // Insert data ke tabel Toko
        $queryToko = "INSERT INTO toko (id_user, nama_toko, nomor_telepon, alamat, nomor_rekening, logo) 
                      VALUES (?, ?, NULL, NULL, NULL, NULL)";
        $stmtToko = $pdo->prepare($queryToko);
        $stmtToko->execute([$id_user, $nama_toko]);

        // Ambil ID Toko yang baru saja dibuat
        $id_toko = $pdo->lastInsertId();

        // Ambil informasi paket Standard dari tabel PaketLangganan
        $queryPaket = "SELECT id, durasi FROM paketlangganan WHERE nama = 'standard' LIMIT 1";
        $stmtPaket = $pdo->prepare($queryPaket);
        $stmtPaket->execute();

        if ($stmtPaket->rowCount() > 0) {
            $paket = $stmtPaket->fetch(PDO::FETCH_ASSOC);
            
            // Hitung tanggal mulai dan tanggal berakhir berdasarkan durasi paket
            $tanggal_mulai = date('Y-m-d H:i:s');
            $tanggal_berakhir = date('Y-m-d H:i:s', strtotime("+{$paket['durasi']} months"));

            // Insert data ke tabel LanggananToko
            $queryLangganan = "INSERT INTO langganantoko (id_toko, id_langganan, tanggal_mulai, tanggal_berakhir) 
                               VALUES (?, ?, ?, ?)";
            $stmtLangganan = $pdo->prepare($queryLangganan);
            $stmtLangganan->execute([$id_toko, $paket['id'], $tanggal_mulai, $tanggal_berakhir]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Paket langganan default (Standard) tidak ditemukan'
            ]);
            $pdo->rollBack();
            exit;
        }

        // Commit transaksi
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Akun berhasil dibuat dengan langganan Standard',
            'data' => [
                'id_user' => $id_user,
                'id_toko' => $id_toko,
                'paket' => 'Standard',
                'tanggal_mulai' => $tanggal_mulai,
                'tanggal_berakhir' => $tanggal_berakhir
            ]
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