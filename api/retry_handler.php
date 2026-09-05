<?php
require_once "cors.php";
require_once "envloader.php";
require_once "database.php";
require_once "call_manager.php";

// DB Verbindung herstellen
$dbInstance = new Database();
$db = $dbInstance->getConnection();

try {
    // 1. Suche nach fälligen Retrys
    $stmt = $db->prepare("
        SELECT client_id, attempt_cycle, last_tel_used, call_type 
        FROM call_status 
        WHERE status = 'retry_scheduled' 
          AND scheduled_time <= NOW()
    ");
    $stmt->execute();
    $dueRetries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($dueRetries)) {
        echo json_encode(["success" => true, "message" => "Keine fälligen Wiederholungsanrufe gefunden."]);
        exit;
    }

    $triggeredClients = [];

    // 2. Schleife durch alle fälligen Klienten
    foreach ($dueRetries as $retry) {
        $clientId = $retry['client_id'];
        $cycle    = (int)$retry['attempt_cycle'];
        $lastTel  = !empty($retry['last_tel_used']) ? $retry['last_tel_used'] : 'tel1';
        $callType = $retry['call_type'] ?? 'call_1';

        // Log für Konsole / Server-Log
        $logMessage = "CRONJOB: Starte WIEDERHOLUNGSANRUF (Versuch $cycle) für Client ID $clientId ($callType) auf Nummerntyp '$lastTel'...";
        error_log($logMessage);

        // Status auf 'calling' setzen
        $updateStmt = $db->prepare("
            UPDATE call_status 
            SET status = 'calling', scheduled_time = NOW() 
            WHERE client_id = ?
        ");
        $updateStmt->execute([$clientId]);

        // Den 2. Versuch ausführen
        executeCall($db, $clientId, $lastTel, $cycle, $callType);

        $triggeredClients[] = [
            'client_id' => $clientId,
            'cycle' => $cycle,
            'call_type' => $callType
        ];
    }

    echo json_encode([
        "success" => true,
        "message" => count($triggeredClients) . " Wiederholungsanruf(e) gestartet.",
        "details" => $triggeredClients
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    $errorMsg = "Fehler im Retry-Handler: " . $e->getMessage();
    error_log($errorMsg);
    echo json_encode([
        "success" => false,
        "message" => $errorMsg
    ]);
}
