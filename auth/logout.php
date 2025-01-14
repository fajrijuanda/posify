<?php
header('Content-Type: application/json');
include('../config/dbconnection.php'); 
include('../config/helpers.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? null;

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        respondJSON(['success' => false, 'error' => 'Token tidak valid'], 401);
    }

    $token = $matches[1];

    try {
        // Hapus token dari tabel sessions
        $query = "DELETE FROM sessions WHERE token = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$token]);

        respondJSON(['success' => true, 'message' => 'Logout berhasil']);
    } catch (PDOException $e) {
        respondJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    }
} else {
    respondJSON(['success' => false, 'error' => 'Invalid request method'], 405);
}
?>
