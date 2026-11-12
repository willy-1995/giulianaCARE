<?php
// ==================================================
// TWILIO + PHP DIREKT-PIPELINE (OHNE VAPI)
// ==================================================

ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/my_php_errors.log');

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/database.php";

$dbInstance = new Database();
$db = $dbInstance->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? null;

// --- 1. ANRUF STARTEN (AUS LANDINGPAGE / CRM) ---
if ($action === 'start') {
    header("Content-Type: application/json; charset=UTF-8");
    $clientId = (int)($_POST['clientId'] ?? $_POST['client_id'] ?? 0);
    $callType = $_POST['call_type'] ?? 'call_1';

    if ($clientId <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Keine Client ID."]);
        exit;
    }

    initializeCallStatus($db, $clientId, $callType);
    executeTwilioCall($db, $clientId, 'tel1', 1, $callType);

    echo json_encode(["success" => true, "message" => "Direkter Twilio-Anruf gestartet."]);
    exit;
}

// --- 2. TWILIO VOICE WEBHOOKS (GESPRÄCHSSTEUERUNG) ---
$incomingStep = $_GET['step'] ?? 'welcome';
$clientId = (int)($_GET['clientId'] ?? 0);
$callType = $_GET['callType'] ?? 'call_1';
$userInput = trim($_POST['SpeechResult'] ?? $_POST['Digits'] ?? '');

header("Content-Type: text/xml; charset=UTF-8");
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<Response>\n";

switch ($incomingStep) {

    // SCHRITT 1: BEGRÜSSUNG & ERSTER IMPULS
    case 'welcome':
        $client = getClientData($db, $clientId);
        $titlePrefix = !empty($client['title']) ? trim($client['title']) . ' ' : '';
        $fullName = trim(($client['firstname'] ?? '') . ' ' . ($client['lastname'] ?? ''));

        $firstText = "Guten Tag " . $titlePrefix . $fullName . ", hier ist die Assistenz von Dschuliana Kär. Ich wollte kurz fragen, ob bei Ihnen alles in Ordnung ist?";

        echo "<Gather input=\"speech\" language=\"de-DE\" speechTimeout=\"auto\" action=\"call_manager.php?step=process_answer&amp;clientId={$clientId}&amp;callType={$callType}\">\n";
        echo "<Say voice=\"alice\" language=\"de-DE\">" . htmlspecialchars($firstText) . "</Say>\n";
        echo "</Gather>\n";
        break;

    // SCHRITT 2: ANTWORT ANALYSIEREN (INTELLIGENTE LOGIK)
    case 'process_answer':
        if (empty($userInput)) {
            echo "<Gather input=\"speech\" language=\"de-DE\" speechTimeout=\"auto\" action=\"call_manager.php?step=process_answer&amp;clientId={$clientId}&amp;callType={$callType}\">\n";
            echo "<Say voice=\"alice\" language=\"de-DE\">Entschuldigung, ich habe Sie akustisch nicht verstanden. Ist bei Ihnen alles in Ordnung?</Say>\n";
            echo "</Gather>\n";
            break;
        }

        // KI-Analyse über OpenAI mit gpt-5.4-mini
        $aiDecision = evaluateClientInputWithAI($userInput);

        // FALL A: BESCHWERDE / KOPFSCHMERZEN ERKANNT
        if ($aiDecision['type'] === 'incident') {
            $reason = $aiDecision['reason'];

            logClientIncident($db, $clientId, $reason);
            updateCallStatusSummary($db, $clientId, $callType, "Beschwerde erfasst: $reason");

            echo "<Gather input=\"speech\" language=\"de-DE\" speechTimeout=\"auto\" action=\"call_manager.php?step=ask_emergency&amp;clientId={$clientId}&amp;callType={$callType}&amp;reason=" . urlencode($reason) . "\">\n";
            echo "<Say voice=\"alice\" language=\"de-DE\">Das tut mir leid zu hören. Möchten Sie, dass ich Ihre Notfallkontakte darüber informiere?</Say>\n";
            echo "</Gather>\n";
        }
        // FALL B: ES IST ALLES GUT -> MEDIKATION PRÜFEN
        else {
            $client = getClientData($db, $clientId);
            $medication = getClientMedication($client, $callType);

            if (!empty($medication)) {
                echo "<Gather input=\"speech\" language=\"de-DE\" speechTimeout=\"auto\" action=\"call_manager.php?step=process_medication&amp;clientId={$clientId}&amp;callType={$callType}\">\n";
                echo "<Say voice=\"alice\" language=\"de-DE\">Freut mich zu hören. Haben Sie Ihre Medikamente, " . htmlspecialchars($medication) . ", schon eingenommen?</Say>\n";
                echo "</Gather>\n";
            } else {
                markCallCompleted($db, $clientId, $callType, "Wohlbefinden bestätigt.");
                echo "<Say voice=\"alice\" language=\"de-DE\">Vielen Dank, ich wünsche Ihnen noch einen schönen Tag. Auf Wiederhören!</Say>\n";
                echo "<Hangup/>\n";
            }
        }
        break;

    // SCHRITT 3: NOTFALLKONTAKT-FRAGE AUSWERTEN
    case 'ask_emergency':
        $reason = $_GET['reason'] ?? 'Unwohlsein';
        $wantsHelp = (stripos($userInput, 'ja') !== false || stripos($userInput, 'möchte') !== false || stripos($userInput, 'bitte') !== false);

        if ($wantsHelp) {
            notifyEmergencyContacts($db, $clientId, $reason);
            updateCallStatusSummary($db, $clientId, $callType, "Beschwerde: $reason | Notfallkontakte informiert.");
            echo "<Say voice=\"alice\" language=\"de-DE\">Ich informiere jetzt umgehend Ihre Notfallkontakte. Gute Besserung und auf Wiederhören!</Say>\n";
        } else {
            updateCallStatusSummary($db, $clientId, $callType, "Beschwerde: $reason | Keine Benachrichtigung gewünscht.");
            echo "<Say voice=\"alice\" language=\"de-DE\">Verstanden, ich habe es notiert. Gute Besserung und auf Wiederhören!</Say>\n";
        }
        echo "<Hangup/>\n";
        break;

    // SCHRITT 4: MEDIKATION SPEICHERN & ABSCHLIESSEN
    case 'process_medication':
        markCallCompleted($db, $clientId, $callType, "Medikation bestätigt: $userInput");
        echo "<Say voice=\"alice\" language=\"de-DE\">Vielen Dank, das habe ich vermerkt. Ich wünsche Ihnen noch einen schönen Tag. Auf Wiederhören!</Say>\n";
        echo "<Hangup/>\n";
        break;
}

echo "</Response>\n";
exit;


// ==================================================
// HILFSFUNKTIONEN (DB & KI-LOGIK)
// ==================================================

function evaluateClientInputWithAI($userInput)
{
    $apiKey = OPEN_AI_KEY; // Nutzt deine Konstante aus der config.php
    $url = 'https://api.openai.com/v1/chat/completions';

    $data = [
        'model' => 'gpt-5.4-mini', // Genutzt wird dein gewünschtes Modell
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Analysiere die Aussage eines Senioren im Gesundheits-Check. 
                Antworte AUSSCHLIESSLICH im JSON-Format: {"type": "incident", "reason": "genaue beschreibung"} falls Schmerzen, Kopfschmerzen, Schwindel, Unwohlsein oder negative Zustände genannt werden. 
                Antworte ansonsten mit {"type": "ok"}.'
            ],
            ['role' => 'user', 'content' => $userInput]
        ],
        'temperature' => 0.0,
        'response_format' => ['type' => 'json_object']
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    $content = json_decode($result['choices'][0]['message']['content'] ?? '{"type":"ok"}', true);
    return $content;
}

function executeTwilioCall($db, $clientId, $telType, $cycle, $callType)
{
    $client = getClientData($db, $clientId);
    $phone = ($telType === 'tel1') ? ($client['tel1'] ?? '') : ($client['tel2'] ?? '');

    if (empty($phone)) {
        if ($telType === 'tel1') {
            escalateCallTwilio($db, $clientId, $cycle, 'tel1', $callType);
        }
        return;
    }

    $webhookUrl = "https://deine-domain.de/call_manager.php?step=welcome&clientId={$clientId}&callType={$callType}"; // Anpassen!

    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Calls.json";
    $postData = [
        'From' => TWILIO_PHONE_NUMBER,
        'To' => $phone,
        'Url' => $webhookUrl,
        'StatusCallback' => "https://deine-domain.de/call_manager.php?action=status_callback&clientId={$clientId}&telType={$telType}&cycle={$cycle}&callType={$callType}",
        'StatusCallbackEvent' => ['completed', 'no-answer', 'busy', 'failed']
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);
    curl_exec($ch);
    curl_close($ch);
}

function getClientData($db, $clientId)
{
    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getClientMedication($client, $callType)
{
    if ($callType === 'call_2') return trim($client['medication_2'] ?? '');
    if ($callType === 'call_3') return trim($client['medication_3'] ?? '');
    return trim($client['medication_1'] ?? '');
}

function initializeCallStatus($db, $clientId, $callType)
{
    $stmt = $db->prepare("INSERT INTO call_status (client_id, attempt_cycle, last_tel_used, call_type, status, scheduled_time, medication_required, medication_checked) VALUES (?, 1, 'tel1', ?, 'calling', NOW(), 1, 0) ON DUPLICATE KEY UPDATE status='calling', attempt_cycle=1");
    $stmt->execute([$clientId, $callType]);
}

function markCallCompleted($db, $clientId, $callType, $summary)
{
    $stmt = $db->prepare("UPDATE call_status SET status = 'completed', summary = ? WHERE client_id = ? AND call_type = ? AND status = 'calling'");
    $stmt->execute([$summary, $clientId, $callType]);
}

function logClientIncident($db, $clientId, $reason)
{
    $stmt = $db->prepare("INSERT INTO client_incidents (client_id, reason, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$clientId, $reason]);
}

function updateCallStatusSummary($db, $clientId, $callType, $summary)
{
    $stmt = $db->prepare("UPDATE call_status SET status = 'incident_reported', summary = ? WHERE client_id = ? AND call_type = ?");
    $stmt->execute([$summary, $clientId, $callType]);
}

function notifyEmergencyContacts(PDO $db, int $clientId, string $reason): void
{
    $client = getClientData($db, $clientId);
    $clientName = trim(($client['title'] ?? '') . ' ' . $client['firstname'] . ' ' . $client['lastname']);

    $stmt = $db->prepare("SELECT email, tel1 FROM contacts WHERE client_id = ?");
    $stmt->execute([$clientId]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $msg = "giulianaCARE Notfall: $clientName hat im Anruf angegeben: $reason. Bitte sofort handeln!";
    foreach ($contacts as $contact) {
        if (!empty($contact['email'])) {
            mail($contact['email'], "NOTFALL: $clientName", $msg, "From: info@giuliana-care.de\r\nContent-Type: text/plain; charset=UTF-8");
        }
    }
}

function escalateCallTwilio($db, $clientId, $cycle, $telType, $callType)
{
    $client = getClientData($db, $clientId);
    if ($telType === 'tel1' && !empty($client['tel2'])) {
        executeTwilioCall($db, $clientId, 'tel2', $cycle, $callType);
    } else {
        $stmt = $db->prepare("UPDATE call_status SET status = 'failed', summary = 'Nach 2 Nummern nicht erreicht.' WHERE client_id = ? AND call_type = ?");
        $stmt->execute([$clientId, $callType]);
    }
}
