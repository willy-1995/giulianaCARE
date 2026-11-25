<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/envloader.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth/jwt.php';

header("Content-Type: application/json; charset=UTF-8");


class AdminManager
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // ==========================================
    // CREATE: Admin anlegen
    // ==========================================
    public function createAdmin($email, $password)
    {
        $sql = "INSERT INTO `admin` (`email`, `password`) VALUES (:email, :password)";
        $stmt = $this->conn->prepare($sql);

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        return $stmt->execute([
            ':email'    => trim($email),
            ':password' => $hashedPassword,
        ]);
    }

    // ==========================================
    // LOGIN: Authentifizierung (E-Mail + Passwort)
    // ==========================================
    public function login($email, $password)
    {
        $sql = "SELECT `id`, `email`, `password`, `created_at` FROM `admin` WHERE `email` = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => trim($email)]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            return false;
        }

        if (!password_verify($password, $admin['password'])) {
            return false;
        }

        unset($admin['password']);
        $admin['role'] = 'admin';
        return $admin;
    }

    // ==========================================
    // READ: Admin per ID laden
    // ==========================================
    public function getAdmin($id)
    {
        $sql = "SELECT `id`, `email`, `created_at` FROM `admin` WHERE `id` = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            return null;
        }

        $admin['role'] = 'admin';
        return $admin;
    }

    // ==========================================
    // UPDATE: E-Mail und/oder Passwort aktualisieren
    // ==========================================
    public function updateAdmin($id, $email = null, $password = null)
    {
        $fields = [];
        $params = [':id' => $id];

        if ($email !== null && trim($email) !== '') {
            $fields[] = "`email` = :email";
            $params[':email'] = trim($email);
        }

        if ($password !== null && $password !== '') {
            $fields[] = "`password` = :password";
            $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE `admin` SET " . implode(", ", $fields) . " WHERE `id` = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    // ==========================================
    // DELETE: Admin löschen
    // ==========================================
    public function deleteAdmin($id)
    {
        $sql = "DELETE FROM `admin` WHERE `id` = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}

// ==========================================
// Request-Handling (nur wenn direkt aufgerufen)
// ==========================================
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $method = $_SERVER['REQUEST_METHOD'];
    $rawInput = file_get_contents("php://input");
    $input = json_decode($rawInput, true);
    $adminManager = new AdminManager();

    try {
        switch ($method) {
            // --------------------------------------
            // POST: Login oder Admin anlegen (?action=register)
            // --------------------------------------
            case 'POST':
                if (!is_array($input)) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Ungültiges JSON-Format."]);
                    exit;
                }

                $action = $_GET['action'] ?? ($input['action'] ?? 'login');

                if ($action === 'register') {
                    if (empty($input['email']) || empty($input['password'])) {
                        http_response_code(400);
                        echo json_encode(["success" => false, "message" => "Bitte E-Mail und Passwort angeben."]);
                        exit;
                    }

                    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                        http_response_code(400);
                        echo json_encode(["success" => false, "message" => "Ungültige E-Mail-Adresse."]);
                        exit;
                    }

                    $created = $adminManager->createAdmin($input['email'], $input['password']);
                    if ($created) {
                        http_response_code(201);
                        echo json_encode(["success" => true, "message" => "Admin erfolgreich angelegt."]);
                    } else {
                        http_response_code(500);
                        echo json_encode(["success" => false, "message" => "Admin konnte nicht angelegt werden."]);
                    }
                    break;
                }

                // Standard: Login
                if (empty($input['email']) || empty($input['password'])) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Bitte E-Mail und Passwort ausfüllen."]);
                    exit;
                }

                $admin = $adminManager->login($input['email'], $input['password']);

                if ($admin) {
                    $token = createJWT((int)$admin['id'], $admin['email'], $admin['role']);
                    echo json_encode([
                        "success" => true,
                        "message" => "Login erfolgreich.",
                        "token"   => $token,
                        "admin"   => $admin,
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode(["success" => false, "message" => "Ungültige Anmeldedaten."]);
                }
                break;

            // --------------------------------------
            // GET: Admin-Daten abrufen (geschützt via JWT)
            // --------------------------------------
            case 'GET':
                $currentAdminId = getUserIdFromToken();
                $adminData = $adminManager->getAdmin($currentAdminId);

                if ($adminData && $adminData['role'] === 'admin') {
                    echo json_encode(["success" => true, "admin" => $adminData]);
                } else {
                    http_response_code(403);
                    echo json_encode(["success" => false, "message" => "Zugriff verweigert."]);
                }
                break;

            // --------------------------------------
            // PUT: Admin aktualisieren (geschützt via JWT)
            // --------------------------------------
            case 'PUT':
                $currentAdminId = getUserIdFromToken();
                $existing = $adminManager->getAdmin($currentAdminId);
                if (!$existing || $existing['role'] !== 'admin') {
                    http_response_code(403);
                    echo json_encode(["success" => false, "message" => "Zugriff verweigert."]);
                    exit;
                }

                if (!is_array($input)) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Ungültiges JSON-Format."]);
                    exit;
                }

                $targetId = isset($input['id']) ? (int)$input['id'] : (int)$currentAdminId;
                $updated = $adminManager->updateAdmin(
                    $targetId,
                    $input['email'] ?? null,
                    $input['password'] ?? null
                );

                if ($updated) {
                    echo json_encode(["success" => true, "message" => "Admin aktualisiert."]);
                } else {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Keine Änderungen vorgenommen oder Fehler beim Aktualisieren."]);
                }
                break;

            // --------------------------------------
            // DELETE: Admin löschen (geschützt via JWT)
            // --------------------------------------
            case 'DELETE':
                $currentAdminId = getUserIdFromToken();
                $existing = $adminManager->getAdmin($currentAdminId);
                if (!$existing || $existing['role'] !== 'admin') {
                    http_response_code(403);
                    echo json_encode(["success" => false, "message" => "Zugriff verweigert."]);
                    exit;
                }

                $targetId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($input['id'] ?? $currentAdminId);
                if ($adminManager->deleteAdmin($targetId)) {
                    echo json_encode(["success" => true, "message" => "Admin gelöscht."]);
                } else {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Admin konnte nicht gelöscht werden."]);
                }
                break;

            default:
                http_response_code(405);
                echo json_encode(["success" => false, "message" => "Methode nicht erlaubt."]);
                break;
        }
    } catch (PDOException $e) {
        error_log("Database Error (Admin): " . $e->getMessage());
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode(["success" => false, "message" => "Diese E-Mail-Adresse ist bereits registriert."]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Ein Datenbankfehler ist aufgetreten."]);
        }
    } catch (Exception $e) {
        error_log("Server Error (Admin): " . $e->getMessage());
        http_response_code(401);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}
