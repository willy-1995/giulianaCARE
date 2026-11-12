<?php
// ==================================================
// TWILIO + PHP DIREKT-PIPELINE (OHNE VAPI)
// ==================================================

// Output-Buffering aktivieren
ob_start();

// Fehler-Logging konfigurieren
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/my_php_errors.log');

try {
    // Config, Database & CORS laden
    require_once __DIR__ . "/config.php";
    require_once __DIR__ . "/database.php";
    require_once __DIR__ . "/cors.php";
    require_once __DIR__ . "/envloader.php";
    require_once __DIR__ . "/rate_limiter.php";

    $dbInstance = new Database();
    $db = $dbInstance->getConnection();

    $action = $_POST['action'] ?? $_GET['action'] ?? null;

    // --- 1. TEST-ANRUF VON DER LANDINGPAGE (AUS REACT FORMULAR) ---
    if ($action === 'test_call') {
        checkRateLimit('landingpage_test_call', 2, 3600);

        header("Content-Type: application/json; charset=UTF-8");
        $phoneNumber = $_POST['phone_number'] ?? null;

        if (empty($phoneNumber)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Keine Telefonnummer übergeben."]);
            exit;
        }

        // Telefonnummer bereinigen und für Twilio formatieren
        $phone = preg_replace('/[^0-9+]/', '', $phoneNumber);
        if (strpos($phone, '0') === 0) {
            $phone = '+49' . substr($phone, 1);
        }

        // Webhook-URL für Twilio (zeigt auf diese Datei im Schritt 'test_welcome')
        $webhookUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/call_manager.php?step=test_welcome";

        $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Calls.json";
        $postData = [
            'From' => TWILIO_PHONE_NUMBER,
            'To' => $phone,
            'Url' => $webhookUrl
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201 || $httpCode === 200) {
            echo json_encode(["success" => true, "message" => "Testanruf erfolgreich gestartet."]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Fehler beim Starten des Twilio-Anrufs."]);
        }
        exit;
    }

    // --- 2. REGULÄRER ANRUF STARTEN (AUS CRM / INTERN) ---
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

    // --- 3. TWILIO VOICE WEBHOOKS (GESPRÄCHSSTEUERUNG) ---
    $incomingStep = $_GET['step'] ?? null;

    if ($incomingStep) {
        $clientId = (int)($_GET['clientId'] ?? 0);
        $callType = $_GET['callType'] ?? 'call_1';
        $userInput = trim($_POST['SpeechResult'] ?? $_POST['Digits'] ?? '');

        header("Content-Type: text/xml; charset=UTF-8");
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<Response>\n";

        switch ($incomingStep) {

            // TEST-ANRUF BEGRÜSSUNG (Landingpage-Test)
            case 'test_welcome':
                $welcomeText = "Guten Tag! Hier ist die Test-Assistenz von Dschuliana Kär. Ich wollte kurz fragen, wie es Ihnen heute geht und ob Sie Ihre 2 Gläser Wasser schon getrunken haben?";
                echo "<Gather input=\"speech\" language=\"de-DE\" speechTimeout=\"auto\" action=\"call_manager.php?step=test_process\">\n";
                echo "<Say voice=\"alice\" language=\"de-DE\">" . htmlspecialchars($welcomeText) . "</Say>\n";
                echo "</Gather>\n";
                break;

            // TEST-ANRUF ANTWORT AUSWERTEN
            case 'test_process':
                if (empty($userInput)) {
                    echo "<Gather input=\"speech\" language=\"de-DE\" speechTimeout=\"auto\" action=\"call_manager.php?step=test_process\">\n";
                    echo "<Say voice=\"alice\" language=\"de-DE\">Entschuldigung, ich habe Sie akustisch nicht verstanden. Wie geht es Ihnen heute?</Say>\n";
                    echo "</Gather>\n";
                    break;
                }
                echo "<Say voice=\"alice\" language=\"de-DE\">Vielen Dank für Ihren Test. Auf Wiederhören!</Say>\n";
                echo "<Hangup/>\n";
                break;

            // REGULÄRER ANRUF: BEGRÜSSUNG (Fragt direkt nach Befinden UND bindet Medikation/Zusatzinfo ein)
            case 'welcome':
                $client = getClientData($db, $clientId);
                $titlePrefix = !empty($client['title']) ? trim($client['title']) . ' ' : '';
                $fullName = trim(($client['firstname'] ?? '') . ' ' . ($client['lastname'] ?? ''));
                $medication = getClientMedication($client, $callType);

                $medText = !empty($medication) ? " Haben Sie auch an Ihre Medikation bzw. Zusatzinfo gedacht: " . $medication . "?" : "";
                $firstText = "Guten Tag " . $titlePrefix . $fullName . ", hier ist die Assistenz von Dschuliana Kär. Ich wollte kurz fragen, ob bei Ihnen alles in Ordnung ist und ob Sie sich gut fühlen?" . $medText;

                echo "<Gather input=\"speech\" language=\"de-DE\" speechTimeout=\"auto\" action=\"call_manager.php?step=process_answer&amp;clientId={$clientId}&amp;callType={$callType}\">\n";
                echo "<Say voice=\"alice\" language=\"de-DE\">" . htmlspecialchars($firstText) . "</Say>\n";
                echo "</Gather>\n";
                break;

            // REGULÄRER ANRUF: ANTWORT ANALYSIEREN (Erfasst Befinden + Medikation im selben Zug)
            case 'process_answer':
                if (empty($userInput)) {
                    echo "<Gather input=\"speech\" language=\"de-DE\" speechTimeout=\"auto\" action=\"call_manager.php?step=process_answer&amp;clientId={$clientId}&amp;callType={$callType}\">\n";
                    echo "<Say voice=\"alice\" language=\"de-DE\">Entschuldigung, ich habe Sie akustisch nicht verstanden. Geht es Ihnen gut und haben Sie Ihre Medikation eingenommen?</Say>\n";
                    echo "</Gather>\n";
                    break;
                }

                $client = getClientData($db, $clientId);
                $medication = getClientMedication($client, $callType);

                // KI analysiert, ob Beschwerden VORLIEGEN und ob Medikation/Zusatzinfo eingenommen wurde
                $aiDecision = evaluateClientInputWithAI($userInput, $medication);

                $hasIncident = ($aiDecision['type'] === 'incident');
                $incidentReason = $aiDecision['reason'] ?? '';
                $medStatus = $aiDecision['medication_status'] ?? 'An Medikation/Zusatzinfo erinnert. Status unklar.';

                // Protokollierung in der Datenbank
                if ($hasIncident) {
                    $fullSummary = "Beschwerde: $incidentReason | $medStatus";
                    logClientIncident($db, $clientId, $fullSummary);
                    updateCallStatusSummary($db, $clientId, $callType, $fullSummary);

                    // Da Beschwerden vorliegen, MUSS IMMER sofort gefragt werden, ob Notfallkontakte informiert werden sollen!
                    echo "<Gather input=\"speech\" language=\"de-DE\" speechTimeout=\"auto\" action=\"call_manager.php?step=ask_emergency&amp;clientId={$clientId}&amp;callType={$callType}&amp;reason=" . urlencode($incidentReason) . "\">\n";
                    echo "<Say voice=\"alice\" language=\"de-DE\">Das tut mir leid zu hören. Möchten Sie, dass ich Ihre Notfallkontakte darüber informiere?</Say>\n";
                    echo "</Gather>\n";
                } else {
                    // Keine Beschwerden, Medikation wurde geprüft
                    markCallCompleted($db, $clientId, $callType, "Wohlbefinden bestätigt. $medStatus");
                    echo "<Say voice=\"alice\" language=\"de-DE\">Vielen Dank. Auf Wiederhören!</Say>\n";
                    echo "<Hangup/>\n";
                }
                break;

            // REGULÄRER ANRUF: NOTFALLKONTAKT-FRAGE AUSWERTEN
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
        }

        echo "</Response>\n";
        exit;
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode(["success = false", "message" => "Datenbankfehler."]);
    exit;
} catch (Exception $e) {
    error_log("Server Error: " . $e->getMessage());
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Serverfehler."]);
    exit;
}


// ==================================================
// HILFSFUNKTIONEN (DB & KI-LOGIK)
// ==================================================

function evaluateClientInputWithAI($userInput, $medicationInfo)
{
    $apiKey = OPEN_AI_KEY;
    $url = 'https://api.openai.com/v1/chat/completions';

    $systemPrompt = "Du bist die analytische KI eines medizinischen Care-Systems. 
    Hinterhergelegte Medikation / Zusatzinfo für diesen Klienten lautet: '{$medicationInfo}'.
    Analysiere die Antwort des Klienten auf zwei Dinge:
    1. Liegt ein gesundheitliches Problem vor (Schmerzen, Kopfschmerzen, Schwindel, Unwohlsein, 'mir geht es nicht gut')? Wenn ja: type = 'incident', und beschreibe den Grund. Wenn nein: type = 'ok'.
    2. Wurde die Medikation/Zusatzinfo eingenommen/durchgeführt? Erstelle einen klaren Protokollsatz für 'medication_status' (z. B. 'An Medikation/Zusatzinfo erinnert: Klient hat seine 2 Gläser Wasser bis dahin noch nicht getrunken' oder 'Medikation erfolgreich eingenommen').
    
    STRIKTE REGELN: Sage niemals, dass man sich wieder meldet oder am Hörer bleibt.
    Antworte AUSSCHLIESSLICH im JSON-Format: 
    {\"type\": \"incident\" oder \"ok\", \"reason\": \"genaue beschreibung der beschwerde falls vorhanden\", \"medication_status\": \"Protokollsatz zur Medikation und ob sie eingenommen wurde\"}";

    $data = [
        'model' => 'gpt-5.4-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
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
    $content = json_decode($result['choices'][0]['message']['content'] ?? '{"type":"ok","reason":"","medication_status":"An Medikation/Zusatzinfo erinnert."}', true);
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

    $webhookUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/call_manager.php?step=welcome&clientId={$clientId}&callType={$callType}";

    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Calls.json";
    $postData = [
        'From' => TWILIO_PHONE_NUMBER,
        'To' => $phone,
        'Url' => $webhookUrl
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
    $phone = ($telType === 'tel1') ? ($client['tel1'] ?? '') : ($client['tel2'] ?? '');

    if ($telType === 'tel1' && !empty($client['tel2'])) {
        executeTwilioCall($db, $clientId, 'tel2', $cycle, $callType);
    } else {
        $stmt = $db->prepare("UPDATE call_status SET status = 'failed', summary = 'Nach 2 Nummern nicht erreicht.' WHERE client_id = ? AND call_type = ?");
        $stmt->execute([$clientId, $callType]);
    }
}
