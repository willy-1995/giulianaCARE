
<?php

// ======================================================
// LOGGING
// ======================================================
require_once __DIR__ . "/cors.php";
ini_set('log_errors', '1');
ini_set('display_errors', '0');

// Eindeutige ID für jede Anfrage
$requestLogId = bin2hex(random_bytes(8));

// Einheitliche Log-Funktion mit Zeitstempel
function requestLog($requestId, $step, $message)
{
    error_log(
        "[" . date('Y-m-d H:i:s P') . "] " .
            "[REQUEST-ID: " . $requestId . "] " .
            "[" . $step . "] " .
            $message
    );
}

// Protokolliert auch unerwartete PHP-Abbrüche
register_shutdown_function(function () use ($requestLogId) {
    $error = error_get_last();

    if (
        $error !== null &&
        in_array($error['type'], [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR
        ], true)
    ) {
        requestLog(
            $requestLogId,
            "FATAL ERROR",
            "Datei: " . $error['file'] .
                " | Zeile: " . $error['line'] .
                " | Fehler: " . $error['message']
        );
    }
});

requestLog(
    $requestLogId,
    "START",
    "Kontaktanfrage gestartet | Methode: " .
        ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN')
);

// ======================================================
// DATEIEN EINBINDEN
// ======================================================



requestLog($requestLogId, "INCLUDE", "Lade rate_limiter.php");
require_once "rate_limiter.php";
requestLog($requestLogId, "INCLUDE", "rate_limiter.php geladen");

requestLog($requestLogId, "INCLUDE", "Lade mailer.php");
require_once "mailer.php";
requestLog($requestLogId, "INCLUDE", "mailer.php geladen");

// Rate Limiter
requestLog($requestLogId, "RATE_LIMIT", "Prüfung gestartet");

checkRateLimit('contact_request', 5, 600);

requestLog($requestLogId, "RATE_LIMIT", "Prüfung erfolgreich abgeschlossen");

// Respond immediately to preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    requestLog($requestLogId, "OPTIONS", "Preflight-Anfrage beantwortet");
    http_response_code(200);
    exit();
}

$recipient_email = "info@giuliana-care.de";
$max_length = 600;
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    requestLog($requestLogId, "POST", "POST-Verarbeitung gestartet");

    // Retrieve and sanitize inputs
    $reference = isset($_POST['reference']) ? trim($_POST['reference']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $tel = isset($_POST['tel']) ? trim($_POST['tel']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';

    requestLog(
        $requestLogId,
        "INPUT",
        "Formulardaten ausgelesen | " .
            "reference vorhanden: " . ($reference !== '' ? 'JA' : 'NEIN') .
            " | email vorhanden: " . ($email !== '' ? 'JA' : 'NEIN') .
            " | tel vorhanden: " . ($tel !== '' ? 'JA' : 'NEIN') .
            " | content vorhanden: " . ($content !== '' ? 'JA' : 'NEIN') .
            " | content Länge: " . strlen($content)
    );

    // Prevent Header Injection
    $subject = str_replace(array("\r", "\n"), '', $reference);
    $email = str_replace(array("\r", "\n"), '', $email);

    requestLog($requestLogId, "VALIDATION", "Validierung gestartet");

    // Validation
    if (empty($subject) || empty($email) || empty($content)) {
        $error_message = "Please fill in all required fields.";

        requestLog(
            $requestLogId,
            "VALIDATION_ERROR",
            "Pflichtfeld fehlt"
        );
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";

        requestLog(
            $requestLogId,
            "VALIDATION_ERROR",
            "Ungültiges E-Mail-Format"
        );
    } elseif (mb_strlen($content, 'UTF-8') > $max_length) {
        $error_message = "Your message exceeds the limit of " . $max_length . " characters.";

        requestLog(
            $requestLogId,
            "VALIDATION_ERROR",
            "Nachricht überschreitet maximale Zeichenlänge"
        );
    } else {

        requestLog(
            $requestLogId,
            "VALIDATION",
            "Alle Prüfungen erfolgreich"
        );

        // Build email subject
        $mail_subject = "Contact Request: " . $reference;

        requestLog($requestLogId, "MAIL_BUILD", "E-Mail-Betreff erstellt");

        // HTML-E-Mail erstellen
        $mail_body = "
            <h2>Neue Kontaktanfrage</h2>
            <p><strong>Betreff:</strong> " . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . "</p>
            <p><strong>E-Mail:</strong> " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</p>
            <p><strong>Telefon:</strong> " . htmlspecialchars($tel ?: 'Not provided', ENT_QUOTES, 'UTF-8') . "</p>
            <hr>
            <p><strong>Nachricht:</strong></p>
            <p>" . nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) . "</p>
        ";

        requestLog(
            $requestLogId,
            "MAIL_BUILD",
            "HTML-E-Mail erfolgreich erstellt"
        );

        // E-Mail über PHPMailer versenden
        requestLog(
            $requestLogId,
            "SMTP_START",
            "sendMail() wird aufgerufen"
        );

        try {

            $mailSent = sendMail(
                $recipient_email,
                $mail_subject,
                $mail_body,
                $email
            );

            requestLog(
                $requestLogId,
                "SMTP_RESULT",
                "sendMail() Ergebnis: " .
                    ($mailSent ? 'TRUE' : 'FALSE')
            );

            if ($mailSent) {

                requestLog(
                    $requestLogId,
                    "SUCCESS",
                    "E-Mail erfolgreich an SMTP-Server übergeben | HTTP 200"
                );

                http_response_code(200);
                echo "Thank you! Your message has been sent successfully.";
                exit();
            } else {

                requestLog(
                    $requestLogId,
                    "SMTP_ERROR",
                    "sendMail() hat FALSE zurückgegeben | HTTP 500"
                );

                http_response_code(500);
                echo "An error occurred while sending your message. Please try again later.";
                exit();
            }
        } catch (Throwable $e) {

            requestLog(
                $requestLogId,
                "SMTP_EXCEPTION",
                "Fehler: " . $e->getMessage() .
                    " | Datei: " . $e->getFile() .
                    " | Zeile: " . $e->getLine()
            );

            // Ursprüngliches Fehlerverhalten beibehalten
            throw $e;
        }
    }

    if (!empty($error_message)) {

        requestLog(
            $requestLogId,
            "HTTP_400",
            "Validierung fehlgeschlagen: " . $error_message
        );

        http_response_code(400);
        echo $error_message;
        exit();
    }
}
