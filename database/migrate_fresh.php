<?php
header("Content-Type: application/json");
include("../config/dbconnection.php");
require_once('../vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

\Midtrans\Config::$serverKey = $_ENV['MIDTRANS_SERVER_KEY'];
\Midtrans\Config::$isProduction = false;

$inputJSON = file_get_contents("php://input");
$notification = json_decode($inputJSON, true);

$order_id = $notification['order_id'];
$transaction_status = $notification['transaction_status'];

try {
    if ($transaction_status == 'capture' || $transaction_status == 'settlement') {
        $status = 'completed';
    } elseif ($transaction_status == 'pending') {
        $status = 'pending';
    } elseif ($transaction_status == 'cancel' || $transaction_status == 'deny' || $transaction_status == 'expire') {
        $status = 'failed';
    }

    $queryUpdate = "UPDATE midtrans_log SET status = ? WHERE transaction_id = ?";
    $stmtUpdate = $pdo->prepare($queryUpdate);
    $stmtUpdate->execute([$status, $order_id]);

    echo json_encode(["message" => "Webhook processed successfully"]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>