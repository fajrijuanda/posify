<?php

header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); 
include('../config/helpers.php');  

// Load .env jika menggunakan PHP dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

// Validasi token untuk otentikasi
$authResult = validateToken(); // Tidak perlu parameter
if (!is_array($authResult) || !isset($authResult['user_id'], $authResult['id_toko'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

// Ambil user_id & id_toko dari token JWT
$user_id = $authResult['user_id'];
$id_toko = $authResult['id_toko'];

// Debugging: Pastikan id_toko ada sebelum query
error_log("User ID: " . $user_id);
error_log("ID Toko: " . $id_toko);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input JSON
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);

    // Debugging: Cek JSON yang diterima
    error_log("JSON Input: " . json_encode($input));

    if (!is_array($input)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON format'
        ]);
        exit;
    }

    // Ambil data input dari JSON tanpa membuatnya null jika tidak ada
    $nomor_telepon = isset($input['nomor_telepon']) ? $input['nomor_telepon'] : null;
    $email = isset($input['email']) ? $input['email'] : null;
    $password = isset($input['password']) ? $input['password'] : null;

    // Validasi input minimal satu field diisi
    if ($nomor_telepon === null && $email === null && $password === null) {
        echo json_encode([
            'success' => false,
            'error' => 'Minimal satu field harus diisi'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update nomor telepon di tabel `toko`
        if ($nomor_telepon !== null) {
            $queryToko = "UPDATE toko SET nomor_telepon = ? WHERE id_user = ?";
            $stmtToko = $pdo->prepare($queryToko);
            $stmtToko->execute([$nomor_telepon, $user_id]);
        }

        // Update email di tabel `users`
        if ($email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Format email tidak valid'
                ]);
                exit;
            }

            $queryEmail = "UPDATE users SET email = ? WHERE id = ?";
            $stmtEmail = $pdo->prepare($queryEmail);
            $stmtEmail->execute([$email, $user_id]);
        }

        // Update password di tabel `users`
        if ($password !== null) {
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
            $stmtPassword->execute([$hashed_password, $user_id]);
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
