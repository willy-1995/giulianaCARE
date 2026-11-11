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

    $callStatus = $input['message']['status'] ?? '';

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
    error_log(
        "Database Error (Vapi Webhook): " . $e->getMessage()
    );

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
    error_log(
        "Server Error (Vapi Webhook): " . $e->getMessage()
    );

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
// VAPI TOOL CALLS
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
        $data['message']['call']['assistantOverrides']['variableValues']
            ?? [],
        $metadata
    );

    $clientId = isset($metadata['clientId'])
        ? (int)$metadata['clientId']
        : null;

    $results = [];
    $reason = 'Unwohlsein / Notfall geäußert';
    $notifyEmergency = false;

    foreach ($toolCalls as $toolCall) {
        $toolCallId = $toolCall['id'] ?? null;

        $functionName = $toolCall['function']['name']
            ?? $toolCall['name']
            ?? '';

        // Medikamentenabfrage protokollieren
        if ($functionName === 'confirmMedication') {
            $rawArgs = $toolCall['function']['arguments']
                ?? $toolCall['arguments']
                ?? '{}';

            $args = is_array($rawArgs)
                ? $rawArgs
                : json_decode($rawArgs, true);

            $answer = is_array($args)
                ? trim((string)($args['response'] ?? ''))
                : '';

            $saved = false;

            if ($clientId > 0 && $answer !== '') {
                $stmt = $db->prepare("
                    UPDATE call_status
                    SET medication_checked = 1,
                        medication_response = ?
                    WHERE client_id = ?
                      AND status = 'calling'
                      AND medication_required = 1
                      AND attempt_cycle = ?
                      AND last_tel_used = ?
                      AND call_type = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");

                $stmt->execute([
                    $answer,
                    $clientId,
                    (int)($metadata['cycle'] ?? 0),
                    $metadata['telType'] ?? '',
                    $metadata['callType'] ?? ''
                ]);

                $saved = $stmt->rowCount() === 1;
            }

            $results[] = [
                'toolCallId' => $toolCallId,
                'result' => $saved
                    ? 'Medikamentenabfrage erfolgreich dokumentiert. Fahre nun mit confirmWellbeing fort.'
                    : 'Medikamentenabfrage nicht gespeichert. Bitte überprüfe die Antwort des Klienten.'
            ];
        }

        // Entwarnung nur nach erfolgreicher Medikamentenabfrage (HARTE BACKEND-SPERRE)
        if ($functionName === 'confirmWellbeing') {
            $updated = false;

            if ($clientId > 0) {
                // Prüfe strikt, ob medication_required = 0 ODER bereits medication_checked = 1 ist
                $stmt = $db->prepare("
                    UPDATE call_status
                    SET status = 'completed',
                        summary = 'Wohlbefinden bestätigt; Medikamentenabfrage abgeschlossen oder nicht erforderlich.'
                    WHERE client_id = ?
                      AND status = 'calling'
                      AND (
                          medication_required = 0
                          OR medication_checked = 1
                      )
                      AND attempt_cycle = ?
                      AND last_tel_used = ?
                      AND call_type = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");

                $stmt->execute([
                    $clientId,
                    (int)($metadata['cycle'] ?? 0),
                    $metadata['telType'] ?? '',
                    $metadata['callType'] ?? ''
                ]);

                $updated = $stmt->rowCount() === 1;
            }

            $results[] = [
                'toolCallId' => $toolCallId,
                'result' => $updated
                    ? 'Entwarnung gespeichert. Verabschiede dich höflich und beende das Gespräch.'
                    : 'FEHLER: Entwarnung abgelehnt! Die Medikamentenabfrage wurde noch nicht in der Datenbank dokumentiert. Frage zwingend zuerst nach der Medikation!'
            ];
        }

        // Notfall / Unwohlsein verarbeiten
        if ($functionName === 'triggerEmergencyCall') {
            $rawArgs = $toolCall['function']['arguments']
                ?? $toolCall['arguments']
                ?? '{}';

            $args = is_array($rawArgs)
                ? $rawArgs
                : json_decode($rawArgs, true);

            $reason = $args['reason'] ?? $reason;
            $isSevere = isset($args['is_severe']) ? (bool)$args['is_severe'] : true;
            $clientRequested = isset($args['client_requested']) ? (bool)$args['client_requested'] : false;

            if ($clientId && $clientId > 0) {
                try {
                    $stmt = $db->prepare("
                        UPDATE call_status
                        SET status = 'incident_reported',
                            summary = ?
                        WHERE client_id = ?
                          AND status = 'calling'
                          AND attempt_cycle = ?
                          AND last_tel_used = ?
                          AND call_type = ?
                    ");

                    $stmt->execute([
                        "Klient meldet Vorfall/Unwohlsein: " . $reason,
                        $clientId,
                        (int)($metadata['cycle'] ?? 0),
                        $metadata['telType'] ?? '',
                        $metadata['callType'] ?? ''
                    ]);

                    logClientIncident(
                        $db,
                        $clientId,
                        $reason
                    );

                    // Notfallkontakte kontaktieren bei schwerem Vorfall ODER auf expliziten Wunsch
                    if ($isSevere || $clientRequested) {
                        $notifyEmergency = true;
                    }
                } catch (Exception $e) {
                    error_log(
                        "DB ERROR (triggerEmergencyCall): " . $e->getMessage()
                    );
                }
            }

            $results[] = [
                'toolCallId' => $toolCallId,
                'result' => $notifyEmergency
                    ? 'Vorfall erfasst. Die Benachrichtigung der Notfallkontakte wurde ausgelöst. Beende jetzt sofort höflich das Gespräch.'
                    : 'Vorfall erfasst. Der Klient wünschte keine Benachrichtigung. Beende jetzt höflich das Gespräch.'
            ];
        }
    }

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');

    $jsonResponse = json_encode([
        'results' => $results
    ]);

    if (function_exists('fastcgi_finish_request')) {
        echo $jsonResponse;
        session_write_close();
        fastcgi_finish_request();
    } else {
        header(
            "Content-Length: " . strlen($jsonResponse)
        );
        header("Connection: close");
        echo $jsonResponse;
        flush();
    }

    if (
        $notifyEmergency
        && $clientId
        && $clientId > 0
    ) {
        try {
            notifyEmergencyContacts(
                $db,
                $clientId,
                $reason
            );

            error_log(
                "SUCCESS: Emergency contacts notified in background for client $clientId"
            );
        } catch (Exception $e) {
            error_log(
                "NOTIFICATION ERROR: " . $e->getMessage()
            );
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
        error_log(
            "VAPI ERROR: Client ID $clientId nicht in Datenbank gefunden oder inaktiv."
        );
        return;
    }

    $phone = ($telType === 'tel1')
        ? ($client['tel1'] ?? '')
        : ($client['tel2'] ?? '');

    if (empty($phone)) {
        error_log(
            "VAPI ERROR: Keine Telefonnummer ($telType) für Client ID $clientId vorhanden."
        );

        if ($telType === 'tel1') {
            escalateCall(
                $db,
                $clientId,
                $cycle,
                'tel1',
                $callType
            );
        }

        return;
    }

    $phone = preg_replace('/[^0-9+]/', '', $phone);

    if (strpos($phone, '0') === 0) {
        $phone = '+49' . substr($phone, 1);
    }

    $titlePrefix = !empty($client['title'])
        ? trim($client['title']) . ' '
        : '';

    $fullName = trim(
        ($client['firstname'] ?? '') . ' '
            . ($client['lastname'] ?? '')
    );

    $currentMedication = '';

    switch ($callType) {
        case 'call_2':
            $currentMedication = trim(
                $client['medication_2'] ?? ''
            );
            break;

        case 'call_3':
            $currentMedication = trim(
                $client['medication_3'] ?? ''
            );
            break;

        case 'call_1':
        default:
            $currentMedication = trim(
                $client['medication_1'] ?? ''
            );
            break;
    }

    $medicationPromptSection = !empty($currentMedication)
        ? "PFLICHT-SCHRITT MEDIKATION/ZUSATZINFORMATION:\nFür diesen Anruf ist folgende Medikation/Information hinterlegt: \"$currentMedication\". DU MUSST DIESE INFORMATION IN JEDEM FALL ABFRAGEN ODER ANSPRECHEN. DIESER SCHRITT DARF NIEMALS ÜBERSPRUNGEN WERDEN."
        : "HINWEIS MEDIKATION:\nFür diesen Anruf ist keine spezifische Medikation hinterlegt.";

    $systemPrompt = <<<PROMPT
Du bist die empathische, hochprofessionelle telefonische Assistenz der Seniorenbetreuung von Dschuliana Kär.

STRENGE GRUNDREGELN:
1. ANSPRACHE & TONFALL: Sieze den Klienten IMMER konsequent ("Sie", "Ihnen"). Sprich kurz, klar, ruhig und verständlich (maximal 1-2 Sätze pro Gesprächsschritt). Verwende NIEMALS Umgangssprache wie "Warte mal kurz" (falls nötig: "Einen Moment bitte").
2. IDENTITÄT & NOTRUF-HINWEIS: Du bist KEIN Notruf, KEIN Rettungsdienst (112) und KEINE medizinische Einsatzzentrale. Sage dies bei akuten medizinischen Krisen sofort.
3. KEIN ANRUFBEANTWORTZER: Wenn du feststellst, dass eine Mailbox, ein Anrufbeantworter oder ein Signalton zu hören ist, beende das Gespräch SOFORT lautlos ohne ein Wort zu sprechen.
4. NIEMALS AM TELEFON HALTEN: Sage dem Klienten NIEMALS, dass er am Telefon oder in der Leitung bleiben soll (weder bei Beschwerden noch beim Auslösen von Benachrichtigungen). Die Telefonleitung des Klienten muss frei bleiben!
5. GESPRÄCHSBEENDIGUNG: Beende das Telefonat umgehend aktiv, sobald alle Punkte geklärt sind oder ein Notfall erfasst wurde.

OBLIGATORISCHER GESPRÄCHS- UND ENTSCHEIDUNGSABLAUF (PROZESSKETTE):

SCHRITT 1: WOHLBEFINDEN & AKUTSICHERHEIT (ERSTE PRÜFUNG)
Frage nach dem Befinden.
- SCHWERER VORFALL (z. B. Sturz, Atemnot, Brustschmerz, Verwirrtheit, Hilflosigkeit):
  * UNVERZÜGLICH und OHNE NACHZUFRAGEN das Tool `triggerEmergencyCall` aufrufen mit `is_severe: true`!
  * Sage ruhig: "Ich erfasse das sofort und benachrichtige umgehend Ihre Notfallkontakte. Bitte wählen Sie bei akuten Schmerzen selbst den Notruf 112. Ich beende nun das Gespräch, damit Ihre Leitung frei ist. Auf Wiederhören."
  * Trenne danach sofort die Verbindung! Rufe KEINE weiteren Tools auf!
- HARMALOSERER VORFALL / LEICHTES UNWOHLSEIN (z. B. leichte Müdigkeit, leichte Kopfschmerzen, Einsamkeit):
  * DU MUSST FRAGEN, ob die Notfallkontakte informiert werden sollen.
  * Beispiel: "Möchten Sie, dass ich Ihre Notfallkontakte darüber informiere?"
  * Wenn der Klient JA sagt: Rufe `triggerEmergencyCall` mit `is_severe: false` und `client_requested: true` auf. Informiere kurz darüber und beende das Gespräch.
  * Wenn der Klient NEIN sagt: Rufe `triggerEmergencyCall` mit `is_severe: false` und `client_requested: false` auf und fahre mit Schritt 2 fort.
- KEINE BESCHWERDEN ("Mir geht es gut"): Fahre DIREKT mit Schritt 2 fort.

SCHRITT 2: MEDIKATION / ZUSATZINFORMATION (UNBEDINGTER PFLICHTSCHRITT)
- Dieser Schritt ist ZWINGEND und darf NIEMALS übersprungen werden!
$medicationPromptSection
- Falls Medikation hinterlegt ist: Frage ausdrücklich nach der Einnahme aller genannten Medikamente.
- Sobald der Klient geantwortet hat: Rufe zwingend das Tool `confirmMedication` mit der tatsächlichen Antwort auf.
- Falls keine Medikation hinterlegt ist: Fahre direkt mit Schritt 3 fort.

SCHRITT 3: ABSCHLUSS & ENTWARNUNG
- Erst wenn das Wohlbefinden geklärt UND die Medikation/Zusatzinformation (falls vorhanden) mittels `confirmMedication` dokumentiert wurde, rufe `confirmWellbeing` auf.
- Nach erfolgreichem Aufruf von `confirmWellbeing` verabschiede dich zügig: "Vielen Dank für Ihre Auskunft. Ich wünsche Ihnen einen schönen Tag. Auf Wiederhören." und beende den Anruf.
PROMPT;

    $payload = [
        'assistantId' => VAPI_ASSISTANT_ID,
        'phoneNumberId' => VAPI_PHONE_ID,

        'assistantOverrides' => [
            'firstMessage' =>
            "Guten Tag " . $titlePrefix . $fullName
                . ", hier ist die Assistenz von Dschuliana Kär. Ich rufe kurz an, um zu hören, wie es Ihnen geht?",

            'voicemailDetection' => [
                'provider' => 'vapi',
                'backoffMode' => 'disabled',
                'voicemailDetectionTypes' => ['machine_start', 'machine_end_tone', 'machine_end_silence'],
                'enabled' => true
            ],

            'silenceTimeoutSeconds' => 30,
            'maxDurationSeconds' => 180,
            'endCallPhrases' => ["Auf Wiederhören.", "Einen schönen Tag."],
            'endCallFunctionEnabled' => true,

            'model' => [
                'provider' => 'azure-openai',
                'model' => 'gpt-5.4-mini',
                'temperature' => 0.1,

                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ]
                ],

                'tools' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'confirmMedication',
                            'description' =>
                            'PFLICHTTOOL: Nach der expliziten Nachfrage zu allen hinterlegten Medikamenten/Zusatzinformationen und erfolgter Antwort des Klienten zwingend aufrufen.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'response' => [
                                        'type' => 'string',
                                        'description' =>
                                        'Wörtliche oder sinngemäße Antwort des Klienten bezüglich der Medikation.'
                                    ]
                                ],
                                'required' => ['response']
                            ]
                        ],
                        'async' => false
                    ],
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'confirmWellbeing',
                            'description' =>
                            'Nur aufrufen, wenn das Wohlbefinden bestätigt ist UND die Medikation erfasst und via confirmMedication dokumentiert wurde (bzw. keine Medikation vorlag).',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => new stdClass()
                            ]
                        ],
                        'async' => false
                    ],
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'triggerEmergencyCall',
                            'description' =>
                            'Protokolliert Vorfälle/Unwohlsein und steuert die Benachrichtigung der Notfallkontakte.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'reason' => [
                                        'type' => 'string',
                                        'description' =>
                                        'Konkrete Beschwerden oder Gründe.'
                                    ],
                                    'is_severe' => [
                                        'type' => 'boolean',
                                        'description' =>
                                        'true bei schweren Vorfällen (Sturz, Notfall), false bei harmloseren Symptomen.'
                                    ],
                                    'client_requested' => [
                                        'type' => 'boolean',
                                        'description' =>
                                        'true wenn der Klient bei harmloseren Symptomen explizit um Benachrichtigung gebeten hat.'
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

    $ch = curl_init(
        'https://api.vapi.ai/call/phone'
    );

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        json_encode($payload)
    );

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . VAPI_PRIVATE_KEY,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );
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
              AND attempt_cycle = ?
              AND last_tel_used = ?
              AND status = 'calling'
            ORDER BY id DESC
            LIMIT 1
        ");

        $updateStmt->execute([
            $telType,
            $cycle,
            $clientId,
            $callType,
            $cycle,
            $telType
        ]);
    } else {
        $maskedPhone = substr($phone, 0, 5)
            . '******'
            . substr($phone, -2);

        error_log(
            "VAPI CRITICAL ERROR [Client ID: $clientId | Tel: $maskedPhone]: HTTP $httpCode | cURL Err: $curlError | Response: $response"
        );

        escalateCall(
            $db,
            $clientId,
            $cycle,
            $telType,
            $callType
        );
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
        $message['call']['assistantOverrides']['variableValues']
            ?? [],
        $metadata
    );

    $clientId = isset($metadata['clientId'])
        ? (int)$metadata['clientId']
        : null;

    $cycle = isset($metadata['cycle'])
        ? (int)$metadata['cycle']
        : 1;

    $telType = $metadata['telType'] ?? 'tel1';
    $callType = $metadata['callType'] ?? 'call_1';

    if (!$clientId) {
        error_log(
            "VAPI WEBHOOK ERROR: Konnte Client ID nicht auslesen!"
        );
        return;
    }

    // Richtigen Anrufvorgang auswählen
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

    // Netzansage erkennen
    $isUnreachableAnnouncement = (bool)preg_match(
        '/(?:^|[\s.!?])die angerufene person ist nicht erreichbar[.!?]?/iu',
        $transcript
    );

    // Ansage kann auch in artifact.messages stehen
    $conversationMessages = $message['artifact']['messages']
        ?? $message['messages']
        ?? [];

    if (
        !$isUnreachableAnnouncement
        && is_array($conversationMessages)
    ) {
        foreach ($conversationMessages as $entry) {
            if (
                !is_array($entry)
                || ($entry['role'] ?? '') !== 'user'
            ) {
                continue;
            }

            $spoken = trim(
                (string)(
                    $entry['message']
                    ?? $entry['content']
                    ?? ''
                )
            );

            if (preg_match(
                '/^die angerufene person ist nicht erreichbar[.!?]?$/iu',
                $spoken
            )) {
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

        escalateCall(
            $db,
            $clientId,
            $cycle,
            $telType,
            $callType
        );
        return;
    }

    // Kein bestätigter Gesprächsabschluss
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
        error_log(
            "ESKALATION ABGEBROCHEN: Vorgang nicht mehr aktuell für Client $clientId."
        );
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

    // TEL1 NICHT ERREICHT -> TEL2
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
                error_log(
                    "ESKALATION: Wechsel von tel1 zu tel2 für Client ID $clientId (Cycle $cycle)"
                );

                executeCall(
                    $db,
                    $clientId,
                    'tel2',
                    $cycle,
                    $callType
                );
            }
        } else {
            handleFailedCycle(
                $db,
                $clientId,
                $cycle,
                $callType
            );
        }
    }

    // TEL2 NICHT ERREICHT
    elseif ($telType === 'tel2') {
        handleFailedCycle(
            $db,
            $clientId,
            $cycle,
            $callType
        );
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
                scheduled_time = DATE_ADD(
                    NOW(),
                    INTERVAL 15 MINUTE
                )
            WHERE client_id = ?
              AND status = 'calling'
              AND attempt_cycle = 1
              AND call_type = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            $clientId,
            $callType
        ]);

        if ($stmt->rowCount() === 1) {
            error_log(
                "RETRY SCHEDULED: Klient ID $clientId für Retry (Versuch 2 in 15 Min) vorgemerkt."
            );
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
            notifyEmergencyContacts(
                $db,
                $clientId,
                null
            );

            error_log(
                "CALL FAILED: Klient ID $clientId nach 2 Zyklen endgültig nicht erreicht. Notfallkontakte EINMALIG benachrichtigt."
            );
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
        ($client['title'] ?? '')
            . ' '
            . $client['firstname']
            . ' '
            . $client['lastname']
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

    $contacts = $stmtContacts->fetchAll(
        PDO::FETCH_ASSOC
    );

    if (empty($contacts)) {
        return;
    }

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

            mail(
                $contact['email'],
                $subject,
                $messageText,
                $headers
            );
        }

        if (
            $smsEnabled
            && !empty($contact['tel1'])
        ) {
            sendSmsNotification(
                $contact['tel1'],
                $messageText
            );
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

    $url =
        "https://api.twilio.com/2010-04-01/Accounts/"
        . TWILIO_ACCOUNT_SID
        . "/Messages.json";

    $postData = [
        'From' => TWILIO_PHONE_NUMBER,
        'To' => $phoneNumber,
        'Body' => $message
    ];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        http_build_query($postData)
    );

    curl_setopt(
        $ch,
        CURLOPT_USERPWD,
        TWILIO_ACCOUNT_SID
            . ':'
            . TWILIO_AUTH_TOKEN
    );

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

    $medicationRequired =
        trim((string)$stmt->fetchColumn()) !== ''
        ? 1
        : 0;

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
    $systemPrompt =
        "Du bist ein freundlicher Test-Assistent. Verabschiede dich höflich.";

    $payload = [
        'assistantId' => VAPI_ASSISTANT_ID,
        'phoneNumberId' => VAPI_PHONE_ID,

        'assistantOverrides' => [
            'firstMessage' =>
            "Guten Tag! Dies ist ein Testanruf.",

            'model' => [
                'provider' => 'azure-openai',
                'model' => 'gpt-5.4-mini',

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

    $ch = curl_init(
        'https://api.vapi.ai/call/phone'
    );

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        json_encode($payload)
    );

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . VAPI_PRIVATE_KEY,
        'Content-Type: application/json'
    ]);

    curl_exec($ch);
    curl_close($ch);
}
