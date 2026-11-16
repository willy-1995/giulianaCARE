<?php

// ==================================================

// TWILIO + PHP STRICT-STRUCTURE PIPELINE (OHNE VAPI)

// ==================================================

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/my_php_errors.log');
require_once __DIR__ . "/cors.php";

try {

    require_once __DIR__ . "/config.php";

    require_once __DIR__ . "/database.php";

    require_once __DIR__ . "/envloader.php";

    require_once __DIR__ . "/rate_limiter.php";

    // ==========================================

    // BUZZWORDS-KONFIGURATION

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

    ];

    $SEVERE_BUZZWORDS = [

        "starke schmerzen",

        "brustschmerzen",

        "luftnot",
        "atemnot",
        "keine luft",
        "starke blutung",

        "sturz",

        "hingefallen",

        "schwindelanfall",

        "benommen",

        "verwirrt",

        "kann nicht mehr",

        "notruf"

    ];

    $dbInstance = new Database();

    $db = $dbInstance->getConnection();

    $action = $_POST['action'] ?? $_GET['action'] ?? null;

    // ==================================================

    // 1. TEST-ANRUF VON DER LANDINGPAGE

    // ==================================================

    if ($action === 'test_call') {

        checkRateLimit('landingpage_test_call', 2, 3600);

        header("Content-Type: application/json; charset=UTF-8");

        $phoneNumber = $_POST['phone_number'] ?? null;

        $testName = trim($_POST['name'] ?? '');

        $testMedication = trim($_POST['medication'] ?? '');

        if (empty($phoneNumber) || empty($testName) || empty($testMedication)) {

            http_response_code(400);

            echo json_encode([

                "success" => false,

                "message" => "Name, Zusatzinfo und Telefonnummer sind erforderlich."

            ]);

            exit;
        }

        $phone = preg_replace('/[^0-9+]/', '', $phoneNumber);

        if (strpos($phone, '0') === 0) {

            $phone = '+49' . substr($phone, 1);
        }

        $queryParams = http_build_query([

            'step' => 'test_welcome',

            'name' => $testName,

            'medication' => $testMedication

        ]);

        $webhookUrl = "https://" . $_SERVER['HTTP_HOST']

            . dirname($_SERVER['PHP_SELF'])

            . "/call_manager.php?" . $queryParams;

        $url = "https://api.twilio.com/2010-04-01/Accounts/"

            . TWILIO_ACCOUNT_SID . "/Calls.json";

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
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $twilioResult = json_decode((string)$response, true);
        $twilioSid = $twilioResult['sid'] ?? null;
        $twilioErrorCode = $twilioResult['code'] ?? null;

        if ($response !== false && $httpCode >= 200 && $httpCode < 300 && !empty($twilioSid)) {
            echo json_encode([
                "success" => true,
                "message" => "Testanruf erfolgreich gestartet.",
                "call_sid" => $twilioSid
            ]);
        } else {
            error_log("Twilio Testanruf fehlgeschlagen. HTTP: {$httpCode}. Twilio-Code: " . ($twilioErrorCode ?? 'unbekannt') . ". cURL: {$curlError}. Antwort: " . substr((string)$response, 0, 1500));
            http_response_code(502);
            echo json_encode([
                "success" => false,
                "message" => "Twilio konnte den Testanruf nicht starten.",
                "http_status" => $httpCode,
                "twilio_error_code" => $twilioErrorCode
            ]);
        }

        exit;
    }

    // ==================================================

    // 2. REGULÄREN ANRUF STARTEN

    // ==================================================

    if ($action === 'start') {

        header("Content-Type: application/json; charset=UTF-8");

        $clientId = (int)($_POST['clientId'] ?? $_POST['client_id'] ?? 0);

        $callType = $_POST['call_type'] ?? 'call_1';

        if ($clientId <= 0) {

            http_response_code(400);

            echo json_encode([

                "success" => false,

                "message" => "Keine Client ID."

            ]);

            exit;
        }

        initializeCallStatus($db, $clientId, $callType);
        $callStarted = executeTwilioCall($db, $clientId, 'tel1', 1, $callType);

        if (!$callStarted) {
            $failedStmt = $db->prepare("UPDATE call_status SET status = 'failed', summary = 'Twilio konnte den Anruf nicht erstellen.' WHERE client_id = ? AND call_type = ? AND status = 'calling'");
            $failedStmt->execute([$clientId, $callType]);
            http_response_code(502);
            echo json_encode([
                "success" => false,
                "message" => "Twilio konnte den Anruf nicht starten. Bitte my_php_errors.log prüfen."
            ]);
            exit;
        }

        echo json_encode([
            "success" => true,
            "message" => "Twilio hat den Anruf angenommen und erstellt."
        ]);

        exit;
    }

    // ==================================================

    // 3. TWILIO VOICE WEBHOOKS

    // ==================================================

    $incomingStep = $_GET['step'] ?? null;

    if ($incomingStep) {

        $clientId = (int)($_GET['clientId'] ?? 0);

        $callType = $_GET['callType'] ?? 'call_1';

        $userInput = trim($_POST['SpeechResult'] ?? $_POST['Digits'] ?? '');

        if ($incomingStep === 'call_status') {
            header('Content-Type: text/plain; charset=UTF-8');
            $telType = $_GET['telType'] ?? 'tel1';
            $cycle = max(1, (int)($_GET['cycle'] ?? 1));
            $twilioStatus = strtolower($_POST['CallStatus'] ?? '');
            $twilioCallSid = $_POST['CallSid'] ?? 'unbekannt';
            error_log("Twilio Statuscallback: Client {$clientId}, CallType {$callType}, Nummer {$telType}, Status {$twilioStatus}, SID {$twilioCallSid}");

            if (in_array($twilioStatus, ['no-answer', 'busy', 'failed', 'canceled'], true)) {
                $client = getClientData($db, $clientId);
                if ($telType === 'tel1' && !empty($client['tel2'])) {
                    $stmt = $db->prepare("UPDATE call_status SET last_tel_used = 'tel2' WHERE client_id = ? AND call_type = ? AND last_tel_used = 'tel1' AND status = 'calling'");
                    $stmt->execute([$clientId, $callType]);
                    if ($stmt->rowCount() > 0) {
                        $secondCallStarted = executeTwilioCall($db, $clientId, 'tel2', $cycle, $callType);
                        if (!$secondCallStarted) {
                            $failedStmt = $db->prepare("UPDATE call_status SET status = 'failed', summary = 'Anruf auf tel2 konnte nicht gestartet werden.' WHERE client_id = ? AND call_type = ? AND status = 'calling'");
                            $failedStmt->execute([$clientId, $callType]);
                        }
                    }
                } elseif ($telType === 'tel2') {
                    $stmt = $db->prepare("UPDATE call_status SET status = 'failed', summary = 'Nach tel1 und tel2 nicht erreicht.' WHERE client_id = ? AND call_type = ? AND last_tel_used = 'tel2' AND status = 'calling'");
                    $stmt->execute([$clientId, $callType]);
                } else {
                    $stmt = $db->prepare("UPDATE call_status SET status = 'failed', summary = 'Anruf fehlgeschlagen; keine zweite Telefonnummer vorhanden.' WHERE client_id = ? AND call_type = ? AND status = 'calling'");
                    $stmt->execute([$clientId, $callType]);
                }
            }
            http_response_code(200);
            echo 'OK';
            exit;
        }

        header("Content-Type: text/xml; charset=UTF-8");

        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";

        echo "<Response>\n";

        switch ($incomingStep) {

            // ==================================================

            // TEST-ANRUF: ZUNÄCHST AUF BEGRÜSSUNG WARTEN

            // ==================================================

            case 'test_welcome':

                $testName = trim($_GET['name'] ?? '');

                $testMedication = trim($_GET['medication'] ?? '');

                $waitCount = 0;

                $actionUrl = htmlspecialchars(

                    "call_manager.php?" . http_build_query([

                        'step' => 'test_after_greeting',

                        'name' => $testName,

                        'medication' => $testMedication,

                        'wait' => $waitCount

                    ]),

                    ENT_QUOTES,

                    'UTF-8'

                );

                // Stilles Zuhören: Die Assistenz spricht noch nicht.

                echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$actionUrl}\">\n";

                echo "</Gather>\n";

                break;

            // ==================================================

            // TEST-ANRUF: BEGRÜSSUNG ERKANNT

            // ==================================================

            case 'test_after_greeting':

                $testName = trim($_GET['name'] ?? '');

                $testMedication = trim($_GET['medication'] ?? '');

                $waitCount = (int)($_GET['wait'] ?? 0);

                if (empty($userInput)) {

                    if ($waitCount < 1) {

                        $retryAction = htmlspecialchars(

                            "call_manager.php?" . http_build_query([

                                'step' => 'test_after_greeting',

                                'name' => $testName,

                                'medication' => $testMedication,

                                'wait' => 1

                            ]),

                            ENT_QUOTES,

                            'UTF-8'

                        );

                        echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$retryAction}\">\n";

                        echo "</Gather>\n";
                    } else {

                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Ich konnte Sie leider nicht hören. Bitte versuchen Sie es später noch einmal. Auf Wiederhören!</Say>\n";

                        echo "<Hangup/>\n";
                    }

                    break;
                }

                $medQuestion = buildMedicationQuestionWithAI($testMedication);

                $welcomeText = "Hallo {$testName}, vielen Dank, dass Sie die Telefonbetreuung von Dschuliana Kär testen. Geht es Ihnen heute gut und {$medQuestion}";

                $nextAction = htmlspecialchars(

                    "call_manager.php?" . http_build_query([

                        'step' => 'test_process',

                        'name' => $testName,

                        'medication' => $testMedication

                    ]),

                    ENT_QUOTES,

                    'UTF-8'

                );

                echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$nextAction}\">\n";

                echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">" . htmlspecialchars($welcomeText, ENT_QUOTES, 'UTF-8') . "</Say>\n";

                echo "</Gather>\n";

                break;

            // ==================================================

            // TEST-ANRUF: AUSWERTUNG DER ANTWORT

            // ==================================================

            case 'test_process':

                $testName = trim($_GET['name'] ?? '');

                $testMedication = trim($_GET['medication'] ?? '');

                if (empty($userInput)) {

                    $retryAction = htmlspecialchars(

                        "call_manager.php?" . http_build_query([

                            'step' => 'test_process',

                            'name' => $testName,

                            'medication' => $testMedication

                        ]),

                        ENT_QUOTES,

                        'UTF-8'

                    );

                    echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$retryAction}\">\n";

                    echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Entschuldigung, ich habe Sie akustisch nicht verstanden. Geht es Ihnen gut und haben Sie an {$testMedication} gedacht?</Say>\n";

                    echo "</Gather>\n";

                    break;
                }

                $analysis = evaluateStrictStructureWithAI(

                    $userInput,

                    $testMedication,

                    $MILD_BUZZWORDS,

                    $SEVERE_BUZZWORDS

                );

                $statusType = $analysis['status_type'];

                $medDone = !empty($analysis['medication_already_done']);

                if ($statusType === 'unclear') {

                    $retryAction = htmlspecialchars(

                        "call_manager.php?" . http_build_query([

                            'step' => 'test_process',

                            'name' => $testName,

                            'medication' => $testMedication

                        ]),

                        ENT_QUOTES,

                        'UTF-8'

                    );

                    echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$retryAction}\">\n";

                    echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Entschuldigung, ich konnte Ihre Antwort nicht eindeutig auswerten. Können Sie mir bitte noch einmal sagen, wie es Ihnen geht?</Say>\n";

                    echo "</Gather>\n";

                    break;
                }

                if ($statusType === 'ok') {

                    if ($medDone) {

                        $reply = "Das freut mich zu hören. Wunderbar, dass Sie {$testMedication} bereits erledigt haben. Vielen Dank für den Test, auf Wiederhören!";
                    } else {

                        $reply = "Das freut mich zu hören. Bitte denken Sie noch daran: {$testMedication}. Vielen Dank für den Test, auf Wiederhören!";
                    }
                } elseif ($statusType === 'mild_incident') {

                    if ($medDone) {

                        $reply = "Das tut mir leid zu hören. Ich habe notiert, dass Sie {$testMedication} bereits erledigt haben, und würde im Ernstfall jetzt Ihre Notfallkontakte informieren. Vielen Dank für den Test, auf Wiederhören!";
                    } else {

                        $reply = "Das tut mir leid zu hören. Bitte denken Sie noch daran: {$testMedication}. Im Ernstfall würde ich jetzt Ihre Notfallkontakte informieren. Vielen Dank für den Test, auf Wiederhören!";
                    }
                } else {

                    if ($medDone) {

                        $reply = "Das klingt ernst. Im Echtbetrieb würde ich sofort Ihre Notfallkontakte informieren und darauf hinweisen, bei akuten Beschwerden den Notruf 112 zu wählen. Vielen Dank für den Test, auf Wiederhören!";
                    } else {

                        $reply = "Das klingt ernst. Bitte denken Sie an {$testMedication} und wählen Sie bei akuten Beschwerden den Notruf 112. Im Echtbetrieb würde ich jetzt sofort Ihre Notfallkontakte informieren. Vielen Dank für den Test, auf Wiederhören!";
                    }
                }

                echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">" . htmlspecialchars($reply, ENT_QUOTES, 'UTF-8') . "</Say>\n";

                echo "<Hangup/>\n";

                break;

            // ==================================================

            // REGULÄRER ANRUF: ZUNÄCHST AUF BEGRÜSSUNG WARTEN

            // ==================================================

            case 'welcome':

                $actionUrl = htmlspecialchars(

                    "call_manager.php?" . http_build_query([

                        'step' => 'welcome_after_greeting',

                        'clientId' => $clientId,

                        'callType' => $callType,

                        'wait' => 0

                    ]),

                    ENT_QUOTES,

                    'UTF-8'

                );

                // Die Assistenz wartet zunächst still auf eine Äußerung.

                echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$actionUrl}\">\n";

                echo "</Gather>\n";

                break;

            // ==================================================

            // REGULÄRER ANRUF: BEGRÜSSUNG ERKANNT

            // ==================================================

            case 'welcome_after_greeting':

                $waitCount = (int)($_GET['wait'] ?? 0);

                if (empty($userInput)) {

                    if ($waitCount < 1) {

                        $retryAction = htmlspecialchars(

                            "call_manager.php?" . http_build_query([

                                'step' => 'welcome_after_greeting',

                                'clientId' => $clientId,

                                'callType' => $callType,

                                'wait' => 1

                            ]),

                            ENT_QUOTES,

                            'UTF-8'

                        );

                        // Ein zweiter stiller Versuch.

                        echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$retryAction}\">\n";

                        echo "</Gather>\n";
                    } else {

                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Ich konnte Sie leider nicht hören. Bitte versuchen Sie es später noch einmal. Auf Wiederhören!</Say>\n";

                        echo "<Hangup/>\n";
                    }

                    break;
                }

                // Sobald der Angerufene gesprochen hat, folgt die Begrüßung.

                $client = getClientData($db, $clientId);

                $titlePrefix = !empty($client['title'])

                    ? trim($client['title']) . ' '

                    : '';

                $fullName = trim(

                    ($client['firstname'] ?? '') . ' ' .

                        ($client['lastname'] ?? '')

                );

                $firstText = "Guten Tag " . $titlePrefix . $fullName

                    . ", hier ist die Assistenz von Dschuliana Kär. "

                    . "Ich wollte kurz fragen, wie es Ihnen heute geht?";

                $nextAction = htmlspecialchars(

                    "call_manager.php?" . http_build_query([

                        'step' => 'process_wellbeing',

                        'clientId' => $clientId,

                        'callType' => $callType

                    ]),

                    ENT_QUOTES,

                    'UTF-8'

                );

                echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$nextAction}\">\n";

                echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">" . htmlspecialchars($firstText, ENT_QUOTES, 'UTF-8') . "</Say>\n";

                echo "</Gather>\n";

                break;

            // ==================================================

            // REGULÄRER ANRUF: AUSWERTUNG WOHLBEFINDEN

            // ==================================================

            case 'process_wellbeing':

                if (empty($userInput)) {

                    echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"call_manager.php?step=process_wellbeing&amp;clientId={$clientId}&amp;callType={$callType}\">\n";

                    echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Entschuldigung, ich habe Sie akustisch nicht verstanden. Wie geht es Ihnen heute?</Say>\n";

                    echo "</Gather>\n";

                    break;
                }

                // Eingabe protokollieren, damit die tatsächliche

                // Twilio-Spracherkennung nachvollziehbar bleibt.

                error_log("Twilio SpeechResult für Client {$clientId}: " . $userInput);

                $client = getClientData($db, $clientId);

                $medication = getClientMedication($client, $callType);

                $analysis = evaluateStrictStructureWithAI(

                    $userInput,

                    $medication,

                    $MILD_BUZZWORDS,

                    $SEVERE_BUZZWORDS

                );

                $statusType = $analysis['status_type'];

                $reason = $analysis['reason'] ?? '';

                $medStatus = $analysis['medication_status'] ?? '';

                $behaviorLog = $analysis['behavior_log'] ?? 'Normale Sprachweise.';

                // KI konnte die Antwort nicht sicher auswerten.

                if ($statusType === 'unclear') {

                    echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"call_manager.php?step=process_wellbeing&amp;clientId={$clientId}&amp;callType={$callType}\">\n";

                    echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Entschuldigung, ich konnte Ihre Antwort nicht eindeutig auswerten. Können Sie mir bitte noch einmal sagen, wie es Ihnen heute geht?</Say>\n";

                    echo "</Gather>\n";

                    break;
                }

                // ==================================================

                // WOHLBEFINDEN: OK

                // ==================================================

                if ($statusType === 'ok') {

                    if (!empty($analysis['medication_already_done'])) {

                        markCallCompleted(

                            $db,

                            $clientId,

                            $callType,

                            "Wohlbefinden gut. Medikation bereits erledigt."

                        );

                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Das freut mich. Dass Sie Ihre Medikation beziehungsweise Zusatzinfo bereits erledigt haben, ist wunderbar. Auf Wiederhören!</Say>\n";
                    } else {

                        markCallCompleted(

                            $db,

                            $clientId,

                            $callType,

                            "Wohlbefinden gut. An Medikation erinnert."

                        );

                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Das freut mich. Bitte denken Sie noch daran, Ihre Medikation beziehungsweise Zusatzinfo einzunehmen. Ich wünsche Ihnen einen schönen Tag. Auf Wiederhören!</Say>\n";
                    }

                    echo "<Hangup/>\n";

                    // ==================================================

                    // LEICHTER VORFALL

                    // ==================================================

                } elseif ($statusType === 'mild_incident') {

                    $fullLog = "Leichte Beschwerden: $reason | Verhalten/Tonalität: $behaviorLog | $medStatus";

                    logClientIncident($db, $clientId, $fullLog);

                    updateCallStatusSummary($db, $clientId, $callType, $fullLog);

                    if (!empty($analysis['medication_already_done'])) {

                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Das tut mir leid zu hören. Ich habe registriert, dass Sie Ihre Medikation bereits erledigt hatten. Ich informiere jetzt Ihre Notfallkontakte darüber. Auf Wiederhören!</Say>\n";
                    } else {

                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Das tut mir leid zu hören. Bitte denken Sie daran, Ihre Medikation beziehungsweise Zusatzinfo einzunehmen. Ich informiere jetzt umgehend Ihre Notfallkontakte darüber. Auf Wiederhören!</Say>\n";
                    }

                    notifyEmergencyContacts($db, $clientId, $reason);

                    echo "<Hangup/>\n";

                    // ==================================================

                    // ERNSTER VORFALL

                    // ==================================================

                } elseif ($statusType === 'severe_incident') {

                    $fullLog = "Ernster Vorfall: $reason | Verhalten/Tonalität: $behaviorLog | $medStatus";

                    logClientIncident($db, $clientId, $fullLog);

                    updateCallStatusSummary($db, $clientId, $callType, $fullLog);

                    if (!empty($analysis['medication_already_done'])) {

                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Das ist ernst. Da Sie Ihre Medikation bereits erledigt hatten, informiere ich sofort Ihre Notfallkontakte. Bitte wählen Sie bei akuten Schmerzen selbst den Notruf 112. Auf Wiederhören!</Say>\n";
                    } else {

                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Das ist ernst. Bitte nehmen Sie sofort Ihre Medikation beziehungsweise Zusatzinfo ein und wählen Sie bei akuten Beschwerden selbst den Notruf 112. Ich informiere sofort Ihre Notfallkontakte. Auf Wiederhören!</Say>\n";
                    }

                    notifyEmergencyContacts($db, $clientId, $reason);

                    echo "<Hangup/>\n";
                }

                break;
        }

        echo "</Response>\n";

        exit;
    }
} catch (Throwable $e) {

    error_log("Error in call_manager.php: " . $e->getMessage());

    while (ob_get_level()) {

        ob_end_clean();
    }

    http_response_code(500);

    exit;
}

// ==================================================

// HILFSFUNKTIONEN

// ==================================================

function buildMedicationQuestionWithAI($medicationText)

{

    $apiKey = $_ENV['API_KEY_OPENAI'] ?? getenv('API_KEY_OPENAI') ?: '';

    if (empty($apiKey)) {

        error_log("OpenAI API-Key API_KEY_OPENAI wurde nicht geladen.");

        throw new RuntimeException("OpenAI API-Konfiguration fehlt.");
    }

    $url = 'https://api.openai.com/v1/chat/completions';

    $systemPrompt = "Formuliere aus dem folgenden Stichwort (Medikation oder Zusatzinfo) eine kurze, höfliche Frage-Ergänzung in der Sie-Form, die direkt nach 'Geht es Ihnen heute gut und ' angeschlossen werden kann.

Beispiele:

\- Eingabe: '2 Gläser Wasser' -> Ausgabe: 'haben Sie Ihre 2 Gläser Wasser bereits getrunken?'

\- Eingabe: 'Blutdrucktablette' -> Ausgabe: 'haben Sie Ihre Blutdrucktablette bereits eingenommen?'

\- Eingabe: 'Spaziergang' -> Ausgabe: 'haben Sie Ihren Spaziergang heute schon gemacht?'

Antworte ausschließlich mit dem Satzteil, kleingeschrieben am Anfang und mit Fragezeichen am Ende. Keine Anführungszeichen.";

    $data = [

        'model' => 'gpt-5.4-mini',

        'messages' => [

            ['role' => 'system', 'content' => $systemPrompt],

            ['role' => 'user', 'content' => $medicationText]

        ],

        'max_completion_tokens' => 80

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

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $curlError = curl_error($ch);

    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {

        error_log("OpenAI Medikamentenfrage fehlgeschlagen. HTTP: {$httpCode}. cURL: {$curlError}. Antwort: " . substr((string)$response, 0, 1000));

        return "haben Sie an {$medicationText} gedacht?";
    }

    $result = json_decode($response, true);

    $question = trim(

        $result['choices'][0]['message']['content'] ?? ''

    );

    return !empty($question)

        ? $question

        : "haben Sie an {$medicationText} gedacht?";
}

// ==================================================

// OPENAI: STRUKTURIERTE WOHLBEFINDENS-AUSWERTUNG

// ==================================================

function evaluateStrictStructureWithAI($userInput, $medicationInfo, $mildBuzzwords, $severeBuzzwords)

{

    $apiKey = $_ENV['API_KEY_OPENAI'] ?? getenv('API_KEY_OPENAI') ?: '';

    if (empty($apiKey)) {

        error_log("OpenAI API-Key API_KEY_OPENAI wurde nicht geladen.");

        return [

            'status_type' => 'unclear',

            'reason' => 'OpenAI API-Konfiguration fehlt.',

            'behavior_log' => 'Keine KI-Auswertung möglich.',

            'medication_already_done' => false,

            'medication_status' => 'Prüfung ausstehend'

        ];
    }

    $url = 'https://api.openai.com/v1/chat/completions';

    $mildList = implode(', ', $mildBuzzwords);

    $severeList = implode(', ', $severeBuzzwords);

    $systemPrompt = "

Du bist das Analysemodul eines telefonischen Care-Systems.

Hinterlegte Medikation/Zusatzinfo für diesen Klienten:

'{$medicationInfo}'

Ernste Beschwerden-Buzzwords:

[{$severeList}]

Leichte Beschwerden-Buzzwords:

[{$mildList}]

Analysiere ausschließlich die tatsächliche Aussage des Klienten.

Die möglichen Werte für status_type sind:

\- ok: Der Klient gibt eindeutig an, dass es ihm gut geht und keine Beschwerden bestehen.

\- mild_incident: Der Klient berichtet über leichte Beschwerden oder Unwohlsein.

\- severe_incident: Der Klient berichtet über ernste Beschwerden, starke Schmerzen, Atemnot, Sturz oder eine andere akute Gefahr.

Weitere Felder:

\- reason: Genaue Beschreibung der Beschwerden oder des Zustands.

\- behavior_log: Hinweise auf auffällige Tonalität, lange Pausen oder zusammenhanglose Aussagen. Wenn nichts Auffälliges erkennbar ist, schreibe 'Normale Sprachweise'.

\- medication_already_done: true, wenn der Klient ausdrücklich angibt, die Medikation oder Zusatzinfo bereits erledigt zu haben. Andernfalls false.

\- medication_status: Ein kurzer Protokollsatz zur Medikation.

Wichtige Regeln:

1\. Eine freundliche Begrüßung wie 'Hallo', 'Guten Tag' oder 'Ja' ist keine Aussage über das Wohlbefinden. Wenn keine ausreichende Information zum Wohlbefinden vorliegt, verwende status_type 'ok' NICHT automatisch. Verwende dann status_type 'unclear'.

2\. Wenn der Klient eindeutig sagt, dass es ihm gut geht, verwende 'ok'.

3\. Wenn Beschwerden genannt werden, dürfen diese nicht als 'ok' eingestuft werden.

4\. Bei ernsten Beschwerden hat 'severe_incident' Vorrang.

5\. Erfinde keine Beschwerden und keine Medikamenteneinnahme.

6\. Eine Verneinung wie 'Ich habe keine Kopfschmerzen' ist keine Beschwerde.

7\. Wenn die Aussage nicht eindeutig ist, verwende 'unclear'.

8\. Antworte ausschließlich mit einem gültigen JSON-Objekt. Keine Markdown-Codeblöcke und keine zusätzlichen Erklärungen.

Das JSON muss genau diese Struktur haben:

{

  \"status_type\": \"ok\",

  \"reason\": \"Keine Beschwerden angegeben.\",

  \"behavior_log\": \"Normale Sprachweise.\",

  \"medication_already_done\": false,

  \"medication_status\": \"Medikation noch nicht bestätigt.\"

}

Erlaubte status_type-Werte: ok, mild_incident, severe_incident oder unclear.

";

    $data = [

        'model' => 'gpt-5.4-mini',

        'messages' => [

            ['role' => 'system', 'content' => $systemPrompt],

            ['role' => 'user', 'content' => $userInput]

        ],

        'response_format' => [

            'type' => 'json_object'

        ]

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

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $curlError = curl_error($ch);

    curl_close($ch);

    // Technische Fehler eindeutig protokollieren.

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {

        error_log(

            "OpenAI Wohlbefindens-Auswertung fehlgeschlagen. " .

                "HTTP: {$httpCode}. " .

                "cURL: {$curlError}. " .

                "Antwort: " . substr((string)$response, 0, 1500)

        );

        return [

            'status_type' => 'unclear',

            'reason' => 'Die Aussage konnte technisch nicht ausgewertet werden.',

            'behavior_log' => 'OpenAI API-Fehler.',

            'medication_already_done' => false,

            'medication_status' => 'Prüfung ausstehend'

        ];
    }

    $result = json_decode($response, true);

    $content = $result['choices'][0]['message']['content'] ?? '';

    $analysis = json_decode($content, true);

    // Prüfen, ob die KI eine gültige Antwort geliefert hat.

    $allowedStatusTypes = [

        'ok',

        'mild_incident',

        'severe_incident',

        'unclear'

    ];

    if (

        !is_array($analysis) ||

        !isset($analysis['status_type']) ||

        !in_array($analysis['status_type'], $allowedStatusTypes, true)

    ) {

        error_log(

            "KI-Auswertung ungültig. " .

                "HTTP: {$httpCode}. " .

                "SpeechResult: {$userInput}. " .

                "KI-Antwort: " . substr((string)$content, 0, 1500)

        );

        return [

            'status_type' => 'unclear',

            'reason' => 'Die Aussage konnte nicht sicher ausgewertet werden.',

            'behavior_log' => 'KI-Auswertung fehlgeschlagen.',

            'medication_already_done' => false,

            'medication_status' => 'Prüfung ausstehend'

        ];
    }

    // Pflichtfelder ergänzen und Datentypen absichern.

    $analysis['reason'] = $analysis['reason'] ?? '';

    $analysis['behavior_log'] = $analysis['behavior_log'] ?? 'Normale Sprachweise.';

    $analysis['medication_already_done'] = filter_var(

        $analysis['medication_already_done'] ?? false,

        FILTER_VALIDATE_BOOLEAN

    );

    $analysis['medication_status'] = $analysis['medication_status'] ?? 'Prüfung ausstehend';

    // ==================================================

    // TECHNISCHE SICHERHEITSPRÜFUNG

    // ==================================================

    $inputLower = mb_strtolower($userInput, 'UTF-8');

    // Ernste Beschwerden haben Vorrang.

    foreach ($severeBuzzwords as $word) {

        $wordLower = mb_strtolower($word, 'UTF-8');

        if (mb_strpos($inputLower, $wordLower) !== false) {

            $position = mb_strpos($inputLower, $wordLower);

            $before = mb_substr(

                $inputLower,

                max(0, $position - 30),

                min(30, $position)

            );

            // Einfache Prüfung auf ausdrückliche Verneinung.

            if (preg_match('/\\b(kein|keine|keinen|keiner|nicht|ohne)\\b/u', $before)) {

                continue;
            }

            $analysis['status_type'] = 'severe_incident';

            $analysis['reason'] = $userInput;

            error_log("Sicherheitsprüfung: Ernste Beschwerde erkannt: " . $userInput);

            break;
        }
    }

    // Leichte Beschwerden prüfen, wenn noch kein ernster Vorfall vorliegt.

    if ($analysis['status_type'] !== 'severe_incident') {

        foreach ($mildBuzzwords as $word) {

            $wordLower = mb_strtolower($word, 'UTF-8');

            if (mb_strpos($inputLower, $wordLower) !== false) {

                $position = mb_strpos($inputLower, $wordLower);

                $before = mb_substr(

                    $inputLower,

                    max(0, $position - 30),

                    min(30, $position)

                );

                if (preg_match('/\\b(kein|keine|keinen|keiner|nicht|ohne)\\b/u', $before)) {

                    continue;
                }

                $analysis['status_type'] = 'mild_incident';

                $analysis['reason'] = $userInput;

                error_log("Sicherheitsprüfung: Leichte Beschwerde erkannt: " . $userInput);

                break;
            }
        }
    }

    return $analysis;
}

// ==================================================

// TWILIO-ANRUF AUSFÜHREN

// ==================================================

function executeTwilioCall($db, $clientId, $telType, $cycle, $callType)
{
    $client = getClientData($db, $clientId);
    if (!$client) {
        error_log("Twilio-Anruf nicht gestartet: Client {$clientId} wurde nicht gefunden.");
        return false;
    }

    $phone = ($telType === 'tel1') ? trim($client['tel1'] ?? '') : trim($client['tel2'] ?? '');
    if ($phone === '') {
        error_log("Twilio-Anruf nicht gestartet: {$telType} ist für Client {$clientId} leer.");
        if ($telType === 'tel1' && !empty($client['tel2'])) {
            $stmt = $db->prepare("UPDATE call_status SET last_tel_used = 'tel2' WHERE client_id = ? AND call_type = ? AND status = 'calling'");
            $stmt->execute([$clientId, $callType]);
            return executeTwilioCall($db, $clientId, 'tel2', $cycle, $callType);
        }
        return false;
    }

    if (
        !defined('TWILIO_ACCOUNT_SID') || !defined('TWILIO_AUTH_TOKEN') || !defined('TWILIO_PHONE_NUMBER') ||
        !TWILIO_ACCOUNT_SID || !TWILIO_AUTH_TOKEN || !TWILIO_PHONE_NUMBER
    ) {
        error_log('Twilio-Konfiguration unvollständig.');
        return false;
    }

    $baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    $webhookUrl = $baseUrl . '/call_manager.php?' . http_build_query([
        'step' => 'welcome',
        'clientId' => $clientId,
        'callType' => $callType
    ]);
    $statusCallbackUrl = $baseUrl . '/call_manager.php?' . http_build_query([
        'step' => 'call_status',
        'clientId' => $clientId,
        'callType' => $callType,
        'telType' => $telType,
        'cycle' => $cycle
    ]);

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Calls.json';
    $postData = [
        'From' => TWILIO_PHONE_NUMBER,
        'To' => $phone,
        'Url' => $webhookUrl,
        'Method' => 'POST',
        'StatusCallback' => $statusCallbackUrl,
        'StatusCallbackMethod' => 'POST',
        'StatusCallbackEvent' => 'completed'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_USERPWD => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $twilioResult = json_decode((string)$response, true);
    $twilioSid = $twilioResult['sid'] ?? null;
    $twilioErrorCode = $twilioResult['code'] ?? null;

    if ($response === false || $httpCode < 200 || $httpCode >= 300 || empty($twilioSid)) {
        error_log("Twilio-Anruf fehlgeschlagen. Client: {$clientId}, Nummer: {$telType}, HTTP: {$httpCode}, Twilio-Code: " . ($twilioErrorCode ?? 'unbekannt') . ", cURL: {$curlError}, Antwort: " . substr((string)$response, 0, 1500));
        return false;
    }

    error_log("Twilio-Anruf erstellt. Client: {$clientId}, Nummer: {$telType}, SID: {$twilioSid}");
    return true;
}

function getClientData($db, $clientId)

{

    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");

    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) {
        error_log("Kein Client-Datensatz gefunden. Client-ID: {$clientId}");
    }
    return $client ?: null;
}

// ==================================================

// MEDIKATION LADEN

// ==================================================

function getClientMedication($client, $callType)

{

    if ($callType === 'call_2') {

        return trim($client['medication_2'] ?? '');
    }

    if ($callType === 'call_3') {

        return trim($client['medication_3'] ?? '');
    }

    return trim($client['medication_1'] ?? '');
}

// ==================================================

// ANRUFSSTATUS INITIALISIEREN

// ==================================================

function initializeCallStatus($db, $clientId, $callType)

{

    $stmt = $db->prepare("

        INSERT INTO call_status

        (

            client_id,

            attempt_cycle,

            last_tel_used,

            call_type,

            status,

            scheduled_time,

            medication_required,

            medication_checked

        )

        VALUES (?, 1, 'tel1', ?, 'calling', NOW(), 1, 0)

        ON DUPLICATE KEY UPDATE

            status = 'calling',

            attempt_cycle = 1

    ");

    $stmt->execute([$clientId, $callType]);
}

// ==================================================

// ANRUF ALS ABGESCHLOSSEN MARKIEREN

// ==================================================

function markCallCompleted($db, $clientId, $callType, $summary)

{

    $stmt = $db->prepare("

        UPDATE call_status

        SET status = 'completed', summary = ?

        WHERE client_id = ?

        AND call_type = ?

        AND status = 'calling'

    ");

    $stmt->execute([$summary, $clientId, $callType]);
}

// ==================================================

// VORFALL PROTOKOLLIEREN

// ==================================================

function logClientIncident($db, $clientId, $reason)

{

    $stmt = $db->prepare("

        INSERT INTO client_incidents (client_id, reason, created_at)

        VALUES (?, ?, NOW())

    ");

    $stmt->execute([$clientId, $reason]);
}

// ==================================================

// ANRUFSSTATUS-ZUSAMMENFASSUNG AKTUALISIEREN

// ==================================================

function updateCallStatusSummary($db, $clientId, $callType, $summary)

{

    $stmt = $db->prepare("

        UPDATE call_status

        SET status = 'incident_reported', summary = ?

        WHERE client_id = ?

        AND call_type = ?

    ");

    $stmt->execute([$summary, $clientId, $callType]);
}

// ==================================================

// NOTFALLKONTAKTE BENACHRICHTIGEN

// ==================================================

function notifyEmergencyContacts(PDO $db, int $clientId, string $reason): void

{

    $client = getClientData($db, $clientId);

    $clientName = trim(

        ($client['title'] ?? '') . ' ' .

            ($client['firstname'] ?? '') . ' ' .

            ($client['lastname'] ?? '')

    );

    $stmt = $db->prepare("

        SELECT email, tel1

        FROM contacts

        WHERE client_id = ?

    ");

    $stmt->execute([$clientId]);

    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $msg = "giulianaCARE Notfall: {$clientName} hat im Anruf angegeben: {$reason}. Bitte sofort handeln!";

    foreach ($contacts as $contact) {

        if (!empty($contact['email'])) {

            mail(

                $contact['email'],

                "NOTFALL: {$clientName}",

                $msg,

                "From: info@giuliana-care.de\r\nContent-Type: text/plain; charset=UTF-8"

            );
        }
    }
}

// ==================================================

// ANRUFSKALATION

// ==================================================

function escalateCallTwilio($db, $clientId, $cycle, $telType, $callType)

{

    $client = getClientData($db, $clientId);

    if ($telType === 'tel1' && !empty($client['tel2'])) {

        executeTwilioCall($db, $clientId, 'tel2', $cycle, $callType);
    } else {

        $stmt = $db->prepare("

            UPDATE call_status

            SET status = 'failed',

                summary = 'Nach 2 Nummern nicht erreicht.'

            WHERE client_id = ?

            AND call_type = ?

        ");

        $stmt->execute([$clientId, $callType]);
    }
}
