<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

// =====================================================
// ENVIRONMENT
// =====================================================
require_once __DIR__ . '/envloader.php';

$openAiApiKey = $_ENV['API_KEY_OPENAI'] ?? '';
$webhookSecret = $_ENV['OPENAI_WEBHOOK_SECRET'] ?? '';

if (!$openAiApiKey || !$webhookSecret) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server configuration missing'
    ]);
    exit;
}

// =====================================================
// RAW REQUEST
// =====================================================
$rawBody = file_get_contents('php://input');

if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

// =====================================================
// HEADERS
// =====================================================
$headers = function_exists('getallheaders') ? getallheaders() : [];

$normalizedHeaders = [];

foreach ($headers as $key => $value) {
    $normalizedHeaders[strtolower($key)] = $value;
}

$webhookId = $normalizedHeaders['webhook-id'] ?? '';
$webhookTimestamp = $normalizedHeaders['webhook-timestamp'] ?? '';
$webhookSignature = $normalizedHeaders['webhook-signature'] ?? '';

if (!$webhookId || !$webhookTimestamp || !$webhookSignature) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing headers']);
    exit;
}

// =====================================================
// SIGNATURE VERIFICATION
// =====================================================

// Timestamp check (5 minutes)
if (!ctype_digit((string)$webhookTimestamp)) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

if (abs(time() - (int)$webhookTimestamp) > 300) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Expired webhook']);
    exit;
}

// Remove whsec_ prefix
$secret = $webhookSecret;

if (str_starts_with($secret, 'whsec_')) {
    $secret = substr($secret, 6);
}

$decodedSecret = base64_decode($secret, true);

if ($decodedSecret === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Invalid webhook secret']);
    exit;
}

// Signed content:
// webhook-id.webhook-timestamp.raw-body
$signedContent = $webhookId . '.' . $webhookTimestamp . '.' . $rawBody;

$expectedSignature = base64_encode(
    hash_hmac('sha256', $signedContent, $decodedSecret, true)
);

// OpenAI signature header can contain multiple signatures
$signatureValid = false;

foreach (explode(' ', $webhookSignature) as $signatureEntry) {

    if (strpos($signatureEntry, 'v1,') !== 0) {
        continue;
    }

    $providedSignature = substr($signatureEntry, 3);

    if (hash_equals($expectedSignature, $providedSignature)) {
        $signatureValid = true;
        break;
    }
}

if (!$signatureValid) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid signature']);
    exit;
}

// =====================================================
// PARSE EVENT
// =====================================================
$event = json_decode($rawBody, true);

if (!is_array($event)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// =====================================================
// EVENT CHECK
// =====================================================
if (($event['type'] ?? '') !== 'live.transport.incoming') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Event ignored']);
    exit;
}

if (($event['data']['type'] ?? '') !== 'sip') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Non SIP event ignored']);
    exit;
}

$sessionId = $event['data']['session_id'] ?? '';

if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing session ID']);
    exit;
}

// =====================================================
// ACCEPT LIVE SIP CALL
// =====================================================
$acceptUrl = 'https://api.openai.com/v1/live/sessions/'
    . rawurlencode($sessionId)
    . '/accept';

$sessionConfiguration = [
    'session' => [
        'type' => 'live',
        'model' => 'gpt-live-1',
        'instructions' =>
        'Du bist die freundliche telefonische Testassistenz von GiulianaCare. '
            . 'Du sprichst ausschließlich Deutsch. '
            . 'Begrüße den Anrufer kurz und sage: '
            . 'Hallo, hier ist die GiulianaCare Testassistenz. '
            . 'Die Sprachverbindung funktioniert. Wie kann ich dir helfen? '
            . 'Antworte kurz, freundlich und natürlich. '
            . 'Dies ist ein reiner Verbindungstest. '
            . 'Du führst keine medizinischen Aufgaben aus und löst keine Notfallprozesse aus.',
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
        'Authorization: Bearer ' . $openAiApiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($sessionConfiguration),
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

// =====================================================
// ERROR HANDLING
// =====================================================
if ($response === false || $httpCode < 200 || $httpCode >= 300) {

    error_log(
        'OpenAI SIP accept failed. HTTP: '
            . $httpCode
            . ' Error: '
            . $curlError
            . ' Response: '
            . $response
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Call acceptance failed'
    ]);

    exit;
}

// =====================================================
// SUCCESS
// =====================================================
http_response_code(200);

echo json_encode([
    'success' => true,
    'message' => 'SIP session accepted'
]);

exit;
