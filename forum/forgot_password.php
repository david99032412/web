<?php
// forum/forgot_password.php - XENFORO ELFELEJTETT JELSZÓ
require 'config.php'; require 'functions.php';

if (isLoggedIn()) { header("Location: index.php"); exit; }

$msg = "";
$token = $_GET['token'] ?? '';

// --- 1. FÁZIS: ÚJ JELSZÓ BEÁLLÍTÁSA (Ha van token az URL-ben) ---
if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM forum_password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $resetReq = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetReq) {
        die("Érvénytelen vagy lejárt hivatkozás!");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
        $newPass = $_POST['new_password'];
        $newPassConfirm = $_POST['new_password_confirm'];

        if ($newPass !== $newPassConfirm) {
            $msg = "<div style='background: rgba(218, 54, 51, 0.1); border-left: 3px solid #da3633; color: #ff7b72; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-triangle-exclamation'></i> A két jelszó nem egyezik!</div>";
        } else {
            // MTA kompatibilis MD5 jelszó hash
            $hashedPass = md5($newPass);
            
            // Jelszó frissítése
            $pdo->prepare("UPDATE accounts SET password = ? WHERE emailAddress = ?")->execute([$hashedPass, $resetReq['email']]);
            
            // Token törlése
            $pdo->prepare("DELETE FROM forum_password_resets WHERE email = ?")->execute([$resetReq['email']]);
            
            $msg = "<div style='background: rgba(46, 160, 67, 0.1); border-left: 3px solid #2ea043; color: #3fb950; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-check'></i> A jelszavad sikeresen megváltozott! <a href='login.php' style='color:#fff; font-weight:bold; text-decoration:underline;'>Kattints ide a belépéshez!</a></div>";
            $token = ''; // Eltüntetjük az űrlapot
        }
    }
} 
// --- 2. FÁZIS: EMAIL MEGADÁSA ÉS KÓD KÉRÉSE ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = clean($_POST['email']);
    
    // Létezik ez az email?
    $stmt = $pdo->prepare("SELECT accountId, username FROM accounts WHERE emailAddress = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $newToken = bin2hex(random_bytes(32)); // Titkos 64 karakteres kód
        
        // Töröljük a régi kéréseit
        $pdo->prepare("DELETE FROM forum_password_resets WHERE email = ?")->execute([$email]);
        // Beírjuk az újat
        $pdo->prepare("INSERT INTO forum_password_resets (email, token) VALUES (?, ?)")->execute([$email, $newToken]);
        
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/forgot_password.php?token=" . $newToken;
        
        /* ÉLESBEN IDE JÖN AZ EMAIL KÜLDÉS!
        mail($email, "Jelszó visszaállítás - NorthSideRP", "Kattints ide: " . $resetLink);
        */

        $msg = "<div style='background: rgba(46, 160, 67, 0.1); border-left: 3px solid #2ea043; color: #3fb950; padding: 12px; margin-bottom: 20px;'>
                    <i class='fa-solid fa-check'></i> Az e-mailt elküldtük!<br><br>
                    <span style='color:#fff; font-size:11px;'>(TESZT MÓD - Mivel a szerver nem küld emailt, itt a linked: <a href='$resetLink' style='color:var(--primary-color);'>$resetLink</a>)</span>
                </div>";
    } else {
        $msg = "<div style='background: rgba(218, 54, 51, 0.1); border-left: 3px solid #da3633; color: #ff7b72; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-triangle-exclamation'></i> Nincs ilyen e-mail cím regisztrálva!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Elfelejtett Jelszó - NorthSideRP</title>
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
<div class="p-nav-sub"><a href="index.php">Vissza a főoldalra</a></div>

<div class="p-body" style="justify-content: center; align-items: center; min-height: 60vh;">
    <main class="p-body-main" style="max-width: 500px; width: 100%;">
        
        <div class="block-container">
            <h2 class="block-header" style="text-align: center;"><i class="fa-solid fa-lock"></i> Elfelejtett Jelszó</h2>
            <div class="block-body" style="padding: 25px;">
                
                <?= $msg ?>

                <?php if(!empty($token) && !strpos($msg, 'Belépéshez')): ?>
                    <form method=\"POST\">
                        <label style="display: block; font-size: 13px; color: var(--text-muted); font-weight: bold; margin-bottom: 5px;">Új Jelszó</label>
                        <input type="password" name="new_password" class="auth-input" required placeholder="Add meg az új jelszavad...">

                        <label style="display: block; font-size: 13px; color: var(--text-muted); font-weight: bold; margin-bottom: 5px;">Új Jelszó Megerősítése</label>
                        <input type="password" name="new_password_confirm" class="auth-input" required placeholder="Írd be újra...">

                        <button type="submit" class="button" style="width: 100%; padding: 12px; font-size: 15px; background: #2ea043;"><i class="fa-solid fa-floppy-disk"></i> Jelszó megváltoztatása</button>
                    </form>
                <?php elseif(empty($token)): ?>
                    <p style="font-size: 13px; color: var(--text-muted); text-align: center; margin-bottom: 20px;">Kérjük, add meg a regisztrált e-mail címedet. Egy hivatkozást fogunk küldeni, amivel új jelszót állíthatsz be.</p>
                    <form method="POST">
                        <label style="display: block; font-size: 13px; color: var(--text-muted); font-weight: bold; margin-bottom: 5px;">E-mail cím</label>
                        <input type="email" name="email" class="auth-input" required placeholder="pelda@email.com">

                        <button type="submit" class="button" style="width: 100%; padding: 12px; font-size: 15px;"><i class="fa-solid fa-paper-plane"></i> Visszaállítási link küldése</button>
                    </form>
                    <div style="text-align: center; margin-top: 15px; font-size: 13px;">
                        <a href="login.php" style="color: var(--text-muted);">Mégis eszembe jutott! Vissza a belépéshez.</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </main>
</div>

</body>
</html>
