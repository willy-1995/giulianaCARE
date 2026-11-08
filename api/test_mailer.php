
<?php

header('Content-Type: application/json; charset=UTF-8');

$autoload = __DIR__ . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Composer-Autoloader fehlt'
    ]);
    exit;
}

require_once $autoload;

echo json_encode([
    'autoload_exists' => true,
    'phpmailer_file_exists' => is_file(
        __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php'
    ),
    'phpmailer_class_loaded' => class_exists(
        \PHPMailer\PHPMailer\PHPMailer::class
    )
]);
