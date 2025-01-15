<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include('../config/cors.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? null;
    $confirm_password = $_POST['confirm_password'] ?? null;

    // Validasi input
    if (empty($new_password) || empty($confirm_password)) {
        echo json_encode([
            'success' => false,
            'error' => 'Password baru dan konfirmasi password wajib diisi.'
        ]);
        exit;
    }

    // Validasi password minimal 8 karakter dan memenuhi kriteria unik
    $passwordPattern = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@#$%^&+=!]).{8,}$/';
    if (!preg_match($passwordPattern, $new_password)) {
        echo json_encode([
            'success' => false,
            'error' => 'Password harus minimal 8 karakter, mengandung huruf besar, huruf kecil, angka, dan karakter khusus(misalnya:#, @, !,dll).'
        ]);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode([
            'success' => false,
            'error' => 'Password baru dan konfirmasi password tidak cocok.'
        ]);
        exit;
    }

    try {
        // Cek apakah ada pengguna yang sudah request_reset
        $query = "SELECT id, email FROM users WHERE reset_requested = TRUE LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Ambil email pengguna
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $email = $user['email'];

            // Hash password baru
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

            // Update password di database dan atur reset_requested ke NULL
            $updateQuery = "UPDATE users SET password = ?, reset_requested = NULL WHERE email = ?";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->execute([$hashed_password, $email]);

            echo json_encode([
                'success' => true,
                'message' => 'Password berhasil diperbarui.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Tidak ada permintaan reset password yang ditemukan.'
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
