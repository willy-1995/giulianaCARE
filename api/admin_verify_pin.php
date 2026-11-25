<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/envloader.php';
require_once __DIR__ . '/rate_limiter.php';

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Methode nicht erlaubt."
    ]);
    exit;
}

// Rate Limiter: max. 5 Versuche innerhalb von 600 Sekunden (10 Minuten)
checkRateLimit('admin_verify_pin', 5, 600);

$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Ungültiges JSON-Format."
    ]);
    exit;
}

$enteredPin = (string) ($input['master_pin'] ?? '');
$masterPin = (string) ($_ENV['ADMIN_MASTER_PIN'] ?? '');

if ($masterPin === '') {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Master-PIN ist auf dem Server nicht konfiguriert."
    ]);
    exit;
}

if (hash_equals($masterPin, $enteredPin)) {
    echo json_encode([
        "success" => true,
        "message" => "PIN korrekt."
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Falsche Master-PIN."
    ]);
}
