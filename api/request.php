<?php
// CORS Headers for React frontend requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Respond immediately to preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$recipient_email = "beispiel@beispiel.de";
$max_length = 500;
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Retrieve and sanitize inputs
    $reference = isset($_POST['reference']) ? trim($_POST['subject']) : '';
    $email   = isset($_POST['email']) ? trim($_POST['email']) : '';
    $tel   = isset($_POST['tel']) ? trim($_POST['phone']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';

    // Prevent Header Injection (remove line breaks from single-line fields)
    $subject = str_replace(array("\r", "\n"), '', $reference);
    $email   = str_replace(array("\r", "\n"), '', $email);

    // Validation
    if (empty($subject) || empty($email) || empty($content)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (mb_strlen($content, 'UTF-8') > $max_length) {
        $error_message = "Your message exceeds the limit of " . $max_length . " characters.";
    } else {
        // Build email body
        $mail_subject = "Contact Request: " . $reference;

        $mail_body  = "New message received via contact form:\n\n";
        $mail_body .= "Subject: " . $reference . "\n";
        $mail_body .= "Email: " . $email . "\n";
        $mail_body .= "Phone: " . ($tel ? $tel : 'Not provided') . "\n";
        $mail_body .= "--------------------------------------------------\n\n";
        $mail_body .= $content;

        // Email headers
        $headers = array(
            'From' => $email,
            'Reply-To' => $email,
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-Type' => 'text/plain; charset=UTF-8'
        );

        // Send email
        if (mail($recipient_email, $mail_subject, $mail_body, $headers)) {
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
