<?php

$allowed_origin = "http://localhost:5173"; // React/ Vite Dev-Server

header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Credentials: true"); //because of Authorization in fetch (is credential)
header("Access-Control-Allow-Headers: Content-Type, Authorization"); //Authorization, bc it is in fetch request, need to be allowed in cors
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS, DELETE");
header("Content-Type: application/json; charset=UTF-8"); // for read json

// Preflight-Anfrage abfangen
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}
