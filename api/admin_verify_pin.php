<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/envloader.php'; // Lädt die .env Datei

header("Content-Type: application/json; charset=UTF-8");

$input = json_decode(file_get_contents("php://input"), true);
$enteredPin = $input['master_pin'] ?? '';

// Direktes Auslesen aus $_ENV (exakt wie in deiner mailer.php und Database-Klasse)
$masterPin = $_ENV['ADMIN_MASTER_PIN'] ?? '';

if (empty($masterPin)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Master-PIN ist auf dem Server nicht konfiguriert."
    ]);
    exit;
}

// PIN vergleichen
if ($enteredPin === $masterPin) {
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
