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

function validateToken($authHeader) {
    global $secretKey;

    if (!$authHeader || !is_string($authHeader)) {
        return ["error" => "Token not provided or invalid type"];
    }

    // Validasi format "Bearer {token}"
    $tokenParts = explode(' ', trim($authHeader));

    if (count($tokenParts) !== 2 || strtolower($tokenParts[0]) !== 'bearer') {
        return ["error" => "Invalid token format"];
    }

    $jwtToken = $tokenParts[1];

    try {
        $decoded = JWT::decode($jwtToken, new Key($secretKey, 'HS256'));

        if (!isset($decoded->user_id)) {
            return ["error" => "Invalid token payload"];
        }

        return $decoded->user_id;
    } catch (\Firebase\JWT\ExpiredException $e) {
        return ["error" => "Token has expired"];
    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        return ["error" => "Invalid token signature"];
    } catch (Exception $e) {
        return ["error" => "Token verification failed: " . $e->getMessage()];
    }
}
