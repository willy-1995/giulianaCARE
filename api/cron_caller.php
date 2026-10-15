<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// api/cron_caller.php
require_once __DIR__ . "/cors.php";
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/call_manager.php";

$dbInstance = new Database();
$db = $dbInstance->getConnection();

$currentTime = date('H:i'); // Aktuelle Stunde und Minute (z.B. "00:10")
$currentDate = date('Y-m-d'); // Heutiges Datum
$currentTimestamp = time();

// ==========================================
// 1. REGULÄRE TÄGLICHE ANRUFE PRÜFEN (ROBUST)
// ==========================================
try {
    $stmt = $db->prepare("SELECT id, status, call_1, call_2, call_3 FROM clients WHERE status = 'active'");
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("CRON DEBUG: Aktive Klienten gefunden: " . count($clients));

    foreach ($clients as $client) {
        $clientId = $client['id'];

        $callTypes = [
            'call_1' => $client['call_1'] ?? null,
            'call_2' => $client['call_2'] ?? null,
            'call_3' => $client['call_3'] ?? null,
        ];

        foreach ($callTypes as $callType => $callTime) {
            if (!empty($callTime)) {
                $shortCallTime = substr($callTime, 0, 5); // z.B. "00:10"

                // Zeit-Toleranz-Berechnung (Vergleich via Timestamp)
                $callTimestamp = strtotime("$currentDate $shortCallTime:00");
                $timeDiff = $currentTimestamp - $callTimestamp;

                // Bedingung: Die Soll-Zeit ist erreicht ($timeDiff >= 0) 
                // UND es ist maximal 5 Minuten her ($timeDiff <= 300), damit alte nicht nachgeholt werden
                if ($timeDiff >= 0 && $timeDiff <= 300) {
                    error_log("CRON CHECK: Client $clientId | Typ $callType | Soll: '$shortCallTime' | Diff: {$timeDiff}s");

                    $checkStmt = $db->prepare("SELECT id FROM call_history_log WHERE client_id = ? AND call_type = ? AND call_date = ?");
                    $checkStmt->execute([$clientId, $callType, $currentDate]);

                    if (!$checkStmt->fetch()) {
                        error_log("CRON START: Führe Anruf aus und schreibe Log...");

                        $logStmt = $db->prepare("INSERT INTO call_history_log (client_id, call_type, call_date) VALUES (?, ?, ?)");
                        $logStmt->execute([$clientId, $callType, $currentDate]);

                        initializeCallStatus($db, $clientId, $callType);
                        executeCall($db, $clientId, 'tel1', 1, $callType);
                    } else {
                        error_log("CRON BLOCK: Heute für Client $clientId ($callType) bereits geloggt.");
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("CRON ERROR (Main Calls): " . $e->getMessage());
}

// ==========================================
// 2. FÄLLIGE RETRYS PRÜFEN UND AUSFÜHREN
// ==========================================
try {
    // Zeitzone für PHP explizit auf Berlin setzen, um Differenzen zu vermeiden
    date_default_timezone_set('Europe/Berlin');
    $nowFormatted = date('Y-m-d H:i:s');

    // Wir prüfen gegen den formatierten PHP-Zeitstempel, um MySQL-Zeitzonenprobleme zu umgehen
    $stmt = $db->prepare("
        SELECT client_id, attempt_cycle, call_type 
        FROM call_status 
        WHERE status = 'retry_scheduled' 
          AND scheduled_time <= ?
    ");
    $stmt->execute([$nowFormatted]);
    $dueRetries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dueRetries as $retry) {
        $clientId = $retry['client_id'];
        $cycle    = (int)$retry['attempt_cycle']; // Sollte hier 2 sein
        $callType = $retry['call_type'] ?? 'call_1';

        error_log("CRONJOB: Starte WIEDERHOLUNGSANRUF (Zyklus $cycle) für Client ID $clientId ($callType)... Beginnt wieder mit tel1.");

        // Status auf calling setzen und last_tel_used strikt auf tel1 zurücksetzen
        $updateStmt = $db->prepare("
            UPDATE call_status 
            SET status = 'calling', last_tel_used = 'tel1' 
            WHERE client_id = ?
        ");
        $updateStmt->execute([$clientId]);

        // Zyklus 2 startet streng sequenziell wieder mit tel1
        executeCall($db, $clientId, 'tel1', $cycle, $callType);
    }
} catch (Exception $e) {
    error_log("CRON ERROR (Retry-Handler): " . $e->getMessage());
}
