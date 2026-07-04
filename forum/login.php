<?php
// forum/login.php - JAVÍTOTT VERZIÓ
require 'config.php';
require 'functions.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Minden mezőt ki kell tölteni!";
    } else {
        $stmt = $pdo->prepare("SELECT accountId, username, password, is_banned, ban_reason, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['is_banned'] == 1) {
                $error = "Ki vagy tiltva! Indok: " . htmlspecialchars($user['ban_reason']);
            } else {
                $dbPass = $user['password'];
                $loginSuccess = false;

                // MTA Hash kompatibilitás
                if (password_verify($password, $dbPass)) $loginSuccess = true;
                elseif (md5($password) === $dbPass) $loginSuccess = true;
                elseif (strtoupper(md5($password)) === $dbPass) $loginSuccess = true;
                elseif (hash('sha256', $password) === $dbPass) $loginSuccess = true;

                if ($loginSuccess) {
                    $_SESSION['user_id'] = $user['accountId'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['admin_level'] = $user['adminLevel'];
                    
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Hibás jelszó!";
                }
            }
        } else {
            $error = "Nincs ilyen fiók!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Belépés - NorthSideRP Fórum</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .auth-input { width: 100%; padding: 12px; background: #0a0a0a; border: 1px solid var(--border-color); color: var(--text-normal); border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 14px; margin-bottom: 15px; }
        .auth-input:focus { border-color: var(--primary-color); outline: none; }
    </style>
</head>
<body>

<header class="p-nav">
    <div class="p-nav-logo">NorthSide <span>Fórum</span></div>
    <div class="p-nav-opposite">
        <a href="login.php" class="p-navgroup-link" style="border:none;">Belépés</a>
        <a href="register.php" class="button">Regisztráció</a>
    </div>
</header>
<div class="p-nav-sub"><a href="../index.php">Vissza a főoldalra</a></div>

<div class="p-body" style="justify-content: center; align-items: center; min-height: 60vh;">
    <main class="p-body-main" style="max-width: 450px; width: 100%;">
        
        <div class="block-container">
            <h2 class="block-header" style="text-align: center;"><i class="fa-solid fa-right-to-bracket"></i> Belépés a fórumba</h2>
            <div class="block-body" style="padding: 25px;">
                
                <?php if(!empty($error)): ?>
                    <div style="background: rgba(218, 54, 51, 0.1); border-left: 3px solid #da3633; color: #ff7b72; padding: 12px; margin-bottom: 20px; font-size: 13px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <label style="display: block; font-size: 13px; color: var(--text-muted); font-weight: bold; margin-bottom: 5px;">Felhasználónév</label>
                    <input type="text" name="username" class="auth-input" required autofocus>

                    <label style="display: block; font-size: 13px; color: var(--text-muted); font-weight: bold; margin-bottom: 5px;">Jelszó</label>
                    <input type="password" name="password" class="auth-input" required>

                    <button type="submit" class="button" style="width: 100%; padding: 12px; font-size: 15px;"><i class="fa-solid fa-right-to-bracket"></i> Belépés</button>
                    
                    <a href="forgot_password.php" style="color:var(--primary-color); font-size:12px; display:block; text-align:center; margin-top:15px;">Elfelejtetted a jelszavad? Kattints ide!</a>
                </form>

            </div>
        </div>

        <div style="text-align: center; margin-top: 15px; font-size: 13px;">
            Még nincs fiókod? <a href="register.php" style="color: var(--primary-color); font-weight: bold;">Regisztrálj most!</a>
        </div>

    </main>
</div>

</body>
</html>