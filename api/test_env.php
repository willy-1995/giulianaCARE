<?php
require_once __DIR__ . '/envloader.php';

header('Content-Type: application/json');
echo json_encode([
    'host' => $host,
    'db' => $db,
    'jwt_key_exists' => !empty($jwt_key),
    'jwt_key_length' => strlen($jwt_key ?? '')
]);
exit;
