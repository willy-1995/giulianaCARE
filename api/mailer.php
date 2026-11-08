
<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/envloader.php';
require_once __DIR__ . '/vendor/autoload.php';

function sendMail($toEmail, $subject, $contentHtml, $replyTo = null)
{
    try {
        // SMTP-Konfiguration prüfen
        $host = $_ENV['SMTP_HOST'] ?? '';
        $user = $_ENV['SMTP_USER'] ?? '';
        $pass = $_ENV['SMTP_PASS'] ?? '';
        $port = (int)($_ENV['SMTP_PORT'] ?? 587);

        if ($host === '' || $user === '' || $pass === '') {
            error_log("MAIL ERROR: SMTP-Konfiguration unvollständig.");
            return false;
        }

        $mail = new PHPMailer(true);

        // SMTP-Verbindung
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->Port = $port;
        $mail->Timeout = 15;

        // Verschlüsselung abhängig vom Port
        $mail->SMTPSecure = $port === 465
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;

        // Zeichensatz
        $mail->CharSet = 'UTF-8';

        // Absender
        $mail->setFrom('info@giuliana-care.de', 'Giuliana Care');

        // Empfänger
        $mail->addAddress($toEmail);

        // Optionale Antwortadresse
        if (
            $replyTo !== null &&
            filter_var($replyTo, FILTER_VALIDATE_EMAIL)
        ) {
            $mail->addReplyTo($replyTo);
        }

        // E-Mail-Inhalt
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $contentHtml;
        $mail->AltBody = trim(
            html_entity_decode(
                strip_tags($contentHtml),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );

        // Versand
        $mail->send();

        error_log("MAIL DEBUG: E-Mail erfolgreich an SMTP übergeben.");

        return true;
    } catch (\Throwable $e) {
        error_log(
            "MAIL ERROR: " . $e->getMessage()
        );

        return false;
    }
}
