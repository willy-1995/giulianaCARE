<?php
// ==================================================
// TWILIO + PHP STRICT-STRUCTURE PIPELINE (OHNE VAPI)
// ==================================================

ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/my_php_errors.log');

try {
    require_once __DIR__ . "/config.php";
    require_once __DIR__ . "/database.php";
    require_once __DIR__ . "/cors.php";
    require_once __DIR__ . "/envloader.php";
    require_once __DIR__ . "/rate_limiter.php";

    // ==========================================
    // BUZZWORDS-KONFIGURATION (HIER ERGÄNZEN!)
    // ==========================================
    $MILD_BUZZWORDS = [
        "kopfschmerzen",
        "schwindel",
        "müde",
        "schwach",
        "leichtes unwohlsein",
        "übel",
        "druck im kopf",
        "kratzen im hals"
        // Hier beliebig weitere leichte Buzzwords mit Komma ergänzen
    ];

    $SEVERE_BUZZWORDS = [
        "starke schmerzen",
        "brustschmerzen",
        "luftnot",
        "atmen",
        "sturz",
        "hingefallen",
        "blut",
        "schwindelanfall",
        "benommen",
        "verwirrt",
        "kann nicht mehr",
        "notruf"
        // Hier beliebig weitere ernste Buzzwords mit Komma ergänzen
    ];

    $dbInstance = new Database();
    $db = $dbInstance->getConnection();

    $action = $_POST['action'] ?? $_GET['action'] ?? null;

    // --- 1. TEST-ANRUF VON DER LANDINGPAGE ---
    if ($action === 'test_call') {
        checkRateLimit('landingpage_test_call', 2, 3600);

        header("Content-Type: application/json; charset=UTF-8");
        $phoneNumber = $_POST['phone_number'] ?? null;

        if (empty($phoneNumber)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Keine Telefonnummer übergeben."]);
            exit;
        }

        $phone = preg_replace('/[^0-9+]/', '', $phoneNumber);
        if (strpos($phone, '0') === 0) {
            $phone = '+49' . substr($phone, 1);
        }

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

    // --- 2. REGULÄRER ANRUF STARTEN ---
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

    // --- 3. TWILIO VOICE WEBHOOKS (STRUKTURIERTER WORKFLOW) ---
    $incomingStep = $_GET['step'] ?? null;

    if ($incomingStep) {
        $clientId = (int)($_GET['clientId'] ?? 0);
        $callType = $_GET['callType'] ?? 'call_1';
        $userInput = trim($_POST['SpeechResult'] ?? $_POST['Digits'] ?? '');

        header("Content-Type: text/xml; charset=UTF-8");
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<Response>\n";

        switch ($incomingStep) {

            case 'test_welcome':
                $welcomeText = "Guten Tag! Hier ist die Test-Assistenz von Dschuliana Kär. Ich wollte kurz fragen, wie es Ihnen heute geht?";
                echo "<Gather input=\"speech\" language=\"de-DE\" speechTimeout=\"auto\" action=\"call_manager.php?step=test_process\">\n";
                echo "<Say voice=\"alice\" language=\"de-DE\">" . htmlspecialchars($welcomeText) . "</Say>\n";
                echo "</Gather>\n";
                break;

            case 'test_process':
                echo "<Say voice=\"alice\" language=\"de-DE\">Vielen Dank für Ihren Test. Auf Wiederhören!</Say>\n";
                echo "<Hangup/>\n";
                break;

            // SCHRITT A: BEGRÜSSUNG & FRAGE NACH DEM WOHLBEFINDEN
            case 'welcome':
                $client = getClientData($db, $clientId);
                $titlePrefix = !empty($client['title']) ? trim($client['title']) . ' ' : '';
                $fullName = trim(($client['firstname'] ?? '') . ' ' . ($client['lastname'] ?? ''));

                $firstText = "Guten Tag " . $titlePrefix . $fullName . ", hier ist die Assistenz von Dschuliana Kär. Ich wollte kurz fragen, wie es Ihnen heute geht?";

                echo "<Gather input=\"speech\" language=\"de-DE\" speechTimeout=\"auto\" action=\"call_manager.php?step=process_wellbeing&amp;clientId={$clientId}&amp;callType={$callType}\">\n";
                echo "<Say voice=\"alice\" language=\"de-DE\">" . htmlspecialchars($firstText) . "</Say>\n";
                echo "</Gather>\n";
                break;

            // SCHRITT B: AUSWERTUNG WOHLBEFINDEN (Struktur-Verzweigung)
            case 'process_wellbeing':
                if (empty($userInput)) {
                    echo "<Gather input=\"speech\" language=\"de-DE\" speechTimeout=\"auto\" action=\"call_manager.php?step=process_wellbeing&amp;clientId={$clientId}&amp;callType={$callType}\">\n";
                    echo "<Say voice=\"alice\" language=\"de-DE\">Entschuldigung, ich habe Sie akustisch nicht verstanden. Wie geht es Ihnen heute?</Say>\n";
                    echo "</Gather>\n";
                    break;
                }

                $client = getClientData($db, $clientId);
                $medication = getClientMedication($client, $callType);

                // Buzzwords an die KI-Analyse übergeben
                $analysis = evaluateStrictStructureWithAI($userInput, $medication, $MILD_BUZZWORDS, $SEVERE_BUZZWORDS);

                $statusType = $analysis['status_type']; // 'ok', 'mild_incident', 'severe_incident'
                $reason = $analysis['reason'] ?? '';
                $medStatus = $analysis['medication_status'] ?? '';
                $behaviorLog = $analysis['behavior_log'] ?? 'Normale Sprachweise.';

                // 1. FALL: ES GEHT IHM GUT
                if ($statusType === 'ok') {
                    if ($analysis['medication_already_done']) {
                        markCallCompleted($db, $clientId, $callType, "Wohlbefinden gut. Medikation bereits erledigt.");
                        echo "<Say voice=\"alice\" language=\"de-DE\">Das freut mich. Dass Sie Ihre Medikation beziehungsweise Zusatzinfo bereits erledigt haben, ist wunderbar. Auf Wiederhören!</Say>\n";
                    } else {
                        markCallCompleted($db, $clientId, $callType, "Wohlbefinden gut. An Medikation erinnert.");
                        echo "<Say voice=\"alice\" language=\"de-DE\">Das freut mich. Bitte denken Sie noch daran, Ihre Medikation beziehungsweise Zusatzinfo einzunehmen. Ich wünsche Ihnen einen schönen Tag. Auf Wiederhören!</Say>\n";
                    }
                    echo "<Hangup/>\n";
                }
                // 2. FALL: LEICHTE BESCHWERDEN
                elseif ($statusType === 'mild_incident') {
                    $fullLog = "Leichte Beschwerden: $reason | Verhalten/Tonalität: $behaviorLog | $medStatus";
                    logClientIncident($db, $clientId, $fullLog);
                    updateCallStatusSummary($db, $clientId, $callType, $fullLog);

                    if ($analysis['medication_already_done']) {
                        echo "<Say voice=\"alice\" language=\"de-DE\">Das tut mir leid zu hören. Ich habe registriert, dass Sie Ihre Medikation bereits erledigt hatten. Ich informiere jetzt Ihre Notfallkontakte darüber. Auf Wiederhören!</Say>\n";
                    } else {
                        echo "<Say voice=\"alice\" language=\"de-DE\">Das tut mir leid zu hören. Bitte denken Sie daran, Ihre Medikation beziehungsweise Zusatzinfo einzunehmen. Ich informiere jetzt umgehend Ihre Notfallkontakte darüber. Auf Wiederhören!</Say>\n";
                    }

                    notifyEmergencyContacts($db, $clientId, $reason);
                    echo "<Hangup/>\n";
                }
                // 3. FALL: ERNSTE BESCHWERDEN
                elseif ($statusType === 'severe_incident') {
                    $fullLog = "Ernster Vorfall: $reason | Verhalten/Tonalität: $behaviorLog | $medStatus";
                    logClientIncident($db, $clientId, $fullLog);
                    updateCallStatusSummary($db, $clientId, $callType, $fullLog);

                    if ($analysis['medication_already_done']) {
                        echo "<Say voice=\"alice\" language=\"de-DE\">Das ist ernst. Da Sie Ihre Medikation bereits erledigt hatten, informiere ich sofort Ihre Notfallkontakte. Bitte wählen Sie bei akuten Schmerzen selbst den Notruf 112. Auf Wiederhören!</Say>\n";
                    } else {
                        echo "<Say voice=\"alice\" language=\"de-DE\">Das ist ernst. Bitte nehmen Sie sofort Ihre Medikation beziehungsweise Zusatzinfo ein und wählen Sie bei akuten Beschwerden selbst den Notruf 112. Ich informiere sofort Ihre Notfallkontakte. Auf Wiederhören!</Say>\n";
                    }

                    notifyEmergencyContacts($db, $clientId, $reason);
                    echo "<Hangup/>\n";
                }
                break;
        }

        echo "</Response>\n";
        exit;
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    exit;
}


// ==================================================
// HILFSFUNKTIONEN (STRUKTURIERTE KI-ANALYSE)
// ==================================================

function evaluateStrictStructureWithAI($userInput, $medicationInfo, $mildBuzzwords, $severeBuzzwords)
{
    $apiKey = OPEN_AI_KEY;
    $url = 'https://api.openai.com/v1/chat/completions';

    $mildList = implode(', ', $mildBuzzwords);
    $severeList = implode(', ', $severeBuzzwords);

    $systemPrompt = "Du bist das strikte Analysegehirn eines medizinischen Care-Systems. 
    Hinterlegte Medikation/Zusatzinfo für diesen Klienten: '{$medicationInfo}'.
    
    Achte bei der Analyse besonders auf folgende Buzzwords:
    - Ernste Beschwerden Buzzwords: [{$severeList}]
    - Leichte Beschwerden Buzzwords: [{$mildList}]

    Analysiere die Aussage des Klienten exakt nach folgenden Vorgaben:
    1. status_type: 
       - 'ok' (wenn es dem Klienten gut geht)
       - 'mild_incident' (bei leichten Beschwerden oder Treffern in den leichten Buzzwords)
       - 'severe_incident' (bei ernsten Beschwerden, starken Schmerzen oder Treffern in den ernsten Buzzwords)
    2. reason: Genaue Beschreibung der Beschwerden oder des Zustands.
    3. behavior_log: Achte auf Hinweise auf Tonalität, auffällig lange Pausen, zusammenhangslose Sätze in der Aussage. Protokolliere dies.
    4. medication_already_done: true, falls der Klient angibt, die Medikation/Zusatzinfo bereits eingenommen/erledigt zu haben. false, wenn noch nicht.
    5. medication_status: Ein passender Protokollsatz dazu.

    STRIKTE REGELN: Keine Füllwörter, kein Am-Hörer-halten, kein Wieder-Melden-Versprechen.
    Antworte AUSSCHLIESSLICH im JSON-Format: 
    {
      \"status_type\": \"ok\" | \"mild_incident\" | \"severe_incident\",
      \"reason\": \"Beschreibung\",
      \"behavior_log\": \"Protokoll zu Tonalität/Pausen/Sätzen\",
      \"medication_already_done\": true | false,
      \"medication_status\": \"Protokollsatz zur Medikation\"
    }";

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
    return json_decode($result['choices'][0]['message']['content'] ?? '{"status_type":"ok","reason":"","behavior_log":"Normal","medication_already_done":false,"medication_status":"Prüfung ausstehend"}', true);
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
