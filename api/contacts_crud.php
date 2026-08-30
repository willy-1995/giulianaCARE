<?php
require_once 'database.php';
require_once "cors.php";

class ContactsManager
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
    // CREATE: Neuen Kontakt anlegen
    // ==========================================
    public function createContact($contactData)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "INSERT INTO contacts (
                        client_id, lastname, firstname, address, 
                        tel1, email
                    ) VALUES (
                        :client_id, :lastname, :firstname, :address, 
                        :tel1, :email
                    )";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':client_id' => $contactData['client_id'], // ID des Klienten, dem der Kontakt gehört
                ':lastname'  => $contactData['lastname'] ?? null,
                ':firstname' => $contactData['firstname'] ?? null,
                ':address'   => $contactData['address'] ?? null,
                ':tel1' => $contactData['tel1'] ?? null,
                ':email'     => $contactData['email'] ?? null
            ]);

            $contactId = $this->conn->lastInsertId();

            $this->conn->commit();
            return $contactId;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    // ==========================================
    // READ: Einzelnen Kontakt laden
    // ==========================================
    public function getContact($id)
    {
        $sql = "SELECT * FROM contacts WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // READ: Alle Kontakte eines bestimmten Klienten laden
    // ==========================================
    public function getContactsByClient($clientId)
    {
        $sql = "SELECT * FROM contacts WHERE client_id = :client_id ORDER BY lastname ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':client_id' => $clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllContactsForUser($userId)
    {
        // Diese SQL-Abfrage holt alle Kontakte von Klienten, die dem User gehören
        $query = "SELECT c.* FROM contacts c 
              JOIN clients cl ON c.client_id = cl.id 
              WHERE cl.user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // UPDATE: Kontakt-Daten aktualisieren
    // ==========================================
    public function updateContact($id, $contactData)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "UPDATE contacts SET 
                        lastname = :lastname, 
                        firstname = :firstname, 
                        address = :address, 
                        tel1 = :tel1, 
                        email = :email
                    WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':lastname'  => $contactData['lastname'],
                ':firstname' => $contactData['firstname'],
                ':address'   => $contactData['address'],
                ':tel1' => $contactData['tel1'],
                ':email'     => $contactData['email'],
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
    // DELETE: Kontakt löschen
    // ==========================================
    public function deleteContact($id)
    {
        $sql = "DELETE FROM contacts WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
