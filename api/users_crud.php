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

            $sql = "INSERT INTO users (email, password, price, country, area_code, agb_accepted) 
                    VALUES (:email, :password, :price, :country, :area_code, :agb_accepted)";
            $stmt = $this->conn->prepare($sql);
            $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);

            $stmt->execute([
                ':email'     => $userData['email'],
                ':password'  => $hashedPassword,
                ':price'     => $userData['price'] ?? null,
                ':country'   => $userData['country'] ?? null,
                ':area_code' => $userData['area_code'] ?? null,
                ':agb_accepted' => $userData['agb_accepted'] ?? null,
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

            // 1. User-Stammdaten aktualisieren
            $sqlUser = "UPDATE users 
                        SET email = :email, 
                            price = :price, 
                            country = :country, 
                            area_code = :area_code
                        WHERE id = :id";

            $stmtUser = $this->conn->prepare($sqlUser);
            $stmtUser->execute([
                ':email'     => $userData['email'],
                ':price'     => $userData['price'] ?? null,
                ':country'   => $userData['country'] ?? null,
                ':area_code' => $userData['area_code'] ?? null,
                ':id'        => $id
            ]);

            // 2. Falls der Preis geändert wurde, ungültige Anrufe im zugehörigen Client zurücksetzen
            if (isset($userData['price'])) {
                $price = $userData['price'];

                if ($price === 'sicherheit') {
                    // Paket 'sicherheit': call_2, medication_2, call_3, medication_3 löschen
                    $sqlClient = "UPDATE clients 
                                  SET call_2 = NULL, medication_2 = NULL, call_3 = NULL, medication_3 = NULL 
                                  WHERE user_id = :user_id";
                    $stmtClient = $this->conn->prepare($sqlClient);
                    $stmtClient->execute([':user_id' => $id]);
                } elseif ($price === 'gutBetreut') {
                    // Paket 'gutBetreut': call_3, medication_3 löschen
                    $sqlClient = "UPDATE clients 
                                  SET call_3 = NULL, medication_3 = NULL 
                                  WHERE user_id = :user_id";
                    $stmtClient = $this->conn->prepare($sqlClient);
                    $stmtClient->execute([':user_id' => $id]);
                }
            }

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
