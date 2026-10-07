<?php
require_once "../../cors.php";
require_once "../../database.php";
require_once "../../envloader.php";



$database = new Database();
$conn = $database->getConnection();
$input = json_decode(file_get_contents("php://input"), true);
$action = $_GET['action'] ?? 'request';

// ==========================================
// 1. PASSSWORT-RESET ANFORDERN (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'request') {
    $email = trim($input['email'] ?? '');

    if (empty($email)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Bitte E-Mail-Adresse angeben."]);
        exit;
    }

    // Prüfen, ob User existiert
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);

    if ($stmt->fetch()) {
        // Token generieren (gültig für 1 Stunde)
        $token = bin2hex(random_bytes(32));
        $expiresAt = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Altes Token für die E-Mail löschen
        $stmtDel = $conn->prepare("DELETE FROM password_resets WHERE email = :email");
        $stmtDel->execute([':email' => $email]);

        // Neues Token speichern
        $stmtIns = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires)");
        $stmtIns->execute([
            ':email' => $email,
            ':token' => $token,
            ':expires' => $expiresAt
        ]);

        // E-Mail verschicken (Hier mail() oder PHPMailer / SendGrid nutzen)
        $resetLink = "https://deinedomain.de/reset-password?token=" . $token;
        $subject = "Passwort zurücksetzen";
        $headers = "From: no-reply@deinedomain.de\r\nContent-Type: text/html; charset=UTF-8";
        $message = "<p>Hallo,</p><p>klicke auf den folgenden Link, um dein Passwort zurückzusetzen:</p>";
        $message .= "<p><a href='{$resetLink}'>{$resetLink}</a></p>";
        $message .= "<p>Der Link ist 1 Stunde gültig.</p>";

        @mail($email, $subject, $message, $headers);
    }

    // Aus Sicherheitsgründen immer 'true' zurückgeben (User-Enumeration verhindern)
    echo json_encode([
        "success" => true,
        "message" => "Wir haben dir per E-Mail einen Link gesendet, über welchen du dein Password ändern kannst. Bitte Prüfe auch deinen Spam-Ordner."
    ]);
    exit;
}

// ==========================================
// 2. NEUES PASSWORT SPEICHERN (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reset') {
    $token = trim($input['token'] ?? '');
    $newPassword = $input['password'] ?? '';

    if (empty($token) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Ungültige Eingabe."]);
        exit;
    }

    // Token validieren & Ablaufzeit prüfen
    $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = :token AND expires_at > NOW() LIMIT 1");
    $stmt->execute([':token' => $token]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetData) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Ungültiges oder abgelaufenes Token."]);
        exit;
    }

    // Passwort aktualisieren
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmtUpdate = $conn->prepare("UPDATE users SET password = :password WHERE email = :email");
    $stmtUpdate->execute([
        ':password' => $hashedPassword,
        ':email' => $resetData['email']
    ]);

    // Token einlösen / löschen
    $stmtDel = $conn->prepare("DELETE FROM password_resets WHERE email = :email");
    $stmtDel->execute([':email' => $resetData['email']]);

    echo json_encode(["success" => true, "message" => "Passwort erfolgreich geändert!"]);
    exit;
}
