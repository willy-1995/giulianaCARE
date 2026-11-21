<?php

// ==================================================
// TWILIO + PHP STRICT-STRUCTURE PIPELINE (OHNE VAPI)
// ==================================================

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/my_php_errors.log');
date_default_timezone_set('Europe/Berlin');
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

        $baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        $webhookUrl = $baseUrl . "/call_manager.php?" . $queryParams;
        $statusCallbackUrl = $baseUrl . "/call_manager.php?step=call_status";
        $amdCallbackUrl = $baseUrl . "/call_manager.php?step=amd_callback&test=1";

        $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Calls.json";

        $postData = [
            'From' => TWILIO_PHONE_NUMBER,
            'To' => $phone,
            'Url' => $webhookUrl,
            'Method' => 'POST',
            'StatusCallback' => $statusCallbackUrl,
            'StatusCallbackMethod' => 'POST',
            'StatusCallbackEvent' => 'initiated ringing answered completed',
            // ANRUFBEANTWORTER ERKENNUNG (AMD) FÜR TESTANRUFE
            'MachineDetection' => 'DetectMessageEnd',
            'AsyncAmd' => 'true',
            'AsyncAmdStatusCallback' => $amdCallbackUrl,
            'AsyncAmdStatusCallbackMethod' => 'POST'
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
            handleCallFailure($db, $clientId, $callType, 'tel1', 1, 'Twilio konnte den Anruf auf tel1 nicht erstellen.');
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
    // 3. TWILIO VOICE WEBHOOKS & CALLBACKS
    // ==================================================
    $incomingStep = $_GET['step'] ?? null;

    if ($incomingStep) {

        $clientId = (int)($_GET['clientId'] ?? 0);
        $callType = $_GET['callType'] ?? 'call_1';
        $userInput = trim($_POST['SpeechResult'] ?? $_POST['Digits'] ?? '');
        if ($userInput === '') {
            $userInput = trim((string)($_GET['earlyInput'] ?? ''));
        }

        // Telefonnummer und Zyklus gehören zu jedem Callback.
        $telType = $_GET['telType'] ?? 'tel1';
        $cycle = max(1, (int)($_GET['cycle'] ?? 1));

        // --- ANRUFBEANTWORTER-ERKENNUNG (AMD CALLBACK) ---
        if ($incomingStep === 'amd_callback') {
            header('Content-Type: text/plain; charset=UTF-8');
            $answeredBy = strtolower(trim($_POST['AnsweredBy'] ?? ''));
            $callSid = $_POST['CallSid'] ?? '';
            error_log("Twilio AMD Callback: Client {$clientId}, Nummer {$telType}, Zyklus {$cycle}, AnsweredBy: {$answeredBy}, CallSid: {$callSid}");

            if (strpos($answeredBy, 'machine') !== false || strpos($answeredBy, 'fax') !== false) {
                if (!empty($callSid)) {
                    terminateTwilioCallImmediately($callSid);
                }
                if ($clientId > 0 && empty($_GET['test'])) {
                    handleCallFailure($db, $clientId, $callType, $telType, $cycle, 'Anrufbeantworter erkannt; Anruf sofort und ohne Nachricht beendet.');
                }
            } elseif ($clientId > 0 && empty($_GET['test'])) {
                // AMD hat einen Menschen oder ein nicht eindeutig klassifiziertes Ziel erkannt.
                // Heartbeat aktualisieren; die eigentliche Gesprächslogik entscheidet über Erfolg.
                touchCallStatus($db, $clientId, $callType, $telType, $cycle);
            }
            http_response_code(200);
            echo 'OK';
            exit;
        }

        // --- TWILIO STATUS CALLBACK ---
        if ($incomingStep === 'call_status') {
            header('Content-Type: text/plain; charset=UTF-8');
            $twilioStatus = strtolower($_POST['CallStatus'] ?? '');
            $twilioCallSid = $_POST['CallSid'] ?? 'unbekannt';
            error_log("Twilio Statuscallback: Client {$clientId}, CallType {$callType}, Nummer {$telType}, Zyklus {$cycle}, Status {$twilioStatus}, SID {$twilioCallSid}");

            if (in_array($twilioStatus, ['initiated', 'ringing', 'answered'], true)) {
                touchCallStatus($db, $clientId, $callType, $telType, $cycle);
            } elseif (in_array($twilioStatus, ['no-answer', 'busy', 'failed', 'canceled'], true)) {
                handleCallFailure($db, $clientId, $callType, $telType, $cycle, "Twilio-Status: {$twilioStatus}");
            } elseif ($twilioStatus === 'completed') {
                // completed ist NICHT automatisch ein erfolgreicher Care-Anruf.
                // Nur markCallCompleted() nach erfolgreichem Gespräch setzt diesen Status.
                handleCallFailure($db, $clientId, $callType, $telType, $cycle, 'Twilio-Status: completed, aber kein erfolgreicher Gesprächsabschluss im Care-System registriert.');
            }
            http_response_code(200);
            echo 'OK';
            exit;
        }

        // Heartbeat bei jedem regulären Voice-Webhook (z.B. nach Gather-Antwort).
        // Dadurch löst der Watchdog keinen noch laufenden Dialog aus.
        if ($clientId > 0 && empty($_GET['test'])) {
            touchCallStatus($db, $clientId, $callType, $telType, $cycle);
        }

        header("Content-Type: text/xml; charset=UTF-8");
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<Response>\n";

        switch ($incomingStep) {

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

                echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$actionUrl}\">\n";
                echo "</Gather>\n";
                break;

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
                        // Keine Nachricht auf Mailbox/bei unklarer Erkennung: still beenden.
                        echo "<Hangup/>\n";
                    }
                    break;
                }

                $itemLanguage = buildMedicationLanguageWithAI($testMedication);
                $medQuestion = $itemLanguage['question'];
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

            case 'test_process':
                $testName = trim($_GET['name'] ?? '');
                $testMedication = trim($_GET['medication'] ?? '');
                $itemLanguage = buildMedicationLanguageWithAI($testMedication);

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
                    $retryQuestion = htmlspecialchars("Entschuldigung, ich habe Sie akustisch nicht verstanden. Geht es Ihnen gut und {$itemLanguage['question']}", ENT_QUOTES | ENT_XML1, 'UTF-8');
                    echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">{$retryQuestion}</Say>\n";
                    echo "</Gather>\n";
                    break;
                }

                $analysis = evaluateStrictStructureWithAI($userInput, $testMedication, $MILD_BUZZWORDS, $SEVERE_BUZZWORDS);
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

                $reminderText = $itemLanguage['reminder'];
                $doneText = $itemLanguage['done_confirmation'];

                if ($statusType === 'ok') {
                    if ($medDone) {
                        $reply = "Das freut mich zu hören. {$doneText} Vielen Dank für den Test, auf Wiederhören!";
                    } else {
                        $reply = "Das freut mich zu hören. {$reminderText} Vielen Dank für den Test, auf Wiederhören!";
                    }
                } elseif ($statusType === 'mild_incident') {
                    if ($medDone) {
                        $reply = "Das tut mir leid zu hören. {$doneText} Im Echtbetrieb würde ich jetzt Ihre Notfallkontakte informieren. Vielen Dank für den Test, auf Wiederhören!";
                    } else {
                        $reply = "Das tut mir leid zu hören. {$reminderText} Im Echtbetrieb würde ich jetzt Ihre Notfallkontakte informieren. Vielen Dank für den Test, auf Wiederhören!";
                    }
                } else {
                    if ($medDone) {
                        $reply = "Das klingt ernst. Im Echtbetrieb würde ich sofort Ihre Notfallkontakte informieren und darauf hinweisen, bei akuten Beschwerden den Notruf eins-eins-zwei zu wählen. Vielen Dank für den Test, auf Wiederhören!";
                    } else {
                        $reply = "Das klingt ernst. {$reminderText} Wählen Sie bei akuten Beschwerden bitte den Notruf eins-eins-zwei. Im Echtbetrieb würde ich jetzt sofort Ihre Notfallkontakte informieren. Vielen Dank für den Test, auf Wiederhören!";
                    }
                }

                echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">" . htmlspecialchars(normalizeEmergencyNumberSpeech($reply), ENT_QUOTES, 'UTF-8') . "</Say>\n";
                echo "<Hangup/>\n";
                break;

            case 'welcome':
                // Erst 3 Sekunden zuhören. Wenn der Klient spricht oder die 3 Sekunden
                // verstreichen, wird die Begrüßung im nächsten Schritt gesprochen.
                $listenAction = htmlspecialchars("call_manager.php?" . http_build_query([
                    'step' => 'welcome_after_greeting',
                    'clientId' => $clientId,
                    'callType' => $callType,
                    'wait' => 0
                ]), ENT_QUOTES, 'UTF-8');
                echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"3\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$listenAction}\">\n";
                echo "</Gather>\n";
                break;

            case 'welcome_after_greeting':
                // Auch bei erkannter Äußerung (z.B. 'Hallo?') und bei 3 Sekunden
                // Stille beginnt die Assistenz erst jetzt mit ihrer Begrüßung.
                $client = getClientData($db, $clientId);
                $titlePrefix = !empty($client['title']) ? trim($client['title']) . ' ' : '';
                $fullName = trim(($client['firstname'] ?? '') . ' ' . ($client['lastname'] ?? ''));
                $hour = (int)(new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('G');
                $greeting = ($hour >= 18) ? 'Guten Abend' : (($hour >= 5 && $hour < 11) ? 'Guten Morgen' : 'Guten Tag');
                $firstText = $greeting . " " . $titlePrefix . $fullName . ", hier ist die Assistenz von Dschuliana Kär. Ich wollte kurz fragen, wie es Ihnen heute geht?";
                $earlyInput = trim($_POST['SpeechResult'] ?? $_POST['Digits'] ?? '');
                $nextActionParams = [
                    'step' => 'process_wellbeing',
                    'clientId' => $clientId,
                    'callType' => $callType,
                    'retry' => 0
                ];
                if ($earlyInput !== '') {
                    // Bereits in der ersten 3-Sekunden-Phase gesprochene Aussage sichern.
                    $nextActionParams['earlyInput'] = $earlyInput;
                    $nextAction = htmlspecialchars("call_manager.php?" . http_build_query($nextActionParams), ENT_QUOTES, 'UTF-8');
                    echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">" . htmlspecialchars($firstText, ENT_QUOTES, 'UTF-8') . "</Say>\n";
                    echo "<Redirect method=\"POST\" >{$nextAction}</Redirect>\n";
                } else {
                    $nextAction = htmlspecialchars("call_manager.php?" . http_build_query($nextActionParams), ENT_QUOTES, 'UTF-8');
                    echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$nextAction}\">\n";
                    echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">" . htmlspecialchars($firstText, ENT_QUOTES, 'UTF-8') . "</Say>\n";
                    echo "</Gather>\n";
                }
                break;

            case 'process_wellbeing':
                $retryCount = (int)($_GET['retry'] ?? 0);
                if (empty($userInput)) {
                    if ($retryCount >= 2) {
                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Ich konnte Sie leider nicht verstehen. Ich beende den Anruf jetzt. Auf Wiederhören.</Say>\n<Hangup/>\n";
                        break;
                    }
                    $nextRetry = $retryCount + 1;
                    $retryAction = htmlspecialchars("call_manager.php?" . http_build_query([
                        'step' => 'process_wellbeing',
                        'clientId' => $clientId,
                        'callType' => $callType,
                        'retry' => $nextRetry
                    ]), ENT_QUOTES, 'UTF-8');
                    $retryPhrase = $retryCount === 0
                        ? "Entschuldigung, ich habe Sie akustisch nicht verstanden. Wie geht es Ihnen heute?"
                        : "Ich habe Sie leider erneut nicht verstanden. Bitte sagen Sie kurz, ob es Ihnen gut geht oder ob Sie Beschwerden haben.";
                    echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$retryAction}\">\n";
                    echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">" . htmlspecialchars($retryPhrase, ENT_QUOTES, 'UTF-8') . "</Say>\n</Gather>\n";
                    break;
                }

                $client = getClientData($db, $clientId);
                $medication = getClientMedication($client, $callType);
                $itemLanguage = buildMedicationLanguageWithAI($medication);
                $itemReminderForSpeech = htmlspecialchars(normalizeEmergencyNumberSpeech($itemLanguage['reminder']), ENT_QUOTES | ENT_XML1, 'UTF-8');
                $itemDoneForSpeech = htmlspecialchars(normalizeEmergencyNumberSpeech($itemLanguage['done_confirmation']), ENT_QUOTES | ENT_XML1, 'UTF-8');

                $analysis = evaluateStrictStructureWithAI($userInput, $medication, $MILD_BUZZWORDS, $SEVERE_BUZZWORDS);

                $statusType = $analysis['status_type'];
                $reason = $analysis['reason'] ?? '';
                $medStatus = $analysis['medication_status'] ?? '';
                $behaviorLog = $analysis['behavior_log'] ?? 'Normale Sprachweise.';

                if ($statusType === 'unclear') {
                    if ($retryCount >= 2) {
                        $fullLog = "Antwort nach maximal zwei Wiederholungen nicht eindeutig: " . $userInput;
                        logClientIncident($db, $clientId, $fullLog);
                        updateCallStatusSummary($db, $clientId, $callType, $fullLog);
                        notifyEmergencyContacts($db, $clientId, "Telefonische Rückmeldung war nach zwei Wiederholungen nicht eindeutig. Gesprochene Antwort: " . $userInput);
                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Ich konnte Ihre Antwort leider nicht sicher einordnen. Ich informiere vorsichtshalber Ihre Notfallkontakte und beende den Anruf. Auf Wiederhören.</Say>\n<Hangup/>\n";
                        break;
                    }
                    $nextRetry = $retryCount + 1;
                    $retryAction = htmlspecialchars("call_manager.php?" . http_build_query([
                        'step' => 'process_wellbeing',
                        'clientId' => $clientId,
                        'callType' => $callType,
                        'retry' => $nextRetry
                    ]), ENT_QUOTES, 'UTF-8');
                    $retryPhrase = $retryCount === 0
                        ? "Entschuldigung, ich konnte Ihre Antwort nicht eindeutig verstehen. Können Sie bitte kurz sagen, ob es Ihnen gut geht oder ob Sie Beschwerden haben?"
                        : "Bitte sagen Sie noch einmal ganz kurz: Geht es Ihnen gut, oder haben Sie Beschwerden?";
                    echo "<Gather input=\"speech\" language=\"de-DE\" speechModel=\"experimental_conversations\" speechTimeout=\"2\" timeout=\"10\" actionOnEmptyResult=\"true\" method=\"POST\" action=\"{$retryAction}\">\n";
                    echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">" . htmlspecialchars($retryPhrase, ENT_QUOTES, 'UTF-8') . "</Say>\n</Gather>\n";
                    break;
                }

                if ($statusType === 'ok') {
                    if (!empty($analysis['medication_already_done'])) {
                        markCallCompleted($db, $clientId, $callType, "Wohlbefinden gut. Medikation bereits erledigt.");
                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Das freut mich. {$itemDoneForSpeech} Auf Wiederhören!</Say>\n";
                    } else {
                        markCallCompleted($db, $clientId, $callType, "Wohlbefinden gut. An Medikation erinnert.");
                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Das freut mich. {$itemReminderForSpeech} Ich wünsche Ihnen einen schönen Tag. Auf Wiederhören!</Say>\n";
                    }
                    echo "<Hangup/>\n";
                } elseif ($statusType === 'mild_incident') {
                    $fullLog = "Leichte Beschwerden: $reason | Verhalten/Tonalität: $behaviorLog | $medStatus";
                    logClientIncident($db, $clientId, $fullLog);
                    updateCallStatusSummary($db, $clientId, $callType, $fullLog);

                    if (!empty($analysis['medication_already_done'])) {
                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Das tut mir leid zu hören. {$itemDoneForSpeech} Ich informiere jetzt Ihre Notfallkontakte darüber. Auf Wiederhören!</Say>\n";
                    } else {
                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Das tut mir leid zu hören. {$itemReminderForSpeech} Ich informiere jetzt umgehend Ihre Notfallkontakte darüber. Auf Wiederhören!</Say>\n";
                    }
                    notifyEmergencyContacts($db, $clientId, $reason);
                    echo "<Hangup/>\n";
                } elseif ($statusType === 'severe_incident') {
                    $fullLog = "Ernster Vorfall: $reason | Verhalten/Tonalität: $behaviorLog | $medStatus";
                    logClientIncident($db, $clientId, $fullLog);
                    updateCallStatusSummary($db, $clientId, $callType, $fullLog);

                    if (!empty($analysis['medication_already_done'])) {
                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Das ist ernst. {$itemDoneForSpeech} Ich informiere sofort Ihre Notfallkontakte. Bitte wählen Sie bei akuten Schmerzen selbst den Notruf eins-eins-zwei. Auf Wiederhören!</Say>\n";
                    } else {
                        echo "<Say voice=\"Google.de-DE-Chirp3-HD-Aoede\" language=\"de-DE\">Das ist ernst. {$itemReminderForSpeech} Bei akuten Beschwerden wählen Sie bitte sofort den Notruf eins-eins-zwei. Ich informiere sofort Ihre Notfallkontakte. Auf Wiederhören!</Say>\n";
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

/** Ersetzt die Notrufnummer in gesprochenen Texten durch die gewünschte Aussprache. */
function normalizeEmergencyNumberSpeech(string $text): string
{
    return preg_replace('/(?<!\\d)112(?!\\d)/u', 'eins-eins-zwei', $text) ?? $text;
}

function terminateTwilioCallImmediately($callSid)
{
    if (!defined('TWILIO_ACCOUNT_SID') || !defined('TWILIO_AUTH_TOKEN') || empty($callSid)) return;

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Calls/' . $callSid . '.json';
    $postData = ['Status' => 'completed'];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_USERPWD => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function buildMedicationLanguageWithAI($itemText)
{
    // Lokale Textbausteine statt zusätzlichem OpenAI-Aufruf: schneller und stabiler.
    $itemText = trim((string)$itemText);
    return [
        'category' => 'other',
        'question' => 'haben Sie das bereits erledigt?',
        'reminder' => $itemText !== '' ? 'Bitte denken Sie noch an Folgendes: ' . $itemText . '.' : 'Bitte denken Sie noch an Ihre hinterlegte Aufgabe.',
        'done_confirmation' => 'Danke für die Rückmeldung. Gut, dass Sie das erledigt haben.'
    ];
}

function evaluateStrictStructureWithAI($userInput, $medicationInfo, $mildBuzzwords, $severeBuzzwords)
{
    // Schnelle lokale Einordnung klarer Aussagen; bei Mehrdeutigkeit weiterhin KI.
    $text = mb_strtolower(trim((string)$userInput), 'UTF-8');
    $severeLocal = ['brustschmerz', 'atemnot', 'luftnot', 'keine luft', 'starke blutung', 'gestürzt', 'hingefallen', 'sturz', 'notruf'];
    foreach ($severeLocal as $word) {
        if (mb_strpos($text, $word) !== false && !preg_match('/(?:keine|kein|nicht)\\s+' . preg_quote($word, '/') . '/u', $text)) {
            return ['status_type' => 'severe_incident', 'reason' => $userInput, 'behavior_log' => 'Lokale Erkennung eines möglichen Warnsignals.', 'medication_already_done' => false, 'medication_status' => 'Prüfung ausstehend'];
        }
    }
    $mildLocal = ['kopfschmerzen', 'schwindel', 'müde', 'schwach', 'übel', 'kratzen im hals', 'unwohl', 'mir geht es schlecht'];
    foreach ($mildLocal as $word) {
        if (mb_strpos($text, $word) !== false) {
            return ['status_type' => 'mild_incident', 'reason' => $userInput, 'behavior_log' => 'Lokale Erkennung möglicher Beschwerden.', 'medication_already_done' => false, 'medication_status' => 'Prüfung ausstehend'];
        }
    }
    $positiveLocal = ['mir geht es gut', 'es geht mir gut', 'alles in ordnung', 'alles okay', 'alles ok', 'gut, danke', 'gut danke', 'prima', 'wunderbar', 'keine beschwerden'];
    foreach ($positiveLocal as $phrase) {
        if ($text === $phrase) {
            return ['status_type' => 'ok', 'reason' => 'Wohlbefinden ausdrücklich positiv bestätigt.', 'behavior_log' => 'Klare positive Rückmeldung.', 'medication_already_done' => false, 'medication_status' => 'Prüfung ausstehend'];
        }
    }
    $apiKey = $_ENV['API_KEY_OPENAI'] ?? getenv('API_KEY_OPENAI') ?: '';
    if (empty($apiKey)) {
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

    $systemPrompt = "Du bist das Analysemodul eines telefonischen Care-Systems. Analysiere ausschließlich die tatsächliche Aussage des Klienten. Werte: ok, mild_incident, severe_incident, unclear. Antworte ausschließlich im JSON-Format mit status_type, reason, behavior_log, medication_already_done, medication_status. Wenn du den Notruf erwähnst, schreibe die Nummer als „eins-eins-zwei“ statt als Ziffern.";

    $data = [
        'model' => 'gpt-5.4-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userInput]
        ],
        'response_format' => ['type' => 'json_object']
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
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

    $allowedStatusTypes = ['ok', 'mild_incident', 'severe_incident', 'unclear'];
    if (!is_array($analysis) || !isset($analysis['status_type']) || !in_array($analysis['status_type'], $allowedStatusTypes, true)) {
        return [
            'status_type' => 'unclear',
            'reason' => 'Die Aussage konnte nicht sicher ausgewertet werden.',
            'behavior_log' => 'KI-Auswertung fehlgeschlagen.',
            'medication_already_done' => false,
            'medication_status' => 'Prüfung ausstehend'
        ];
    }

    $analysis['reason'] = $analysis['reason'] ?? '';
    $analysis['behavior_log'] = $analysis['behavior_log'] ?? 'Normale Sprachweise.';
    $analysis['medication_already_done'] = filter_var($analysis['medication_already_done'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $analysis['medication_status'] = $analysis['medication_status'] ?? 'Prüfung ausstehend';

    return $analysis;
}

/**
 * Aktualisiert den Aktivitätszeitpunkt nur für den aktuell aktiven Anruf.
 * Verspätete Callbacks von tel1 dürfen einen bereits gestarteten tel2-Anruf
 * nicht künstlich am Leben halten.
 */
function touchCallStatus(PDO $db, int $clientId, string $callType, string $telType, int $cycle): void
{
    $stmt = $db->prepare("
        UPDATE call_status
        SET updated_at = CURRENT_TIMESTAMP
        WHERE client_id = ?
          AND call_type = ?
          AND status = 'calling'
          AND attempt_cycle = ?
          AND last_tel_used = ?
    ");
    $stmt->execute([$clientId, $callType, $cycle, $telType]);
}

/**
 * Eskalation bei fehlgeschlagenem Anruf:
 * Zyklus 1: tel1 -> tel2 -> Retry in 15 Minuten.
 * Zyklus 2: tel1 -> tel2 -> Notfallkontakte benachrichtigen.
 * Der Status-Guard verhindert doppelte Verarbeitung von AMD- und Statuscallback.
 */
function handleCallFailure(PDO $db, int $clientId, string $callType, string $telType, int $cycle, string $reason): void
{
    $client = getClientData($db, $clientId);
    if (!$client) {
        error_log("Call escalation: Client {$clientId} nicht gefunden.");
        return;
    }

    if ($telType === 'tel1' && !empty($client['tel2'])) {
        $lock = $db->prepare("
            UPDATE call_status
            SET last_tel_used = 'tel2'
            WHERE client_id = ? AND call_type = ?
              AND status = 'calling' AND attempt_cycle = ?
              AND last_tel_used = 'tel1'
        ");
        $lock->execute([$clientId, $callType, $cycle]);

        if ($lock->rowCount() > 0) {
            if (!executeTwilioCall($db, $clientId, 'tel2', $cycle, $callType)) {
                handleCallFailure($db, $clientId, $callType, 'tel2', $cycle, 'Anruf auf tel2 konnte nicht gestartet werden.');
            }
        }
        return;
    }

    // Kein zweites Telefon vorhanden oder tel2 ebenfalls fehlgeschlagen.
    if ($cycle <= 1) {
        $schedule = $db->prepare("
            UPDATE call_status
            SET status = 'retry_scheduled',
                attempt_cycle = 2,
                last_tel_used = 'tel2',
                scheduled_time = ?,
                summary = ?
            WHERE client_id = ? AND call_type = ?
              AND status = 'calling' AND attempt_cycle = ?
        ");
        $schedule->execute([
            date('Y-m-d H:i:s', time() + 15 * 60),
            "Zyklus 1 fehlgeschlagen ({$reason}); Retry-Zyklus 2 in 15 Minuten geplant.",
            $clientId,
            $callType,
            $cycle
        ]);

        if ($schedule->rowCount() > 0) {
            error_log("Retry für Client {$clientId}, {$callType}, in 15 Minuten geplant.");
        }
        return;
    }

    // Zyklus 2 ist endgültig fehlgeschlagen: Status sichern und Kontakte informieren.
    $finish = $db->prepare("
        UPDATE call_status
        SET status = 'failed',
            scheduled_time = NULL,
            summary = ?
        WHERE client_id = ? AND call_type = ?
          AND status = 'calling' AND attempt_cycle = ?
    ");
    $finish->execute([
        "Beide Anrufzyklen fehlgeschlagen. Notfallkontakte werden informiert. Ursache: {$reason}",
        $clientId,
        $callType,
        $cycle
    ]);

    if ($finish->rowCount() > 0) {
        $incidentReason = "Person nach allen Anrufversuchen nicht erreicht. " .
            "Zyklus 1: tel1 und tel2; Zyklus 2 nach 15 Minuten: tel1 und tel2. " .
            "Notfallkontakte wurden informiert. Ursache/Details: {$reason}";

        // Endgültigen Nichterreichbarkeits-Vorfall in client_incidents protokollieren.
        logClientIncident($db, $clientId, $incidentReason);

        notifyEmergencyContacts($db, $clientId, $incidentReason);
        error_log("Endgültiger Nichterreichbarkeits-Vorfall für Client {$clientId} protokolliert; Notfallkontakte informiert.");
    }
}

function executeTwilioCall($db, $clientId, $telType, $cycle, $callType)
{
    $client = getClientData($db, $clientId);
    if (!$client) {
        return false;
    }

    $phone = ($telType === 'tel1') ? trim($client['tel1'] ?? '') : trim($client['tel2'] ?? '');
    if ($phone === '') {
        if ($telType === 'tel1' && !empty($client['tel2'])) {
            $stmt = $db->prepare("UPDATE call_status SET last_tel_used = 'tel2' WHERE client_id = ? AND call_type = ? AND status = 'calling' AND attempt_cycle = ?");
            $stmt->execute([$clientId, $callType, $cycle]);
            return executeTwilioCall($db, $clientId, 'tel2', $cycle, $callType);
        }
        if ($telType === 'tel2') {
            handleCallFailure($db, (int)$clientId, (string)$callType, 'tel2', (int)$cycle, 'Telefonnummer tel2 fehlt.');
        }
        return false;
    }

    if (!defined('TWILIO_ACCOUNT_SID') || !defined('TWILIO_AUTH_TOKEN') || !defined('TWILIO_PHONE_NUMBER')) {
        return false;
    }

    $baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    $webhookUrl = $baseUrl . '/call_manager.php?' . http_build_query([
        'step' => 'welcome',
        'clientId' => $clientId,
        'callType' => $callType,
        'telType' => $telType,
        'cycle' => $cycle
    ]);
    $statusCallbackUrl = $baseUrl . '/call_manager.php?' . http_build_query([
        'step' => 'call_status',
        'clientId' => $clientId,
        'callType' => $callType,
        'telType' => $telType,
        'cycle' => $cycle
    ]);
    $amdCallbackUrl = $baseUrl . '/call_manager.php?' . http_build_query([
        'step' => 'amd_callback',
        'clientId' => $clientId,
        'callType' => $callType,
        'telType' => $telType,
        'cycle' => $cycle
    ]);

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Calls.json';

    // ANRUFBEANTWORTER ERKENNUNG (AMD) FÜR REGULÄRE ANRUFE
    $postData = [
        'From' => TWILIO_PHONE_NUMBER,
        'To' => $phone,
        'Url' => $webhookUrl,
        'Method' => 'POST',
        'StatusCallback' => $statusCallbackUrl,
        'StatusCallbackMethod' => 'POST',
        'StatusCallbackEvent' => 'initiated ringing answered completed',
        'MachineDetection' => 'DetectMessageEnd',
        'AsyncAmd' => 'true',
        'AsyncAmdStatusCallback' => $amdCallbackUrl,
        'AsyncAmdStatusCallbackMethod' => 'POST',
        'Timeout' => 50
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

    if ($response === false || $httpCode < 200 || $httpCode >= 300 || empty($twilioSid)) {
        error_log("Twilio-Anruf konnte nicht erstellt werden. Client {$clientId}, {$callType}, {$telType}, Zyklus {$cycle}. HTTP {$httpCode}; cURL: {$curlError}; Antwort: " . substr((string)$response, 0, 1200));
        return false;
    }

    error_log("Twilio-Anruf erstellt. Client {$clientId}, {$callType}, {$telType}, Zyklus {$cycle}, CallSid {$twilioSid}.");

    return true;
}

function getClientData($db, $clientId)
{
    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

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

function initializeCallStatus($db, $clientId, $callType)
{
    $stmt = $db->prepare("
        INSERT INTO call_status
        (client_id, attempt_cycle, last_tel_used, call_type, status, scheduled_time, medication_required, medication_checked)
        VALUES (?, 1, 'tel1', ?, 'calling', NOW(), 1, 0)
        ON DUPLICATE KEY UPDATE status = 'calling', attempt_cycle = 1
    ");
    $stmt->execute([$clientId, $callType]);
}

function markCallCompleted($db, $clientId, $callType, $summary)
{
    $stmt = $db->prepare("
        UPDATE call_status
        SET status = 'completed', summary = ?
        WHERE client_id = ? AND call_type = ? AND status = 'calling'
    ");
    $stmt->execute([$summary, $clientId, $callType]);
}

function logClientIncident($db, $clientId, $reason)
{
    $stmt = $db->prepare("
        INSERT INTO client_incidents (client_id, reason, created_at)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$clientId, $reason]);
}

function updateCallStatusSummary($db, $clientId, $callType, $summary)
{
    $stmt = $db->prepare("
        UPDATE call_status
        SET status = 'incident_reported', summary = ?
        WHERE client_id = ? AND call_type = ?
    ");
    $stmt->execute([$summary, $clientId, $callType]);
}


function notifyEmergencyContacts(PDO $db, int $clientId, string $reason): void
{
    if ($clientId <= 0) {
        error_log("NOTIFY ERROR: Ungültige Client-ID: {$clientId}");
        return;
    }

    // Kundendaten einschließlich SMS-Freigabe laden.
    $stmt = $db->prepare("
        SELECT firstname, lastname, title, sms_status
        FROM clients
        WHERE id = ?
    ");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        error_log("NOTIFY ERROR: Client {$clientId} nicht gefunden.");
        return;
    }

    $clientName = trim(
        ($client['title'] ?? '') . ' ' .
            ($client['firstname'] ?? '') . ' ' .
            ($client['lastname'] ?? '')
    );

    // SMS nur versenden, wenn beim Kunden aktiviert.
    $smsEnabled = !empty($client['sms_status']);

    // Notfallkontakte laden.
    $stmtContacts = $db->prepare("
        SELECT firstname, lastname, email, tel1
        FROM contacts
        WHERE client_id = ?
    ");
    $stmtContacts->execute([$clientId]);
    $contacts = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

    if (empty($contacts)) {
        error_log("NOTIFY ERROR: Keine Notfallkontakte für Client {$clientId} gefunden.");
        return;
    }

    $subject = "NOTFALL-ALARM: {$clientName} benötigt Hilfe!";

    $messageText =
        "giulianaCARE NOTFALLMELDUNG\n\n" .
        "Betroffene Person: {$clientName}\n\n" .
        "Grund: {$reason}\n\n" .
        "Bitte nehmen Sie umgehend Kontakt auf und überprüfen Sie, ob Hilfe benötigt wird.";

    error_log(
        "NOTIFY: Client {$clientId}: " . count($contacts) .
            " Notfallkontakt(e). SMS-Status: " .
            ($smsEnabled ? 'AKTIV' : 'INAKTIV')
    );

    foreach ($contacts as $contact) {

        $contactName = trim(
            ($contact['firstname'] ?? '') . ' ' .
                ($contact['lastname'] ?? '')
        );

        // =====================================
        // 1. E-MAIL VERSENDEN
        // =====================================
        if (!empty($contact['email'])) {

            $headers =
                "From: giulianaCARE <info@giuliana-care.de>\r\n" .
                "Reply-To: info@giuliana-care.de\r\n" .
                "MIME-Version: 1.0\r\n" .
                "Content-Type: text/plain; charset=UTF-8\r\n";

            $mailResult = mail(
                $contact['email'],
                $subject,
                $messageText,
                $headers
            );

            error_log(
                "NOTIFY EMAIL: Kontakt {$contactName} ({$contact['email']}): " .
                    ($mailResult ? "an Mailserver übergeben" : "VERSAND FEHLGESCHLAGEN")
            );
        } else {
            error_log(
                "NOTIFY EMAIL: Keine E-Mail-Adresse für {$contactName} hinterlegt."
            );
        }

        // =====================================
        // 2. SMS ÜBER TWILIO VERSENDEN
        // =====================================
        $phoneToSms = trim($contact['tel1'] ?? '');

        if ($smsEnabled) {

            if ($phoneToSms !== '') {

                error_log(
                    "NOTIFY SMS: Starte SMS an {$contactName} ({$phoneToSms})."
                );

                try {
                    sendSmsNotification($phoneToSms, $messageText);
                } catch (Throwable $e) {
                    error_log(
                        "NOTIFY SMS ERROR für {$contactName}: " . $e->getMessage()
                    );
                }
            } else {
                error_log(
                    "NOTIFY SMS: Keine Telefonnummer tel1 für {$contactName} vorhanden."
                );
            }
        } else {
            error_log(
                "NOTIFY SMS: SMS-Versand deaktiviert (sms_status) für Client {$clientId}."
            );
        }
    }
}


/**
 * Versendet eine SMS über die Twilio REST API.
 */
function sendSmsNotification(string $phoneNumber, string $message): void
{
    if (
        !defined('TWILIO_ACCOUNT_SID') ||
        !defined('TWILIO_AUTH_TOKEN') ||
        !defined('TWILIO_PHONE_NUMBER')
    ) {
        throw new RuntimeException(
            "Twilio-Zugangsdaten fehlen in config.php."
        );
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException("PHP-cURL ist nicht aktiviert.");
    }

    $phoneNumber = trim($phoneNumber);

    if ($phoneNumber === '') {
        throw new InvalidArgumentException("SMS-Zielnummer ist leer.");
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/" .
        TWILIO_ACCOUNT_SID . "/Messages.json";

    $postData = [
        'From' => TWILIO_PHONE_NUMBER,
        'To'   => $phoneNumber,
        'Body' => $message
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_USERPWD => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($response === false || $httpCode !== 201) {
        error_log(
            "TWILIO SMS FEHLER: HTTP {$httpCode}; " .
                "cURL: {$curlError}; Antwort: " .
                substr((string)$response, 0, 1000)
        );

        throw new RuntimeException(
            "Twilio konnte die SMS nicht annehmen (HTTP {$httpCode})."
        );
    }

    $result = json_decode($response, true);
    $messageSid = $result['sid'] ?? 'unbekannt';

    error_log(
        "TWILIO SMS ERFOLGREICH ANGENOMMEN: {$phoneNumber}; SID: {$messageSid}"
    );
}
