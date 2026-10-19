<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/cors.php";
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/call_manager.php";

$dbInstance = new Database();
$db = $dbInstance->getConnection();

// Zeitzone für PHP fest auf Berlin einstellen
date_default_timezone_set('Europe/Berlin');

$currentTime = date('H:i');
$currentDate = date('Y-m-d');
$currentTimestamp = time();
$nowFormatted = date('Y-m-d H:i:s');

// ==========================================
// 1. REGULÄRE TÄGLICHE ANRUFE PRÜFEN
// ==========================================
try {
    $stmt = $db->prepare("SELECT id, status, call_1, call_2, call_3 FROM clients WHERE status = 'active'");
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($clients as $client) {
        $clientId = $client['id'];
        $callTypes = [
            'call_1' => $client['call_1'] ?? null,
            'call_2' => $client['call_2'] ?? null,
            'call_3' => $client['call_3'] ?? null,
        ];

        foreach ($callTypes as $callType => $callTime) {
            if (!empty($callTime)) {
                $shortCallTime = substr($callTime, 0, 5);
                $callTimestamp = strtotime("$currentDate $shortCallTime:00");
                $timeDiff = $currentTimestamp - $callTimestamp;

                if ($timeDiff >= 0 && $timeDiff <= 300) {
                    $checkStmt = $db->prepare("SELECT id FROM call_history_log WHERE client_id = ? AND call_type = ? AND call_date = ?");
                    $checkStmt->execute([$clientId, $callType, $currentDate]);

                    if (!$checkStmt->fetch()) {
                        $logStmt = $db->prepare("INSERT INTO call_history_log (client_id, call_type, call_date) VALUES (?, ?, ?)");
                        $logStmt->execute([$clientId, $callType, $currentDate]);

                        initializeCallStatus($db, $clientId, $callType);
                        executeCall($db, $clientId, 'tel1', 1, $callType);
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("CRON ERROR (Main Calls): " . $e->getMessage());
}

// ==========================================
// 2. FÄLLIGE RETRYS PRÜFEN (ZYKLUS 2)
// ==========================================
try {
    // Atomare Abfrage: Setzt den Status sofort auf 'calling_processing', damit kein anderer Cronjob den Eintrag zeitgleich greift
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
        $cycle    = (int)$retry['attempt_cycle']; // Ist 2
        $callType = $retry['call_type'] ?? 'call_1';

        // Sperre setzen, damit der Datensatz nicht doppelt verarbeitet wird
        $lockStmt = $db->prepare("
            UPDATE call_status 
            SET status = 'calling', last_tel_used = 'tel1', scheduled_time = NULL 
            WHERE client_id = ? AND status = 'retry_scheduled'
        ");
        $lockStmt->execute([$clientId]);

        if ($lockStmt->rowCount() > 0) {
            error_log("CRONJOB: Starte WIEDERHOLUNGSANRUF (Zyklus $cycle) für Client ID $clientId ($callType). Beginnt sauber mit tel1.");
            executeCall($db, $clientId, 'tel1', $cycle, $callType);
        }
    }
} catch (Exception $e) {
    error_log("CRON ERROR (Retry-Handler): " . $e->getMessage());
}
