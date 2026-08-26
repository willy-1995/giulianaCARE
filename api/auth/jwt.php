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
/**
 * Holt die User-ID aus dem Token (unterstützt X-Access-Token, X-Token & Authorization-Header)
 */
function getUserIdFromToken(): int
{
    $secretKey = $_ENV['JWT_SECRET'] ?? '';
    $token = '';

    // 1. Custom Header: X-Access-Token (PHP wandelt Bindestriche in Unterstriche um)
    if (!empty($_SERVER['HTTP_X_ACCESS_TOKEN'])) {
        $token = $_SERVER['HTTP_X_ACCESS_TOKEN'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_X_ACCESS_TOKEN'])) {
        $token = $_SERVER['REDIRECT_HTTP_X_ACCESS_TOKEN'];
    }
    // 2. Custom Header Fallback: X-Token
    elseif (!empty($_SERVER['HTTP_X_TOKEN'])) {
        $token = $_SERVER['HTTP_X_TOKEN'];
    }
    // 3. Standard-Fallback: Authorization Bearer Header
    else {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (empty($authHeader) && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (preg_match('/Bearer\s+(\S+)/i', trim($authHeader), $matches)) {
            $token = $matches[1];
        }
    }

    $token = trim($token);

    // 4. Token verifizieren, wenn ein Wert gefunden wurde
    if (!empty($token)) {
        try {
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
            $finalId = $decoded->id ?? $decoded->user_id ?? null;

            if ($finalId) {
                return (int)$finalId;
            }
            throw new Exception("ID nicht im Token enthalten");
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "message" => "JWT FEHLER: " . $e->getMessage()
            ]);
            exit;
        }
    }

    // Wenn kein Token ausgelesen werden konnte
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Nicht autorisiert (Kein Token übergeben)"
    ]);
    exit;
}
