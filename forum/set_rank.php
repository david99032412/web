<?php
/**
 * NorthSideRP - Rang Beállító (Fejlesztői eszköz)
 * Helye: forum/set_rank.php
 * FONTOS: Ha élesben megy a szerver, ezt a fájlt TÖRÖLD KI!
 */

if (file_exists('config.php')) { require 'config.php'; require 'functions.php'; } 
else { require '../config.php'; require '../functions.php'; }

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $level = (int)$_POST['level'];

    // Megpróbáljuk frissíteni (AdminLevel VAGY adminLevel)
    // Ez a kód mindkét oszlopnevet megpróbálja, így biztosan működik
    try {
        // 1. Próba: AdminLevel (Nagy betűs)
        $stmt = $pdo->prepare("UPDATE accounts SET AdminLevel = ? WHERE username = ?");
        $stmt->execute([$level, $username]);
        $count = $stmt->rowCount();
        
        if($count == 0) {
            // 2. Próba: adminLevel (Kis betűs)
            $stmt = $pdo->prepare("UPDATE accounts SET adminLevel = ? WHERE username = ?");
            $stmt->execute([$level, $username]);
            $count = $stmt->rowCount();
        }

        if ($count > 0) {
            $msg = "<div style='color:green; font-weight:bold;'>Siker! $username rangja mostantól: $level</div>";
        } else {
            $msg = "<div style='color:red;'>Nem történt változás (vagy rossz a név, vagy már ez a rangja).</div>";
        }
    } catch (Exception $e) {
        $msg = "<div style='color:red;'>Hiba: " . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Rang Állító</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body { background: #0d1117; color: white; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: sans-serif; }
        .box { background: #161b22; padding: 30px; border: 1px solid #30363d; border-radius: 6px; width: 400px; text-align: center; }
        input, select { width: 100%; padding: 10px; margin: 10px 0; background: #0d1117; border: 1px solid #30363d; color: white; border-radius: 4px; }
        button { background: #238636; color: white; padding: 10px; width: 100%; border: none; cursor: pointer; font-weight: bold; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Rang Beállítása</h2>
        <?= $msg ?>
        <form method="POST">
            <label>Felhasználónév</label>
            <input type="text" name="username" placeholder="pl. Admin" required>

            <label>Kívánt Rang</label>
            <select name="level">
                <option value="0">0 - Játékos</option>
                <option value="1">1 - VIP</option>
                <option value="2">2 - Adminsegéd</option>
                <option value="3">3 - Admin 1</option>
                <option value="4">4 - Admin 2</option>
                <option value="5">5 - Admin 3</option>
                <option value="6">6 - Admin 4</option>
                <option value="7">7 - Admin 5</option>
                <option value="8">8 - Főadmin</option>
                <option value="9">9 - SzuperAdmin</option>
                <option value="10">10 - Fejlesztő</option>
                <option value="11">11 - Tulajdonos</option>
            </select>

            <button type="submit">Beállítás</button>
        </form>
        <br>
        <a href="index.php" style="color: #58a6ff;">Vissza a fórumra</a>
    </div>
</body>
</html>