<?php
require_once 'config.php';
require_once 'functions.php';

$token = $_GET['token'] ?? '';
$message = ''; $error = '';

if (empty($token)) die("<div style='background:#1a1b1f; color:#ef4444; padding:40px; text-align:center; font-family:sans-serif;'><h2>Érvénytelen kérelem.</h2></div>");

$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) die("<div style='background:#1a1b1f; color:#ef4444; padding:40px; text-align:center; font-family:sans-serif;'><h2>A link lejárt vagy érvénytelen.</h2><a href='forgot.php' style='color:#0d6efd;'>Kérj újat!</a></div>");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm'];
    
    if ($pass !== $confirm) { 
        $error = "A két jelszó nem egyezik meg."; 
    }
    elseif (strlen($pass) < 6) { 
        $error = "A jelszónak legalább 6 karakternek kell lennie!"; 
    }
    else {
        // JAVÍTVA: Csak 'email' oszlop alapján frissítünk
        $hashed = md5($pass);
        $pdo->prepare("UPDATE accounts SET password = ? WHERE email = ?")->execute([$hashed, $reset['email']]);
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$reset['email']]);
        $message = "Sikeres jelszócsere! Most már bejelentkezhetsz.";
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Új jelszó megadása</title>
    <script src="https://kit.fontawesome.com/122db0fdde.js" crossorigin="anonymous"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap');
        body { background: #1a1b1f; color: #fff; font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: rgb(33, 35, 40); padding: 40px; border-radius: 12px; width: 400px; text-align: center; border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .box h2 { font-weight: 900; margin-bottom: 20px; color:#2ecc71;}
        input { width: 100%; padding: 15px; margin: 10px 0; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 8px; box-sizing: border-box; outline: none; }
        input:focus { border-color: #2ecc71; }
        button { width: 100%; padding: 15px; background: #2ecc71; border: none; color: #fff; border-radius: 8px; font-weight: bold; cursor: pointer; text-transform: uppercase; margin-top: 10px;}
        button:hover { background: #27ae60; }
    </style>
</head>
<body>
    <div class="box">
        <h2><i class="fa-solid fa-key"></i> Új Jelszó</h2>
        <?php if($message): ?>
            <p style='color: #2ecc71; font-weight:bold;'><?= $message ?></p>
            <a href="login.php" style="display:block; margin-top:20px; color:#fff; text-decoration:none; background:#0d6efd; padding:10px; border-radius:6px;">Ugrás a Belépéshez</a>
        <?php else: ?>
            <?php if($error) echo "<p style='color: #ef4444; font-size:14px; background:rgba(239,68,68,0.1); padding:10px; border-radius:6px;'>$error</p>"; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Új jelszó" required>
                <input type="password" name="confirm" placeholder="Új jelszó megerősítése" required>
                <button type="submit">Jelszó Mentése</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>