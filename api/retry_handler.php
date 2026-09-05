<?php
require_once "cors.php";
require_once "envloader.php";
require_once "database.php";
// WICHTIG: Wir laden die call_manager.php, um Zugriff auf executeCall() zu haben
require_once "call_manager.php";

// DB Verbindung herstellen
$dbInstance = new Database();
$db = $dbInstance->getConnection();

try {
    // 1. Suche nach fälligen Retrys
    // Wir holen alle Einträge, die auf 'retry_scheduled' stehen UND deren Zeit erreicht/überschritten ist
    $stmt = $db->prepare("
       SELECT client_id, attempt_cycle, last_tel_used, call_type 
        FROM call_status 
        WHERE status = 'retry_scheduled' 
          AND scheduled_time <= NOW()
    ");
    $stmt->execute();
    $dueRetries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($dueRetries)) {
        // Nichts zu tun – wir antworten friedlich
        echo json_encode(["success" => true, "message" => "Keine fälligen Wiederholungsanrufe gefunden."]);
        exit;
    }

    // Speicher für das Protokoll im Response
    $triggeredClients = [];

    // 2. Schleife durch alle fälligen Klienten
    foreach ($dueRetries as $retry) {
        $clientId = $retry['client_id'];
        $cycle = (int)$retry['attempt_cycle'];
        $lastTel = $retry['last_tel_used'];
        $callType = $retry['call_type'] ?? 'call_1';

        // Status in der DB wieder auf 'calling' setzen, da der Versuch jetzt startet
        $updateStmt = $db->prepare("
            UPDATE call_status 
            SET status = 'calling', scheduled_time = NOW() 
            WHERE client_id = ?
        ");
        $updateStmt->execute([$clientId]);

        // Den Anruf erneut triggern. 
        // Wir übergeben die clientId, die aktuelle Telefonart (tel1), calltype und das erhöhte Cycle (2)
        executeCall($db, $clientId, $lastTel, $cycle, $callType);

        $triggeredClients[] = $clientId;
    }

    echo json_encode([
        "success" => true,
        "message" => count($triggeredClients) . " Wiederholungsanruf(e) gestartet.",
        "client_ids" => $triggeredClients
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Fehler im Retry-Handler: " . $e->getMessage()
    ]);
}
