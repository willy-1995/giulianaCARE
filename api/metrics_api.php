<?php
require_once __DIR__ . "/cors.php";
require_once __DIR__ . "/envloader.php";
require_once __DIR__ . "/metrics_manager.php";
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/auth/jwt.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    // 1. Authentifizierung: Wer fragt an? (Prüft den JWT-Token)
    $currentUserId = getUserIdFromToken();

    // 2. Datenbankverbindung herstellen und prüfen, ob der User Admin ist
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$currentUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Admin-Check: Rolle muss exakt 'admin' sein
    if (!$user || !isset($user['role']) || strtolower(trim($user['role'])) !== 'admin') {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Zugriff verweigert. Administrator-Rechte erforderlich."
        ]);
        exit;
    }

    // 4. Wenn Admin: Metriken laden und ausgeben
    $metricsManager = new MetricsManager();
    $data = $metricsManager->getDashboardMetrics();

    echo json_encode([
        "success" => true,
        "metrics" => $data
    ]);
} catch (Exception $e) {
    error_log("Metrics API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Ein interner Fehler ist aufgetreten."
    ]);
}
