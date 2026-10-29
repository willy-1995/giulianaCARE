<?php

require_once "cors.php";
require_once "envloader.php";
require_once "users_crud.php";
require_once __DIR__ . "/auth/jwt.php"; // Hier liegen createJWT und getUserIdFromToken

header("Content-Type: application/json; charset=UTF-8");

$input = json_decode(file_get_contents("php://input"), true);
$method = $_SERVER['REQUEST_METHOD'];
$userManager = new UserManager();

try {
    switch ($method) {
        // ==========================================
        // CREATE (REGISTRIERUNG)
        // ==========================================
        // ==========================================
        // CREATE (REGISTRIERUNG)
        // ==========================================
        case 'POST':
            if (empty($input['email']) || empty($input['password']) || empty($input['country']) || empty($input['price']) || !isset($input['agb_accepted'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Bitte alle Felder ausfüllen."]);
                exit;
            }

            // Prüfen, ob E-Mail schon existiert
            $existingUser = $userManager->getUserByEmail($input['email']);

            if ($existingUser) {
                // 1. Fall: Nutzer ist bereits aktiv (bereits bezahlt)
                if (isset($existingUser['subscription_status']) && $existingUser['subscription_status'] === 'active') {
                    http_response_code(409);
                    echo json_encode(["success" => false, "message" => "Diese E-Mail-Adresse ist bereits registriert und aktiv. Bitte logge dich ein."]);
                    exit;
                }

                // 2. Fall: Nutzer existiert, hat aber noch nicht bezahlt ('unpaid')
                // Wir prüfen zur Sicherheit das Passwort, damit niemand fremde Accounts kapern kann
                if (!password_verify($input['password'], $existingUser['password'])) {
                    http_response_code(401);
                    echo json_encode(["success" => false, "message" => "E-Mail bereits registriert. Das eingegebene Passwort ist jedoch falsch."]);
                    exit;
                }

                // Passwort stimmt -> Bestehende ID weiterverwenden und neuen Token ausstellen
                $userId = $existingUser['id'];
                $token = createJWT((int)$userId, $input['email'], $input['price']);
            } else {
                // 3. Fall: Ganz normaler neuer Account
                $userId = $userManager->createUser($input);
                $token = createJWT((int)$userId, $input['email'], $input['price']);
            }

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Registrierung erfolgreich!",
                "token" => $token,
                "userId" => $userId,
                "subscription_status" => "unpaid"
            ]);
            break;
        /*
        case 'POST':
            if (empty($input['email']) || empty($input['password']) || empty($input['country']) || empty($input['price']) || !isset($input['agb_accepted'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Bitte alle Felder ausfüllen."]);
                exit;
            }

            $userId = $userManager->createUser($input);

            // [HINWEIS]: Nach der Registrierung ist der subscription_status standardmäßig 'unpaid'
            $token = createJWT((int)$userId, $input['email'], $input['price']);

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Registrierung erfolgreich!",
                "token" => $token,
                "userId" => $userId,
                "subscription_status" => "unpaid" // [NEU]: Status explizit für Frontend mitgeben
            ]);
            break;
*/
        // ==========================================
        // READ (USER-DATEN LADEN)
        // ==========================================
        case 'GET':
            $currentUserId = getUserIdFromToken();
            $userData = $userManager->getUser($currentUserId);

            if ($userData) {
                // Passwort vor der Ausgabe entfernen (falls aus Versehen dabei)
                unset($userData['password']);

                // [GEÄNDERT]: $userData enthält durch das users_crud-Update jetzt bereits:
                // - stripe_customer_id
                // - stripe_subscription_id
                // - subscription_status ('unpaid', 'active', 'canceled', 'past_due')
                echo json_encode([
                    "success" => true,
                    "user" => $userData
                ]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "User nicht gefunden."]);
            }
            break;

        // ==========================================
        // UPDATE (PROFIL / PASSWORT AKTUALISIEREN)
        // ==========================================
        case 'PUT':
            $currentUserId = getUserIdFromToken();

            try {
                if ($userManager->updateUser($currentUserId, $input)) {
                    echo json_encode(["success" => true, "message" => "Aktualisierung erfolgreich."]);
                } else {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Update fehlgeschlagen."]);
                }
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => $e->getMessage()]);
            }
            break;

        // ==========================================
        // DELETE (ACCOUNT LÖSCHEN)
        // ==========================================
        case 'DELETE':
            $currentUserId = getUserIdFromToken();

            if ($userManager->deleteUser($currentUserId)) {
                echo json_encode(["success" => true, "message" => "Account gelöscht."]);
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
    // Fehler intern ins Server-Log schreiben, statt an den Client zu senden
    error_log("Database Error: " . $e->getMessage());

    http_response_code(409);
    $msg = str_contains($e->getMessage(), 'Duplicate entry') ? "Diese E-Mail-Adresse ist bereits vergeben." : "Ein Datenbankfehler ist aufgetreten.";

    // KEIN 'debug'-Feld mehr an den User schicken!
    echo json_encode(["success" => false, "message" => $msg]);
} catch (Exception $e) {
    // Fehler intern loggen
    error_log("Server Error: " . $e->getMessage());

    http_response_code(500);
    // Dem Nutzer nur eine sichere, generische Meldung anzeigen
    echo json_encode(["success" => false, "message" => "Ein unerwarteter Serverfehler ist aufgetreten."]);
}
