<?php
require_once 'database.php';

class UserManager
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getDb()
    {
        return $this->conn;
    }

    // ==========================================
    // CREATE: User Account
    // ==========================================
    public function createUser($userData)
    {
        try {
            // Wir behalten die Transaction bei, falls du später wieder erweitern willst
            $this->conn->beginTransaction();

            $sql = "INSERT INTO users (email, password, price, country, area_code) 
                    VALUES (:email, :password, :price, :country, :area_code)";
            $stmt = $this->conn->prepare($sql);
            $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);

            $stmt->execute([
                ':email'     => $userData['email'],
                ':password'  => $hashedPassword,
                ':price'     => $userData['price'] ?? null,
                ':country'   => $userData['country'] ?? null,
                ':area_code' => $userData['area_code'] ?? null,
            ]);

            $userId = $this->conn->lastInsertId();

            $this->conn->commit();
            return $userId;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    // ==========================================
    // READ: Einzelnen Nutzer laden
    // ==========================================
    public function getUser($id)
    {
        $sql = "SELECT id, email, price, country, area_code, created_at FROM users WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return null;

        return $user;
    }

    // ==========================================
    // LOGIN: Authentifizierung
    // ==========================================
    public function login($email, $password)
    {
        // Wir selektieren alles, um die ID für das JWT zu haben
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']); // Passwort aus dem Resultat entfernen
            return $user; // Gibt id, email, created_at zurück
        }
        return false;
    }

    // ==========================================
    // UPDATE: Basis-Daten aktualisieren
    // ==========================================
    public function updateUser($id, $userData)
    {
        try {
            $this->conn->beginTransaction();

            // Da keine Profile mehr existieren, aktualisieren wir nur die E-Mail
            // (Passwort-Update müsste separat mit hashing erfolgen)
            $sql = "UPDATE users 
                    SET email = :email, price = :price, country = :country, area_code = :area_code 
                    WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':email'     => $userData['email'],
                ':price'     => $userData['price'] ?? null,
                ':country'   => $userData['country'] ?? null,
                ':area_code' => $userData['area_code'] ?? null,
                ':id'        => $id
            ]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    // ==========================================
    // DELETE: Nutzer löschen
    // ==========================================
    public function deleteUser($id)
    {
        // Dank "ON DELETE CASCADE" in der DB werden verknüpfte 
        // Clients und Contacts automatisch mitgelöscht.
        $sql = "DELETE FROM users WHERE id = :id";
        return $this->conn->prepare($sql)->execute([':id' => $id]);
    }
}
