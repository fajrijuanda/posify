<?php
header('Content-Type: application/json');
include("../config/dbconnection.php"); // Sesuaikan path jika diperlukan
include('../config/cors.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    // Validasi jenis aksi: "request_reset" atau "reset_password"
    if ($action === 'request_reset') {
        // Permintaan token reset password
        $email = $_POST['email'] ?? null;

        if (empty($email)) {
            echo json_encode([
                'success' => false,
                'error' => 'Email wajib diisi.'
            ]);
            exit;
        }

        try {
            // Cek apakah email terdaftar
            $query = "SELECT id FROM users WHERE email = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {
                // Buat token reset password
                $reset_token = bin2hex(random_bytes(32));
                $reset_token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Simpan token ke database
                $updateQuery = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([$reset_token, $reset_token_expiry, $email]);

                // Kirim token ke email (palsu untuk testing)
                echo json_encode([
                    'success' => true,
                    'message' => 'Token reset password telah dikirim ke email Anda.',
                    'reset_token' => $reset_token // Hanya untuk testing
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Email tidak terdaftar.'
                ]);
            }
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
    } elseif ($action === 'reset_password') {
        // Reset password
        $reset_token = $_POST['reset_token'] ?? null;
        $new_password = $_POST['new_password'] ?? null;

        if (empty($reset_token) || empty($new_password)) {
            echo json_encode([
                'success' => false,
                'error' => 'Token reset dan password baru wajib diisi.'
            ]);
            exit;
        }

        try {
            // Validasi token
            $query = "SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$reset_token, date('Y-m-d H:i:s')]);

            if ($stmt->rowCount() > 0) {
                // Hash password baru
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

                // Update password dan hapus token
                $updateQuery = "UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([$hashed_password, $reset_token]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Password berhasil diperbarui.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Token reset tidak valid atau kadaluarsa.'
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
            'error' => 'Invalid action.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method.'
    ]);
}
?>
