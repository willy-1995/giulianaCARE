<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/envloader.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth/jwt.php';

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Methode nicht erlaubt."]);
    exit;
}

$resource = $_GET['resource'] ?? '';
$allowedResources = ['users', 'clients', 'contacts', 'protocol'];

if (!in_array($resource, $allowedResources, true)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Ungültige Ressource angefordert."]);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // JWT auslesen und prüfen, ob es sich um einen gültigen Admin handelt
    $tokenUserId = getUserIdFromToken();
    if (!$tokenUserId) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Ungültiger oder fehlender Token."]);
        exit;
    }

    $adminStmt = $conn->prepare("SELECT `id`, `email` FROM `admin` WHERE `id` = :id LIMIT 1");
    $adminStmt->execute([':id' => (int)$tokenUserId]);
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Zugriff verweigert. Nur für Admins."]);
        exit;
    }

    switch ($resource) {
        case 'users':
            $stmt = $conn->query("SELECT * FROM `users` ORDER BY `id` DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                "success" => true,
                "users"   => $rows,
            ]);
            break;

        case 'clients':
            $stmt = $conn->query("SELECT * FROM `clients` ORDER BY `id` DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                "success" => true,
                "clients" => $rows,
            ]);
            break;

        case 'contacts':
            $stmt = $conn->query("SELECT * FROM `contacts` ORDER BY `id` DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                "success"  => true,
                "contacts" => $rows,
            ]);
            break;

        case 'protocol':
            $stmt = $conn->query("SELECT * FROM `protocol` ORDER BY `id` DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                "success"  => true,
                "protocol" => $rows,
            ]);
            break;
    }
} catch (PDOException $e) {
    error_log("Database Error (admin_loader): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Datenbankfehler beim Laden der Daten."]);
} catch (Exception $e) {
    error_log("Server Error (admin_loader): " . $e->getMessage());
    http_response_code(401);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
