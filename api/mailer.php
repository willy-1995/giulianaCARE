<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/envloader.php';
require_once __DIR__ . '/vendor/autoload.php';

function sendMail($toEmail, $subject, $contentHtml, $replyTo = null)
{
    $mail = new PHPMailer(true);

    try {
        // SMTP-Einstellungen
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$_ENV['SMTP_PORT'];

        // Zeichensatz
        $mail->CharSet = 'UTF-8';

        // Absender und Empfänger
        $mail->setFrom('info@giuliana-care.de', 'Giuliana Care');
        $mail->addAddress($toEmail);

        // Optionale Antwortadresse des Kunden
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }

        // Inhalt
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $contentHtml;
        $mail->AltBody = strip_tags($contentHtml);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("E-Mail konnte nicht gesendet werden. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
