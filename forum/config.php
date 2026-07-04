<?php
// forum/config.php

// Hibakeresés bekapcsolása
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session indítása, ha még nem megy
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ADATBÁZIS ADATOK
$host = 'localhost';
$dbname = 'web';
$dbuser = 'web';
$dbpass = 'laller';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Adatbázis hiba: " . $e->getMessage());
}
?>