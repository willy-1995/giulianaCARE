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
            // Status aus der Checkout-Session abfragen (kann 'active' oder 'trialing' sein)
            $status = $session->payment_status === 'paid' || $session->status === 'complete' ? 'active' : 'trialing';
            $userManager->updateStripeSubscription($userId, $customerId, $subscriptionId, $status);
        }
        break;

    // B) Wiederkehrende Zahlung erfolgreich
    case 'invoice.payment_succeeded':
        $invoice = $event->data->object;
        $subscriptionId = $invoice->subscription ?? null;

        if ($subscriptionId) {
            $userManager->updateSubscriptionStatusBySubId($subscriptionId, 'active');
        }
        break;

    // C) Abo wurde aktualisiert (z.B. Kündigung vorgemerkt oder Paket gewechselt)
    case 'customer.subscription.updated':
        $subscription = $event->data->object;
        $subscriptionId = $subscription->id ?? null;

        if ($subscriptionId) {
            // Falls Kündigung zum Periodenende vorgemerkt wurde:
            if ($subscription->cancel_at_period_end) {
                $userManager->updateSubscriptionStatusBySubId($subscriptionId, 'cancel_pending');
            } else {
                // Ansonsten Stripe-Status direkt übernehmen (z.B. 'active', 'past_due', 'trialing')
                $userManager->updateSubscriptionStatusBySubId($subscriptionId, $subscription->status);
            }
        }
        break;

    // D) Wiederkehrende Zahlung fehlgeschlagen
    case 'invoice.payment_failed':
        $invoice = $event->data->object;
        $subscriptionId = $invoice->subscription ?? null;

        if ($subscriptionId) {
            $userManager->updateSubscriptionStatusBySubId($subscriptionId, 'past_due');
        }
        break;

    // E) Abo endgültig abgelaufen / gelöscht
    case 'customer.subscription.deleted':
        $subscription = $event->data->object;
        $subscriptionId = $subscription->id ?? null;

        if ($subscriptionId) {
            $userManager->updateSubscriptionStatusBySubId($subscriptionId, 'canceled');
        }
        break;

    default:
        // Andere Events ignorieren
        break;
}

// 6. Stripe Bestätigung
http_response_code(200);
echo json_encode(["status" => "success"]);
