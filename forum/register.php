<?php
require_once 'config.php';
require_once 'functions.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $email = clean($_POST['email'] ?? '');

    if (empty($username) || empty($password) || empty($confirm)) {
        $errors[] = "Minden mező kitöltése kötelező.";
    } elseif ($password !== $confirm) {
        $errors[] = "A megadott jelszavak nem egyeznek.";
    } else {
        // 1. Felhasználónév ellenőrzése
        $stmt = $pdo->prepare("SELECT accountId FROM accounts WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "Ez a felhasználónév már foglalt.";
        } else {
            // 2. Email cím ellenőrzése
            if (!empty($email)) {
                $stmtEmail = $pdo->prepare("SELECT accountId FROM accounts WHERE emailAddress = ?");
                $stmtEmail->execute([$email]);
                if ($stmtEmail->fetch()) {
                    $errors[] = "Ez az email cím már használatban van.";
                }
            }

            // 3. Mentés az accounts táblába + Aktuális dátum (NOW())
            if (empty($errors)) {
                $hashedPass = md5($password); // MTA szabvány szerinti titkosítás
                
                $stmtInsert = $pdo->prepare("INSERT INTO accounts (username, password, emailAddress, registerDate) VALUES (?, ?, ?, NOW())");
                if ($stmtInsert->execute([$username, $hashedPass, $email])) {
                    $success = true;
                } else {
                    $errors[] = "Hiba történt a regisztráció során.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NorthSide RP - Regisztráció</title>
    <script src="https://kit.fontawesome.com/122db0fdde.js" crossorigin="anonymous"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: rgb(26, 27, 31); font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .login-box { background: rgb(33, 35, 40); padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); width: 100%; max-width: 400px; text-align: center; border: 1px solid rgba(255,255,255,0.05); }
        .logo { font-size: 28px; font-weight: 900; color: #fff; margin-bottom: 5px; text-transform: uppercase; }
        .logo span { color: #1f6feb; }
        .subtitle { color: #9ca3af; font-size: 14px; margin-bottom: 30px; }
        
        .input-group { position: relative; margin-bottom: 20px; }
        .input-group i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
        .input-group input { width: 100%; padding: 12px 15px 12px 40px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-family: inherit; font-size: 15px; transition: 0.2s; }
        .input-group input:focus { outline: none; border-color: #1f6feb; box-shadow: 0 0 0 3px rgba(31,111,235,0.2); }
        
        .btn { width: 100%; padding: 12px; background: #1f6feb; color: #fff; border: none; border-radius: 6px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; text-transform: uppercase; letter-spacing: 1px; }
        .btn:hover { background: #1550b0; }
        
        .error-box { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; text-align: left; }
        .success-box { background: rgba(46, 204, 113, 0.1); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.2); padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        
        .links { margin-top: 20px; font-size: 14px; color: #9ca3af; }
        .links a { color: #1f6feb; text-decoration: none; font-weight: 600; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">NorthSide <span>UCP</span></div>
        <div class="subtitle">Hozz létre egy új fiókot!</div>
        
        <?php if ($errors): ?>
            <div class="error-box">
                <?php foreach ($errors as $e) echo "<div><i class='fa-solid fa-circle-xmark'></i> $e</div>"; ?>
            </div>
        <?php elseif ($success): ?>
            <div class="success-box"><i class="fa-solid fa-check"></i> Sikeres regisztráció!</div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST">
            <div class="input-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="username" placeholder="Felhasználónév" required>
            </div>
            <div class="input-group">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" placeholder="Email cím" required>
            </div>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="Jelszó" required>
            </div>
            <div class="input-group">
                <i class="fa-solid fa-key"></i>
                <input type="password" name="confirm" placeholder="Jelszó újra" required>
            </div>
            <button type="submit" class="btn">Regisztráció</button>
        </form>
        <?php endif; ?>

        <div class="links">
            Már van fiókod? <a href="login.php">Lépj be itt!</a>
        </div>
    </div>
</body>
</html>
