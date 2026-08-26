<?php
// Erlaubte Domains (lokal & live)
$allowed_origins = [
    "http://localhost:5173",
    "http://localhost:3000",
    "https://www.giuliana-care.de",
    "https://giuliana-care.de"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
} else {
    // Standard-Fallback für lokale Tests
    header("Access-Control-Allow-Origin: http://localhost:5173");
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS, DELETE");
header("Content-Type: application/json; charset=UTF-8");

// Preflight-Anfrage (OPTIONS) abfangen
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}
