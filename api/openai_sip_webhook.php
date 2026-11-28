
<?php
declare(strict_types=1);

// ============================================
// OPENAI SIP WEBHOOK DEBUG LOGGING
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$webhookLogFile = __DIR__ . '/openai_sip_debug.log';

function sipWebhookLog(string $message, array $context = []): void
{
    global $webhookLogFile;

    $timestamp = date('Y-m-d H:i:s');

    $contextText = !empty($context)
        ? ' | ' . json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        )
        : '';

    error_log(
        '[' . $timestamp . '] ' . $message . $contextText . PHP_EOL,
        3,
        $webhookLogFile
    );
}

// ============================================
// EXCEPTION HANDLER
// ============================================

set_exception_handler(function (Throwable $e): void {
    sipWebhookLog('UNCAUGHT EXCEPTION', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
});

// ============================================
// FATAL ERROR HANDLER
// ============================================

register_shutdown_function(function (): void {
    $error = error_get_last();

    if ($error !== null) {
        $fatalTypes = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR
        ];

        if (in_array($error['type'], $fatalTypes, true)) {
            sipWebhookLog('FATAL PHP ERROR', [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        }
    }
});

// ============================================
// INITIALIZATION
// ============================================

header('Content-Type: application/json; charset=utf-8');

sipWebhookLog('WEBHOOK SCRIPT START', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'remote' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

// ============================================
// ENVIRONMENT
// ============================================

sipWebhookLog('Loading envloader.php');

try {
    require_once __DIR__ . '/envloader.php';
} catch (Throwable $e) {
    sipWebhookLog('ENVLOADER FAILED', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Environment loading failed'
    ]);

    exit;
}

sipWebhookLog('ENVLOADER LOADED');

$openAiApiKey = $_ENV['API_KEY_OPENAI'] ?? '';
$webhookSecret = $_ENV['OPENAI_WEBHOOK_SECRET'] ?? '';

if (!$openAiApiKey || !$webhookSecret) {
    sipWebhookLog('CONFIGURATION MISSING', [
        'api_key_present' => !empty($openAiApiKey),
        'webhook_secret_present' => !empty($webhookSecret)
    ]);

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Server configuration missing'
    ]);

    exit;
}

sipWebhookLog('CONFIGURATION CHECK OK');

// ============================================
// RAW REQUEST
// ============================================

sipWebhookLog('READING REQUEST BODY');

$rawBody = file_get_contents('php://input');

if ($rawBody === false || $rawBody === '') {
    sipWebhookLog('EMPTY REQUEST BODY');

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Empty request body'
    ]);

    exit;
}

sipWebhookLog('REQUEST BODY RECEIVED', [
    'length' => strlen($rawBody)
]);

// ============================================
// HEADERS
// ============================================

sipWebhookLog('READING HEADERS');

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

sipWebhookLog('WEBHOOK HEADERS CHECK', [
    'webhook_id_present' => !empty($webhookId),
    'timestamp_present' => !empty($webhookTimestamp),
    'signature_present' => !empty($webhookSignature)
]);

if (!$webhookId || !$webhookTimestamp || !$webhookSignature) {
    sipWebhookLog('REQUIRED WEBHOOK HEADERS MISSING');

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Missing headers'
    ]);

    exit;
}

// ============================================
// SIGNATURE VERIFICATION
// ============================================

sipWebhookLog('STARTING SIGNATURE VERIFICATION');

// Timestamp check (5 minutes)

if (!ctype_digit((string)$webhookTimestamp)) {
    sipWebhookLog('INVALID WEBHOOK TIMESTAMP FORMAT');

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid timestamp'
    ]);

    exit;
}

if (abs(time() - (int)$webhookTimestamp) > 300) {
    sipWebhookLog('WEBHOOK TIMESTAMP EXPIRED');

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Expired webhook'
    ]);

    exit;
}

// Remove whsec_ prefix

$secret = $webhookSecret;

if (str_starts_with($secret, 'whsec_')) {
    $secret = substr($secret, 6);
}

$decodedSecret = base64_decode($secret, true);

if ($decodedSecret === false) {
    sipWebhookLog('WEBHOOK SECRET BASE64 DECODING FAILED');

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid webhook secret'
    ]);

    exit;
}

// Signed content:
// webhook-id.webhook-timestamp.raw-body

$signedContent = $webhookId . '.' . $webhookTimestamp . '.' . $rawBody;

$expectedSignature = base64_encode(
    hash_hmac('sha256', $signedContent, $decodedSecret, true)
);

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

sipWebhookLog('SIGNATURE VERIFICATION RESULT', [
    'valid' => $signatureValid
]);

if (!$signatureValid) {
    sipWebhookLog('SIGNATURE VERIFICATION FAILED');

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid signature'
    ]);

    exit;
}

sipWebhookLog('SIGNATURE VERIFIED');

// ============================================
// PARSE EVENT
// ============================================

sipWebhookLog('PARSING EVENT JSON');

$event = json_decode($rawBody, true);

if (!is_array($event)) {
    sipWebhookLog('JSON PARSING FAILED', [
        'json_error' => json_last_error_msg()
    ]);

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON'
    ]);

    exit;
}

$eventType = $event['type'] ?? 'unknown';

sipWebhookLog('EVENT RECEIVED', [
    'event_type' => $eventType
]);

// ============================================
// EVENT CHECK
// ============================================

if ($eventType !== 'live.transport.incoming') {

    sipWebhookLog('EVENT IGNORED', [
        'event_type' => $eventType
    ]);

    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'Event ignored'
    ]);

    exit;
}

sipWebhookLog('INCOMING LIVE TRANSPORT EVENT DETECTED');

if (($event['data']['type'] ?? '') !== 'sip') {

    sipWebhookLog('NON SIP EVENT RECEIVED', [
        'data_type' => $event['data']['type'] ?? 'unknown'
    ]);

    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'Non SIP event ignored'
    ]);

    exit;
}

sipWebhookLog('SIP EVENT DETECTED');

// ============================================
// SESSION ID
// ============================================

$sessionId = $event['data']['session_id'] ?? '';

if (!$sessionId) {

    sipWebhookLog('SESSION ID MISSING');

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Missing session ID'
    ]);

    exit;
}

sipWebhookLog('SESSION ID RECEIVED', [
    'session_id' => $sessionId
]);

// ============================================
// ACCEPT LIVE SIP CALL
// ============================================

sipWebhookLog('PREPARING OPENAI ACCEPT REQUEST');

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

sipWebhookLog('INITIALIZING CURL');

$ch = curl_init($acceptUrl);

if ($ch === false) {

    sipWebhookLog('CURL INITIALIZATION FAILED');

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'CURL initialization failed'
    ]);

    exit;
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $openAiApiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode(
        $sessionConfiguration,
        JSON_UNESCAPED_UNICODE
    ),
    CURLOPT_TIMEOUT => 15
]);

sipWebhookLog('SENDING ACCEPT REQUEST TO OPENAI');

// ============================================
// OPENAI RESPONSE
// ============================================

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

sipWebhookLog('OPENAI RESPONSE RECEIVED', [
    'http_code' => $httpCode,
    'curl_error' => $curlError,
    'response_length' => is_string($response)
        ? strlen($response)
        : 0
]);

// ============================================
// ERROR HANDLING
// ============================================

if ($response === false || $httpCode < 200 || $httpCode >= 300) {

    sipWebhookLog('OPENAI ACCEPT REQUEST FAILED', [
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'response' => is_string($response)
            ? substr($response, 0, 2000)
            : null
    ]);

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Call acceptance failed'
    ]);

    exit;
}

sipWebhookLog('OPENAI ACCEPT REQUEST SUCCESS');

// ============================================
// SUCCESS
// ============================================

http_response_code(200);

echo json_encode([
    'success' => true,
    'message' => 'SIP session accepted'
]);

sipWebhookLog('WEBHOOK SCRIPT COMPLETED');

exit;
