<?php
// cancel_subscription.php
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

    // 1. AGB-Kündigungsfrist berechnen: 14 Tage zum Monatsende
    $today = new DateTime();
    $targetDate = (clone $today)->modify('+14 days');

    if ($targetDate->format('Y-m') === $today->format('Y-m')) {
        // Frist reicht noch für das Ende des aktuellen Monats
        $effectiveDate = new DateTime('last day of this month 23:59:59');
    } else {
        // Frist geht über das Monatsende hinaus -> Ende des Folgemonats
        $effectiveDate = new DateTime('last day of next month 23:59:59');
    }

    $cancelTimestamp = $effectiveDate->getTimestamp();
    $formattedDate = $effectiveDate->format('d.m.Y');

    // 2. Kündigung bei Stripe mit spezifischem Enddatum (cancel_at) einsteuern
    // Passe diese Methode in deiner UserManager-Klasse ggf. an ($cancelTimestamp übergeben)
    $userManager->cancelSubscriptionAtTimestamp($userId, $cancelTimestamp);

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "effective_date" => $formattedDate,
        "message" => "Dein Abonnement wurde fristgerecht zum " . $formattedDate . " gekündigt. Bis dahin kannst du deinen Account vollumfänglich nutzen."
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Fehler bei der Kündigung: " . $e->getMessage()]);
}
