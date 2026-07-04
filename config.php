<?php
session_start();

// Hibakeresés BEKAPCSOLVA
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- BEÁLLÍTÁSOK ---

// Főoldali nagy kép és gomb megjelenítése (true = BE, false = KI)
define('SHOW_HERO_SECTION', false);

// Fórum linkje (Ha majd telepítesz igazi fórumot, ide írd a címét)
// Ha üresen hagyod, akkor a forum.php egy "Karbantartás" oldalt mutat.
define('FORUM_URL', 'https://northsiderp.hu/forum/index.php'); 

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
    echo "<div style='background:red;color:white;padding:20px;'>ADATBÁZIS HIBA: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}
?>