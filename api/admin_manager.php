<?php
require_once "cors.php";
require_once "envloader.php";
require_once "admin_crud.php";
require_once __DIR__ . "/auth/jwt.php";

header("Content-Type: application/json; charset=UTF-8");

$input = json_decode(file_get_contents("php://input"), true);
$method = $_SERVER['REQUEST_METHOD'];
$adminManager = new AdminManager();

try {
    switch ($method) {
        // ==========================================
        // POST: Admin Login
        // ==========================================
        case 'POST':
            if (empty($input['email']) || empty($input['password']) || empty($input['pin'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Bitte E-Mail, Passwort und PIN ausfüllen."]);
                exit;
            }

            $admin = $adminManager->login($input['email'], $input['password'], $input['pin']);

            if ($admin) {
                // JWT-Token für den Admin ausstellen
                $token = createJWT((int)$admin['id'], $admin['email'], $admin['role']);

                echo json_encode([
                    "success" => true,
                    "message" => "Login erfolgreich.",
                    "token" => $token,
                    "admin" => $admin
                ]);
            } else {
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Ungültige Anmeldedaten oder falsche PIN."]);
            }
            break;

        // ==========================================
        // GET: Admin-Daten abrufen (geschützt via JWT)
        // ==========================================
        case 'GET':
            $currentAdminId = getUserIdFromToken();
            $adminData = $adminManager->getAdmin($currentAdminId);

            if ($adminData && $adminData['role'] === 'admin') {
                echo json_encode(["success" => true, "admin" => $adminData]);
            } else {
                http_response_code(403);
                echo json_encode(["success" => false, "message" => "Zugriff verweigert."]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Methode nicht erlaubt."]);
            break;
    }
} catch (PDOException $e) {
    error_log("Database Error (Admin): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Ein Datenbankfehler ist aufgetreten."]);
} catch (Exception $e) {
    error_log("Server Error (Admin): " . $e->getMessage());
    http_response_code(401);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
