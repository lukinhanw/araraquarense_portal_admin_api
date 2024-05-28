<?php
require_once 'vendor/autoload.php';

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$DB_HOST = $_ENV['DB_HOST'];
$DB_PORT = $_ENV['DB_PORT'];
$DB_USER = $_ENV['DB_USER'];
$DB_PASS = $_ENV['DB_PASS'];
$DB_DATABASE = $_ENV['DB_DATABASE'];

$accessToken = $_ENV['TOKEN_INSTAGRAM'];

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS, PUT");

$db = new PDO('mysql:host='.$DB_HOST.';dbname='.$DB_DATABASE.';charset=utf8', ''.$DB_USER.'', ''.$DB_PASS.'');

?>