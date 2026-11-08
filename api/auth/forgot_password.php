
<?php
require_once "../cors.php";
require_once "../database.php";
require_once "../envloader.php";
require_once "../mailer.php";

header("Content-Type: application/json; charset=UTF-8");

$database = new Database();
$conn = $database->getConnection();

$input = json_decode(file_get_contents("php://input"), true) ?? [];
$action = $_GET['action'] ?? 'request';

// ==========================================
// 1. PASSWORT-RESET ANFORDERN (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'request') {

    $email = trim($input['email'] ?? '');

    if (empty($email)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Bitte E-Mail-Adresse angeben."
        ]);
        exit;
    }

    // Prüfen, ob User existiert
    $stmt = $conn->prepare(
        "SELECT id FROM users WHERE email = :email LIMIT 1"
    );
    $stmt->execute([':email' => $email]);

    if ($stmt->fetch()) {

        // Token generieren (gültig für 1 Stunde)
        $token = bin2hex(random_bytes(32));
        $expiresAt = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Altes Token für die E-Mail löschen
        $stmtDel = $conn->prepare(
            "DELETE FROM password_resets WHERE email = :email"
        );
        $stmtDel->execute([':email' => $email]);

        // Neues Token speichern
        $stmtIns = $conn->prepare(
            "INSERT INTO password_resets
            (email, token, expires_at)
            VALUES (:email, :token, :expires)"
        );

        $stmtIns->execute([
            ':email' => $email,
            ':token' => $token,
            ':expires' => $expiresAt
        ]);

        // ==========================================
        // RESET-LINK ERSTELLEN
        // ==========================================
        $resetLink = "https://www.giuliana-care.de/reset-password?token="
            . urlencode($token);

        $subject = "Passwort zurücksetzen - Giuliana Care";

        $message = "
            <html>
            <body style='font-family: Arial, sans-serif;'>

                <h2>Passwort zurücksetzen</h2>

                <p>Hallo,</p>

                <p>
                    Du hast angefordert, dein Passwort
                    bei Giuliana Care zurückzusetzen.
                </p>

                <p>
                    Klicke auf den folgenden Link,
                    um ein neues Passwort festzulegen:
                </p>

                <p>
                    <a href='{$resetLink}'>
                        Passwort zurücksetzen
                    </a>
                </p>

                <p>
                    Dieser Link ist 1 Stunde gültig.
                </p>

                <p>
                    Falls du diese Anfrage nicht gestellt hast,
                    kannst du diese E-Mail ignorieren.
                </p>

                <p>
                    Viele Grüße<br>
                    Dein Giuliana Care Team
                </p>

            </body>
            </html>
        ";

        // ==========================================
        // E-MAIL ÜBER MAILER.PHP VERSENDEN
        // ==========================================
        $mailSent = sendMail($email, $subject, $message);

        if (!$mailSent) {
            error_log(
                "Passwort-Reset: E-Mail-Versand fehlgeschlagen."
            );
        }
    }

    // Einheitliche Antwort gegen User-Enumeration
    echo json_encode([
        "success" => true,
        "message" => "Wenn ein Konto mit dieser E-Mail-Adresse existiert, erhältst du einen Link zum Zurücksetzen deines Passworts. Bitte prüfe auch deinen Spam-Ordner."
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
        echo json_encode([
            "success" => false,
            "message" => "Ungültige Eingabe."
        ]);
        exit;
    }

    // Passwortlänge prüfen
    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Das Passwort muss mindestens 8 Zeichen lang sein."
        ]);
        exit;
    }

    // Token validieren & Ablaufzeit prüfen
    $stmt = $conn->prepare(
        "SELECT email
         FROM password_resets
         WHERE token = :token
         AND expires_at > NOW()
         LIMIT 1"
    );

    $stmt->execute([':token' => $token]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetData) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Ungültiges oder abgelaufenes Token."
        ]);
        exit;
    }

    // Passwort aktualisieren
    $hashedPassword = password_hash(
        $newPassword,
        PASSWORD_BCRYPT
    );

    $stmtUpdate = $conn->prepare(
        "UPDATE users
         SET password = :password
         WHERE email = :email"
    );

    $stmtUpdate->execute([
        ':password' => $hashedPassword,
        ':email' => $resetData['email']
    ]);

    // Token einlösen / löschen
    $stmtDel = $conn->prepare(
        "DELETE FROM password_resets WHERE email = :email"
    );

    $stmtDel->execute([
        ':email' => $resetData['email']
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Passwort erfolgreich geändert!"
    ]);
    exit;
}

// Ungültige Aktion
http_response_code(400);
echo json_encode([
    "success" => false,
    "message" => "Ungültige Anfrage."
]);
