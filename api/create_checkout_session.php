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

// Domain/Base-URL dynamisch aus .env laden
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

$priceId = $priceMap[$selectedPackage];

try {
    // -------------------------------------------------------------
    // Berechnungen für 14 Tage Trial + Abrechnung zum 1. des Monats
    // -------------------------------------------------------------
    $trialDays = 14;

    // Zeitpunkt, an dem die 14 Tage Testphase enden
    $trialEndTimestamp = strtotime("+{$trialDays} days");

    // Der 1. des Monats, der auf das Ende der Testphase folgt
    // (Bsp: Registrierung am 10. Okt -> Trial endet 24. Okt -> Anker ist 1. Nov)
    $firstOfNextMonthAfterTrial = strtotime('first day of next month 00:00:00', $trialEndTimestamp);

    $session = \Stripe\Checkout\Session::create([
        'line_items' => [[
            'price'    => $priceId,
            'quantity' => 1,
        ]],
        'mode' => 'subscription',
        'success_url' => $clientUrl . '/dashboard?welcome=true',
        'cancel_url'  => $clientUrl . '/registration?canceled=true',

        // Abo-Einstellungen für Trial & anteilige Abrechnung
        'subscription_data' => [
            'trial_period_days' => $trialDays,
            'billing_cycle_anchor' => $firstOfNextMonthAfterTrial,
            'proration_behavior' => 'create_prorations', // Berechnet den Restmonat anteilig
        ],

        // User-ID für spätere Webhook-Zuordnung mitgeben
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
