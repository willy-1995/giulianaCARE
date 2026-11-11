<?php
require_once 'database.php';
require_once 'mailer.php';
require_once 'cors.php';
require_once 'envloader.php';
require_once __DIR__ . '/auth/jwt.php';

header("Content-Type: application/json; charset=UTF-8");

class CareMatchManager
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function createUser($data)
    {
        $sql = "INSERT INTO carematch_users (email, password, role, name, gender, region, languages, care_insurance_billing, services, conditions) 
                VALUES (:email, :password, :role, :name, :gender, :region, :languages, :care_insurance_billing, :services, :conditions)";
        $stmt = $this->conn->prepare($sql);
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

        $stmt->execute([
            ':email'                  => $data['email'],
            ':password'               => $hashedPassword,
            ':role'                   => $data['role'],
            ':name'                   => $data['name'],
            ':gender'                 => $data['gender'] ?? null,
            ':region'                 => $data['region'] ?? null,
            ':languages'              => $data['languages'] ?? null,
            ':care_insurance_billing' => $data['care_insurance_billing'] ?? 0,
            ':services'               => $data['services'] ?? null,
            ':conditions'             => $data['conditions'] ?? null,
        ]);

        return $this->conn->lastInsertId();
    }

    public function getUser($id)
    {
        $sql = "SELECT id, email, role, name, gender, region, languages, care_insurance_billing, services, conditions, created_at 
                FROM carematch_users WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserByEmail($email)
    {
        $sql = "SELECT * FROM carematch_users WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateUser($id, $data)
    {
        $sql = "UPDATE carematch_users 
                SET name = :name, 
                    gender = :gender, 
                    region = :region, 
                    languages = :languages, 
                    care_insurance_billing = :care_insurance_billing, 
                    services = :services, 
                    conditions = :conditions 
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':name'                   => $data['name'],
            ':gender'                 => $data['gender'] ?? null,
            ':region'                 => $data['region'] ?? null,
            ':languages'              => $data['languages'] ?? null,
            ':care_insurance_billing' => $data['care_insurance_billing'] ?? 0,
            ':services'               => $data['services'] ?? null,
            ':conditions'             => $data['conditions'] ?? null,
            ':id'                     => $id
        ]);
    }

    public function searchProfiles($filters, $limit = 10, $offset = 0)
    {
        // Wenn ein Suchender (seeker) sucht, sucht er Pflegekräfte (caregiver) und umgekehrt
        $targetRole = ($filters['role'] === 'seeker') ? 'caregiver' : 'seeker';

        $sql = "SELECT id, role, name, gender, region, languages, care_insurance_billing, services, conditions 
                FROM carematch_users WHERE role = :targetRole";
        $params = [':targetRole' => $targetRole];

        if (!empty($filters['region'])) {
            $sql .= " AND region LIKE :region";
            $params[':region'] = '%' . $filters['region'] . '%';
        }
        if (!empty($filters['gender'])) {
            $sql .= " AND gender = :gender";
            $params[':gender'] = $filters['gender'];
        }
        if (isset($filters['care_insurance_billing']) && $filters['care_insurance_billing'] !== '') {
            $sql .= " AND care_insurance_billing = :billing";
            $params[':billing'] = $filters['care_insurance_billing'];
        }
        if (!empty($filters['languages'])) {
            $sql .= " AND languages LIKE :languages";
            $params[':languages'] = '%' . $filters['languages'] . '%';
        }
        if (!empty($filters['service'])) {
            $sql .= " AND services LIKE :service";
            $params[':service'] = '%' . $filters['service'] . '%';
        }
        if (!empty($filters['condition'])) {
            $sql .= " AND conditions LIKE :condition";
            $params[':condition'] = '%' . $filters['condition'] . '%';
        }

        $sql .= " LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ==========================================
// API-ROUTER / ENDPUNKT LOGIK
// ==========================================
$input = json_decode(file_get_contents("php://input"), true);
$method = $_SERVER['REQUEST_METHOD'];
$manager = new CareMatchManager();
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            if ($action === 'register') {
                if (empty($input['email']) || empty($input['password']) || empty($input['role']) || empty($input['name'])) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Pflichtfelder fehlen."]);
                    exit;
                }
                if ($manager->getUserByEmail($input['email'])) {
                    http_response_code(409);
                    echo json_encode(["success" => false, "message" => "E-Mail bereits vergeben."]);
                    exit;
                }
                $userId = $manager->createUser($input);
                $token = createJWT((int)$userId, $input['email'], $input['role']);

                http_response_code(201);
                echo json_encode(["success" => true, "token" => $token, "userId" => $userId]);
            } elseif ($action === 'login') {
                $user = $manager->getUserByEmail($input['email'] ?? '');
                if ($user && password_verify($input['password'] ?? '', $user['password'])) {
                    unset($user['password']);
                    $token = createJWT((int)$user['id'], $user['email'], $user['role']);
                    echo json_encode(["success" => true, "token" => $token, "user" => $user]);
                } else {
                    http_response_code(401);
                    echo json_encode(["success" => false, "message" => "Ungültige Anmeldedaten."]);
                }
            }
            break;

        case 'GET':
            $currentUserId = getUserIdFromToken();
            if ($action === 'profile') {
                $profileId = $_GET['id'] ?? $currentUserId;
                $user = $manager->getUser($profileId);
                echo json_encode(["success" => true, "user" => $user]);
            } elseif ($action === 'search') {
                $currentUser = $manager->getUser($currentUserId);
                $filters = $_GET;
                $filters['role'] = $currentUser['role'];
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

                $results = $manager->searchProfiles($filters, $limit, $offset);
                echo json_encode(["success" => true, "data" => $results]);
            }
            break;

        case 'PUT':
            $currentUserId = getUserIdFromToken();
            if ($manager->updateUser($currentUserId, $input)) {
                echo json_encode(["success" => true, "message" => "Profil aktualisiert."]);
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Aktualisierung fehlgeschlagen."]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Methode nicht erlaubt."]);
            break;
    }
} catch (Exception $e) {
    error_log("CareMatch API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Ein Serverfehler ist aufgetreten."]);
}
