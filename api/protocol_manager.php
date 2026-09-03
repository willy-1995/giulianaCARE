<?php



require_once "cors.php";
require_once "envloader.php";
require_once "database.php";
require_once __DIR__ . "/auth/jwt.php";

header("Content-Type: application/json; charset=UTF-8");

$userId = getUserIdFromToken();

$dbInstance = new Database();
$db = $dbInstance->getConnection();

// Lade ALLE Incidents OHNE Filter, um zu sehen was existiert:
$sql = "
    SELECT 
        ci.id AS incident_id,
        ci.client_id,
        ci.reason,
        c.user_id AS klient_gehoert_zu_user_id
    FROM client_incidents ci
    LEFT JOIN clients c ON ci.client_id = c.id
";

$stmt = $db->prepare($sql);
$stmt->execute();
$allIncidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "success" => true,
    "deine_eingeloggte_user_id" => $userId,
    "gefundene_incidents_in_db" => $allIncidents
]);
exit;



/*
require_once "cors.php";
require_once "envloader.php";
require_once "database.php";
require_once __DIR__ . "/auth/jwt.php";

// Header für JSON-Antwort festlegen
header("Content-Type: application/json; charset=UTF-8");

// Authentifizierung prüfen
$userId = getUserIdFromToken();

$dbInstance = new Database();
$db = $dbInstance->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        // Wir holen alle Vorfälle, verknüpft mit den Stammdaten des Klienten.
        // Nur Vorfälle von Klienten laden, die zum aktuell eingeloggten User gehören!
        $sql = "
            SELECT 
                ci.id,
                ci.client_id,
                ci.reason,
                ci.created_at,
                c.firstname,
                c.lastname
            FROM client_incidents ci
            INNER JOIN clients c ON ci.client_id = c.id
            WHERE c.user_id = :user_id
            ORDER BY ci.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        
        
        echo json_encode([
            "success" => true,
            "data" => $incidents
        ]);
        
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Fehler beim Laden des Protokolls: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Methode nicht erlaubt."
    ]);
}

*/
