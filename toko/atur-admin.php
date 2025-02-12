<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Tambahkan metode CORS
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php');
include('../config/helpers.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'https://posifyapi.muhsalfazi.my.id';

// ✅ Validasi token JWT
$authResult = validateToken();
if (!is_array($authResult) || !isset($authResult['user_id'], $authResult['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

// ✅ Cek apakah pengguna adalah admin
if ($authResult['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Akses ditolak']);
    exit;
}

// Ambil user_id dari token
$user_id = $authResult['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input JSON
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);

    // Debugging: Cek JSON yang diterima
    error_log("JSON Input: " . json_encode($input));

    // Pastikan input berupa array yang valid
    if (!is_array($input)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON format'
        ]);
        exit;
    }

    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    // Validasi input minimal satu field harus diisi
    if ($email === null && $password === null) {
        echo json_encode([
            'success' => false,
            'error' => 'Minimal satu field harus diisi'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update email di tabel `users`
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
            $stmtEmail->execute([$email, $user_id]);
        }

        // Update password di tabel `users`
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
