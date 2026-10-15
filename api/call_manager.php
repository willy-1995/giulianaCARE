<?php
// 1. Output-Buffering aktivieren (schützt vor kaputten JSON-Responses durch Whitespaces/PHP-Warnings)
ob_start();

// Fehler-Logging konfigurieren
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/my_php_errors.log');

// Config & Database laden
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/database.php";

// --- 1. WEBHOOK-JSON SOFORT AUSLESEN ---
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    file_put_contents(__DIR__ . '/vapi_webhook_debug.log', date('Y-m-d H:i:s') . " - " . $rawInput . "\n\n", FILE_APPEND);
}
$input = json_decode($rawInput, true) ?? [];

// --- 2. VAPI WEBHOOKS DIREKT ABFANGEN ---
$messageType = $input['message']['type'] ?? '';

if ($messageType === 'tool-calls' || isset($input['message']['toolCalls']) || isset($input['toolCalls'])) {
    $dbInstance = new Database();
    $db = $dbInstance->getConnection();
    handleVapiToolCall($db, $input);
    exit;
}

$callStatus = $input['message']['status'] ?? '';

if ($messageType === 'end-of-call-report' || ($messageType === 'status-update' && $callStatus === 'ended')) {
    $dbInstance = new Database();
    $db = $dbInstance->getConnection();
    handleVapiWebhook($db, $input);
    echo json_encode(["success" => true]);
    exit;
}

// --- 3. STANDARD FORMULAR- & API-ANFRAGEN BEARBEITEN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . "/cors.php";
    require_once __DIR__ . "/envloader.php";
    require_once __DIR__ . "/rate_limiter.php";

    $action = $_POST['action'] ?? null;
    $clientId = $_POST['clientId'] ?? $_POST['client_id'] ?? null;
    $callType = $_POST['call_type'] ?? 'call_1';
    $directPhoneNumber = $_POST['phone_number'] ?? null;

    $dbInstance = new Database();
    $db = $dbInstance->getConnection();

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

        default:
            echo json_encode(["success" => false, "message" => "Aktion nicht erkannt."]);
            exit;
    }
}

// --- FUNKTIONEN ---

function handleVapiToolCall($db, $data)
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    $toolCalls = $data['message']['toolCalls'] ?? $data['message']['toolCallList'] ?? [];
    $metadata = $data['message']['call']['metadata'] ?? $data['message']['metadata'] ?? [];

    $clientId = isset($metadata['clientId']) ? (int)$metadata['clientId'] : null;
    $results = [];
    $reason = 'Unwohlsein / Notfall geäußert';

    foreach ($toolCalls as $toolCall) {
        $toolCallId = $toolCall['id'] ?? null;
        $functionName = $toolCall['function']['name'] ?? $toolCall['name'] ?? '';

        if ($functionName === 'triggerEmergencyCall') {
            $rawArgs = $toolCall['function']['arguments'] ?? $toolCall['arguments'] ?? '{}';
            $args = is_array($rawArgs) ? $rawArgs : json_decode($rawArgs, true);
            $reason = $args['reason'] ?? $reason;

            if ($clientId && $clientId > 0) {
                try {
                    logClientIncident($db, $clientId, $reason);
                    updateCallStatus($db, $clientId, 'incident_reported', "Klient meldet Unwohlsein: " . $reason);
                } catch (Exception $e) {
                    error_log("DB ERROR: " . $e->getMessage());
                }
            }

            $results[] = [
                'toolCallId' => $toolCallId,
                'result'     => 'Notfallkontakte wurden erfolgreich informiert und der Vorfall wurde im System protokolliert.'
            ];
        }
    }

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    $jsonResponse = json_encode(['results' => $results]);

    if (function_exists('fastcgi_finish_request')) {
        echo $jsonResponse;
        session_write_close();
        fastcgi_finish_request();
    } else {
        header("Content-Length: " . strlen($jsonResponse));
        header("Connection: close");
        echo $jsonResponse;
        flush();
    }

    if ($clientId && $clientId > 0) {
        try {
            notifyEmergencyContacts($db, $clientId, $reason);
            error_log("SUCCESS: Emergency contacts notified in background for client $clientId");
        } catch (Exception $e) {
            error_log("NOTIFICATION ERROR: " . $e->getMessage());
        }
    }

    exit;
}

function executeCall($db, $clientId, $telType, $cycle, $callType = 'call_1')
{
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
        // Wenn tel2 fehlt, direkt in die nächste Eskalationsstufe springen
        if ($telType === 'tel1') {
            escalateCall($db, $clientId, $cycle, 'tel1', $callType);
        }
        return;
    }

    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (strpos($phone, '0') === 0) {
        $phone = '+49' . substr($phone, 1);
    }

    $titlePrefix = !empty($client['title']) ? trim($client['title']) . ' ' : '';
    $fullName = trim(($client['firstname'] ?? '') . ' ' . ($client['lastname'] ?? ''));

    $currentMedication = '';
    switch ($callType) {
        case 'call_2':
            $currentMedication = trim($client['medication_2'] ?? '');
            break;
        case 'call_3':
            $currentMedication = trim($client['medication_3'] ?? '');
            break;
        case 'call_1':
        default:
            $currentMedication = trim($client['medication_1'] ?? '');
            break;
    }

    $medicationPromptSection = !empty($currentMedication)
        ? "MEDIKATION HINWEIS:\nDer Klient muss zu dieser Zeit folgende Medikamente einnehmen: \"$currentMedication\". Erkundige dich höflich, ob die Einnahme geklappt hat bzw. erinnere freundlich daran."
        : "MEDIKATION HINWEIS:\nFür diesen Anruf ist keine spezifische Medikamenteneinnahme hinterlegt.";

    $systemPrompt = <<<PROMPT
Du bist ein empathischer, aufmerksamer Telefon-Assistent für die Seniorenbetreuung von Dschuliana Kär.
ZIEL DES ANRUFS:
Kurz erfragen, wie es dem Klienten geht, immer an die Medikamente erinnern bzw. diese abfragen und sicherstellen, dass alles in Ordnung ist. 
$medicationPromptSection
VERHALTENSREGELN UND REAKTIONEN:
1. Grundhaltung: Antworte immer extrem kurz (max. 1-2 Sätze pro Gesprächsschritt), verständlich und einfühlsam.
2. Den Klienten immer sietzen
3. Begrüße mit "Guten Tag..." vor 18 Uhr und "Guten Abend..." ab 18 Uhr.
4. WICHTIGE PFLICHTABFRAGE (Gilt IMMER): Bevor nach Notfallkontakten gefragt wird oder das Gespräch beendet wird, MUSS IMMER zuerst die Medikamenteneinnahme abgefragt bzw. daran erinnert werden.
5. Wenn der Klient sagt, dass alles gut ist: Verabschiede dich höflich mit "Schön zu hören! Ich wünsche Ihnen einen schönen Tag. Auf Wiederhören!" und sprich absolut nichts mehr.
6. Wenn es dem Klienten schlecht geht: Frage nach Beschwerden, biete Notfallkontakt-Benachrichtigung an und handle entsprechend.
PROMPT;

    $payload = [
        'assistantId' => VAPI_ASSISTANT_ID,
        'phoneNumberId' => VAPI_PHONE_ID,
        'voicemailId' => null,
        'assistantOverrides' => [
            'firstMessage' => "Guten Tag " . $titlePrefix . $fullName . ", hier spricht die Assistenz von Dschuliana Kär. Ich wollte kurz fragen, ob bei Ihnen alles in Ordnung ist?",
            'endCallPhrases' => ["Auf Wiederhören!", "Einen schönen Tag.", "Einen schönen Abend."],
            'endCallFunctionEnabled' => false,
            'voicemailMessage' => '',
            'model' => [
                'provider' => 'azure-openai',
                'model'    => 'gpt-5.4-mini',
                'messages' => [['role' => 'system', 'content' => $systemPrompt]],
                'tools' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'triggerEmergencyCall',
                            'description' => 'Aktiviert den Notfallalarm, benachrichtigt die Notfallkontakte und speichert die Beschwerden.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => ['reason' => ['type' => 'string', 'description' => 'Die konkreten Beschwerden oder Gründe.']],
                                'required' => ['reason']
                            ]
                        ],
                        'async' => false
                    ]
                ]
            ],
            'variableValues' => [
                'clientId' => (string)$clientId,
                'cycle'    => (string)$cycle,
                'telType'  => $telType,
                'callType' => $callType
            ]
        ],
        'customer' => ['number' => $phone],
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

    // PRODUKTIONS-SICHERUNG: Status nur bei Erfolg (HTTP 201) setzen, sonst Eskalation triggern
    if ($httpCode === 201) {
        $updateStmt = $db->prepare("UPDATE call_status SET last_tel_used = ?, attempt_cycle = ?, status = 'calling' WHERE client_id = ?");
        $updateStmt->execute([$telType, $cycle, $clientId]);
    } else {
        $maskedPhone = substr($phone, 0, 5) . '******' . substr($phone, -2);
        error_log("VAPI CRITICAL ERROR [Client ID: $clientId | Tel: $maskedPhone]: HTTP $httpCode | cURL Err: $curlError | Response: $response");
        escalateCall($db, $clientId, $cycle, $telType, $callType);
    }
}

function logClientIncident(PDO $db, int $clientId, string $reason): void
{
    $stmt = $db->prepare("INSERT INTO client_incidents (client_id, reason, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$clientId, $reason]);
}

function handleVapiWebhook($db, $data)
{
    $message = $data['message'] ?? [];
    $endedReason = $message['endedReason'] ?? $message['call']['endedReason'] ?? '';
    $summary = $message['analysis']['summary'] ?? '';

    $metadata = $message['call']['metadata'] ?? $message['metadata'] ?? $data['metadata'] ?? [];

    $clientId = isset($metadata['clientId']) ? (int)$metadata['clientId'] : null;
    $cycle    = isset($metadata['cycle']) ? (int)$metadata['cycle'] : 1;
    $telType  = $metadata['telType'] ?? 'tel1';
    $callType = $metadata['callType'] ?? 'call_1';

    if (!$clientId && isset($message['call']['assistantOverrides']['variableValues']['clientId'])) {
        $clientId = (int)$message['call']['assistantOverrides']['variableValues']['clientId'];
    }

    if (!$clientId) {
        error_log("VAPI WEBHOOK ERROR: Konnte Client ID nicht auslesen! Payload: " . json_encode($data));
        return;
    }

    // Prüfen, ob es eine Mailbox war (anhand von Summary oder Voicemail-Erkennung)
    $isVoicemail = stripos($summary, 'voicemail') !== false || stripos($summary, 'Anrufbeantworter') !== false || stripos($summary, 'nachricht') !== false;

    // Nur wenn es KEINE Mailbox war UND ein echtes Gespräch stattfand, gilt es als 'completed'
    if (($endedReason === 'customer-ended-call' || $endedReason === 'assistant-ended-call') && !$isVoicemail) {
        $stmt = $db->prepare("SELECT status FROM call_status WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $currentStatus = $stmt->fetchColumn();

        if ($currentStatus !== 'incident_reported') {
            updateCallStatus($db, $clientId, 'completed', $summary);
        }
    } else {
        // Mailbox oder echter Nichterfolg -> Direkt in die Eskalation (tel2 / Retry)
        error_log("VAPI INFO: Anruf nicht erfolgreich oder Mailbox erkannt (Reason: $endedReason, Voicemail: " . ($isVoicemail ? 'Ja' : 'Nein') . "). Starte Eskalation für Client ID $clientId...");
        escalateCall($db, $clientId, $cycle, $telType, $callType);
    }
}

function escalateCall($db, $clientId, $cycle, $telType, $callType = 'call_1')
{
    error_log("ESKALATION AUFGERUFEN: Client=$clientId | Cycle=$cycle | TelType=$telType | CallType=$callType");

    $stmt = $db->prepare("SELECT title, firstname, lastname, tel1, tel2 FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    // Sequenzieller Ablauf: Wenn tel1 fehlschlägt und tel2 existiert -> jetzt tel2 anrufen
    if ($telType === 'tel1' && !empty($client['tel2'])) {
        error_log("ESKALATION: Wechsel von tel1 zu tel2 für Client ID $clientId (Cycle $cycle)");
        executeCall($db, $clientId, 'tel2', $cycle, $callType);
    }
    // Wenn Zyklus 1 komplett durch ist (weder tel1 noch tel2 erreichbar) -> 15 Minuten Retry ansetzen
    elseif ($cycle === 1) {
        $stmt = $db->prepare("UPDATE call_status SET status = 'retry_scheduled', attempt_cycle = 2, scheduled_time = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE client_id = ?");
        $stmt->execute([$clientId]);
        error_log("RETRY SCHEDULED: Klient ID $clientId für Retry (Versuch 2 in 15 Min) vorgemerkt.");
    }
    // Wenn Zyklus 2 ebenfalls komplett durch ist -> Endgültig fehlgeschlagen, Notfallkontakte alarmieren
    else {
        updateCallStatus($db, $clientId, 'failed', 'Niemand erreicht nach 2 Zyklen.');
        notifyEmergencyContacts($db, $clientId, "Klient war nach allen automatischen Anrufversuchen (2 Zyklen auf tel1 und tel2) nicht erreichbar.");
        error_log("CALL FAILED: Klient ID $clientId nach 2 Zyklen endgültig nicht erreicht. Notfallkontakte benachrichtigt.");
    }
}

function notifyEmergencyContacts(PDO $db, int $clientId, ?string $customReason = null): void
{
    if ($clientId <= 0) return;

    $stmt = $db->prepare("SELECT lastname, firstname, title, sms_status FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) return;

    $clientName = trim(($client['title'] ?? '') . ' ' . $client['firstname'] . " " . $client['lastname']);
    $smsEnabled = !empty($client['sms_status']);

    $stmtContacts = $db->prepare("SELECT lastname, firstname, email, tel1 FROM contacts WHERE client_id = ?");
    $stmtContacts->execute([$clientId]);
    $contacts = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

    if (empty($contacts)) return;

    $subject = "NOTFALL-ALARM: $clientName benötigt Hilfe!";
    $messageText = $customReason
        ? "giulianaCARE Warnung: $clientName hat im Telefonat angegeben, dass es ihm/ihr nicht gut geht.\n\nSymptome/Grund: $customReason\n\nBitte werden Sie umgehend aktiv!"
        : "giulianaCARE Warnung: $clientName konnte nach allen automatischen Anrufversuchen nicht erreicht werden. Bitte werden Sie umgehend aktiv!";

    foreach ($contacts as $contact) {
        if (!empty($contact['email'])) {
            $headers = [
                'From' => 'info@giuliana-care.de',
                'Reply-To' => 'info@giuliana-care.de',
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Mailer' => 'PHP/' . phpversion()
            ];
            mail($contact['email'], $subject, $messageText, $headers);
        }

        if ($smsEnabled && !empty($contact['tel1'])) {
            sendSmsNotification($contact['tel1'], $messageText);
        }
    }
}

function sendSmsNotification(string $phoneNumber, string $message): void
{
    if (!defined('TWILIO_ACCOUNT_SID') || !defined('TWILIO_AUTH_TOKEN') || !defined('TWILIO_PHONE_NUMBER')) return;

    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";
    $postData = ['From' => TWILIO_PHONE_NUMBER, 'To' => $phoneNumber, 'Body' => $message];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);
    curl_exec($ch);
    curl_close($ch);
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
    // Testanruf-Funktion (unverändert robust)
    $systemPrompt = "Du bist ein freundlicher Test-Assistent. Verabschiede dich höflich.";
    $payload = [
        'assistantId' => VAPI_ASSISTANT_ID,
        'phoneNumberId' => VAPI_PHONE_ID,
        'assistantOverrides' => [
            'firstMessage' => "Guten Tag! Dies ist ein Testanruf.",
            'model' => ['provider' => 'azure-openai', 'model' => 'gpt-5.4-mini', 'messages' => [['role' => 'system', 'content' => $systemPrompt]]]
        ],
        'customer' => ['number' => $phone]
    ];
    $ch = curl_init('https://api.vapi.ai/call/phone');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . VAPI_PRIVATE_KEY, 'Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
}
