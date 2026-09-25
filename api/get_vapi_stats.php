<?php
require_once "cors.php";
require_once "envloader.php";



// Deinen Vapi Private/Secret Key aus der Umgebungsvariable oder Direktzuweisung laden
$vapiApiKey = getenv("VAPI_PRIVATE_KEY") ?: "DEIN_VAPI_PRIVATE_API_KEY";

// Lädt die letzten Anrufe von Vapi (Limit kann angepasst werden)
$url = "https://api.vapi.ai/call?limit=1000";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $vapiApiKey,
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $rawCalls = json_decode($response, true);

    $totalSeconds = 0;
    $totalCost = 0.0;
    $callList = [];

    if (is_array($rawCalls)) {
        foreach ($rawCalls as $call) {
            $durationSeconds = $call['duration'] ?? 0;
            $cost = $call['cost'] ?? 0.0;
            $createdAt = $call['createdAt'] ?? null;

            // Gesamtsummen aufrechnen
            $totalSeconds += $durationSeconds;
            $totalCost += $cost;

            // Einzelne Anrufe für die Detailübersicht aufbereiten
            $callList[] = [
                "id" => $call['id'] ?? null,
                "duration_seconds" => $durationSeconds,
                "duration_minutes" => round($durationSeconds / 60, 2),
                "cost" => round($cost, 4),
                "created_at" => $createdAt,
                "status" => $call['status'] ?? null,
                "ended_reason" => $call['endedReason'] ?? null
            ];
        }
    }

    $totalCallsCount = count($callList);
    $totalMinutes = ceil($totalSeconds / 60);

    // DURCHSCHNITTSWERTE BERECHNEN (Schutz vor Division durch 0)
    $avgSeconds = $totalCallsCount > 0 ? round($totalSeconds / $totalCallsCount, 2) : 0;
    $avgMinutes = $totalCallsCount > 0 ? round(($totalSeconds / 60) / $totalCallsCount, 2) : 0;
    $avgCost = $totalCallsCount > 0 ? round($totalCost / $totalCallsCount, 4) : 0.0;

    echo json_encode([
        "success" => true,

        // Gesamtsummen
        "totalMinutes" => $totalMinutes,
        "totalSeconds" => $totalSeconds,
        "totalCost" => round($totalCost, 2),
        "totalCallsCount" => $totalCallsCount,

        // Durchschnittswerte pro Anruf
        "average" => [
            "duration_seconds" => $avgSeconds,
            "duration_minutes" => $avgMinutes,
            "cost" => $avgCost
        ],

        // Liste aller Anrufe
        "calls" => $callList
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Fehler beim Abrufen der Vapi-Statistiken",
        "raw" => json_decode($response)
    ]);
}
