<?php
// config.php
session_start();

// Hibakeresés bekapcsolva – most hagyd így
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Discord webhook (ha nincs, hagyd üresen)
define('DISCORD_WEBHOOK', '');

// Adatbázis
$host = 'localhost';
$dbname = 'web';
$dbuser = 'web';
$dbpass = 'laller';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Adatbázis hiba (config.php): " . htmlspecialchars($e->getMessage()));
}
define('RCON_MASTER_CODE', 'laller');  // Ezt soha ne oszd meg senkivel!
// Weboldal hibák naplózása egy külön fájlba (weberrors.log)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/weberrors.log');
?>