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
        case 'POST':
            if (empty($input['email']) || empty($input['password']) || empty($input['area_code']) || empty($input['country']) || empty($input['price']) || !isset($input['agb_accepted'])) {
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
        // UPDATE (PROFIL AKTUALISIEREN)
        // ==========================================
        case 'PUT':
            $currentUserId = getUserIdFromToken();

            if ($userManager->updateUser($currentUserId, $input)) {
                echo json_encode(["success" => true, "message" => "Profil aktualisiert."]);
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Update fehlgeschlagen."]);
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
    http_response_code(409);
    $msg = str_contains($e->getMessage(), 'Duplicate entry') ? "Email bereits vergeben." : "DB-Fehler.";
    echo json_encode(["success" => false, "message" => $msg, "debug" => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Serverfehler: " . $e->getMessage()]);
}
