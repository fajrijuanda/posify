<?php
function respondJSON($data, $status = 200) {
    header("Content-Type: application/json");
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags($input));
}
?>
