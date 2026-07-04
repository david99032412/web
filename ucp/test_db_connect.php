<?php
require_once __DIR__ . '/../config.php';

echo "<pre>";
echo "Config adatok:\n";
echo "Host: " . (defined('DB_HOST') ? DB_HOST : $host ?? 'Nincs definiálva') . "\n";
echo "DB név: " . (defined('DB_NAME') ? DB_NAME : $dbname ?? 'Nincs definiálva') . "\n";
echo "Felhasználó: " . (defined('DB_USER') ? DB_USER : $username ?? 'Nincs definiálva') . "\n";
echo "Jelszó hossza: " . (strlen($password ?? '') > 0 ? 'van' : 'NINCS') . "\n\n";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->query("SELECT 1");
    echo "<h2 style='color: lime;'>Sikeres csatlakozás az adatbázishoz! 🎉</h2>";
    
    // Extra teszt: accounts tábla létezik-e?
    $stmt = $pdo->query("SHOW TABLES LIKE 'accounts'");
    if ($stmt->rowCount() > 0) {
        echo "OK: accounts tábla létezik.\n";
    } else {
        echo "FIGYELEM: accounts tábla NEM létezik!\n";
    }
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Adatbázis csatlakozási hiba:</h2>";
    echo "<pre>";
    echo $e->getMessage() . "\n";
    echo "Kód: " . $e->getCode() . "\n";
    echo "</pre>";
}
echo "</pre>";
?>