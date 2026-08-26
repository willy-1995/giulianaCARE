<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JWT erstellen
 */
function createJWT(int $userId, string $email, string $username): string
{
    $secretKey = $_ENV['JWT_SECRET'] ?? '';

    $payload = [
        "id" => $userId,
        "email" => $email,
        "username" => $username,
        "iat" => time(),
        "exp" => time() + (10 * 365 * 24 * 60 * 60) // 10 Jahre gültig
    ];

    return JWT::encode($payload, $secretKey, "HS256");
}

/**
 * JWT verifizieren
 */
function verifyJWT(string $token): object
{
    $secretKey = $_ENV['JWT_SECRET'] ?? '';
    return JWT::decode($token, new Key($secretKey, "HS256"));
}

/**
 * Holt die User-ID aus dem Authorization-Header
 */
function getUserIdFromToken()
{
    $secretKey = $_ENV['JWT_SECRET'] ?? '';

    // 1. Authorization Header aus allen Server-Quellen abrufen
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['Authorization']
        ?? '';

    // Fallback über getallheaders(), falls Server-Variablen leer sind
    if (empty($authHeader) && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    // 2. Token extrahieren (akzeptiert zeilenumbruchsfreie Bearer-Tokens)
    if (preg_match('/Bearer\s+(\S+)/i', trim($authHeader), $matches)) {
        $token = $matches[1];

        try {
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
            $finalId = $decoded->id ?? $decoded->user_id ?? null;

            if ($finalId) {
                return (int)$finalId;
            }
            throw new Exception("ID nicht im Token enthalten");
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "JWT FEHLER: " . $e->getMessage()]);
            exit;
        }
    }

    // Falls gar kein Token gefunden werden konnte
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Authorization-Header konnte nicht ausgelesen werden",
        "debug_raw" => $authHeader
    ]);
    exit;
}
