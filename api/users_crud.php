<?php
require_once 'database.php';
require_once 'mailer.php';

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

    /**
     * E-Mail bei Registrierung senden (Nachdem Create & Stripe erfolgreich waren aufrufen)
     */
    public function sendRegistrationEmail($email)
    {
        $subject = "Willkommen bei Giuliana Care!";
        $content = "
            <h2>Herzlich willkommen!</br></br></h2>
            <p>Deine Registrierung und dein Abonnement bei Giuliana Care wurden erfolgreich abgeschlossen.</p>
            <p>Du kannst dich ab sofort unter <a href='https://giuliana-care.de/login'>giuliana-care.de/login</a> in deinem Dashboard einloggen.</br></br></p>
            <p>Herzliche Grüße<br>Dein Giuliana Care Team</p>
        ";
        return sendMail($email, $subject, $content);
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
    // READ: Nutzer per E-Mail laden (für Registrierungs-Check)
    // ==========================================
    public function getUserByEmail($email)
    {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
    // ==========================================
    // UPDATE: Basis-Daten & optional Passwort aktualisieren
    // ==========================================
    public function updateUser($id, $userData)
    {
        try {
            $this->conn->beginTransaction();

            $passwordChanged = false;

            // 1. Wenn ein neues Passwort angegeben wurde, prüfen wir das aktuelle Passwort
            if (!empty($userData['new_password'])) {
                if (empty($userData['current_password'])) {
                    throw new Exception("Bitte gib dein aktuelles Passwort ein.");
                }

                // Aktuellen Passwort-Hash & E-Mail aus der DB holen
                $sqlPass = "SELECT email, password FROM users WHERE id = :id";
                $stmtPass = $this->conn->prepare($sqlPass);
                $stmtPass->execute([':id' => $id]);
                $currentUser = $stmtPass->fetch(PDO::FETCH_ASSOC);

                // Altes Passwort abgleichen
                if (!$currentUser || !password_verify($userData['current_password'], $currentUser['password'])) {
                    throw new Exception("Das aktuelle Passwort ist nicht korrekt.");
                }

                // Neues Passwort hashen und abspeichern
                $newHashedPassword = password_hash($userData['new_password'], PASSWORD_BCRYPT);
                $sqlUpdatePass = "UPDATE users SET password = :password WHERE id = :id";
                $stmtUpdatePass = $this->conn->prepare($sqlUpdatePass);
                $stmtUpdatePass->execute([
                    ':password' => $newHashedPassword,
                    ':id'       => $id
                ]);

                $passwordChanged = true;
            }

            // 2. Allgemeine Benutzerdaten aktualisieren
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

            // 3. Pflege-Anrufe bereinigen bei Paketänderung
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

            // E-Mail nach erfolgreichem Commit bei Passwortänderung senden
            if ($passwordChanged && !empty($userData['email'])) {
                $subject = "Dein Passwort wurde geändert - Giuliana Care";
                $content = "
                    <h2>Passwortänderung</h2>
                    <p>Hallo,</p>
                    <p>dein Passwort für dein Giuliana Care Konto wurde erfolgreich geändert.</p>
                    <p>Falls du diese Änderung nicht vorgenommen hast, kontaktiere bitte umgehend unseren Support.</p>
                    <p>Herzliche Grüße<br>Dein Giuliana Care Team</p>
                ";
                sendMail($userData['email'], $subject, $content);
            }

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
        // 1. User-Daten vor dem Löschen laden (für E-Mail und created_at)
        $user = $this->getUser($id);
        if (!$user) return false;

        try {
            $this->conn->beginTransaction();

            // 2. Metrik für die durchschnittliche Laufzeit speichern (falls created_at existiert)
            if (!empty($user['created_at'])) {
                $stmtMetric = $this->conn->prepare("INSERT INTO subscription_metrics (created_at, deleted_at) VALUES (?, NOW())");
                $stmtMetric->execute([$user['created_at']]);
            }

            // 3. User physisch löschen (wie gewohnt)
            $sql = "DELETE FROM users WHERE id = :id";
            $stmtDel = $this->conn->prepare($sql);
            $stmtDel->execute([':id' => $id]);

            $this->conn->commit();

            // 4. E-Mail nach erfolgreichem Löschen versenden
            if (!empty($user['email'])) {
                $subject = "Account gelöscht - giulianaCARE";
                $content = "
                <h2>Account-Löschung</br></br></h2>
                <p>Hallo,</br></br></p>
                <p>dein Account und alle zugehörigen Daten wurden erfolgreich gelöscht.</br></p>
                <p>Wir bedauern es sehr, dich als Kunden zu verlieren. Alles Gute für deine Zukunft!</br></br></p>
                <p>Herzliche Grüße<br></br>dein Team von giulianaCARE/p>
            ";
                sendMail($user['email'], $subject, $content);
            }

            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log("Error during user deletion for ID $id: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Kündigt das Stripe-Abo zum Ende des aktuellen Abrechnungszeitraums (bzw. der Trial).
     * Der Service bleibt bis dahin voll nutzbar!
     */
    // ===========================================
    //CANCEL SUBSCRIPTION
    //============================================
    public function cancelSubscriptionAtPeriodEnd($userId)
    {
        $user = $this->getUser($userId);

        if (!$user || empty($user['stripe_subscription_id'])) {
            throw new Exception("Keine aktive Subscription für diesen Nutzer gefunden.");
        }

        $stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
        \Stripe\Stripe::setApiKey($stripeSecretKey);

        // Stripe anweisen, das Abo zum Periodenende nicht mehr zu verlängern
        $subscription = \Stripe\Subscription::update($user['stripe_subscription_id'], [
            'cancel_at_period_end' => true,
        ]);

        // Status in der lokalen Datenbank vermerken
        $sql = "UPDATE users SET subscription_status = 'cancel_pending' WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $userId]);

        return $subscription;
    }

    /**
     * Kündigt das Stripe-Abo zu einem spezifischen Zeitpunkt (z.B. gemäß AGB 14 Tage zum Monatsende).
     * Der Service bleibt bis zu diesem Zeitpunkt voll nutzbar.
     */
    public function cancelSubscriptionAtTimestamp($userId, $cancelTimestamp)
    {
        $user = $this->getUser($userId);

        if (!$user || empty($user['stripe_subscription_id'])) {
            throw new Exception("Keine aktive Subscription für diesen Nutzer gefunden.");
        }

        $stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
        \Stripe\Stripe::setApiKey($stripeSecretKey);

        // Stripe anweisen, das Abo genau zum berechneten Timestamp zu beenden
        $subscription = \Stripe\Subscription::update($user['stripe_subscription_id'], [
            'cancel_at' => $cancelTimestamp,
        ]);

        // Status in der lokalen Datenbank auf 'cancel_pending' setzen
        $sql = "UPDATE users SET subscription_status = 'cancel_pending' WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $userId]);

        return $subscription;
    }
}
