<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function sendMail($toEmail, $subject, $contentHtml)
{
    $mail = new PHPMailer(true);

    try {
        // Servereinstellungen (Trage hier deine SMTP-Daten ein)
        $mail->isSMTP();
        $mail->Host       = 'dein-smtp-server.de';     // z.B. smtp.ionos.de oder smtp.gmail.com
        $mail->SMTPAuth   = true;
        $mail->Username   = 'info@giuliana-care.de';   // Deine Absender-Adresse
        $mail->Password   = 'DEIN_EMAIL_PASSWORT';     // Dein E-Mail-Passwort
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // oder PHPMailer::ENCRYPTION_SMTPS für Port 465
        $mail->Port       = 587;                       // Port (587 für TLS, 465 für SSL)

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
        // Fehler loggen oder bei Bedarf werfen
        error_log("E-Mail konnte nicht gesendet werden. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
