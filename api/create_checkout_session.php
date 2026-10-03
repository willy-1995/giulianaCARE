<?php

require_once "cors.php";
require_once "envloader.php";
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/auth/jwt.php";

// Stripe initialisieren aus .env
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');

if (empty($stripeSecretKey)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "STRIPE_SECRET_KEY fehlt in der .env."]);
    exit;
}

\Stripe\Stripe::setApiKey($stripeSecretKey);

// JWT Token auswerten
$userId = getUserIdFromToken();

$input = json_decode(file_get_contents("php://input"), true);

// Domain/Base-URL dynamisch aus .env laden (Fallback auf giuliana-care.de)
$clientUrl = $_ENV['CLIENT_URL'] ?? 'https://giuliana-care.de';

// Price-IDs dynamisch aus der .env-Datei auslesen
$priceMap = [
    'sicherheit'    => $_ENV['STRIPE_PRICE_SICHERHEIT'] ?? '',
    'gutBetreut'    => $_ENV['STRIPE_PRICE_GUT_BETREUT'] ?? '',
    'rundumSorglos' => $_ENV['STRIPE_PRICE_RUNDUM_SORGLOS'] ?? ''
];

$selectedPackage = $input['price'] ?? '';

// Prüfen, ob das Paket existiert und die Price-ID in der .env gesetzt ist
if (empty($selectedPackage) || empty($priceMap[$selectedPackage])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Ungültiges Paket gewählt oder Price-ID fehlt in der .env."
    ]);
    exit;
}

// ✅ Korrektur: Die zugewiesene Price-ID in der Variable $priceId speichern
$priceId = $priceMap[$selectedPackage];

try {
    // Optional: Nächsten 1. des Monats als Abrechnungs-Anker
    $firstOfNextMonth = strtotime('first day of next month 00:00:00');

    $session = \Stripe\Checkout\Session::create([
        'line_items' => [[
            'price'    => $priceId,
            'quantity' => 1,
        ]],
        'mode' => 'subscription',
        'success_url' => $clientUrl . '/dashboard?welcome=true',
        'cancel_url'  => $clientUrl . '/registration?canceled=true',

        // Optional & sehr empfohlen: User-ID mitgeben für spätere Webhook-Zuordnung
        'client_reference_id' => $userId,
        'metadata' => [
            'user_id' => $userId,
            'package' => $selectedPackage
        ]
    ]);

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "url" => $session->url,
        "id"  => $session->id
    ]);
} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Stripe Fehler: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server Fehler: " . $e->getMessage()]);
}
