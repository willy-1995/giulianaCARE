<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/envloader.php'; // Lädt die .env Datei
require_once __DIR__ . '/vendor/autoload.php';

function sendMail($toEmail, $subject, $contentHtml)
{
    $mail = new PHPMailer(true);

    try {
        // Servereinstellungen direkt über das $_ENV Array (wie in deiner Database-Klasse)
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$_ENV['SMTP_PORT'];

        // Absender und Empfänger
        $mail->setFrom('info@giuliana-care.de', 'Giuliana Care');
        $mail->addAddress($toEmail);

        // Inhalt
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $contentHtml;
        // Optional: Plaintext-Alternative für alte E-Mail-Clients
        $mail->AltBody = strip_tags($contentHtml);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Fehler loggen, damit sensible Details nicht an den Endnutzer durchsickern
        error_log("E-Mail konnte nicht gesendet werden. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
