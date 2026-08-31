<?php

require_once "cors.php";
require_once "envloader.php";
require_once "salespartner_crud.php";
require_once __DIR__ . "/auth/jwt.php"; // Bietet createJWT und getUserIdFromToken

header("Content-Type: application/json; charset=UTF-8");

$input = json_decode(file_get_contents("php://input"), true);
$method = $_SERVER['REQUEST_METHOD'];
$partnerManager = new SalesPartnerManager();

try {
    switch ($method) {
        // ==========================================
        // CREATE (REGISTRIERUNG VERTRIEBSPARTNER)
        // ==========================================
        case 'POST':
            $requiredFields = ['firstname', 'lastname', 'birthday', 'country', 'street', 'tel', 'email', 'password'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Bitte alle Pflichtfelder ausfüllen."]);
                    exit;
                }
            }

            if (empty($input['sales_areas']) || !is_array($input['sales_areas'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Bitte mindestens ein Vertriebsgebiet auswählen."]);
                exit;
            }

            // Anlegen des Partners inklusive Vertriebsgebieten
            $partnerId = $partnerManager->createSalesPartner($input);

            // JWT-Token für den neuen Partner generieren
            $token = createJWT((int)$partnerId, $input['email'], 'sales_partner');

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Registrierung als Vertriebspartner erfolgreich!",
                "token"   => $token,
                "userId"  => $partnerId
            ]);
            break;

        // ==========================================
        // UPDATE (PROFIL / GEBIETE AKTUALISIEREN)
        // ==========================================
        case 'PUT':
            // Holt die Partner-ID aus dem JWT Bearer Token
            $currentPartnerId = getUserIdFromToken();

            if ($partnerManager->updateSalesPartner($currentPartnerId, $input)) {
                echo json_encode(["success" => true, "message" => "Vertriebspartner-Profil aktualisiert."]);
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Update fehlgeschlagen."]);
            }
            break;

        // ==========================================
        // DELETE (PARTNER-ACCOUNT LÖSCHEN)
        // ==========================================
        case 'DELETE':
            // Holt die Partner-ID aus dem JWT Bearer Token
            $currentPartnerId = getUserIdFromToken();

            if ($partnerManager->deleteSalesPartner($currentPartnerId)) {
                echo json_encode(["success" => true, "message" => "Vertriebspartner-Account gelöscht."]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Fehler beim Löschen des Accounts."]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Methode nicht erlaubt."]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(409);
    $msg = str_contains($e->getMessage(), 'Duplicate entry') ? "E-Mail oder Vertriebsgebiet bereits vergeben." : "DB-Fehler.";
    echo json_encode(["success" => false, "message" => $msg, "debug" => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Serverfehler: " . $e->getMessage()]);
}
