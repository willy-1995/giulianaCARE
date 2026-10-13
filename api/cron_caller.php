<?php
// api/cron_caller.php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/database.php";
// Hier binden wir die executeCall-Funktion oder Logik aus der call_manager.php ein
require_once __DIR__ . "/call_manager.php"; // (oder direkt hier definieren)

$dbInstance = new Database();
$db = $dbInstance->getConnection();

$currentTime = date('H:i'); // Aktuelle Stunde und Minute (z.B. "08:30")
$currentDate = date('Y-m-d'); // Heutiges Datum

// 1. Alle aktiven Clients laden
$stmt = $db->prepare("SELECT id, call_1, call_2, call_3 FROM clients WHERE status = 'active'");
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($clients as $client) {
    $clientId = $client['id'];

    // Prüfen, welche Anrufzeit jetzt übereinstimmt
    $callTypes = [
        'call_1' => $client['call_1'] ?? null,
        'call_2' => $client['call_2'] ?? null,
        'call_3' => $client['call_3'] ?? null,
    ];

    foreach ($callTypes as $callType => $callTime) {
        if (!empty($callTime)) {
            // Nur die ersten 5 Zeichen vergleichen (HH:MM)
            $shortCallTime = substr($callTime, 0, 5);

            if ($shortCallTime === $currentTime) {

                // 2. PRÜFUNG: Wurde dieser Anruf für diesen Klienten HEUTE schon ausgelöst?
                $checkStmt = $db->prepare("SELECT id FROM call_history_log WHERE client_id = ? AND call_type = ? AND call_date = ?");
                $checkStmt->execute([$clientId, $callType, $currentDate]);

                if (!$checkStmt->fetch()) {
                    // Noch nicht ausgeführt heute! Also jetzt starten:
                    error_log("CRON: Starte automatischen $callType für Client $clientId um $currentTime");

                    // Protokollieren, dass er heute läuft, damit er in dieser Minute nicht doppelt feuert
                    $logStmt = $db->prepare("INSERT INTO call_history_log (client_id, call_type, call_date) VALUES (?, ?, ?)");
                    $logStmt->execute([$clientId, $callType, $currentDate]);

                    // Anruf ausführen (Funktion aus deinem Manager)
                    initializeCallStatus($db, $clientId, $callType);
                    executeCall($db, $clientId, 'tel1', 1, $callType);
                }
            }
        }
    }
}
echo "Cron-Check ausgeführt um " . date('Y-m-d H:i:s');
