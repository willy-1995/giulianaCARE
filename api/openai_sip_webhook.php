<?php

declare(strict_types=1);

/*
 * GiulianaCare – OpenAI Live SIP Incoming Webhook
 * Erstmaliger Verbindungstest – ohne VPS
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

// ======================================================
// 1. GEHEIMNISSE – SPÄTER EINTRAGEN
// ======================================================

$OPENAI_API_KEY = 'HIER_DEIN_OPENAI_API_KEY';
$OPENAI_WEBHOOK_SECRET = 'HIER_DEIN_WEBHOOK_SIGNING_SECRET';

// ======================================================
// 2. LOGGING
// ======================================================

function sipLog(string $message): void
{
    $logFile = __DIR__ . '/openai_sip.log';

    file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL,
        FILE_APPEND
    );
}

function respond(int $status, array $data): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

// ======================================================
// 3. NUR POST ERLAUBEN
// ======================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

// ======================================================
// 4. RAW BODY UND HEADERS
// ======================================================

$rawBody = file_get_contents('php://input');

if ($rawBody === false || $rawBody === '') {
    respond(400, ['error' => 'Empty request body']);
}

$headers = function_exists('getallheaders')
    ? getallheaders()
    : [];

$normalizedHeaders = [];

foreach ($headers as $key => $value) {
    $normalizedHeaders[strtolower($key)] = $value;
}

$webhookId = $normalizedHeaders['webhook-id'] ?? '';
$webhookTimestamp = $normalizedHeaders['webhook-timestamp'] ?? '';
$webhookSignature = $normalizedHeaders['webhook-signature'] ?? '';

if (
    $webhookId === '' ||
    $webhookTimestamp === '' ||
    $webhookSignature === ''
) {
    sipLog('Webhook rejected: missing headers');

    respond(400, ['error' => 'Missing webhook headers']);
}

// ======================================================
// 5. SIGNATUR PRÜFEN
// ======================================================

if ($OPENAI_WEBHOOK_SECRET === 'HIER_DEIN_WEBHOOK_SIGNING_SECRET') {
    sipLog('Webhook secret not configured');

    respond(500, ['error' => 'Webhook secret not configured']);
}

// Replay-Schutz: maximal 5 Minuten Zeitabweichung

if (!is_numeric($webhookTimestamp)) {
    respond(400, ['error' => 'Invalid timestamp']);
}

if (abs(time() - (int)$webhookTimestamp) > 300) {
    sipLog('Webhook rejected: timestamp outside tolerance');

    respond(400, ['error' => 'Webhook timestamp expired']);
}

// OpenAI secret format: whsec_...

$secret = $OPENAI_WEBHOOK_SECRET;

if (str_starts_with($secret, 'whsec_')) {
    $secret = substr($secret, 6);
}

$decodedSecret = base64_decode($secret, true);

if ($decodedSecret === false) {
    sipLog('Webhook rejected: invalid secret format');

    respond(500, ['error' => 'Invalid webhook secret']);
}

$signedPayload = $webhookId . '.' . $webhookTimestamp . '.' . $rawBody;

$expectedSignature = base64_encode(
    hash_hmac('sha256', $signedPayload, $decodedSecret, true)
);

$signatureValid = false;

foreach (explode(' ', $webhookSignature) as $signaturePart) {

    $parts = explode(',', $signaturePart, 2);

    if (
        count($parts) === 2 &&
        $parts[0] === 'v1' &&
        hash_equals($expectedSignature, $parts[1])
    ) {
        $signatureValid = true;
        break;
    }
}

if (!$signatureValid) {
    sipLog('Webhook rejected: invalid signature');

    respond(401, ['error' => 'Invalid signature']);
}

// ======================================================
// 6. JSON AUSLESEN
// ======================================================

$event = json_decode($rawBody, true);

if (!is_array($event)) {
    respond(400, ['error' => 'Invalid JSON']);
}

$eventType = $event['type'] ?? '';

sipLog('Verified webhook received: ' . $eventType);

// ======================================================
// 7. NUR LIVE SIP INCOMING VERARBEITEN
// ======================================================

if ($eventType !== 'live.transport.incoming') {
    respond(200, [
        'success' => true,
        'message' => 'Event ignored'
    ]);
}

$data = $event['data'] ?? [];

if (($data['type'] ?? '') !== 'sip') {
    sipLog('Incoming event is not SIP');

    respond(200, [
        'success' => true,
        'message' => 'Non-SIP event ignored'
    ]);
}

$sessionId = $data['session_id'] ?? '';

if ($sessionId === '') {
    sipLog('Missing session ID');

    respond(400, ['error' => 'Missing session ID']);
}

// ======================================================
// 8. OPENAI API KEY PRÜFEN
// ======================================================

if ($OPENAI_API_KEY === 'HIER_DEIN_OPENAI_API_KEY') {
    sipLog('OpenAI API key not configured');

    respond(500, ['error' => 'API key not configured']);
}

// ======================================================
// 9. SIP ANRUF ANNEHMEN
// ======================================================

$acceptUrl = 'https://api.openai.com/v1/live/sessions/'
    . rawurlencode($sessionId)
    . '/accept';

$sessionConfiguration = [
    'session' => [
        'type' => 'live',
        'model' => 'gpt-live-1',
        'instructions' =>
        'Du bist eine freundliche deutschsprachige Test-KI am Telefon. '
            . 'Du führst zunächst ein einfaches, unverbindliches Gespräch. '
            . 'Begrüße den Anrufer kurz und stelle eine einfache Frage. '
            . 'Antworte kurz, natürlich und verständlich. '
            . 'Unterhalte dich ausschließlich auf Deutsch. '
            . 'Dies ist ein technischer Test. '
            . 'Du führst keine medizinischen Beratungen durch und '
            . 'löst keine Notfälle oder Benachrichtigungen aus.',
        'audio' => [
            'output' => [
                'voice' => 'marin'
            ]
        ],
        'delegation' => [
            'type' => 'client'
        ]
    ]
];

$ch = curl_init($acceptUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $OPENAI_API_KEY,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode(
        $sessionConfiguration,
        JSON_UNESCAPED_UNICODE
    ),
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);

$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// ======================================================
// 10. FEHLERBEHANDLUNG
// ======================================================

if ($response === false) {
    sipLog('cURL error: ' . $curlError);

    respond(500, [
        'error' => 'OpenAI request failed'
    ]);
}

if ($httpCode < 200 || $httpCode >= 300) {

    sipLog(
        'OpenAI accept failed. HTTP ' .
            $httpCode . ' Response: ' . $response
    );

    respond(500, [
        'error' => 'OpenAI could not accept the call',
        'http_status' => $httpCode
    ]);
}

// ======================================================
// 11. ERFOLG
// ======================================================

sipLog('SIP session accepted: ' . $sessionId);

respond(200, [
    'success' => true,
    'message' => 'SIP session accepted'
]);
