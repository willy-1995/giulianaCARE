<?php
require_once __DIR__ . '/database.php';

class MetricsManager
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getDashboardMetrics()
    {
        $metrics = [];

        // 1. Gesamtzahl User
        $stmt = $this->conn->query("SELECT COUNT(*) FROM users");
        $metrics['total_users'] = (int)$stmt->fetchColumn();

        // 2. Registrierungen im aktuellen/gewählten Monat (Beispiel: Aktueller Monat)
        $stmt = $this->conn->query("SELECT COUNT(*) FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        $metrics['users_this_month'] = (int)$stmt->fetchColumn();

        // 3. Registrierungen im aktuellen Jahr (z.B. 2026)
        $stmt = $this->conn->query("SELECT COUNT(*) FROM users WHERE YEAR(created_at) = YEAR(CURRENT_DATE())");
        $metrics['users_this_year'] = (int)$stmt->fetchColumn();

        // 4. Durchschnittliche Abo-Laufzeit (aus unserer subscription_metrics Tabelle)
        try {
            $stmt = $this->conn->query("SELECT AVG(duration_in_days) FROM subscription_metrics");
            $metrics['avg_subscription_days'] = round((float)$stmt->fetchColumn(), 1);
        } catch (Exception $e) {
            $metrics['avg_subscription_days'] = 0;
        }

        // 5. Welches Paket wie oft gebucht wurde
        $stmt = $this->conn->query("SELECT price, COUNT(*) as count FROM users GROUP BY price");
        $metrics['packages_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. Gesamtzahl Clients
        $stmt = $this->conn->query("SELECT COUNT(*) FROM clients");
        $metrics['total_clients'] = (int)$stmt->fetchColumn();

        // 7. Gesamtzahl Contacts
        $stmt = $this->conn->query("SELECT COUNT(*) FROM contacts");
        $metrics['total_contacts'] = (int)$stmt->fetchColumn();

        // 8. Durchschnittliche Anzahl Contacts pro Client
        $totalClients = $metrics['total_clients'] > 0 ? $metrics['total_clients'] : 1;
        $metrics['avg_contacts_per_client'] = round($metrics['total_contacts'] / $totalClients, 2);

        // 9. Postleitzahlen-Verteilung (area_code) gruppiert mit Mehrfachzählung
        $stmt = $this->conn->query("SELECT area_code, COUNT(*) as count FROM users WHERE area_code IS NOT NULL AND area_code != '' GROUP BY area_code HAVING count > 0 ORDER BY count DESC");
        $metrics['postal_codes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 10. Logs & Fehlermeldungen (aus error_log Datei oder DB, hier aus Log-Datei ausgelesen)
        $metrics['server_logs'] = $this->readServerLogs();

        return $metrics;
    }

    private function readServerLogs()
    {
        $logFile = __DIR__ . '/my_php_errors.log';
        if (!file_exists($logFile)) {
            return ["Keine Log-Datei gefunden."];
        }

        // Die letzten 50 Zeilen der Log-Datei einlesen
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return [];

        $recentLines = array_slice($lines, -50);
        return array_reverse($recentLines); // Neueste zuerst
    }
}
