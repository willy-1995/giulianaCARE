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

$currentTime = date('H:i'); // Aktuelle Stunde und Minute (z.B. "08:30")
$currentDate = date('Y-m-d'); // Heutiges Datum

// ==========================================
// 1. REGULÄRE TÄGLICHE ANRUFE PRÜFEN
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
                $shortCallTime = substr($callTime, 0, 5);

                error_log("CRON CHECK: Client $clientId | Typ $callType | Soll: '$shortCallTime' vs Ist: '$currentTime'");

                if ($shortCallTime === $currentTime) {
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
    // Wichtig: Wir holen nur Retrys, deren Zeit erreicht ist
    $stmt = $db->prepare("
        SELECT client_id, attempt_cycle, last_tel_used, call_type 
        FROM call_status 
        WHERE status = 'retry_scheduled' 
          AND scheduled_time <= NOW()
    ");
    $stmt->execute();
    $dueRetries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dueRetries as $retry) {
        $clientId = $retry['client_id'];
        $cycle    = (int)$retry['attempt_cycle'];
        $lastTel  = !empty($retry['last_tel_used']) ? $retry['last_tel_used'] : 'tel1';
        $callType = $retry['call_type'] ?? 'call_1';

        error_log("CRONJOB: Starte WIEDERHOLUNGSANRUF (Versuch $cycle) für Client ID $clientId ($callType)...");

        // WICHTIG: Sofort den Status auf 'calling' setzen UND scheduled_time weit in die Zukunft, 
        // damit dieser Datensatz bei einem gleichzeitigen zweiten Cron-Durchlauf nicht doppelt greift!
        $updateStmt = $db->prepare("
            UPDATE call_status 
            SET status = 'calling', scheduled_time = DATE_ADD(NOW(), INTERVAL 1 HOUR) 
            WHERE client_id = ?
        ");
        $updateStmt->execute([$clientId]);

        // Anruf ausführen
        executeCall($db, $clientId, $lastTel, $cycle, $callType);
    }
} catch (Exception $e) {
    error_log("CRON ERROR (Retry-Handler): " . $e->getMessage());
}

echo json_encode([
    "success" => true,
    "message" => "Cron-Check erfolgreich ausgeführt um " . date('Y-m-d H:i:s')
]);
