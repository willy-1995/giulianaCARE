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

            // Nutzt deine createJWT aus jwt.php
            // Hinweis: 'username' wird hier als Rolle/Name übergeben, je nachdem was du im Token brauchst
            $token = createJWT((int)$userId, $input['email'], $input['price']);

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Registrierung erfolgreich!",
                "token" => $token,
                "userId" => $userId
            ]);
            break;



        // ==========================================
        // UPDATE (PROFIL AKTUALISIEREN)
        // ==========================================
        case 'PUT':
            // Nutzt DEINE Funktion aus jwt.php
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
            // Nutzt DEINE Funktion aus jwt.php
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
