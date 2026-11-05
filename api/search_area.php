<?php
require_once "cors.php";
require_once "envloader.php";
require_once "database.php";

header("Content-Type: json/application; charset=UTF-8");
header("Content-Type: application/json; charset=UTF-8");

$country = $_GET['country'] ?? '';

if (empty($country)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Land wurde nicht übergeben."]);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Beispiel-Abfrage: Holt alle Gebiete/PLZs für das angegebene Land
    // Passe den Tabellennamen ('sales_areas' oder 'zip_codes') und die Spalten an deine DB an!
    $sql = "SELECT id, area_code FROM sales_areas WHERE country = :country ORDER BY area_code ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':country' => $country]);
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $areas
    ]);
} catch (PDOException $e) {
    error_log("Database Error (Search Area): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Datenbankfehler beim Laden der Gebiete."]);
}
