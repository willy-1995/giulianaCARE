<?php
// kuendigen.php
header('Content-Type: application/json; charset=utf-8');

require_once "cors.php";
require_once "envloader.php";
require_once "users_crud.php";
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/auth/jwt.php";

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Methode nicht erlaubt."]);
        exit;
    }

    $userId = getUserIdFromToken();
    $userManager = new UserManager();

    // Abo bei Stripe zum Periodenende stornieren
    $userManager->cancelSubscriptionAtPeriodEnd($userId);

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Dein Abonnement wurde gekündigt. Du kannst den Service noch bis zum Ende der aktuellen Laufzeit uneingeschränkt nutzen."
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Fehler bei der Kündigung: " . $e->getMessage()]);
}
