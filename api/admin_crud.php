<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/mailer.php';

class AdminManager
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // ==========================================
    // CREATE: Admin anlegen (z.B. für Backend/Setup)
    // ==========================================
    public function createAdmin($data)
    {
        $sql = "INSERT INTO admins (email, password, pin, role) VALUES (:email, :password, :pin, :role)";
        $stmt = $this->conn->prepare($sql);

        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

        return $stmt->execute([
            ':email'    => $data['email'],
            ':password' => $hashedPassword,
            ':pin'      => $data['pin'], // 6-stellige PIN
            ':role'     => $data['role'] ?? 'admin'
        ]);
    }

    // ==========================================
    // LOGIN: Authentifizierung (E-Mail + Passwort + 6-stellige PIN)
    // ==========================================
    public function login($email, $password, $pin)
    {
        $sql = "SELECT * FROM admins WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            return false;
        }

        // Passwort prüfen
        if (!password_verify($password, $admin['password'])) {
            return false;
        }

        // 6-stellige PIN prüfen
        if (trim($admin['pin']) !== trim($pin)) {
            return false;
        }

        unset($admin['password']); // Sicherheitshalber Passwort aus dem Array entfernen
        return $admin;
    }

    // ==========================================
    // READ: Admin per ID laden
    // ==========================================
    public function getAdmin($id)
    {
        $sql = "SELECT id, email, role, created_at FROM admins WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        return $admin ? $admin : null;
    }

    // ==========================================
    // DELETE: Admin löschen
    // ==========================================
    public function deleteAdmin($id)
    {
        $sql = "DELETE FROM admins WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
