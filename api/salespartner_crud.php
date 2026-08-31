<?php
require_once 'database.php';

class SalesPartnerManager
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
    // CREATE: Vertriebspartner & Gebiete anlegen
    // ==========================================
    public function createSalesPartner($partnerData)
    {
        try {
            $this->conn->beginTransaction();

            // 1. E-Mail Eindeutigkeit prüfen
            $checkEmail = $this->conn->prepare("SELECT id FROM sales_partners WHERE email = :email LIMIT 1");
            $checkEmail->execute([':email' => trim($partnerData['email'])]);
            if ($checkEmail->fetch()) {
                throw new Exception("Diese E-Mail-Adresse ist bereits registriert.");
            }

            // 2. Exklusivität der Vertriebsgebiete prüfen
            $salesAreas = $partnerData['sales_areas'] ?? [];
            if (!is_array($salesAreas) || count($salesAreas) === 0) {
                throw new Exception("Bitte wählen Sie mindestens ein Vertriebsgebiet aus.");
            }

            $checkArea = $this->conn->prepare("
                SELECT area_code 
                FROM sales_partner_areas 
                WHERE country = :country AND area_code = :area_code 
                LIMIT 1
            ");

            foreach ($salesAreas as $areaCode) {
                $checkArea->execute([
                    ':country'   => trim($partnerData['country']),
                    ':area_code' => trim($areaCode)
                ]);
                if ($checkArea->fetch()) {
                    throw new Exception("Das Gebiet '{$areaCode}' ist in {$partnerData['country']} bereits vergeben.");
                }
            }

            // 3. Partner in `sales_partners` anlegen
            $sql = "INSERT INTO sales_partners (firstname, lastname, birthday, country, street, tel, email, password, status) 
                    VALUES (:firstname, :lastname, :birthday, :country, :street, :tel, :email, :password, :status)";

            $stmt = $this->conn->prepare($sql);
            $hashedPassword = password_hash($partnerData['password'], PASSWORD_BCRYPT);

            $stmt->execute([
                ':firstname' => trim($partnerData['firstname']),
                ':lastname'  => trim($partnerData['lastname']),
                ':birthday'  => $partnerData['birthday'],
                ':country'   => trim($partnerData['country']),
                ':street'    => trim($partnerData['street']),
                ':tel'       => trim($partnerData['tel']),
                ':email'     => trim($partnerData['email']),
                ':password'  => $hashedPassword,
                ':status'    => $partnerData['status'] ?? 'pending',
            ]);

            $partnerId = $this->conn->lastInsertId();

            // 4. Gebiete in `sales_partner_areas` anlegen
            $stmtArea = $this->conn->prepare("
                INSERT INTO sales_partner_areas (sales_partner_id, country, area_code)
                VALUES (:partner_id, :country, :area_code)
            ");

            foreach ($salesAreas as $areaCode) {
                $stmtArea->execute([
                    ':partner_id' => $partnerId,
                    ':country'    => trim($partnerData['country']),
                    ':area_code'  => trim($areaCode)
                ]);
            }

            $this->conn->commit();
            return $partnerId;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    // ==========================================
    // READ: Einzelnen Partner mit Gebieten laden
    // ==========================================
    public function getSalesPartner($id)
    {
        $sql = "SELECT id, firstname, lastname, birthday, country, street, tel, email, status, created_at, updated_at 
                FROM sales_partners WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $partner = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$partner) return null;

        // Zugewiesene Vertriebsgebiete nachladen
        $areaSql = "SELECT area_code FROM sales_partner_areas WHERE sales_partner_id = :partner_id";
        $areaStmt = $this->conn->prepare($areaSql);
        $areaStmt->execute([':partner_id' => $id]);
        $partner['sales_areas'] = $areaStmt->fetchAll(PDO::FETCH_COLUMN);

        return $partner;
    }

    // ==========================================
    // LOGIN: Authentifizierung
    // ==========================================
    public function login($email, $password)
    {
        $sql = "SELECT * FROM sales_partners WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        $partner = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($partner && password_verify($password, $partner['password'])) {
            unset($partner['password']); // Passwort aus Resultat entfernen
            return $partner;
        }
        return false;
    }

    // ==========================================
    // UPDATE: Stammdaten & Gebiete aktualisieren
    // ==========================================
    public function updateSalesPartner($id, $partnerData)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "UPDATE sales_partners 
                    SET firstname = :firstname, lastname = :lastname, birthday = :birthday, 
                        country = :country, street = :street, tel = :tel, email = :email, status = :status
                    WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':firstname' => trim($partnerData['firstname']),
                ':lastname'  => trim($partnerData['lastname']),
                ':birthday'  => $partnerData['birthday'],
                ':country'   => trim($partnerData['country']),
                ':street'    => trim($partnerData['street']),
                ':tel'       => trim($partnerData['tel']),
                ':email'     => trim($partnerData['email']),
                ':status'    => $partnerData['status'] ?? 'pending',
                ':id'        => $id
            ]);

            // Falls neue Gebiete übergeben wurden, diese aktualisieren
            if (isset($partnerData['sales_areas']) && is_array($partnerData['sales_areas'])) {
                // Altzuweisungen löschen
                $deleteAreas = $this->conn->prepare("DELETE FROM sales_partner_areas WHERE sales_partner_id = :partner_id");
                $deleteAreas->execute([':partner_id' => $id]);

                // Neue Gebiete eintragen
                $insertArea = $this->conn->prepare("
                    INSERT INTO sales_partner_areas (sales_partner_id, country, area_code)
                    VALUES (:partner_id, :country, :area_code)
                ");

                foreach ($partnerData['sales_areas'] as $areaCode) {
                    $insertArea->execute([
                        ':partner_id' => $id,
                        ':country'    => trim($partnerData['country']),
                        ':area_code'  => trim($areaCode)
                    ]);
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
    // DELETE: Vertriebspartner löschen
    // ==========================================
    public function deleteSalesPartner($id)
    {
        // Dank "ON DELETE CASCADE" in der DB werden die Zuweisungen
        // in sales_partner_areas automatisch mitgelöscht.
        $sql = "DELETE FROM sales_partners WHERE id = :id";
        return $this->conn->prepare($sql)->execute([':id' => $id]);
    }
}
