<?php
header('Content-Type: application/json');
include("../config/dbconnection.php"); // Sesuaikan path jika diperlukan
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data input
    $id_user = $_POST['id_user'] ?? null;
    $nomor_telepon = $_POST['nomor_telepon'] ?? null;
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;

    // Validasi ID User
    if (empty($id_user)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID User diperlukan'
        ]);
        exit;
    }

    // Validasi input minimal satu field diisi
    if (empty($nomor_telepon) && empty($email) && empty($password)) {
        echo json_encode([
            'success' => false,
            'error' => 'Minimal satu field harus diisi'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update nomor telepon di tabel Toko
        if (!empty($nomor_telepon)) {
            $queryToko = "UPDATE toko SET nomor_telepon = ? WHERE id_user = ?";
            $stmtToko = $pdo->prepare($queryToko);
            $stmtToko->execute([$nomor_telepon, $id_user]);
        }

        // Update email di tabel Users
        if (!empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Format email tidak valid'
                ]);
                exit;
            }

            $queryEmail = "UPDATE users SET email = ? WHERE id = ?";
            $stmtEmail = $pdo->prepare($queryEmail);
            $stmtEmail->execute([$email, $id_user]);
        }

        // Update password di tabel Users
        if (!empty($password)) {
            if (strlen($password) < 8) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Password harus minimal 8 karakter'
                ]);
                exit;
            }

            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $queryPassword = "UPDATE users SET password = ? WHERE id = ?";
            $stmtPassword = $pdo->prepare($queryPassword);
            $stmtPassword->execute([$hashed_password, $id_user]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Informasi akun berhasil diperbarui'
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
