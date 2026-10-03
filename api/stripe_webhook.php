<?php
require_once "envloader.php";
require_once "database.php";
require_once "users_crud.php";
require_once __DIR__ . "/vendor/autoload.php";

// 1. Stripe API & Webhook Secret aus .env laden
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
$endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';

// 2. Webhook-Payload & Signatur-Header einlesen
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($sig_header) || empty($payload)) {
    http_response_code(400);
    echo json_encode(["error" => "Ungültige Anfrage / Fehlender Header"]);
    exit();
}

// 3. Event-Signatur verifizieren (Sicherheit gegen Manipulation)
try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Ungültige Signatur
    http_response_code(400);
    echo json_encode(["error" => "Ungültige Stripe-Signatur"]);
    exit();
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(["error" => $e->getMessage()]);
    exit();
}

// 4. UserManager-Instanz für Datenbank-Zugriffe erstellen
$userManager = new UserManager();

// 5. Events verarbeiten
switch ($event->type) {

    // A) Erstmaliger Checkout erfolgreich abgeschlossen
    case 'checkout.session.completed':
        $session = $event->data->object;

        $userId = $session->client_reference_id ?? null;
        $customerId = $session->customer ?? null;
        $subscriptionId = $session->subscription ?? null;

        if ($userId && $customerId && $subscriptionId) {
            $userManager->updateStripeSubscription($userId, $customerId, $subscriptionId, 'active');
        }
        break;

    // B) Wiederkehrende Zahlung erfolgreich (z. B. im Folgemonat)
    case 'invoice.payment_succeeded':
        $invoice = $event->data->object;
        $subscriptionId = $invoice->subscription ?? null;

        if ($subscriptionId) {
            $userManager->updateSubscriptionStatusBySubId($subscriptionId, 'active');
        }
        break;

    // C) Wiederkehrende Zahlung fehlgeschlagen (z. B. Karte abgelaufen / Konto nicht gedeckt)
    case 'invoice.payment_failed':
        $invoice = $event->data->object;
        $subscriptionId = $invoice->subscription ?? null;

        if ($subscriptionId) {
            $userManager->updateSubscriptionStatusBySubId($subscriptionId, 'past_due');
        }
        break;

    // D) Abo wurde endgültig gekündigt oder nach Fehlversuchen von Stripe beendet
    case 'customer.subscription.deleted':
        $subscription = $event->data->object;
        $subscriptionId = $subscription->id ?? null;

        if ($subscriptionId) {
            $userManager->updateSubscriptionStatusBySubId($subscriptionId, 'canceled');
        }
        break;

    default:
        // Andere Events (z.B. payment_intent.created) ignorieren wir schweigend
        break;
}

// 6. Stripe mitteilen, dass der Webhook erfolgreich empfangen wurde
http_response_code(200);
echo json_encode(["status" => "success"]);
