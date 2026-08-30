<?php
require_once "cors.php";
require_once "config.php";
require_once "envloader.php";
require_once "database.php";
require_once "rate_limiter.php";


// 1. INPUT ERFASSEN
// Falls dein TS-Frontend "action=start&client_id=123" sendet:
$action = $_POST['action'] ?? null;
$clientId = $_POST['client_id'] ?? null;
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
        // HIER: Deine Frontend-ID wird verarbeitet
        if ($clientId) {
            // Initialen Status in der DB setzen, damit wir wissen: "Anruf läuft"
            initializeCallStatus($db, $clientId);

            // Den eigentlichen Anruf bei Vapi auslösen
            executeCall($db, $clientId, 'tel1', 1);

            echo json_encode(["success" => true, "message" => "Anruf für Client $clientId gestartet."]);
        } else {
            echo json_encode(["success" => false, "message" => "Keine Client ID übergeben."]);
        }
        break;

    case 'test_call': // NEU: Der Fall für die Landingpage

        // Erlaubt maximal 2 Testanrufe pro IP-Adresse innerhalb von 1 Stunde (3600 Sek.)
        checkRateLimit('landingpage_test_call', 2, 3600);

        if ($directPhoneNumber) {
            // Wir erstellen ein temporäres Payload ohne DB-Abfrage
            executeDirectTestCall($directPhoneNumber);
            echo json_encode(["success" => true, "message" => "Testanruf gestartet."]);
        } else {
            echo json_encode(["success" => false, "message" => "Keine Telefonnummer übergeben."]);
        }
        break;

    case 'end-of-call-report':
        // HIER: Vapi meldet sich zurück
        handleVapiWebhook($db, $input);
        echo json_encode(["success" => true]);
        break;

    default:
        echo json_encode(["success" => false, "message" => "Aktion nicht erkannt."]);
        break;
}

// --- FUNKTIONEN ---

function executeCall($db, $clientId, $telType, $cycle)
{
    // 1. Klientendaten aus der DB holen (inklusive 'title'!)
    $stmt = $db->prepare("SELECT title, name, tel1, tel2 FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) return;

    $phone = ($telType === 'tel1') ? $client['tel1'] : $client['tel2'];
    if (empty($phone)) return;

    // Titel sicher formatieren (z. B. "Herr " oder leer, falls null)
    $titlePrefix = !empty($client['title']) ? trim($client['title']) . ' ' : '';

    // 2. Vapi API Call vorbereiten
    $payload = [
        'assistantId' => VAPI_ASSISTANT_ID,
        'phoneNumberId' => VAPI_PHONE_ID,
        'assistantOverrides' => [
            // Dynamische Ansprache für den echten Kunden
            'firstMessage' => "Guten Tag " . $titlePrefix . $client['name'] . ", hier spricht die Assistenz von Dschuliana Kär. Ich wollte kurz fragen, ob bei Ihnen alles in Ordnung ist?",

            // Erzwingt das Azure OpenAI Modell aus Frankfurt
            'model' => [
                'provider' => 'azure-openai',
                'model'    => 'gpt-5.4-mini', // Exakt dein Deployment-Name in Azure
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'Du bist ein freundlicher Telefon-Assistent für Dschuliana Kär. Antworte immer kurz, empathisch und ausschließlich auf Deutsch. Wenn sich das Gespräch dem Ende neigt oder der Kunde sich verabschiedet, verabschiede dich höflich und beende den Anruf sofort mit der Auflegen-Funktion.'
                    ]
                ]
            ],

            // Ermöglicht das automatische Auflegen nach der Verabschiedung
            'endCallFunctionEnabled' => true
        ],
        'customer' => [
            'number' => $phone,
            'extension' => [
                'clientId' => $clientId, // Bleibt für den Webhook erhalten
                'cycle'    => $cycle,
                'telType'  => $telType
            ]
        ]
    ];

    // 3. Absenden an Vapi
    $ch = curl_init('https://api.vapi.ai/call/phone');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . VAPI_PRIVATE_KEY,
        'Content-Type: application/json'
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function handleVapiWebhook($db, $data)
{
    $endedReason = $data['message']['endedReason'] ?? '';
    // Hier holt PHP die ID wieder aus dem "Rucksack" (extension)
    $metadata = $data['message']['customer']['extension'] ?? [];

    $clientId = $metadata['clientId'] ?? null;
    $cycle    = $metadata['cycle'] ?? 1;
    $telType  = $metadata['telType'] ?? 'tel1';

    if (!$clientId) return;

    // Erfolg: Klient hat das Gespräch normal beendet
    if ($endedReason === 'customer-ended-call' || $endedReason === 'assistant-ended-call') {
        $summary = $data['message']['analysis']['summary'] ?? '';
        updateCallStatus($db, $clientId, 'completed', $summary);
    }
    // Fehlschlag: Eskalation
    else {
        escalateCall($db, $clientId, $cycle, $telType);
    }
}

function escalateCall($db, $clientId, $cycle, $telType)
{
    // Klientendaten laden für Tel2 Check
    $stmt = $db->prepare("SELECT name, tel1, tel2 FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($telType === 'tel1' && !empty($client['tel2'])) {
        // Sofort Tel2 anrufen
        executeCall($db, $clientId, 'tel2', $cycle);
    } elseif ($cycle === 1) {
        // Beide Nummern im ersten Durchgang gescheitert -> 15 Min Pause
        $stmt = $db->prepare("UPDATE call_status SET status = 'retry_scheduled', attempt_cycle = 2, scheduled_time = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE client_id = ? AND status = 'calling'");
        $stmt->execute([$clientId]);
    } else {
        // Finaler Fail
        updateCallStatus($db, $clientId, 'failed', 'Niemand erreicht.');
        // HIER NOTFALL ALARM (E-Mail/SMS)
        notifyEmergencyContacts($db, $clientId);
    }
}

// --- BENACHRICHTIGUNGS- & NOTFALLFUNKTIONEN ---

/**
 * Benachrichtigt alle Notfallkontakte eines Klienten per E-Mail und SMS (falls aktiviert).
 */
function notifyEmergencyContacts(PDO $db, int $clientId): void
{
    // 1. Klientendaten inkl. sms_status laden
    $stmt = $db->prepare("SELECT lastname, firstname, title, sms_status FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) return;

    $clientName = trim(($client['title'] ?? '') . ' ' . $client['firstname'] . " " . $client['lastname']);

    // Prüfen, ob SMS-Versand für diesen Klienten aktiviert ist
    $smsEnabled = !empty($client['sms_status']);

    // 2. Notfallkontakte laden
    $stmtContacts = $db->prepare("SELECT lastname, firstname, email, phone FROM contacts WHERE client_id = ?");
    $stmtContacts->execute([$clientId]);
    $contacts = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

    if (empty($contacts)) return;

    $subject = "NOTFALL-ALARM: $clientName wurde nicht erreicht!";
    $messageText = "ACHTUNG: $clientName konnte nach allen automatischen Anrufversuchen nicht erreicht werden. Bitte werden Sie umgehend aktiv!";

    // 3. Kontakte durchlaufen
    foreach ($contacts as $contact) {

        // A) E-Mail versenden (wenn vorhanden)
        if (!empty($contact['email'])) {
            $headers = [
                'From' => 'no-reply@giulianacare.de',
                'Reply-To' => 'support@giulianacare.de',
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Mailer' => 'PHP/' . phpversion()
            ];

            mail($contact['email'], $subject, $messageText, $headers);
        }

        // B) SMS versenden (NUR wenn sms_status = true UND Telefonnummer vorhanden)
        if ($smsEnabled && !empty($contact['tel1'])) {
            sendSmsNotification($contact['tel1'], $messageText);
        }
    }
}

/**
 * Hilfsfunktion zum Versenden von SMS via API (z. B. Seven.io, Twilio, etc.)
 */
function sendSmsNotification(string $phoneNumber, string $message): void
{
    // Sicherheitsprüfung: Sind die Konstanten in der config.php definiert?
    if (!defined('TWILIO_ACCOUNT_SID') || !defined('TWILIO_AUTH_TOKEN') || !defined('TWILIO_PHONE_NUMBER')) {
        error_log("Twilio SMS-Fehler: Zugangsdaten (TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_PHONE_NUMBER) fehlen in config.php.");
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

    // Twilio HTTP Basic Auth via Account SID & Auth Token
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Fehler-Logging, falls Twilio keinen 201-Created-Status zurückgibt
    if ($httpCode !== 201) {
        error_log("Twilio SMS-Fehler (Status $httpCode): " . $response);
    }
}

// --- HILFSFUNKTIONEN FÜR DB ---

function initializeCallStatus($db, $clientId)
{
    $stmt = $db->prepare("INSERT INTO call_status (client_id, attempt_cycle, last_tel_used, status, scheduled_time) 
                          VALUES (?, 1, 'tel1', 'calling', NOW()) 
                          ON DUPLICATE KEY UPDATE status = 'calling', attempt_cycle = 1, last_tel_used = 'tel1', scheduled_time = NOW()");
    $stmt->execute([$clientId]);
}

function updateCallStatus($db, $clientId, $status, $summary)
{
    $stmt = $db->prepare("UPDATE call_status SET status = ?, summary = ? WHERE client_id = ?");
    $stmt->execute([$status, $summary, $clientId]);
}

function executeDirectTestCall($phone)
{
    // Bereite das Vapi Payload direkt mit der eingegebenen Nummer vor
    $payload = [
        'assistantId' => VAPI_ASSISTANT_ID,
        'phoneNumberId' => VAPI_PHONE_ID,
        'assistantOverrides' => [
            // Netter, allgemeiner Ansprachetext für die Landingpage (UNVERÄNDERT)
            'firstMessage' => "Guten Tag! Schön, dass Sie die Telefon Assistenz von dschuljiana Kaer ausprobieren. Dies ist ein automatisierter Testanruf, um Ihnen zu zeigen, wie klar und verständlich unsere KI spricht. Können Sie mich gut hören?",

            // Zwingt Vapi dazu, exakt dein Azure-Deployment zu nutzen
            'model' => [
                'provider' => 'azure-openai',
                'model'    => 'gpt-5.4-mini',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'Du bist ein freundlicher Telefon-Assistent für Dschuliana Kär. Antworte immer kurz, prägnant und ausschließlich auf Deutsch. Wenn sich das Gespräch dem Ende neigt oder der Kunde sich verabschiedet, verabschiede dich höflich und beende den Anruf sofort mit der Auflegen-Funktion. Bleibe immer im Kontext der Senioren-Betreuung!'
                    ]
                ]
            ],
            // NEU: Erlaubt der KI, das Telefonat von sich aus zu beenden (Auflegen)
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
        'Authorization: Bearer ' . /*getenv('Vapi_Public_Key')*/ VAPI_PRIVATE_KEY,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Das sendet die Vapi-Antwort direkt zurück an dein React-Frontend!
    if ($httpCode !== 201) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Vapi Fehler (Status $httpCode): " . $response
        ]);
        exit;
    }
}
