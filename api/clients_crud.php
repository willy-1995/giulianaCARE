<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'database.php';
require_once "cors.php";

class ClientsManager
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
    // CREATE: Neuen Client anlegen
    // ==========================================
    public function createClient($clientData)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "INSERT INTO clients (
                        user_id, status, title, lastname, firstname, tel1, tel2, birthday, 
                        language, german_level, address, info, 
                        call_1, medication_1, call_2, medication_2, call_3, medication_3
                    ) VALUES (
                        :user_id, :status, :title, :lastname, :firstname, :tel1, :tel2, :birthday, 
                        :language, :german_level, :address, :info, 
                        :call_1, :medication_1, :call_2, :medication_2, :call_3, :medication_3
                    )";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':user_id'      => $clientData['user_id'], // ID des Users, der den Client erstellt
                ':status'   => $clientData['status'] ?? 'active',
                ':title'     => $clientData['title'] ?? null,
                ':lastname'     => $clientData['lastname'] ?? null,
                ':firstname'    => $clientData['firstname'] ?? null,
                ':birthday'     => $clientData['birthday'] ?? null,
                ':tel1'     => $clientData['tel1'] ?? null,
                ':tel2'     => $clientData['tel2'] ?? null,
                ':language'     => $clientData['language'] ?? null,
                ':german_level' => $clientData['german_level'] ?? null,
                ':address'      => $clientData['address'] ?? null,
                ':info'         => $clientData['info'] ?? null,
                ':call_1'        => $clientData['call_1'] ?? null,
                ':medication_1'  => $clientData['medication_1'] ?? null,
                ':call_2'        => $clientData['call_2'] ?? null,
                ':medication_2'  => $clientData['medication_2'] ?? null,
                ':call_3'        => $clientData['call_3'] ?? null,
                ':medication_3'  => $clientData['medication_3'] ?? null,

            ]);

            $clientId = $this->conn->lastInsertId();

            $this->conn->commit();
            return $clientId;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    // ==========================================
    // READ: Einzelnen Client laden
    // ==========================================
    public function getClient($id)
    {
        $sql = "SELECT * FROM clients WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // READ: Alle Clients eines bestimmten Users laden
    // ==========================================
    public function getClientsByUser($userId)
    {
        $sql = "SELECT * FROM clients WHERE user_id = :user_id ORDER BY lastname ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // UPDATE: Client-Daten aktualisieren
    // ==========================================
    public function updateClient($id, $clientData)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "UPDATE clients SET 
                        status = :status,
                        title = :title,
                        lastname = :lastname, 
                        firstname = :firstname, 
                        tel1 = :tel1,
                        tel2 = :tel2,
                        birthday = :birthday, 
                        language = :language, 
                        german_level = :german_level, 
                        address = :address, 
                        call_1 = :call_1,
                        medication_1 = :medication_1,
                        call_2 = :call_2,
                        medication_2 = :medication_2,
                        call_3 = :call_3,
                        medication_3 = :medication_3
                        info = :info
                       
                    WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':status'       => $clientData['status'] ?? 'active',
                ':title'     => $clientData['title'],
                ':lastname'     => $clientData['lastname'],
                ':firstname'    => $clientData['firstname'],
                ':birthday'     => $clientData['birthday'],
                ':tel1'     => $clientData['tel1'] ?? null,
                ':tel2'     => $clientData['tel2'] ?? null,
                ':language'     => $clientData['language'],
                ':german_level' => $clientData['german_level'],
                ':address'      => $clientData['address'],
                ':info'         => $clientData['info'],
                ':call_1'       => $clientData['call_1'] ?? null,
                ':medication_1' => $clientData['medication_1'] ?? null,
                ':call_2'       => $clientData['call_2'] ?? null,
                ':medication_2' => $clientData['medication_2'] ?? null,
                ':call_3'       => $clientData['call_3'] ?? null,
                ':medication_3' => $clientData['medication_3'] ?? null,
                ':id'           => $id
            ]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }


    // ==========================================
    // UPDATE: Update Status
    // ==========================================
    public function toggleStatus($id)
    {
        // 1. Status direkt in der DB umschalten
        $sql = "UPDATE clients 
                SET status = IF(status = 'active', 'inactive', 'active') 
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);

        // 2. Aktualisierten Status auslesen und zurückgeben
        return $this->getClientStatus($id);
    }

    private function getClientStatus($id)
    {
        $sql = "SELECT status FROM clients WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetchColumn(); // Liefert 'active' oder 'inactive'
    }

    // ==========================================
    // DELETE: Client löschen
    // ==========================================
    public function deleteClient($id)
    {
        // CASCADE in der DB löscht verknüpfte Kontakte/Calls automatisch
        $sql = "DELETE FROM clients WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
