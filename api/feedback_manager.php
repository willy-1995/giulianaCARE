<?php
require_once 'cors.php';
require_once 'database.php';

// JSON Payload einlesen
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Keine Daten übermittelt."]);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $sql = "INSERT INTO feedback (
                user_id,
                first_visit_understood_service,
                how_it_works_understood,
                information_clear,
                data_security_clear,
                registration_easy,
                client_setup_easy,
                dashboard_clear,
                settings_found_quickly,
                ai_call_on_time,
                ai_fluent_conversation,
                ai_response_speed,
                ai_retry_attempts_done,
                ai_emergency_contacts_called,
                comments
            ) VALUES (
                :user_id,
                :first_visit_understood_service,
                :how_it_works_understood,
                :information_clear,
                :data_security_clear,
                :registration_easy,
                :client_setup_easy,
                :dashboard_clear,
                :settings_found_quickly,
                :ai_call_on_time,
                :ai_fluent_conversation,
                :ai_response_speed,
                :ai_retry_attempts_done,
                :ai_emergency_contacts_called,
                :comments
            )";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':user_id'                      => $data['userId'] ?? null,
        ':first_visit_understood_service' => (int) $data['firstVisitUnderstoodService'],
        ':how_it_works_understood'      => (int) $data['howItWorksUnderstood'],
        ':information_clear'            => (int) $data['informationClear'],
        ':data_security_clear'          => (int) $data['dataSecurityClear'],
        ':registration_easy'            => (int) $data['registrationEasy'],
        ':client_setup_easy'            => (int) $data['clientSetupEasy'],
        ':dashboard_clear'              => (int) $data['dashboardClear'],
        ':settings_found_quickly'       => (int) $data['settingsFoundQuickly'],
        ':ai_call_on_time'              => (int) $data['aiCallOnTime'],
        ':ai_fluent_conversation'       => (int) $data['aiFluentConversation'],
        ':ai_response_speed'            => $data['aiResponseSpeed'],
        ':ai_retry_attempts_done'       => (int) $data['aiRetryAttemptsDone'],
        ':ai_emergency_contacts_called' => (int) $data['aiEmergencyContactsCalled'],
        ':comments'                     => $data['comments'] ?? null
    ]);

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Vielen Dank! Dein Feedback wurde erfolgreich gespeichert."
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Datenbankfehler: " . $e->getMessage()
    ]);
}
