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
            $this->conn->beginTransaction();

            $sql = "INSERT INTO users (email, password, price, country, area_code, agb_accepted) 
                    VALUES (:email, :password, :price, :country, :area_code, :agb_accepted)";
            $stmt = $this->conn->prepare($sql);
            $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);

            $stmt->execute([
                ':email'        => $userData['email'],
                ':password'     => $hashedPassword,
                ':price'        => $userData['price'] ?? null,
                ':country'      => $userData['country'] ?? null,
                ':area_code'    => $userData['area_code'] ?? null,
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
    // [GEÄNDERT]: Stripe-Spalten werden jetzt mit ausgelesen!
    // ==========================================
    public function getUser($id)
    {
        $sql = "SELECT id, email, price, country, area_code, 
                       stripe_customer_id, stripe_subscription_id, subscription_status, 
                       created_at 
                FROM users 
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return null;

        return $user;
    }

    // ==========================================
    // LOGIN: Authentifizierung
    // (Unverändert - `SELECT *` liefert bereits alle Spalten inkl. Stripe zurück)
    // ==========================================
    public function login($email, $password)
    {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            return $user;
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

            if (isset($userData['price'])) {
                $price = $userData['price'];

                if ($price === 'sicherheit') {
                    $sqlClient = "UPDATE clients 
                                  SET call_2 = NULL, medication_2 = NULL, call_3 = NULL, medication_3 = NULL 
                                  WHERE user_id = :user_id";
                    $stmtClient = $this->conn->prepare($sqlClient);
                    $stmtClient->execute([':user_id' => $id]);
                } elseif ($price === 'gutBetreut') {
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
    // [NEU] STRIPE: Abo-Daten aktualisieren (für Webhook & Checkout)
    // ==========================================
    public function updateStripeSubscription($userId, $customerId, $subscriptionId, $status = 'active')
    {
        $sql = "UPDATE users 
                SET stripe_customer_id = :cust, 
                    stripe_subscription_id = :sub, 
                    subscription_status = :status 
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':cust'   => $customerId,
            ':sub'    => $subscriptionId,
            ':status' => $status,
            ':id'     => $userId
        ]);
    }

    // ==========================================
    // [NEU] STRIPE: Status per Subscription-ID aktualisieren (für Webhook)
    // ==========================================
    public function updateSubscriptionStatusBySubId($subscriptionId, $status)
    {
        $sql = "UPDATE users 
                SET subscription_status = :status 
                WHERE stripe_subscription_id = :sub";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':sub'    => $subscriptionId
        ]);
    }

    // ==========================================
    // DELETE: Nutzer löschen
    // ==========================================
    public function deleteUser($id)
    {
        $sql = "DELETE FROM users WHERE id = :id";
        return $this->conn->prepare($sql)->execute([':id' => $id]);
    }
}
