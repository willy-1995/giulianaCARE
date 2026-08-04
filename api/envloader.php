<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$host = $_ENV['DB_HOST'];
$db = $_ENV['DB_NAME'];
$dbpassword = $_ENV['DB_PASS'];
$charset = $_ENV['DB_CHARSET'];
$dbusername = $_ENV['DB_USER'];

$jwt_key = $_ENV['JWT_SECRET'];

$openAiApiKey = $_ENV['API_KEY_OPENAI'];
