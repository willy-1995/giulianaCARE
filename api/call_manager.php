<?php
// 1. Output-Buffering aktivieren (schützt vor kaputten JSON-Responses)
ob_start();

// Fehler-Logging konfigurieren
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/my_php_errors.log');

header("Content-Type: application/json; charset=UTF-8");

try {
    // Config & Database laden
    require_once __DIR__ . "/config.php";
    require_once __DIR__ . "/database.php";

    // --- 1. WEBHOOK-JSON SOFORT AUSLESEN ---
    $rawInput = file_get_contents('php://input');

    if (!empty($rawInput)) {
        file_put_contents(
            __DIR__ . '/vapi_webhook_debug.log',
            date('Y-m-d H:i:s') . " - " . $rawInput . "\n\n",
            FILE_APPEND
        );
    }

    $input = json_decode($rawInput, true);

    if (!is_array($input)) {
        $input = [];
    }

    // --- 2. VAPI WEBHOOKS DIREKT ABFANGEN ---
    $messageType = $input['message']['type'] ?? '';

    if (
        $messageType === 'tool-calls'
        || isset($input['message']['toolCalls'])
        || isset($input['toolCalls'])
    ) {
        $dbInstance = new Database();
        $db = $dbInstance->getConnection();

        handleVapiToolCall($db, $input);
        exit;
    }

    if ($messageType === 'end-of-call-report') {
        $dbInstance = new Database();
        $db = $dbInstance->getConnection();

        handleVapiWebhook($db, $input);

        echo json_encode(["success" => true]);
        exit;
    }

    // --- 3. STANDARD FORMULAR- & API-ANFRAGEN ---
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['action'])
    ) {
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
                    initializeCallStatus(
                        $db,
                        (int)$clientId,
                        $callType
                    );

                    executeCall(
                        $db,
                        (int)$clientId,
                        'tel1',
                        1,
                        $callType
                    );

                    echo json_encode([
                        "success" => true,
                        "message" => "Anruf gestartet."
                    ]);
                    exit;
                } else {
                    http_response_code(400);
                    echo json_encode([
                        "success" => false,
                        "message" => "Keine Client ID übergeben."
                    ]);
                    exit;
                }

            case 'test_call':
                checkRateLimit(
                    'landingpage_test_call',
                    2,
                    3600
                );

                if ($directPhoneNumber) {
                    executeDirectTestCall($directPhoneNumber);

                    echo json_encode([
                        "success" => true,
                        "message" => "Testanruf gestartet."
                    ]);
                    exit;
                } else {
                    http_response_code(400);
                    echo json_encode([
                        "success" => false,
                        "message" => "Keine Telefonnummer übergeben."
                    ]);
                    exit;
                }

            default:
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Aktion nicht erkannt."
                ]);
                exit;
        }
    }
} catch (PDOException $e) {
    error_log("Database Error (Vapi Webhook): " . $e->getMessage());
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Ein interner Datenbankfehler ist aufgetreten."
    ]);
    exit;
} catch (Exception $e) {
    error_log("Server Error (Vapi Webhook): " . $e->getMessage());
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Ein unerwarteter Serverfehler ist aufgetreten."
    ]);
    exit;
}


// ==================================================
// VAPI TOOL CALLS (OPTIMIERT AUF SCHNELLIGKEIT & LOGIK)
// ==================================================

function handleVapiToolCall($db, $data)
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    $toolCalls = $data['message']['toolCalls']
        ?? $data['message']['toolCallList']
        ?? [];

    $metadata = $data['message']['call']['metadata']
        ?? $data['message']['metadata']
        ?? [];

    $metadata = array_merge(
        $data['message']['call']['assistantOverrides']['variableValues'] ?? [],
        $metadata
    );

    $clientId = isset($metadata['clientId']) ? (int)$metadata['clientId'] : 0;
    $callType = $metadata['callType'] ?? 'call_1';

    $results = [];
    $reason = 'Unwohlsein / Notfall geäußert';
    $notifyEmergency = false;

    foreach ($toolCalls as $toolCall) {
        $toolCallId = $toolCall['id'] ?? null;
        $functionName = $toolCall['function']['name'] ?? $toolCall['name'] ?? '';

        $rawArgs = $toolCall['function']['arguments']
            ?? $toolCall['arguments']
            ?? '{}';
        $args = is_array($rawArgs) ? $rawArgs : (json_decode($rawArgs, true) ?: []);

        // --------------------------------------------------
        // 1. MEDIKATION DOKUMENTIEREN & DIREKT ABSCHLIESSEN
        // --------------------------------------------------
        if ($functionName === 'confirmMedication') {
            $answer = trim((string)($args['response'] ?? ''));
            if ($answer === '') {
                $answer = 'Antwort erfasst';
            }

            $wellbeingOk = isset($args['wellbeing_confirmed']) ? (bool)$args['wellbeing_confirmed'] : false;

            if ($clientId > 0) {
                if ($wellbeingOk) {
                    $stmt = $db->prepare("
                        UPDATE call_status
                        SET medication_checked = 1,
                            medication_response = ?,
                            status = 'completed',
                            summary = ?
                        WHERE client_id = ?
                          AND call_type = ?
                          AND status = 'calling'
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $stmt->execute([
                        $answer,
                        "Wohlbefinden bestätigt; Medikation abgefragt ($answer).",
                        $clientId,
                        $callType
                    ]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE call_status
                        SET medication_checked = 1,
                            medication_response = ?
                        WHERE client_id = ?
                          AND call_type = ?
                          AND status = 'calling'
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $stmt->execute([
                        $answer,
                        $clientId,
                        $callType
                    ]);
                }
            }

            $results[] = [
                'toolCallId' => $toolCallId,
                'result' => $wellbeingOk
                    ? 'Medikation und Wohlbefinden gespeichert. Verabschiede dich jetzt kurz und freundlich und beende das Gespräch.'
                    : 'Medikation gespeichert. Falls Wohlbefinden bestätigt, rufe jetzt confirmWellbeing auf.'
            ];
        }

        // --------------------------------------------------
        // 2. WOHLBEFINDEN & ABSCHLUSS
        // --------------------------------------------------
        if ($functionName === 'confirmWellbeing') {
            $blockedByMedication = false;

            if ($clientId > 0) {
                $checkStmt = $db->prepare("
                    SELECT id, medication_required, medication_checked
                    FROM call_status
                    WHERE client_id = ?
                      AND call_type = ?
                      AND status = 'calling'
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $checkStmt->execute([$clientId, $callType]);
                $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($row && (int)$row['medication_required'] === 1 && (int)$row['medication_checked'] === 0) {
                    $blockedByMedication = true;
                } else {
                    $upd = $db->prepare("
                        UPDATE call_status
                        SET status = 'completed',
                            summary = 'Wohlbefinden bestätigt; Medikamentenabfrage abgeschlossen oder nicht erforderlich.'
                        WHERE client_id = ?
                          AND call_type = ?
                          AND status = 'calling'
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $upd->execute([$clientId, $callType]);
                }
            }

            $results[] = [
                'toolCallId' => $toolCallId,
                'result' => $blockedByMedication
                    ? 'STOPP: Die hinterlegte Medikation wurde noch nicht abgefragt! Frage den Klienten jetzt freundlich danach.'
                    : 'Abgeschlossen. Verabschiede dich jetzt freundlich und beende das Gespräch.'
            ];
        }

        // --------------------------------------------------
        // 3. VORFALL / UNWOHLSEIN MELDEN (NUR PRIVATE KONTAKTE)
        // --------------------------------------------------
        if ($functionName === 'triggerEmergencyCall') {
            $reason = trim((string)($args['reason'] ?? $reason));
            $isSevere = isset($args['is_severe']) ? (bool)$args['is_severe'] : true;
            $clientRequested = isset($args['client_requested']) ? (bool)$args['client_requested'] : false;

            if ($clientId > 0) {
                try {
                    $stmt = $db->prepare("
                        UPDATE call_status
                        SET status = 'incident_reported',
                            summary = ?
                        WHERE client_id = ?
                          AND call_type = ?
                          AND status = 'calling'
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $stmt->execute([
                        "Klient meldet Vorfall/Unwohlsein: " . $reason,
                        $clientId,
                        $callType
                    ]);

                    logClientIncident($db, $clientId, $reason);

                    if ($isSevere || $clientRequested) {
                        $notifyEmergency = true;
                    }
                } catch (Exception $e) {
                    error_log("DB ERROR (triggerEmergencyCall): " . $e->getMessage());
                }
            }

            $results[] = [
                'toolCallId' => $toolCallId,
                'result' => $notifyEmergency
                    ? 'Ihre privaten Notfallkontakte werden jetzt informiert. Beende das Gespräch sofort freundlich.'
                    : 'Vorfall protokolliert, keine Benachrichtigung gewünscht. Verabschiede dich kurz und beende das Gespräch.'
            ];
        }
    }

    // Antwort sofort an Vapi senden
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

    if ($notifyEmergency && $clientId > 0) {
        try {
            notifyEmergencyContacts($db, $clientId, $reason);
            error_log("SUCCESS: Emergency contacts notified in background for client $clientId");
        } catch (Exception $e) {
            error_log("NOTIFICATION ERROR: " . $e->getMessage());
        }
    }

    exit;
}


// ==================================================
// ANRUF STARTEN
// ==================================================

function executeCall(
    $db,
    $clientId,
    $telType,
    $cycle,
    $callType = 'call_1'
) {
    error_log(
        "CALL START [Versuch $cycle]: Client ID $clientId | Typ: $callType | Nummerntyp: $telType"
    );

    $stmt = $db->prepare("
        SELECT
            title,
            firstname,
            lastname,
            tel1,
            tel2,
            call_1,
            call_2,
            call_3,
            medication_1,
            medication_2,
            medication_3
        FROM clients
        WHERE id = ?
          AND status = 'active'
    ");

    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        error_log("VAPI ERROR: Client ID $clientId nicht in Datenbank gefunden oder inaktiv.");
        return;
    }

    $phone = ($telType === 'tel1')
        ? ($client['tel1'] ?? '')
        : ($client['tel2'] ?? '');

    if (empty($phone)) {
        error_log("VAPI ERROR: Keine Telefonnummer ($telType) für Client ID $clientId vorhanden.");

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

    $hasMedication = !empty($currentMedication);

    $medicationBlock = $hasMedication
        ? "HINTERLEGTE MEDIKATION / ZUSATZINFO: \"$currentMedication\"\n"
        . "- Frage nach Schritt 1 freundlich nach dieser Medikation (z. B. \"Haben Sie Ihre Medikamente, $currentMedication, schon eingenommen?\").\n"
        . "- Sobald der Klient antwortet (egal ob Ja oder Nein), rufe `confirmMedication` auf und frage NIEMALS ein zweites danach."
        : "HINTERLEGTE MEDIKATION / ZUSATZINFO: Keine.\n"
        . "- Rufe `confirmMedication` nicht auf. Wenn es dem Klienten gut geht, rufe direkt `confirmWellbeing` auf.";

    $systemPrompt = <<<PROMPT
Du bist die telefonische Assistenz der Seniorenbetreuung von Dschuliana Kär.
Sprich natürlich, herzlich, ruhig und kurz (maximal 1–2 Sätze pro Antwort).

STRENGE REGELN:
1. IMMER SIEZEN: Verwende ausnahmslos "Sie" und "Ihnen".
2. KEINE FÜLLWÖRTER: Sage NIEMALS "Warte mal kurz", "Moment mal" oder Ähnliches.
3. KEIN ANRUFBEANTWORTZER: Bei Mailbox/Anrufbeantworter sofort stumm auflegen.
4. NICHT IN DER LEITUNG HALTEN: Sage niemals, der Klient solle am Telefon bleiben. Beende das Gespräch zügig, sobald alles besprochen ist.
5. KEINE NOTRUF-VERWECHSLUNG: Du bist KEIN Notruf und bietest NIEMALS an, den Notruf (112) zu wählen. Du informierst ausschließlich die hinterlegten **privaten Notfallkontakte** (Angehörige/Betreuer).

ABLAUF (SCHRITT FÜR SCHRITT):

SCHRITT 1 – WOHLBEFINDEN & BESCHWERDEN:
- Die Begrüßung fragte bereits nach dem Befinden.
- Wenn der Klient sagt, es geht ihm SCHLECHT, er hat Schmerzen, Schwindel oder Unwohlsein:
  * Frage ihn IMMER sofort und natürlich: "Möchten Sie, dass ich Ihre Notfallkontakte darüber informiere?"
  * Sagt er JA: Rufe `triggerEmergencyCall` (`is_severe: false`, `client_requested: true`) auf. Sage: "Ich benachrichtige jetzt Ihre Notfallkontakte. Alles Gute, auf Wiederhören." und beende das Gespräch.
  * Sagt er NEIN: Rufe `triggerEmergencyCall` (`is_severe: false`, `client_requested: false`) auf und fahre mit Schritt 2 fort.
- Wenn er einen SCHWEREN Notfall schildert (Sturz, Atemnot, absolute Hilflosigkeit): 
  * Rufe SOFORT `triggerEmergencyCall` (`is_severe: true`) auf, sage: "Ich benachrichtige sofort Ihre Notfallkontakte. Bitte wählen Sie bei akuten Beschwerden selbst den Notruf 112. Ich beende jetzt das Gespräch, damit Ihre Leitung frei ist. Auf Wiederhören." und beende den Anruf.
- Wenn er sagt, es geht ihm gut ("Mir geht es gut", "Alles bestens"): Fahre direkt mit Schritt 2 fort.

SCHRITT 2 – MEDIKATION / ZUSATZINFO:
$medicationBlock

SCHRITT 3 – ABSCHLUSS:
- Wenn Medikation da war: Rufe `confirmMedication` mit `wellbeing_confirmed: true` auf. Verabschiede dich: "Vielen Dank, das habe ich vermerkt. Ich wünsche Ihnen noch einen schönen Tag. Auf Wiederhören!"
- Wenn keine Medikation da war: Rufe `confirmWellbeing` auf und verabschiede dich genauso.
PROMPT;

    $silentToolMessages = [
        ['type' => 'request-start', 'content' => ''],
        ['type' => 'request-response-delayed', 'content' => '']
    ];

    $payload = [
        'assistantId' => VAPI_ASSISTANT_ID,
        'phoneNumberId' => VAPI_PHONE_ID,

        'assistantOverrides' => [
            'firstMessage' =>
            "Guten Tag " . $titlePrefix . $fullName
                . ", hier ist die Assistenz von Dschuliana Kär. Ich wollte kurz fragen, ob bei Ihnen alles in Ordnung ist?",

            'responseDelaySeconds' => 0.15,
            'llmRequestDelaySeconds' => 0.05,
            'numWordsToInterruptAssistant' => 2,
            'backchannelingEnabled' => false,
            'silenceTimeoutSeconds' => 25,
            'maxDurationSeconds' => 180,

            'endCallPhrases' => [
                "Auf Wiederhören!",
                "Auf Wiederhören.",
                "Einen schönen Tag. Auf Wiederhören."
            ],
            'endCallFunctionEnabled' => true,

            'voicemailDetection' => [
                'provider' => 'vapi',
                'backoffMode' => 'disabled',
                'voicemailDetectionTypes' => [
                    'machine_start',
                    'machine_end_tone',
                    'machine_end_silence'
                ],
                'enabled' => true
            ],
            'voicemailMessage' => '',

            'model' => [
                'provider' => 'azure-openai',
                'model' => 'gpt-5.4-mini',
                'temperature' => 0.1,
                'maxTokens' => 100,

                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ]
                ],

                'tools' => [
                    [
                        'type' => 'function',
                        'messages' => $silentToolMessages,
                        'function' => [
                            'name' => 'confirmMedication',
                            'description' => 'Einmalig aufrufen, sobald der Klient auf die Medikamentenfrage geantwortet hat.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'response' => [
                                        'type' => 'string',
                                        'description' => 'Die Antwort des Klienten zur Medikation.'
                                    ],
                                    'wellbeing_confirmed' => [
                                        'type' => 'boolean',
                                        'description' => 'true, wenn das Wohlbefinden bereits bestätigt ist.'
                                    ]
                                ],
                                'required' => ['response', 'wellbeing_confirmed']
                            ]
                        ],
                        'async' => false
                    ],
                    [
                        'type' => 'function',
                        'messages' => $silentToolMessages,
                        'function' => [
                            'name' => 'confirmWellbeing',
                            'description' => 'Aufrufen, wenn keine Medikation hinterlegt ist und Wohlbefinden bestätigt wurde.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => new stdClass()
                            ]
                        ],
                        'async' => false
                    ],
                    [
                        'type' => 'function',
                        'messages' => $silentToolMessages,
                        'function' => [
                            'name' => 'triggerEmergencyCall',
                            'description' => 'Protokolliert Unwohlsein und steuert die Benachrichtigung der privaten Notfallkontakte.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'reason' => [
                                        'type' => 'string',
                                        'description' => 'Konkrete Beschwerden oder Gründe.'
                                    ],
                                    'is_severe' => [
                                        'type' => 'boolean',
                                        'description' => 'true bei schweren Notfällen, false bei leichtem Unwohlsein.'
                                    ],
                                    'client_requested' => [
                                        'type' => 'boolean',
                                        'description' => 'true wenn der Klient bei leichtem Unwohlsein um Benachrichtigung gebeten hat.'
                                    ]
                                ],
                                'required' => ['reason', 'is_severe']
                            ]
                        ],
                        'async' => false
                    ]
                ]
            ],

            'variableValues' => [
                'clientId' => (string)$clientId,
                'cycle' => (string)$cycle,
                'telType' => $telType,
                'callType' => $callType
            ]
        ],

        'customer' => [
            'number' => $phone
        ],

        'metadata' => [
            'clientId' => (string)$clientId,
            'cycle' => (string)$cycle,
            'telType' => $telType,
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

    if ($httpCode === 201) {
        $updateStmt = $db->prepare("
            UPDATE call_status
            SET last_tel_used = ?,
                attempt_cycle = ?,
                status = 'calling'
            WHERE client_id = ?
              AND call_type = ?
              AND status = 'calling'
            ORDER BY id DESC
            LIMIT 1
        ");

        $updateStmt->execute([
            $telType,
            $cycle,
            $clientId,
            $callType
        ]);
    } else {
        $maskedPhone = substr($phone, 0, 5) . '******' . substr($phone, -2);
        error_log(
            "VAPI CRITICAL ERROR [Client ID: $clientId | Tel: $maskedPhone]: HTTP $httpCode | cURL Err: $curlError | Response: $response"
        );

        escalateCall($db, $clientId, $cycle, $telType, $callType);
    }
}


// ==================================================
// NOTFALL PROTOKOLLIEREN
// ==================================================

function logClientIncident(
    PDO $db,
    int $clientId,
    string $reason
): void {
    $stmt = $db->prepare("
        INSERT INTO client_incidents (
            client_id,
            reason,
            created_at
        )
        VALUES (?, ?, NOW())
    ");

    $stmt->execute([
        $clientId,
        $reason
    ]);
}


// ==================================================
// VAPI ABSCHLUSS-WEBHOOK
// ==================================================

function handleVapiWebhook($db, $data)
{
    $message = $data['message'] ?? [];

    $endedReason = $message['endedReason']
        ?? $message['call']['endedReason']
        ?? '';

    $summary = $message['analysis']['summary'] ?? '';
    $transcript = $message['transcript'] ?? '';

    if (!is_string($transcript)) {
        $transcript = '';
    }

    $metadata = $message['call']['metadata']
        ?? $message['metadata']
        ?? $data['metadata']
        ?? [];

    $metadata = array_merge(
        $message['call']['assistantOverrides']['variableValues'] ?? [],
        $metadata
    );

    $clientId = isset($metadata['clientId']) ? (int)$metadata['clientId'] : null;
    $cycle = isset($metadata['cycle']) ? (int)$metadata['cycle'] : 1;
    $telType = $metadata['telType'] ?? 'tel1';
    $callType = $metadata['callType'] ?? 'call_1';

    if (!$clientId) {
        error_log("VAPI WEBHOOK ERROR: Konnte Client ID nicht auslesen!");
        return;
    }

    $stmt = $db->prepare("
        SELECT
            id,
            status,
            attempt_cycle,
            last_tel_used,
            call_type
        FROM call_status
        WHERE client_id = ?
          AND call_type = ?
          AND attempt_cycle = ?
          AND last_tel_used = ?
          AND status = 'calling'
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $clientId,
        $callType,
        $cycle,
        $telType
    ]);

    $state = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$state
        || $state['status'] !== 'calling'
        || (int)$state['attempt_cycle'] !== $cycle
        || $state['last_tel_used'] !== $telType
        || $state['call_type'] !== $callType
    ) {
        error_log(
            "VAPI WEBHOOK IGNORIERT: Client $clientId, Zyklus $cycle, $telType, Status/Versuch nicht mehr aktuell."
        );
        return;
    }

    $failedReasons = [
        'customer-did-not-answer',
        'voicemail',
        'phone-call-provider-closed-call',
        'pipeline-error-twilio-execution-failed'
    ];

    $isVoicemail = false;
    $voicemailKeywords = [
        'voicemail',
        'anrufbeantworter',
        'sprachbox',
        'piepton'
    ];

    $isUnreachableAnnouncement = (bool)preg_match(
        '/(?:^|[\s.!?])die angerufene person ist nicht erreichbar[.!?]?/iu',
        $transcript
    );

    $conversationMessages = $message['artifact']['messages']
        ?? $message['messages']
        ?? [];

    if (!$isUnreachableAnnouncement && is_array($conversationMessages)) {
        foreach ($conversationMessages as $entry) {
            if (!is_array($entry) || ($entry['role'] ?? '') !== 'user') {
                continue;
            }

            $spoken = trim((string)($entry['message'] ?? $entry['content'] ?? ''));

            if (preg_match('/^die angerufene person ist nicht erreichbar[.!?]?$/iu', $spoken)) {
                $isUnreachableAnnouncement = true;
                break;
            }
        }
    }

    foreach ($voicemailKeywords as $keyword) {
        if (
            stripos($summary, $keyword) !== false
            || stripos($transcript, $keyword) !== false
        ) {
            $isVoicemail = true;
            break;
        }
    }

    if (
        in_array($endedReason, $failedReasons, true)
        || $isVoicemail
        || $isUnreachableAnnouncement
    ) {
        error_log(
            "VAPI INFO: Anruf nicht erreicht ($endedReason). Eskalation für Client ID $clientId ($telType, Cycle $cycle)..."
        );

        escalateCall($db, $clientId, $cycle, $telType, $callType);
        return;
    }

    error_log(
        "VAPI UNKLARES GESPRÄCHSERGEBNIS: Client $clientId, endedReason=$endedReason. Keine automatische Nichterreichbarkeitseskalation."
    );

    $stmt = $db->prepare("
        UPDATE call_status
        SET status = 'review_required',
            summary = ?
        WHERE id = ?
          AND status = 'calling'
    ");

    $stmt->execute([
        "Gesprächsausgang unklar: " . $summary,
        $state['id']
    ]);
}


// ==================================================
// ESKALATION: TEL1 -> TEL2
// ==================================================

function escalateCall(
    $db,
    $clientId,
    $cycle,
    $telType,
    $callType = 'call_1'
) {
    error_log(
        "ESKALATION AUFGERUFEN: Client=$clientId | Cycle=$cycle | TelType=$telType | CallType=$callType"
    );

    $stmt = $db->prepare("
        SELECT
            id,
            status,
            attempt_cycle,
            last_tel_used,
            call_type
        FROM call_status
        WHERE client_id = ?
          AND call_type = ?
          AND attempt_cycle = ?
          AND last_tel_used = ?
          AND status = 'calling'
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $clientId,
        $callType,
        $cycle,
        $telType
    ]);

    $state = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$state
        || $state['status'] !== 'calling'
        || (int)$state['attempt_cycle'] !== (int)$cycle
        || $state['last_tel_used'] !== $telType
        || $state['call_type'] !== $callType
    ) {
        error_log("ESKALATION ABGEBROCHEN: Vorgang nicht mehr aktuell für Client $clientId.");
        return;
    }

    $stmt = $db->prepare("
        SELECT
            title,
            firstname,
            lastname,
            tel1,
            tel2
        FROM clients
        WHERE id = ?
    ");

    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        return;
    }

    if ($telType === 'tel1') {
        if (!empty($client['tel2'])) {
            $lockStmt = $db->prepare("
                UPDATE call_status
                SET last_tel_used = 'tel2'
                WHERE id = ?
                  AND status = 'calling'
                  AND attempt_cycle = ?
                  AND last_tel_used = 'tel1'
                  AND call_type = ?
            ");

            $lockStmt->execute([
                $state['id'],
                $cycle,
                $callType
            ]);

            if ($lockStmt->rowCount() === 1) {
                error_log("ESKALATION: Wechsel von tel1 zu tel2 für Client ID $clientId (Cycle $cycle)");
                executeCall($db, $clientId, 'tel2', $cycle, $callType);
            }
        } else {
            handleFailedCycle($db, $clientId, $cycle, $callType);
        }
    } elseif ($telType === 'tel2') {
        handleFailedCycle($db, $clientId, $cycle, $callType);
    }
}


// ==================================================
// ZYKLUS FEHLGESCHLAGEN
// ==================================================

function handleFailedCycle(
    $db,
    $clientId,
    $cycle,
    $callType
) {
    if ((int)$cycle === 1) {
        $stmt = $db->prepare("
            UPDATE call_status
            SET status = 'retry_scheduled',
                attempt_cycle = 2,
                scheduled_time = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
            WHERE client_id = ?
              AND status = 'calling'
              AND attempt_cycle = 1
              AND call_type = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([$clientId, $callType]);

        if ($stmt->rowCount() === 1) {
            error_log("RETRY SCHEDULED: Klient ID $clientId für Retry (Versuch 2 in 15 Min) vorgemerkt.");
        }
    } else {
        $stmt = $db->prepare("
            UPDATE call_status
            SET status = 'failed',
                summary = ?
            WHERE client_id = ?
              AND status = 'calling'
              AND attempt_cycle = 2
              AND call_type = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'Niemand erreicht nach 2 Zyklen (tel1 & tel2).',
            $clientId,
            $callType
        ]);

        if ($stmt->rowCount() === 1) {
            notifyEmergencyContacts($db, $clientId, null);
            error_log("CALL FAILED: Klient ID $clientId nach 2 Zyklen endgültig nicht erreicht. Notfallkontakte EINMALIG benachrichtigt.");
        }
    }
}


// ==================================================
// NOTFALLKONTAKTE BENACHRICHTIGEN
// ==================================================

function notifyEmergencyContacts(
    PDO $db,
    int $clientId,
    ?string $customReason = null
): void {
    if ($clientId <= 0) {
        return;
    }

    $stmt = $db->prepare("
        SELECT
            lastname,
            firstname,
            title,
            sms_status
        FROM clients
        WHERE id = ?
    ");

    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        return;
    }

    $clientName = trim(
        ($client['title'] ?? '') . ' ' . $client['firstname'] . ' ' . $client['lastname']
    );

    $smsEnabled = !empty($client['sms_status']);

    $stmtContacts = $db->prepare("
        SELECT
            lastname,
            firstname,
            email,
            tel1
        FROM contacts
        WHERE client_id = ?
    ");

    $stmtContacts->execute([$clientId]);
    $contacts = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

    if (empty($contacts)) {
        return;
    }

    $subject = "NOTFALL-ALARM: $clientName benötigt Hilfe!";

    $messageText = $customReason
        ? "giulianaCARE Warnung: $clientName hat im Telefonat angegeben, dass es ihm/ihr nicht gut geht.\n\nSymptome/Grund: $customReason\n\nBitte werden Sie umgehend aktiv!"
        : "giulianaCARE Warnung: $clientName konnte nach automatischen Anrufversuchen nicht erreicht werden. Bitte werden Sie umgehend aktiv!";

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


// ==================================================
// SMS BENACHRICHTIGUNG
// ==================================================

function sendSmsNotification(
    string $phoneNumber,
    string $message
): void {
    if (
        !defined('TWILIO_ACCOUNT_SID')
        || !defined('TWILIO_AUTH_TOKEN')
        || !defined('TWILIO_PHONE_NUMBER')
    ) {
        return;
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";

    $postData = [
        'From' => TWILIO_PHONE_NUMBER,
        'To' => $phoneNumber,
        'Body' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);
    curl_exec($ch);
    curl_close($ch);
}


// ==================================================
// ANRUFSTATUS INITIALISIEREN
// ==================================================

function initializeCallStatus(
    $db,
    $clientId,
    $callType = 'call_1'
) {
    $column = [
        'call_1' => 'medication_1',
        'call_2' => 'medication_2',
        'call_3' => 'medication_3'
    ][$callType] ?? 'medication_1';

    $stmt = $db->prepare("
        SELECT $column
        FROM clients
        WHERE id = ?
    ");

    $stmt->execute([$clientId]);

    $medicationRequired = trim((string)$stmt->fetchColumn()) !== '' ? 1 : 0;

    $stmt = $db->prepare("
        INSERT INTO call_status (
            client_id,
            attempt_cycle,
            last_tel_used,
            call_type,
            status,
            scheduled_time,
            medication_required,
            medication_checked,
            medication_response
        )
        VALUES (
            ?,
            1,
            'tel1',
            ?,
            'calling',
            NOW(),
            ?,
            0,
            NULL
        )
        ON DUPLICATE KEY UPDATE
            status = 'calling',
            attempt_cycle = 1,
            last_tel_used = 'tel1',
            call_type = ?,
            scheduled_time = NOW(),
            medication_required = ?,
            medication_checked = 0,
            medication_response = NULL
    ");

    $stmt->execute([
        $clientId,
        $callType,
        $medicationRequired,
        $callType,
        $medicationRequired
    ]);
}


// ==================================================
// STATUS AKTUALISIEREN
// ==================================================

function updateCallStatus(
    $db,
    $clientId,
    $status,
    $summary
) {
    $stmt = $db->prepare("
        UPDATE call_status
        SET status = ?,
            summary = ?
        WHERE client_id = ?
    ");

    $stmt->execute([
        $status,
        $summary,
        $clientId
    ]);
}


// ==================================================
// DIREKTER TESTANRUF
// ==================================================

function executeDirectTestCall($phone)
{
    $systemPrompt = "Du bist ein freundlicher Test-Assistent. Sieze den Angerufenen und verabschiede dich höflich.";

    $payload = [
        'assistantId' => VAPI_ASSISTANT_ID,
        'phoneNumberId' => VAPI_PHONE_ID,

        'assistantOverrides' => [
            'firstMessage' => "Guten Tag! Dies ist ein Testanruf.",
            'responseDelaySeconds' => 0.15,
            'backchannelingEnabled' => false,

            'model' => [
                'provider' => 'azure-openai',
                'model' => 'gpt-5.4-mini',
                'maxTokens' => 80,

                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ]
                ]
            ]
        ],

        'customer' => [
            'number' => $phone
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

    curl_exec($ch);
    curl_close($ch);
}
