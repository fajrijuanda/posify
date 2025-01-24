<?php
require_once '../config/dbconnection.php';

try {
    // Nonaktifkan foreign key constraint sementara
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Hapus semua tabel dalam database
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($tables)) {
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            echo "Table $table deleted successfully.\n";
        }
    }

    // Aktifkan kembali foreign key constraint
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Lokasi file SQL dump
    $sqlFile = __DIR__ . '/db_posify.sql';

    // Periksa apakah file SQL tersedia
    if (!file_exists($sqlFile)) {
        throw new Exception("File SQL tidak ditemukan.");
    }

    // Membaca isi file SQL
    $sqlQueries = file_get_contents($sqlFile);

    // Pastikan file tidak kosong
    if (empty(trim($sqlQueries))) {
        throw new Exception("File SQL kosong atau tidak valid.");
    }

    // Eksekusi query SQL untuk mereset database
    $pdo->exec($sqlQueries);

    echo json_encode([
        "success" => true,
        "message" => "Database berhasil direset menggunakan file db_posify.sql"
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "error" => "Kesalahan database: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
