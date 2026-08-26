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

    // Robust alle möglichen Server-Quellen für den Authorization-Header prüfen
    $authHeader = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    // Steuerzeichen & Zeilenumbrüche säubern
    $authHeader = str_replace(["\r", "\n"], '', trim($authHeader));

    // Regex akzeptiert beliebige Abstände zwischen Bearer und Token
    if (!empty($authHeader) && preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        $token = trim($matches[1]);

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

    // Fallback: Wenn der Header schlicht nicht gegriffen hat
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Nicht autorisiert (Header nicht gefunden)",
        "received_header" => $authHeader
    ]);
    exit;
}
