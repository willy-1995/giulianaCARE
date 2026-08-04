<?php
require_once "cors.php";
require_once "envloader.php";
require_once "contacts_crud.php"; // Die vorhin erstellte CRUD Klasse für Kontakte
require_once "clients_crud.php";  // Benötigt für den Berechtigungscheck
require_once __DIR__ . "/auth/jwt.php";

header("Content-Type: application/json; charset=UTF-8");

$input = json_decode(file_get_contents("php://input"), true);
$method = $_SERVER['REQUEST_METHOD'];

$contactsManager = new ContactsManager();
$clientsManager = new ClientsManager();

try {
    // Authentifizierung: Wer fragt an?
    $currentUserId = getUserIdFromToken($jwt_key);

    switch ($method) {
        // ==========================================
        // CREATE: Neuen Kontakt für einen Client anlegen
        // ==========================================
        case 'POST':
            if (empty($input['client_id'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Client-ID fehlt."]);
                exit;
            }

            // Sicherheitscheck: Gehört der Klient dem aktuellen User?
            $parentClient = $clientsManager->getClient((int)$input['client_id']);
            if (!$parentClient || $parentClient['user_id'] != $currentUserId) {
                http_response_code(403);
                echo json_encode(["success" => false, "message" => "Zugriff verweigert. Klient gehört nicht zu Ihrem Konto."]);
                exit;
            }

            $contactId = $contactsManager->createContact($input);

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Kontakt erfolgreich angelegt.",
                "contactId" => $contactId
            ]);
            break;


        // ==========================================
        // READ: Kontakte eines Clients, Einzelkontakt ODER alle Kontakte des Users
        // ==========================================
        case 'GET':
            if (isset($_GET['id'])) {
                // Einzelnen Kontakt laden
                $contact = $contactsManager->getContact((int)$_GET['id']);

                if ($contact) {
                    $parentClient = $clientsManager->getClient($contact['client_id']);
                    if ($parentClient && $parentClient['user_id'] == $currentUserId) {
                        echo json_encode(["success" => true, "data" => $contact]);
                    } else {
                        http_response_code(403);
                        echo json_encode(["success" => false, "message" => "Zugriff verweigert."]);
                    }
                } else {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "Kontakt nicht gefunden."]);
                }
            } elseif (isset($_GET['client_id'])) {
                // Alle Kontakte eines bestimmten Klienten laden
                $clientId = (int)$_GET['client_id'];
                $parentClient = $clientsManager->getClient($clientId);
                if ($parentClient && $parentClient['user_id'] == $currentUserId) {
                    $contacts = $contactsManager->getContactsByClient($clientId);
                    echo json_encode(["success" => true, "data" => $contacts]);
                } else {
                    http_response_code(403);
                    echo json_encode(["success" => false, "message" => "Zugriff verweigert."]);
                }
            } else {
                // NEU: Alle Kontakte laden, die zu den Klienten des aktuellen Users gehören
                // Hierfür brauchst du eine Methode in deiner CRUD-Klasse.
                // Falls du diese noch nicht hast, kannst du alternativ alle Kontakte holen 
                // und im Manager filtern oder eine neue SQL-Funktion schreiben.

                // Wir gehen davon aus, dass deine CRUD-Klasse eine Methode hat, 
                // die alle Kontakte für eine User-ID holt (via JOIN über die clients Tabelle):
                $allUserContacts = $contactsManager->getAllContactsForUser($currentUserId);

                echo json_encode([
                    "success" => true,
                    "data" => $allUserContacts ?: [] // Falls null, leeres Array senden
                ]);
            }
            break;

        // ==========================================
        // UPDATE: Kontakt-Daten aktualisieren
        // ==========================================
        case 'PUT':
            if (empty($input['id'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Kontakt-ID fehlt."]);
                exit;
            }

            // Prüfen, ob der Kontakt existiert und dem User gehört
            $existingContact = $contactsManager->getContact($input['id']);
            if ($existingContact) {
                $parentClient = $clientsManager->getClient($existingContact['client_id']);
                if (!$parentClient || $parentClient['user_id'] != $currentUserId) {
                    http_response_code(403);
                    echo json_encode(["success" => false, "message" => "Keine Berechtigung für dieses Update."]);
                    exit;
                }
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Kontakt nicht gefunden."]);
                exit;
            }

            if ($contactsManager->updateContact($input['id'], $input)) {
                echo json_encode(["success" => true, "message" => "Kontakt-Daten aktualisiert."]);
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Update fehlgeschlagen."]);
            }
            break;

        // ==========================================
        // DELETE: Kontakt löschen
        // ==========================================
        case 'DELETE':
            $contactId = $_GET['id'] ?? $input['id'] ?? null;

            if (!$contactId) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID fehlt."]);
                exit;
            }

            // Berechtigungsprüfung vor dem Löschen
            $existingContact = $contactsManager->getContact($contactId);
            if ($existingContact) {
                $parentClient = $clientsManager->getClient($existingContact['client_id']);
                if (!$parentClient || $parentClient['user_id'] != $currentUserId) {
                    http_response_code(403);
                    echo json_encode(["success" => false, "message" => "Löschen nicht erlaubt."]);
                    exit;
                }
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Kontakt nicht gefunden."]);
                exit;
            }

            if ($contactsManager->deleteContact($contactId)) {
                echo json_encode(["success" => true, "message" => "Kontakt gelöscht."]);
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
    http_response_code(401);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
