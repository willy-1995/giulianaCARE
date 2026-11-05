<?php
require_once "cors.php";
require_once "rate_limiter.php";
require_once "mailer.php";

// Rate Limiter
checkRateLimit('contact_request', 5, 600);

// Respond immediately to preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$recipient_email = "info@giuliana-care.de";
$max_length = 600;
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Retrieve and sanitize inputs
    $reference = isset($_POST['reference']) ? trim($_POST['reference']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $tel = isset($_POST['tel']) ? trim($_POST['tel']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';

    // Prevent Header Injection
    $subject = str_replace(array("\r", "\n"), '', $reference);
    $email = str_replace(array("\r", "\n"), '', $email);

    // Validation
    if (empty($subject) || empty($email) || empty($content)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (mb_strlen($content, 'UTF-8') > $max_length) {
        $error_message = "Your message exceeds the limit of " . $max_length . " characters.";
    } else {

        // Build email subject
        $mail_subject = "Contact Request: " . $reference;

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

        // E-Mail über PHPMailer versenden
        if (sendMail($recipient_email, $mail_subject, $mail_body, $email)) {
            http_response_code(200);
            echo "Thank you! Your message has been sent successfully.";
            exit();
        } else {
            http_response_code(500);
            echo "An error occurred while sending your message. Please try again later.";
            exit();
        }
    }

    if (!empty($error_message)) {
        http_response_code(400);
        echo $error_message;
        exit();
    }
}