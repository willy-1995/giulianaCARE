<?php
// Debugging-Output (optional)

require_once "../cors.php";
require_once "../envloader.php";
require_once "../users_crud.php";
require_once "../auth/jwt.php";
require_once "../database.php";

header("Content-Type: application/json; charset=UTF-8");

$database = new Database();
$pdo = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents("php://input"), true);

// 1. METHOD CHECK
if ($method !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Fehlerhafte Anfrage!"]);
    exit;
}

// 2. INPUT CHECK
if (empty($input['email']) || empty($input['password'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Bitte E-Mail und Passwort eingeben!"]);
    exit;
}

// --- RATE LIMITING ---
$max_attempts = 5;
$lockout_minutes = 15;
$ip = $_SERVER['REMOTE_ADDR'];
$userManager = new UserManager();

// Alte Versuche löschen (Cleanup)
$pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? MINUTE)")
    ->execute([$lockout_minutes]);

// Aktuelle Fehlversuche zählen
$stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ?");
$stmt->execute([$ip]);
$attemptsCount = (int)$stmt->fetchColumn();

if ($attemptsCount >= $max_attempts) {
    http_response_code(429);
    echo json_encode([
        "success" => false,
        "message" => "Zu viele Versuche. Bitte warte 15 Minuten.",
        "remaining_attempts" => 0
    ]);
    exit;
}

try {
    // Nutzt die login() Methode aus UserManager
    $loggedInUser = $userManager->login($input['email'], $input['password']);

    if (!$loggedInUser) {
        // Fehlversuch loggen
        $pdo->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)")->execute([$ip]);
        $newCount = $attemptsCount + 1;

        // "Brute-Force-Bremse" (künstliche Verzögerung)
        usleep(150000);

        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Ungültige Zugangsdaten!",
            "remaining_attempts" => max(0, $max_attempts - $newCount)
        ]);
        exit;
    }

    // LOGIN ERFOLGREICH: Versuche zurücksetzen
    $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);

    // TOKEN ERSTELLEN
    // Da 'role' nicht mehr existiert, übergeben wir einen Standard-String ("user"),
    // um den TypeError in createJWT zu vermeiden, da die Funktion 4 Argumente erwartet.
    $token = createJWT(
        (int)$loggedInUser['id'],
        $loggedInUser['email'],
        "user",
        $jwt_key
    );

    echo json_encode([
        "success" => true,
        "message" => "Login erfolgreich!",
        "token"   => $token,
        "user"    => [
            "id" => $loggedInUser['id'],
            "email" => $loggedInUser['email']
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Serverfehler: " . $e->getMessage()]);
}
