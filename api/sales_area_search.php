<?php
require_once "cors.php";
require_once "database.php";

header('Content-Type: application/json; charset=utf-8');

$country = trim($_GET['country'] ?? '');
$query   = trim($_GET['q'] ?? '');

// Wenn Land oder Suchbegriff fehlen, leeres Array zurückgeben
if (empty($country) || empty($query)) {
    echo json_encode([]);
    exit;
}

$dbInstance = new Database();
$db = $dbInstance->getConnection();

// PLZ/Vorwahl suchen, die mit dem Suchbegriff beginnt
$stmt = $db->prepare("
    SELECT id, area_code 
    FROM sales_areas 
    WHERE country = :country AND area_code LIKE :query 
    ORDER BY area_code ASC 
    LIMIT 10
");

$stmt->execute([
    'country' => $country,
    'query'   => $query . '%' // Sucht alles, was mit $query beginnt (z.B. 53%)
]);

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);
