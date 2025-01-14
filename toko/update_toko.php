<?php
header('Content-Type: application/json');
include("../config/dbconnection.php"); // Sesuaikan path jika diperlukan
include('../middlewares/auth_middleware.php'); // Middleware untuk validasi token

// Validasi token untuk otentikasi
$user_id = validateToken($pdo); // Mendapatkan user_id dari token jika valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input dari user
    $id_toko = $_POST['id_toko'] ?? null;
    $logo = $_FILES['logo'] ?? null; // Upload logo jika ada
    $nomor_telepon = $_POST['nomor_telepon'] ?? null;
    $nomor_rekening = $_POST['nomor_rekening'] ?? null;

    // Validasi input
    if (empty($id_toko) || empty($nomor_telepon) || empty($nomor_rekening)) {
        echo json_encode([
            'success' => false,
            'error' => 'Semua field wajib diisi'
        ]);
        exit;
    }

    try {
        // Proses upload logo (opsional)
        $logoPath = null;
        if (!empty($logo) && $logo['error'] === UPLOAD_ERR_OK) {
            $uploadDir = "../uploads/";
            $logoPath = $uploadDir . basename($logo['name']);
            move_uploaded_file($logo['tmp_name'], $logoPath);
        }

        // Update informasi bisnis
        $query = "UPDATE toko SET logo = ?, nomor_telepon = ?, nomor_rekening = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$logoPath, $nomor_telepon, $nomor_rekening, $id_toko]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Informasi bisnis berhasil diperbarui'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Gagal memperbarui informasi bisnis'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    // Jika metode request bukan POST
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?>
