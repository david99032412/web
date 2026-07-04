<?php
session_start();

// Hibakeresés BEKAPCSOLVA – látod a hibákat
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
    echo "<div style='background:red;color:white;padding:20px;'>";
    echo "ADATBÁZIS HIBA (config.php):<br>" . htmlspecialchars($e->getMessage());
    echo "<br><br>Ellenőrizd: host, dbname, user, pass, jogosultságok!";
    echo "</div>";
    exit;
}
?>