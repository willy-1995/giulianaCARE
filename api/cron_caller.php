<?php


// Produktivbetrieb: Fehler erfassen, aber nicht öffentlich ausgeben
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . "/cors.php";
require_once __DIR__ . "/config.php";
require_once  __DIR__ . "/envloader.php";
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
                        if (!executeTwilioCall($db, $clientId, 'tel1', 1, $callType)) {
                            handleCallFailure($db, (int)$clientId, $callType, 'tel1', 1, 'Twilio konnte den Anruf auf tel1 nicht erstellen.');
                        }
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
        $cycle    = (int)$retry['attempt_cycle'];
        $callType = $retry['call_type'] ?? 'call_1';

        // Datensatz bedingt reservieren,
        // damit kein zweiter Cronjob denselben Retry startet.
        $lockStmt = $db->prepare("
            UPDATE call_status
            SET status = 'calling',
                last_tel_used = 'tel1',
                scheduled_time = NULL
            WHERE client_id = ?
              AND status = 'retry_scheduled'
              AND attempt_cycle = 2
              AND call_type = ?
              AND scheduled_time <= ?
        ");
        $lockStmt->execute([
            $clientId,
            $callType,
            $nowFormatted
        ]);

        if ($lockStmt->rowCount() > 0) {
            error_log(
                "CRONJOB: Starte WIEDERHOLUNGSANRUF " .
                    "(Zyklus $cycle) für Client ID $clientId " .
                    "($callType). Beginnt mit tel1."
            );

            if (!executeTwilioCall($db, $clientId, 'tel1', $cycle, $callType)) {
                handleCallFailure($db, (int)$clientId, $callType, 'tel1', $cycle, 'Twilio konnte den Wiederholungsanruf auf tel1 nicht erstellen.');
            }
        }
    }
} catch (Exception $e) {
    error_log("CRON ERROR (Retry-Handler): " . $e->getMessage());
}






// ==========================================
// 3. WATCHDOG FÜR FESTHÄNGENDE ANRUFE (TEL1 UND TEL2)
// ==========================================
// Heartbeats werden durch Twilio-Statuscallbacks und Voice-Webhooks aktualisiert.
// Nur wenn 3 Minuten keinerlei Aktivität kam, wird der aktuelle Telefonversuch
// als ausgefallen behandelt. Die zentrale Eskalation unterscheidet Zyklus 1/2.
try {
    $watchdogStmt = $db->prepare("
        SELECT client_id, call_type, attempt_cycle, last_tel_used
        FROM call_status
        WHERE status = 'calling'
          AND last_tel_used IN ('tel1', 'tel2')
          AND attempt_cycle IN (1, 2)
          AND updated_at <= DATE_SUB(NOW(), INTERVAL 3 MINUTE)
    ");
    $watchdogStmt->execute();
    $staleCalls = $watchdogStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($staleCalls as $staleCall) {
        $clientId = (int)$staleCall['client_id'];
        $callType = $staleCall['call_type'] ?? 'call_1';
        $cycle = (int)$staleCall['attempt_cycle'];
        $telType = $staleCall['last_tel_used'];

        error_log("CRON WATCHDOG: Keine Aktivität seit 3 Minuten. Client {$clientId}, {$callType}, Zyklus {$cycle}, zuletzt {$telType}.");

        handleCallFailure(
            $db,
            $clientId,
            $callType,
            $telType,
            $cycle,
            'Watchdog: Kein verwertbarer Twilio-Callback/Voice-Webhook innerhalb von 3 Minuten.'
        );
    }
} catch (Exception $e) {
    error_log("CRON ERROR (Call Watchdog): " . $e->getMessage());
}
