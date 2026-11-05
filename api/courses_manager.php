<?php
require_once "cors.php";
require_once "envloader.php";
require_once "courses_crud.php";
require_once __DIR__ . "/auth/jwt.php";

header("Content-Type: application/json; charset=UTF-8");

$input = json_decode(file_get_contents("php://input"), true);
$method = $_SERVER['REQUEST_METHOD'];
$coursesManager = new CoursesManager();

try {
    $currentUserId = getUserIdFromToken();

    switch ($method) {
        case 'GET':
            if (isset($_GET['course_id'])) {
                // Teilnehmer eines spezifischen Kurses laden
                $participants = $coursesManager->getParticipantsByCourse((int)$_GET['course_id']);
                echo json_encode(["success" => true, "data" => $participants]);
            } else {
                // Alle Kurse des Users laden
                $courses = $coursesManager->getCoursesByUser($currentUserId);
                echo json_encode(["success" => true, "data" => $courses]);
            }
            break;

        case 'POST':
            // Prüfen ob es sich um eine Verschiebe-Aktion handelt
            if (isset($input['action']) && $input['action'] === 'move_participant') {
                if (empty($input['participant_id']) || empty($input['new_course_id'])) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Teilnehmer-ID oder Zielkurs fehlt."]);
                    exit;
                }
                $coursesManager->moveParticipant($input['participant_id'], $input['new_course_id']);
                echo json_encode(["success" => true, "message" => "Teilnehmer erfolgreich verschoben."]);
                break;
            }

            // Normaler Kurs-Create
            $courseId = $coursesManager->createCourse($input);
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Kurs erstellt.", "courseId" => $courseId]);
            break;

        case 'PUT':
            if (empty($input['id'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Kurs-ID fehlt."]);
                exit;
            }
            $coursesManager->updateCourse($input['id'], $input);
            echo json_encode(["success" => true, "message" => "Kurs aktualisiert."]);
            break;

        case 'DELETE':
            $courseId = $_GET['id'] ?? $input['id'] ?? null;
            if (!$courseId) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID fehlt."]);
                exit;
            }
            $coursesManager->deleteCourse($courseId);
            echo json_encode(["success" => true, "message" => "Kurs gelöscht."]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Methode nicht erlaubt."]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
