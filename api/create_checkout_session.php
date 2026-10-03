<?php
require_once "cors.php";
require_once "envloader.php";
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/auth/jwt.php";



// Stripe initialisieren aus .env
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

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

try {
    // Nächsten 1. des Monats als Abrechnungs-Anker berechnen
    $firstOfNextMonth = strtotime('first day of next month 00:00:00');

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card', 'sepa_debit'],
        'mode' => 'subscription',
        'customer_email' => $input['email'],
        'client_reference_id' => $userId, // Verknüpfung zu deiner DB-User-ID
        'line_items' => [[
            'price' => $priceMap[$selectedPackage],
            'quantity' => 1,
        ]],
        'subscription_data' => [
            // Richtet die Abbuchung fest auf den 1. des Monats aus (inkl. anteiliger Berechnungen)
            'billing_cycle_anchor' => $firstOfNextMonth,
            'proration_behavior' => 'create_prorations',
        ],
        'success_url' => $clientUrl . '/dashboard?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $clientUrl . '/registration?canceled=true',
    ]);

    echo json_encode(["success" => true, "url" => $session->url]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
