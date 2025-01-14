<?php
header('Content-Type: application/json');
include("../config/dbconnection.php");
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_voucher = $_POST['id_voucher'] ?? null;

    if (empty($id_voucher)) {
        echo json_encode([
            'success' => false,
            'error' => 'ID Voucher diperlukan'
        ]);
        exit;
    }

    try {
        // Cek apakah voucher sudah digunakan di checkout
        $queryCheckUsed = "
            SELECT c.id FROM checkout c
            WHERE c.id_voucher = ? AND c.status = 'checkout'";
        $stmtCheckUsed = $pdo->prepare($queryCheckUsed);
        $stmtCheckUsed->execute([$id_voucher]);

        // Cek apakah voucher sudah digunakan
        if ($stmtCheckUsed->rowCount() > 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Voucher sudah digunakan di checkout dan tidak dapat dihapus'
            ]);
            exit;
        }

        // Cek apakah voucher sudah kadaluarsa
        $queryCheckExpired = "
            SELECT id FROM voucher WHERE id = ? AND tanggal_berakhir < NOW()";
        $stmtCheckExpired = $pdo->prepare($queryCheckExpired);
        $stmtCheckExpired->execute([$id_voucher]);

        if ($stmtCheckExpired->rowCount() > 0) {
            // Jika voucher sudah kadaluarsa, hapus voucher
            $queryDelete = "DELETE FROM voucher WHERE id = ?";
            $stmtDelete = $pdo->prepare($queryDelete);
            $stmtDelete->execute([$id_voucher]);

            echo json_encode([
                'success' => true,
                'message' => 'Voucher berhasil dihapus karena kadaluarsa'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Voucher tidak kadaluarsa dan belum digunakan'
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
        'error' => 'Invalid request method'
    ]);
}
?>
