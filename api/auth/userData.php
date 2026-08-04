<?php
require_once "../cors.php";          // erlaubt CORS
require_once "../envloader.php";     // lädt $jwt_key
require_once "../users_crud.php";    // UserManager zum Zugriff auf DB
require_once "../vendor/autoload.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header("Content-Type: application/json; charset=UTF-8");

// 1. Alle Header holen
$headers = getallheaders();

// 2. Authorization-Header robust auslesen
$authHeader =
    $headers['Authorization']
    ?? $headers['authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? null;

if (!$authHeader) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Kein Authorization-Header vorhanden"]);
    exit;
}

// 3. Bearer-Token extrahieren
if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Kein Bearer-Token im Authorization-Header"]);
    exit;
}

$token = $matches[1];

// 4. Token decodieren
try {
    $decoded = JWT::decode($token, new Key($jwt_key, 'HS256'));
    $currentUserId = $decoded->user_id;

    // 5. User aus DB holen
    $userManager = new UserManager();
    $user = $userManager->getUser($currentUserId);

    if (!$user) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "User nicht gefunden"]);
        exit;
    }

    // 6. User-Daten zurückgeben
    echo json_encode([
        "success" => true,
        "data" => [
            "id"            => $user['id'],
            "name"          => $user['name'],
            "email"         => $user['email'],
            "birthday"      => $user['birthday'] ?? null,
            "gender"        => $user['gender'] ?? null,
            "sex"           => $user['sex'] ?? null,
            "target_gender" => $user['target_gender'] ?? null,
            "searchmode"    => $user['searchmode'] ?? null,
            "height"        => $user['height'] ?? null,
            "ethnicity"     => $user['ethnicity'] ?? null,
            "country_name"  => $user['country_name'] ?? null,
            "state_name"    => $user['state_name'] ?? null,
            "city_name"     => $user['city_name'] ?? null,
            "home_location" => $user['home_location'] ?? null,
            "distance"      => $user['distance'] ?? null,
            "degree"        => $user['degree'] ?? null,
            "work"          => $user['work'] ?? null,
            "profiletext"   => $user['profiletext'] ?? null,
            "searchtext"    => $user['searchtext'] ?? null,
            "premium"       => $user['premium'] ?? 0
        ]
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Token ungültig oder abgelaufen",
        "error" => $e->getMessage()
    ]);
}
