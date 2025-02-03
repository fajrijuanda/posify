<?php
header('Content-Type: application/json');
include('../config/cors.php');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Load .env jika menggunakan PHP dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseURL = $_ENV['APP_URL'] ?? 'http://posify.test';

// Validasi token untuk otentikasi
$userData = validateToken(); // Dapatkan data user dari token

if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid atau sudah expired']);
    exit;
}

$user_id = $userData['user_id'];

// Debugging: Pastikan ID User ada
error_log("DEBUG: User ID -> " . $user_id);

// METHOD POST: Ubah status checkout menjadi `checkout`
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Ambil input JSON
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);

    if (!is_array($input)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON format'
        ]);
        exit;
    }

    // Ambil `id` dari input JSON
    $id_checkout = $input['id'] ?? null;

    if (empty($id_checkout)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID checkout diperlukan'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // **Cek apakah checkout masih berstatus `sementara`**
        $queryCheck = "SELECT id FROM checkout WHERE id = ? AND status = 'sementara'";
        $stmtCheck = $pdo->prepare($queryCheck);
        $stmtCheck->execute([$id_checkout]);
        $checkoutExists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$checkoutExists) {
            echo json_encode([
                'success' => false,
                'error' => 'Checkout tidak ditemukan atau sudah diproses'
            ]);
            exit;
        }

        // **Ubah status menjadi `checkout`**
        $queryUpdate = "UPDATE checkout SET status = 'checkout' WHERE id = ?";
        $stmtUpdate = $pdo->prepare($queryUpdate);
        $stmtUpdate->execute([$id_checkout]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Checkout berhasil diperbarui menjadi `checkout`',
            'id_checkout' => $id_checkout,
            'status' => 'checkout'
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