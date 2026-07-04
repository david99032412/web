<?php
require_once 'config.php';
require_once 'functions.php';

// Ha már be van lépve, egyből a dashboardra dobjuk
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Minden mező kitöltése kötelező!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Jelszó ellenőrzése (Támogatja a SeeMTA-s MD5-öt és az új BCRYPT-et is)
            $isPassValid = false;
            if (md5($password) === $user['password']) {
                $isPassValid = true;
            } elseif (password_verify($password, $user['password'])) {
                $isPassValid = true;
            } elseif ($password === $user['password']) {
                $isPassValid = true; // Sima szöveges jelszó eshetőség (ha nem titkosított)
            }

            if ($isPassValid) {
                // Sikeres belépés
                $_SESSION['user_id'] = $user['accountId'];
                $_SESSION['user_username'] = $user['username'];
                redirect('dashboard.php');
            } else {
                $error = 'Hibás jelszó!';
            }
        } else {
            $error = 'Nem található ilyen fiók a szerveren!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NorthSide RP - Belépés</title>
    <script src="https://kit.fontawesome.com/122db0fdde.js" crossorigin="anonymous"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        /* A DASHBOARD SZÍNEI */
        body { background-color: rgb(26, 27, 31); font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; color: #fff; }
        .login-box { background: rgb(33, 35, 40); padding: 40px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 20px 40px rgba(0,0,0,0.5); width: 100%; max-width: 400px; text-align: center; }
        
        .logo { font-size: 28px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 10px; }
        .logo span { color: #1f6feb; } /* DASHBOARD KÉK SZÍN */
        
        .subtitle { color: #9ca3af; font-size: 14px; margin-bottom: 30px; }
        
        .input-group { position: relative; margin-bottom: 15px; }
        .input-group i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
        
        input { width: 100%; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 15px 15px 15px 45px; border-radius: 8px; font-family: inherit; font-size: 15px; outline: none; transition: 0.2s; }
        input:focus { border-color: #1f6feb; box-shadow: 0 0 0 3px rgba(31,111,235,0.2); }
        
        .btn { width: 100%; padding: 15px; background: #1f6feb; color: #fff; border: none; border-radius: 8px; font-weight: 700; font-size: 15px; text-transform: uppercase; cursor: pointer; transition: 0.2s; letter-spacing: 1px; margin-top: 10px; }
        .btn:hover { background: #1550b0; }
        
        .error-box { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; text-align: left; }
        
        .links { margin-top: 20px; font-size: 14px; color: #9ca3af; }
        .links a { color: #1f6feb; text-decoration: none; font-weight: 600; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">NorthSide <span>UCP</span></div>
        <div class="subtitle">Jelentkezz be a fiókodba</div>
        
        <?php if ($error): ?>
            <div class="error-box">
                <i class='fa-solid fa-circle-xmark'></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="username" placeholder="Felhasználónév" required>
            </div>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="Jelszó" required>
            </div>
            <button type="submit" class="btn"><i class="fa-solid fa-right-to-bracket"></i> Belépés</button>
        </form>

        <div class="links">
            <a href="/"><i class="fa-solid fa-house"></i> Vissza a főoldalra</a>
        </div>
    </div>
</body>
</html>