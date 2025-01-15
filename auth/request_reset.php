<?php
header('Content-Type: application/json');
include("../config/dbconnection.php"); // Sesuaikan path jika diperlukan
include('../config/cors.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? null;

    // Validasi input
    if (empty($email)) {
        echo json_encode([
            'success' => false,
            'error' => 'Email wajib diisi.'
        ]);
        exit;
    }

    try {
        // Cek apakah email sudah terdaftar
        $checkQuery = "SELECT reset_requested FROM users WHERE email = ?";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$email]);

        if ($checkStmt->rowCount() > 0) {
            $user = $checkStmt->fetch(PDO::FETCH_ASSOC);

            // Periksa apakah sudah pernah melakukan request reset
            if ($user['reset_requested']) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Email sudah melakukan permintaan reset password sebelumnya.',
                    'status' => 2 // Status boolean 2 untuk email sudah request
                ]);
                exit;
            }

            // Jika belum, update reset_requested menjadi TRUE
            $query = "UPDATE users SET reset_requested = TRUE WHERE email = ?";
            $updateStmt = $pdo->prepare($query);
            $updateStmt->execute([$email]);

            echo json_encode([
                'success' => true,
                'message' => 'Email terdaftar. Silakan lanjutkan dengan memperbarui password.',
                'status' => 1 // Status boolean 1 untuk email ditemukan
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Email tidak terdaftar.',
                'status' => 0 // Status boolean 0 untuk email tidak ditemukan
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method.'
    ]);
}
?>
