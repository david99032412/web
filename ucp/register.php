<?php
// ucp/register.php - FIVEM QBCORE WHITELIST REGISZTRÁCIÓ
require_once 'config.php';
require_once 'functions.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $license = clean($_POST['license'] ?? ''); // Pl: license:2732...
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (empty($username) || empty($license) || empty($password)) {
        $errors[] = "Minden mező kitöltése kötelező!";
    } elseif ($password !== $confirm) {
        $errors[] = "A jelszavak nem egyeznek.";
    } elseif (!str_starts_with($license, 'license:')) {
        $errors[] = "Hibás Rockstar License formátum! (Példa: license:abcdef123...)";
    } else {
        // Ellenőrizzük, létezik-e már a felhasználó vagy a license
        $stmt = $pdo->prepare("SELECT accountId FROM accounts WHERE username = ? OR license = ?");
        $stmt->execute([$username, $license]);
        if ($stmt->fetch()) {
            $errors[] = "Ez a felhasználónév vagy License már foglalt!";
        } else {
            // Mentés az 'accounts' táblába - Ez az alapja a szervernek is!
            $hashedPass = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO accounts (username, password, email, license, adminLevel) VALUES (?, ?, ?, ?, 0)");
            if ($stmt->execute([$username, $hashedPass, $email, $license])) {
                $success = true;
                // Opcionális: Discord Webhook értesítés, hogy új tag regisztrált
                sendDiscordWebhook("Új Regisztráció", "Felhasználó: $username\nLicense: $license");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Regisztráció - NorthSideRP</title>
    <style>
        body { background: #1a1b1f; color: #fff; font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .box { background: #212328; padding: 40px; border-radius: 12px; width: 400px; border: 1px solid #333; }
        input { width: 100%; padding: 12px; margin: 10px 0; background: #1a1b1f; border: 1px solid #444; color: #fff; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #0d6efd; border: none; color: #fff; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .error { color: #ef4444; background: rgba(239,68,68,0.1); padding: 10px; border-radius: 6px; margin-bottom: 10px; font-size: 14px; }
        .success { color: #2ecc71; background: rgba(46,204,113,0.1); padding: 10px; border-radius: 6px; text-align: center; }
    </style>
</head>
<body>
    <div class="box">
        <h2 style="text-align: center;">Regisztráció</h2>
        <?php foreach($errors as $e) echo "<div class='error'>$e</div>"; ?>
        <?php if($success): ?>
            <div class="success">Sikeres regisztráció! Most már felcsatlakozhatsz a szerverre.</div>
            <p style="text-align:center;"><a href="login.php" style="color:#0d6efd;">Tovább az UCP-be</a></p>
        <?php else: ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Felhasználónév" required>
                <input type="text" name="license" placeholder="Rockstar License (license:...)" required>
                <input type="email" name="email" placeholder="Email cím">
                <input type="password" name="password" placeholder="Jelszó" required>
                <input type="password" name="confirm" placeholder="Jelszó újra" required>
                <button type="submit">Regisztráció</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
