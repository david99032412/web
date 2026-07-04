<?php
require_once 'config.php';
require_once 'functions.php';

$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');
    
    // JAVÍTVA: Csak a létező 'email' oszlopot keressük!
    $stmt = $pdo->prepare("SELECT accountId FROM accounts WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));
        
        $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")->execute([$email, $token, $expires]);
        
        // VÁLLALKOZÓI MEGJEGYZÉS: Itt küldenénk ki az emailt. 
        // Ha nincs SMTP beállítva, akkor jelenleg csak kiírjuk a linket teszteléshez.
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/ucp/reset.php?token=" . $token;
        $message = "A jelszóvisszaállító linket elküldtük! (Teszt link: <a href='$resetLink'>Kattints ide</a>)";
    } else {
        $error = "Nem található fiók ezzel az e-mail címmel.";
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Elfelejtett jelszó</title>
    <script src="https://kit.fontawesome.com/122db0fdde.js" crossorigin="anonymous"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap');
        body { background: #1a1b1f; color: #fff; font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: rgb(33, 35, 40); padding: 40px; border-radius: 12px; width: 400px; text-align: center; border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .box h2 { font-weight: 900; margin-bottom: 20px; }
        input { width: 100%; padding: 15px; margin: 15px 0; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 8px; box-sizing: border-box; outline: none; }
        input:focus { border-color: #0d6efd; }
        button { width: 100%; padding: 15px; background: #0d6efd; border: none; color: #fff; border-radius: 8px; font-weight: bold; cursor: pointer; text-transform: uppercase; margin-top: 10px;}
        button:hover { background: #0b5ed7; }
    </style>
</head>
<body>
    <div class="box">
        <h2><i class="fa-solid fa-unlock-keyhole" style="color:#0d6efd;"></i> Elfelejtett jelszó</h2>
        <?php if($message) echo "<p style='color: #2ecc71; font-size:14px; background:rgba(46,204,113,0.1); padding:10px; border-radius:6px;'>$message</p>"; ?>
        <?php if($error) echo "<p style='color: #ef4444; font-size:14px; background:rgba(239,68,68,0.1); padding:10px; border-radius:6px;'>$error</p>"; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Add meg a regisztrált e-mail címed" required>
            <button type="submit">Visszaállító link kérése</button>
        </form>
        <p style="margin-top: 20px;"><a href="login.php" style="color: #9ca3af; text-decoration: none; font-size: 13px;">Vissza a belépéshez</a></p>
    </div>
</body>
</html>