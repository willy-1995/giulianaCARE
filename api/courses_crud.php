<?php
class CoursesManager
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // ==========================================
    // READ: Alle Kurse eines Users laden (inkl. Standort & Übungsleiter)
    // ==========================================
    public function getCoursesByUser($userId)
    {
        $sql = "SELECT c.*, l.name AS location_name, i.name AS instructor_name,
                (SELECT COUNT(*) FROM participants p WHERE p.course_id = c.id) AS current_participants_count
                FROM courses c
                JOIN locations l ON c.location_id = l.id
                LEFT JOIN instructors i ON c.instructor_id = i.id
                WHERE l.user_id = :user_id
                ORDER BY l.name ASC, c.name ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCourse($id)
    {
        $sql = "SELECT c.*, l.user_id FROM courses c 
                JOIN locations l ON c.location_id = l.id 
                WHERE c.id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // CREATE: Neuen Kurs anlegen
    // ==========================================
    public function createCourse($data)
    {
        $sql = "INSERT INTO courses (location_id, instructor_id, name, category, max_participants) 
                VALUES (:location_id, :instructor_id, :name, :category, :max_participants)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':location_id'      => $data['location_id'],
            ':instructor_id'    => $data['instructor_id'] ?? null,
            ':name'             => $data['name'],
            ':category'         => $data['category'],
            ':max_participants' => $data['max_participants'] ?? 20
        ]);
        return $this->conn->lastInsertId();
    }

    // ==========================================
    // UPDATE: Kurs aktualisieren
    // ==========================================
    public function updateCourse($id, $data)
    {
        $sql = "UPDATE courses SET 
                location_id = :location_id, 
                instructor_id = :instructor_id, 
                name = :name, 
                category = :category, 
                max_participants = :max_participants 
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':location_id'      => $data['location_id'],
            ':instructor_id'    => $data['instructor_id'] ?? null,
            ':name'             => $data['name'],
            ':category'         => $data['category'],
            ':max_participants' => $data['max_participants'],
            ':id'               => $id
        ]);
    }

    // ==========================================
    // DELETE: Kurs löschen
    // ==========================================
    public function deleteCourse($id)
    {
        $sql = "DELETE FROM courses WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    // ==========================================
    // SPEZIAL: Teilnehmer verschieben (Kurswechsel)
    // ==========================================
    public function moveParticipant($participantId, $newCourseId)
    {
        $sql = "UPDATE participants SET course_id = :new_course_id WHERE id = :participant_id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':new_course_id'   => $newCourseId,
            ':participant_id'  => $participantId
        ]);
    }

    // Teilnehmer eines Kurses laden
    public function getParticipantsByCourse($courseId)
    {
        $sql = "SELECT * FROM participants WHERE course_id = :course_id ORDER BY lastname ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':course_id' => $courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
