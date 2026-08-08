<?php

require_once "cors.php";
require_once "envloader.php";
require_once "clients_crud.php"; // Die vorhin erstellte CRUD Klasse
require_once __DIR__ . "/auth/jwt.php";

header("Content-Type: application/json; charset=UTF-8");

$input = json_decode(file_get_contents("php://input"), true);
$method = $_SERVER['REQUEST_METHOD'];
$clientsManager = new ClientsManager();

try {
    // Authentifizierung: Wer fragt an?
    $currentUserId = getUserIdFromToken($jwt_key);

    switch ($method) {
        // ==========================================
        // CREATE: Neuen Client für den User anlegen
        // ==========================================
        case 'POST':
            /* if (empty($input['lastname']) || empty($input['firstname'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Nachname und Vorname sind Pflichtfelder."]);
                exit;
            }
            */
            // Wir setzen die user_id automatisch aus dem Token
            $input['user_id'] = $currentUserId;
            $clientId = $clientsManager->createClient($input);

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Client erfolgreich angelegt.",
                "clientId" => $clientId
            ]);
            break;

        // ==========================================
        // READ: Liste aller Clients oder Einzelansicht
        // ==========================================
        case 'GET':
            if (isset($_GET['id'])) {
                // Einzelnen Client laden
                $client = $clientsManager->getClient((int)$_GET['id']);

                // Sicherheitscheck: Gehört der Client dem User?
                if ($client && $client['user_id'] == $currentUserId) {
                    echo json_encode(["success" => true, "data" => $client]);
                } else {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "Client nicht gefunden oder Zugriff verweigert."]);
                }
            } else {
                // Alle Clients des Users laden
                $clients = $clientsManager->getClientsByUser($currentUserId);
                echo json_encode(["success" => true, "data" => $clients]);
            }
            break;

        // ==========================================
        // UPDATE: Client-Daten aktualisieren
        // ==========================================
        case 'PUT':
            if (empty($input['id'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Client-ID fehlt."]);
                exit;
            }

            // Prüfen, ob der Client dem User gehört
            $existingClient = $clientsManager->getClient($input['id']);
            if (!$existingClient || $existingClient['user_id'] != $currentUserId) {
                http_response_code(403);
                echo json_encode(["success" => false, "message" => "Keine Berechtigung für dieses Update."]);
                exit;
            }

            if ($clientsManager->updateClient($input['id'], $input)) {
                echo json_encode(["success" => true, "message" => "Client-Daten aktualisiert."]);
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Update fehlgeschlagen."]);
            }
            break;

        // ==========================================
        // PATCH: Status per Toggle umschalten
        // ==========================================
        case 'PATCH':
            $clientId = $input['id'] ?? $_GET['id'] ?? null;

            if (!$clientId) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Client-ID fehlt."]);
                exit;
            }

            // Sicherheitscheck: Gehört der Client dem eingeloggten User?
            $existingClient = $clientsManager->getClient($clientId);
            if (!$existingClient || $existingClient['user_id'] != $currentUserId) {
                http_response_code(403);
                echo json_encode(["success" => false, "message" => "Keine Berechtigung für diese Aktion."]);
                exit;
            }

            // Status umschalten und neuen Status erhalten
            $newStatus = $clientsManager->toggleStatus($clientId);

            if ($newStatus) {
                echo json_encode([
                    "success" => true,
                    "message" => "Status erfolgreich geändert.",
                    "newStatus" => $newStatus
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Status konnte nicht geändert werden."]);
            }
            break;

        // ==========================================
        // DELETE: Client löschen
        // ==========================================
        case 'DELETE':
            $clientId = $_GET['id'] ?? $input['id'] ?? null;

            if (!$clientId) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID fehlt."]);
                exit;
            }

            // Prüfen, ob der Client dem User gehört
            $existingClient = $clientsManager->getClient($clientId);
            if (!$existingClient || $existingClient['user_id'] != $currentUserId) {
                http_response_code(403);
                echo json_encode(["success" => false, "message" => "Löschen nicht erlaubt."]);
                exit;
            }

            if ($clientsManager->deleteClient($clientId)) {
                echo json_encode(["success" => true, "message" => "Client gelöscht."]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Fehler beim Löschen."]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Methode nicht erlaubt."]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Datenbankfehler.", "debug" => $e->getMessage()]);
} catch (Exception $e) {
    // Falls getUserIdFromToken fehlschlägt (Token ungültig/abgelaufen)
    http_response_code(401);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
