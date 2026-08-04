<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JWT erstellen
 */
function createJWT(int $userId, string $email, string $username): string
{
    // Holt sich den Key direkt aus der Umgebungsvariable
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
function getUserIdFromToken() // <-- KEIN Parameter mehr nötig!
{
    $secretKey = $_ENV['JWT_SECRET'] ?? ''; // Holt sich den Key selbst

    $headers = getallheaders();
    // Fix für Server, die "authorization" kleinschreiben
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        try {
            // Hier nutzen wir das interne $secretKey
            $decoded = JWT::decode($matches[1], new Key($secretKey, 'HS256'));

            $finalId = $decoded->id ?? $decoded->user_id ?? null;

            if ($finalId) {
                return (int)$finalId;
            }
            throw new Exception("ID nicht im Token enthalten");
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "JWT FEHLER " . $e->getMessage()]);
            exit;
        }
    }
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Nicht autorisiert"]);
    exit;
}
