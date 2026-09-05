<?php

// Aktiviert das Schreiben von Fehlern in eine benutzerdefinierte Datei
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/my_php_errors.log');
require_once "cors.php";
require_once "config.php";
require_once "envloader.php";
require_once "database.php";
require_once "rate_limiter.php";

// 1. INPUT ERFASSEN
$action = $_POST['action'] ?? null;
$clientId = $_POST['client_id'] ?? null;
$callType = $_POST['call_type'] ?? 'call_1';
$directPhoneNumber = $_POST['phone_number'] ?? null;

// Falls der Aufruf von Vapi (Webhook) kommt, ist es ein JSON-Body:
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$action && isset($input['message']['type'])) {
    $action = $input['message']['type'];
}

// DB Verbindung herstellen
$dbInstance = new Database();
$db = $dbInstance->getConnection();

// --- LOGIK-WEICHE ---

switch ($action) {
    case 'start':
        if ($clientId) {
            initializeCallStatus($db, $clientId, $callType);
            executeCall($db, $clientId, 'tel1', 1, $callType);
            echo json_encode(["success" => true, "message" => "Anruf ($callType) für Client $clientId gestartet."]);
            exit;
        } else {
            echo json_encode(["success" => false, "message" => "Keine Client ID übergeben."]);
            exit;
        }
        break;

    case 'test_call':
        checkRateLimit('landingpage_test_call', 2, 3600);
        if ($directPhoneNumber) {
            executeDirectTestCall($directPhoneNumber);
            echo json_encode(["success" => true, "message" => "Testanruf gestartet."]);
            exit;
        } else {
            echo json_encode(["success" => false, "message" => "Keine Telefonnummer übergeben."]);
            exit;
        }
        break;

    case 'tool-calls':
        handleVapiToolCall($db, $input);
        exit;

    case 'end-of-call-report':
        handleVapiWebhook($db, $input);
        echo json_encode(["success" => true]);
        exit;

    default:
        echo json_encode(["success" => false, "message" => "Aktion nicht erkannt."]);
        exit;
}

// --- FUNKTIONEN ---

function executeCall($db, $clientId, $telType, $cycle, $callType = 'call_1')
{
    // Konsolen-/Server-Log für den Start des Anrufs (Versuch 1 oder 2)
    error_log("CALL START [Versuch $cycle]: Client ID $clientId | Typ: $callType | Nummerntyp: $telType");

    $stmt = $db->prepare("SELECT title, firstname, lastname, tel1, tel2, call_1, call_2, call_3, medication_1, medication_2, medication_3 FROM clients WHERE id = ? AND status = 'active'");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        error_log("VAPI ERROR: Client ID $clientId nicht in Datenbank gefunden oder inaktiv.");
        return;
    }

    $phone = ($telType === 'tel1') ? ($client['tel1'] ?? '') : ($client['tel2'] ?? '');

    if (empty($phone)) {
        error_log("VAPI ERROR: Keine Telefonnummer ($telType) für Client ID $clientId vorhanden.");
        return;
    }

    // Telefonnummer formatieren (E.164 Pflicht für Vapi)
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (strpos($phone, '0') === 0) {
        $phone = '+49' . substr($phone, 1);
    }

    $titlePrefix = !empty($client['title']) ? trim($client['title']) . ' ' : '';
    $fullName = trim(($client['firstname'] ?? '') . ' ' . ($client['lastname'] ?? ''));

    // Medikation bestimmen
    $currentMedication = '';
    $callTimeText = '';

    switch ($callType) {
        case 'call_2':
            $currentMedication = trim($client['medication_2'] ?? '');
            $callTimeText = trim($client['call_2'] ?? '');
            break;
        case 'call_3':
            $currentMedication = trim($client['medication_3'] ?? '');
            $callTimeText = trim($client['call_3'] ?? '');
            break;
        case 'call_1':
        default:
            $currentMedication = trim($client['medication_1'] ?? '');
            $callTimeText = trim($client['call_1'] ?? '');
            break;
    }

    if (!empty($currentMedication)) {
        $medicationPromptSection = "MEDIKATION HINWEIS:\nDer Klient muss zu dieser Zeit folgende Medikamente einnehmen: \"$currentMedication\". Erkundige dich höflich, ob die Einnahme geklappt hat bzw. erinnere freundlich daran.";
    } else {
        $medicationPromptSection = "MEDIKATION HINWEIS:\nFür diesen Anruf ist keine spezifische Medikamenteneinnahme hinterlegt.";
    }

    $systemPrompt = <<<PROMPT
Du bist ein empathischer, aufmerksamer Telefon-Assistent für die Seniorenbetreuung von Dschuliana Kär.

ZIEL DES ANRUFS:
Kurz erfragen, wie es dem Klienten geht und ob alles in Ordnung ist. 

$medicationPromptSection

VERHALTENSREGELN UND REAKTION AUF UNWOHLSEIN:
1. Grundhaltung: Antworte immer extrem kurz (max. 1-2 Sätze), verständlich und einfühlsam.
2. Begrüße mit "Guten Tag..." vor 18 Uhr und "Guten Abend..." ab 18 Uhr.
3. Wenn der Klient sagt, dass alles gut ist und die Medikamente (falls vorhanden) eingenommen wurden:
   Verabschiede dich höflich ("Schön zu hören! Ich wünsche Ihnen einen schönen Tag bzw. Abend. Auf Wiederhören.") und beende das Gespräch umgehend über die `endCall`-Funktion.
4. Wenn der Klient äußert, dass es ihm SCHLECHT geht oder er Hilfe braucht:
   a) Reagiere mit großem Mitgefühl und frage kurz nach den konkreten Beschwerden/Gründen.
   b) Informiere den Klienten ausdrücklich: "Soll ich Ihre Notfallkontakte darüber informieren, damit jemand nach Ihnen sieht?"
   c) Wenn der Klient JA sagt:
      - Rufe SOFORT die Funktion `triggerEmergencyCall` auf.
      - Sage dem Klienten kurz: "Ich habe Ihre Notfallkontakte sofort benachrichtigt. Es kümmert sich jemand um Sie. Gute Besserung!"
      - Beende danach das Gespräch höflich über `endCall`.
   d) Wenn der Klient NEIN sagt:
      - Wünsche ihm gute Besserung und beende den Anruf höflich.
PROMPT;

    $payload = [
        'assistantId' => VAPI_ASSISTANT_ID,
        'phoneNumberId' => VAPI_PHONE_ID,
        'assistantOverrides' => [
            'firstMessage' => "Guten Tag " . $titlePrefix . $fullName . ", hier spricht die Assistenz von Dschuliana Kär. Ich wollte kurz fragen, ob bei Ihnen alles in Ordnung ist?",
            'model' => [
                'provider' => 'azure-openai',
                'model'    => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => $systemPrompt
                    ]
                ],
                'tools' => [
                    ['type' => 'endCall'],
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'triggerEmergencyCall',
                            'description' => 'Aktiviert den Notfallalarm, benachrichtigt die Notfallkontakte und speichert die Beschwerden.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'reason' => [
                                        'type' => 'string',
                                        'description' => 'Die konkreten Beschwerden oder Gründe.'
                                    ]
                                ],
                                'required' => ['reason']
                            ]
                        ]
                    ]
                ]
            ],
            'endCallFunctionEnabled' => true,
            'variableValues' => [
                'clientId' => (string)$clientId,
                'cycle'    => (string)$cycle,
                'telType'  => $telType,
                'callType' => $callType
            ]
        ],
        'customer' => [
            'number' => $phone
        ],
        'metadata' => [
            'clientId' => (string)$clientId,
            'cycle'    => (string)$cycle,
            'telType'  => $telType,
            'callType' => $callType
        ]
    ];

    $ch = curl_init('https://api.vapi.ai/call/phone');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . VAPI_PRIVATE_KEY,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // DB-Status aktualisieren, welches Telefon genutzt wurde
    $updateStmt = $db->prepare("UPDATE call_status SET last_tel_used = ?, attempt_cycle = ? WHERE client_id = ?");
    $updateStmt->execute([$telType, $cycle, $clientId]);

    if ($httpCode !== 201) {
        $maskedPhone = substr($phone, 0, 5) . '******' . substr($phone, -2);
        error_log("VAPI ERROR [Client ID: $clientId | Tel: $maskedPhone]: HTTP $httpCode | cURL Err: $curlError | Response: $response");
    }
}

function handleVapiToolCall($db, $data)
{
    $toolCalls = $data['message']['toolCalls'] ?? [];

    foreach ($toolCalls as $toolCall) {
        if (($toolCall['function']['name'] ?? '') === 'triggerEmergencyCall') {
            $args = json_decode($toolCall['function']['arguments'] ?? '{}', true);
            $reason = $args['reason'] ?? 'Unwohlsein (keine näheren Angaben)';

            $metadata = $data['message']['call']['metadata'] ?? [];
            $clientId = $metadata['clientId'] ?? null;

            if ($clientId) {
                logClientIncident($db, (int)$clientId, $reason);
                updateCallStatus($db, (int)$clientId, 'incident_reported', "Klient meldet Unwohlsein: " . $reason);
                notifyEmergencyContacts($db, (int)$clientId, $reason);
            }

            echo json_encode([
                'results' => [
                    [
                        'toolCallId' => $toolCall['id'],
                        'result' => 'Notfallkontakte wurden erfolgreich informiert.'
                    ]
                ]
            ]);
            exit;
        }
    }
}

function logClientIncident(PDO $db, int $clientId, string $reason): void
{
    $stmt = $db->prepare("INSERT INTO client_incidents (client_id, reason, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$clientId, $reason]);
}

function handleVapiWebhook($db, $data)
{
    $endedReason = $data['message']['endedReason'] ?? '';
    $metadata = $data['message']['call']['metadata'] ?? [];

    $clientId = $metadata['clientId'] ?? null;
    $cycle    = (int)($metadata['cycle'] ?? 1);
    $telType  = $metadata['telType'] ?? 'tel1';
    $callType = $metadata['callType'] ?? 'call_1';

    if (!$clientId) return;

    if ($endedReason === 'customer-ended-call' || $endedReason === 'assistant-ended-call') {
        $summary = $data['message']['analysis']['summary'] ?? '';

        $stmt = $db->prepare("SELECT status FROM call_status WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $currentStatus = $stmt->fetchColumn();

        if ($currentStatus !== 'incident_reported') {
            updateCallStatus($db, $clientId, 'completed', $summary);
        }
    } else {
        // Klient hat nicht abgehoben -> Eskalation auslösen
        error_log("VAPI INFO: Anruf nicht erfolgreich (Reason: $endedReason). Starte Eskalation/Retry...");
        escalateCall($db, $clientId, $cycle, $telType, $callType);
    }
}

function escalateCall($db, $clientId, $cycle, $telType, $callType = 'call_1')
{
    $stmt = $db->prepare("SELECT title, firstname, lastname, tel1, tel2 FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    // Versuche Erstnummer -> Zweitnummer (falls vorhanden im selben Zyklus)
    if ($telType === 'tel1' && !empty($client['tel2'])) {
        error_log("ESKALATION: Wechsel von tel1 zu tel2 für Client ID $clientId (Cycle $cycle)");
        executeCall($db, $clientId, 'tel2', $cycle, $callType);
    } elseif ($cycle === 1) {
        // Nach Versuch 1 (egal ob tel1 oder tel2): Planen für Zyklus 2 in 15 Minuten
        $stmt = $db->prepare("UPDATE call_status SET status = 'retry_scheduled', attempt_cycle = 2, scheduled_time = DATE_ADD(NOW(), INTERVAL 2 MINUTE) WHERE client_id = ?");
        $stmt->execute([$clientId]);
        error_log("RETRY SCHEDULED: Klient ID $clientId für Retry (Versuch 2) in 15 Min vorgemerkt.");
    } else {
        // Versuch 2 ebenfalls fehlgeschlagen
        updateCallStatus($db, $clientId, 'failed', 'Niemand erreicht nach 2 Zyklen.');
        notifyEmergencyContacts($db, $clientId, "Klient war nach mehreren Versuchen telefonisch nicht erreichbar.");
        error_log("CALL FAILED: Klient ID $clientId nach 2 Zyklen nicht erreicht. Notfallkontakte benachrichtigt.");
    }
}

function notifyEmergencyContacts(PDO $db, int $clientId, string $customReason = null): void
{
    $stmt = $db->prepare("SELECT lastname, firstname, title, sms_status FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) return;

    $clientName = trim(($client['title'] ?? '') . ' ' . $client['firstname'] . " " . $client['lastname']);
    $smsEnabled = !empty($client['sms_status']);

    $stmtContacts = $db->prepare("SELECT lastname, firstname, email, phone FROM contacts WHERE client_id = ?");
    $stmtContacts->execute([$clientId]);
    $contacts = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

    if (empty($contacts)) return;

    $subject = "NOTFALL-ALARM: $clientName benötigt Hilfe!";

    if ($customReason) {
        $messageText = "ACHTUNG: $clientName hat im Telefonat angegeben, dass es ihm/ihr nicht gut geht.\n\nSymptome/Grund: $customReason\n\nBitte werden Sie umgehend aktiv!";
    } else {
        $messageText = "ACHTUNG: $clientName konnte nach allen automatischen Anrufversuchen nicht erreicht werden. Bitte werden Sie umgehend aktiv!";
    }

    foreach ($contacts as $contact) {
        if (!empty($contact['email'])) {
            $headers = [
                'From' => 'no-reply@giulianacare.de',
                'Reply-To' => 'support@giulianacare.de',
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Mailer' => 'PHP/' . phpversion()
            ];
            mail($contact['email'], $subject, $messageText, $headers);
        }

        $phoneToSms = $contact['phone'] ?? null;
        if ($smsEnabled && !empty($phoneToSms)) {
            sendSmsNotification($phoneToSms, $messageText);
        }
    }
}

function sendSmsNotification(string $phoneNumber, string $message): void
{
    if (!defined('TWILIO_ACCOUNT_SID') || !defined('TWILIO_AUTH_TOKEN') || !defined('TWILIO_PHONE_NUMBER')) {
        error_log("Twilio SMS-Fehler: Zugangsdaten fehlen in config.php.");
        return;
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";

    $postData = [
        'From' => TWILIO_PHONE_NUMBER,
        'To'   => $phoneNumber,
        'Body' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 201) {
        error_log("Twilio SMS-Fehler (Status $httpCode): " . $response);
    }
}

function initializeCallStatus($db, $clientId, $callType = 'call_1')
{
    $stmt = $db->prepare("INSERT INTO call_status (client_id, attempt_cycle, last_tel_used, call_type, status, scheduled_time) 
                          VALUES (?, 1, 'tel1', ?, 'calling', NOW()) 
                          ON DUPLICATE KEY UPDATE status = 'calling', attempt_cycle = 1, last_tel_used = 'tel1', call_type = ?, scheduled_time = NOW()");
    $stmt->execute([$clientId, $callType, $callType]);
}

function updateCallStatus($db, $clientId, $status, $summary)
{
    $stmt = $db->prepare("UPDATE call_status SET status = ?, summary = ? WHERE client_id = ?");
    $stmt->execute([$status, $summary, $clientId]);
}

function executeDirectTestCall($phone)
{
    $payload = [
        'assistantId' => VAPI_ASSISTANT_ID,
        'phoneNumberId' => VAPI_PHONE_ID,
        'assistantOverrides' => [
            'firstMessage' => "Guten Tag! Schön, dass Sie die Telefon Assistenz von dschuljiana Kaer ausprobieren. Dies ist ein automatisierter Testanruf.",
            'model' => [
                'provider' => 'azure-openai',
                'model'    => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'Du bist ein freundlicher Telefon-Assistent für Dschuliana Kär. Halte dich kurz.'
                    ]
                ]
            ],
            'endCallFunctionEnabled' => true
        ],
        'customer' => [
            'number' => $phone
        ],
        'metadata' => [
            'type' => 'landingpage_test'
        ]
    ];

    $ch = curl_init('https://api.vapi.ai/call/phone');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . VAPI_PRIVATE_KEY,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 201) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Vapi Fehler (Status $httpCode): " . $response
        ]);
        exit;
    }
}
