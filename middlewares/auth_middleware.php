<?php 
require_once __DIR__ . '/../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$secretKey = $_ENV['JWT_SECRET'] ?? null;

if (!$secretKey) {
    http_response_code(500);
    echo json_encode(["error" => "JWT secret key is not set"]);
    exit;
}

function validateToken() {
    global $secretKey, $pdo; // Gunakan $pdo untuk koneksi database

    // Ambil header Authorization
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? '';

    if (!$authHeader) {
        return ['error' => 'Token tidak ditemukan'];
    }

    // Validasi format "Bearer {token}"
    $tokenParts = explode(' ', trim($authHeader));

    if (count($tokenParts) !== 2 || strtolower($tokenParts[0]) !== 'bearer') {
        return ['error' => 'Format token tidak valid'];
    }

    $jwtToken = $tokenParts[1];

    try {
        $decoded = JWT::decode($jwtToken, new Key($secretKey, 'HS256'));
        $decodedArray = (array) $decoded;

        // ✅ **Pastikan token memiliki user_id dan role**
        if (!isset($decodedArray['user_id']) || !isset($decodedArray['role'])) {
            return ['error' => 'Token tidak valid'];
        }

        // ✅ **Cek apakah token masih valid di database**
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE token = ? AND expired_at > NOW()");
        $stmt->execute([$jwtToken]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            // ❌ **Jika token expired, hapus dari database**
            $deleteStmt = $pdo->prepare("DELETE FROM sessions WHERE token = ?");
            $deleteStmt->execute([$jwtToken]);

            http_response_code(401);
            echo json_encode(["success" => false, "error" => "Token expired, please login again"]);
            exit;
        }

        // ✅ **Pisahkan data berdasarkan role**
        if ($decodedArray['role'] === 'User') {
            return [
                'user_id' => $decodedArray['user_id'],
                'id_toko' => $decodedArray['id_toko'] ?? null
            ];
        } elseif ($decodedArray['role'] === 'Admin') {
            return [
                'user_id' => $decodedArray['user_id'],
                'role' => $decodedArray['role']
            ];
        } else {
            return ['error' => 'Role tidak dikenali'];
        }
    } catch (\Firebase\JWT\ExpiredException $e) {
        // ❌ **Jika token expired, hapus dari database**
        $deleteStmt = $pdo->prepare("DELETE FROM sessions WHERE token = ?");
        $deleteStmt->execute([$jwtToken]);

        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Token expired, please login again"]);
        exit;
    } catch (\Exception $e) {
        return ['error' => 'Token tidak valid'];
    }
}
?>
