<?php
require_once "envloader.php"; // Lädt die .env Datei

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Wir nutzen DIREKT die Namen aus deiner .env Datei
        // Kein global nötig, keine Umwege über Zwischenvariablen
        $this->host     = $_ENV['DB_HOST'];
        $this->db_name   = $_ENV['DB_NAME'];
        $this->username = $_ENV['DB_USER'];
        $this->password = $_ENV['DB_PASS'];
    }

    public function getConnection() {
        $this->conn = null;
        try {
            // Wir nutzen die oben gesetzten Eigenschaften
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            // Wir werfen den Fehler, damit dein Hauptskript ihn fängt
            throw new Exception("Verbindung fehlgeschlagen: " . $e->getMessage());
        }
        return $this->conn;
    }
}