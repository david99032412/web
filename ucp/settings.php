<?php
// ucp/settings.php - BIZTONSÁG, JELSZÓCSERE ÉS SERIAL VÁLTÁS
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$stmtAcc = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
$stmtAcc->execute([$_SESSION['user_username']]);
$account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

if (!$account) die("Hiba a fiók betöltésekor!");

$isAdmin = (isset($account['adminLevel']) && (int)$account['adminLevel'] >= 7);
$accountId = $account['accountId'];

$successMsg = '';
$errorMsg = '';

// --- AUTOMATIKUS TÁBLA LÉTREHOZÁS A SERIAL KÉRELMEKNEK ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `serial_requests` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `accountId` int(11) NOT NULL,
        `oldSerial` varchar(32) NOT NULL,
        `newSerial` varchar(32) NOT NULL,
        `reason` text NOT NULL,
        `status` int(1) NOT NULL DEFAULT 0,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch(Exception $e) {}

// --- JELSZÓ VÁLTOZTATÁS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $oldPass = $_POST['old_password'];
    $newPass = $_POST['new_password'];
    $newPassConfirm = $_POST['new_password_confirm'];

    if ($newPass !== $newPassConfirm) {
        $errorMsg = "A két új jelszó nem egyezik meg!";
    } elseif (strlen($newPass) < 6) {
        $errorMsg = "Az új jelszónak legalább 6 karakter hosszúnak kell lennie!";
    } else {
        $isOldPassValid = false;
        if (md5($oldPass) === $account['password']) $isOldPassValid = true;
        elseif (password_verify($oldPass, $account['password'])) $isOldPassValid = true;
        elseif ($oldPass === $account['password']) $isOldPassValid = true;

        if (!$isOldPassValid) {
            $errorMsg = "A jelenlegi jelszó helytelen!";
        } else {
            $hashedNewPass = md5($newPass); 
            $stmtUpdate = $pdo->prepare("UPDATE accounts SET password = ? WHERE accountId = ?");
            if ($stmtUpdate->execute([$hashedNewPass, $accountId])) {
                $successMsg = "A jelszavad sikeresen megváltozott!";
                $account['password'] = $hashedNewPass; 
            } else {
                $errorMsg = "Hiba történt a jelszó módosításakor.";
            }
        }
    }
}

// --- SERIAL VÁLTÁSI KÉRELEM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_serial') {
    $newSerial = trim($_POST['new_serial']);
    $reason = trim($_POST['reason']);
    
    $checkPending = $pdo->prepare("SELECT id FROM serial_requests WHERE accountId = ? AND status = 0");
    $checkPending->execute([$accountId]);
    
    if ($checkPending->rowCount() > 0) {
        $errorMsg = "Már van egy folyamatban lévő kérelmed! Kérlek várj az elbírálásra.";
    } elseif (strlen($newSerial) !== 32) {
        $errorMsg = "A megadott MTA Serialnak pontosan 32 karakter hosszúnak kell lennie!";
    } elseif (empty($reason)) {
        $errorMsg = "Kérlek add meg a váltás indokát is!";
    } else {
        $oldSerial = $account['serial'] ?? $account['mtaserial'] ?? 'Ismeretlen';
        $stmtReq = $pdo->prepare("INSERT INTO serial_requests (accountId, oldSerial, newSerial, reason, status) VALUES (?, ?, ?, ?, 0)");
        if ($stmtReq->execute([$accountId, $oldSerial, $newSerial, $reason])) {
            $successMsg = "Serial váltási kérelem sikeresen beküldve! Az adminok hamarosan elbírálják.";
        } else {
            $errorMsg = "Hiba történt a kérelem beküldésekor.";
        }
    }
}

// Aktív kérelem lekérdezése megjelenítéshez
$activeReq = $pdo->prepare("SELECT * FROM serial_requests WHERE accountId = ? ORDER BY id DESC LIMIT 1");
$activeReq->execute([$accountId]);
$lastRequest = $activeReq->fetch(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']); 
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NorthSide RP - Beállítások</title>
    <script src="https://kit.fontawesome.com/122db0fdde.js" crossorigin="anonymous"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: rgb(26, 27, 31); color: #d1d5db; font-family: 'Inter', sans-serif; }
        .navbar { background: rgba(17, 18, 20, 0.95); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; backdrop-filter: blur(10px); }
        .navbar-brand { font-size: 20px; font-weight: 900; color: #fff; text-transform: uppercase; }
        .navbar-brand span { color: #0d6efd; }
        .nav-links a { color: #9ca3af; text-decoration: none; margin-left: 20px; font-size: 14px; font-weight: 500; transition: 0.2s; }
        .nav-links a:hover, .nav-links a.active { color: #fff; }
        
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        
        .welcome-box { background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), transparent); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 12px; padding: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .welcome-box h1 { color: #fff; font-size: 28px; font-weight: 800; margin: 0; }
        
        .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;}
        @media (max-width: 800px) { .settings-grid { grid-template-columns: 1fr; } }
        
        .card { background: rgb(33, 35, 40); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 30px; }
        .card-title { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 12px; color: #9ca3af; font-weight: bold; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input, .form-group textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 15px; border-radius: 8px; font-family: inherit; font-size: 14px; transition: 0.2s; outline: none; }
        .form-group input:focus, .form-group textarea:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2); }
        .form-group textarea { resize: vertical; min-height: 100px; }
        
        .btn-submit { padding: 15px 25px; border: none; border-radius: 8px; cursor: pointer; color: white; font-weight: bold; text-transform: uppercase; font-size: 13px; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 10px; width: 100%; }
        .btn-purple { background: #8b5cf6; }
        .btn-purple:hover { background: #7c3aed; transform: translateY(-2px); }
        .btn-warning { background: #e67e22; }
        .btn-warning:hover { background: #d35400; transform: translateY(-2px); }
        
        .alert { padding: 15px; border-radius: 8px; font-weight: bold; margin-bottom: 25px; display: flex; align-items: center; gap: 10px;}
        .alert-success { background: rgba(46, 204, 113, 0.1); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.2); }
        .alert-error { background: rgba(231, 76, 60, 0.1); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.2); }
        .alert-info { background: rgba(52, 152, 219, 0.1); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.2); margin-bottom: 20px;}
        
        .data-row { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px dashed rgba(255,255,255,0.05); }
        .data-row:last-child { border-bottom: none; }
        .data-label { color: #9ca3af; font-weight: bold; }
        .data-value { color: #fff; font-family: monospace; font-size: 16px; font-weight: bold;}
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-brand">NorthSide <span>UCP</span></div>
        <div class="nav-links">
            <a href="dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">Kezdőlap</a>
            <a href="profile.php" class="<?= $currentPage == 'profile.php' ? 'active' : '' ?>">Profilom</a>
            <a href="characters.php" class="<?= $currentPage == 'characters.php' ? 'active' : '' ?>">Karakterek</a>
            <a href="factions.php" class="<?= $currentPage == 'factions.php' ? 'active' : '' ?>">Frakcióim</a>
            <a href="support.php" class="<?= $currentPage == 'support.php' ? 'active' : '' ?>">Support</a>
            <a href="vehicles.php" class="<?= $currentPage == 'vehicles.php' ? 'active' : '' ?>">Járművek</a>
            <a href="premium.php" class="<?= $currentPage == 'premium.php' ? 'active' : '' ?>"><i class="fa-solid fa-star"></i> Prémium</a>
            <a href="settings.php" class="active" style="color:#8b5cf6;"><i class="fa-solid fa-gear"></i> Beállítások</a>
            <?php if($isAdmin): ?>
                <a href="adminpanel.php" style="color: #ef4444;"><i class="fa-solid fa-shield-halved"></i> Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php" style="color: #ef4444;"><i class="fa-solid fa-power-off"></i> Kilépés</a>
        </div>
    </nav>

    <div class="container">
        
        <div class="welcome-box">
            <h1><i class="fa-solid fa-shield-halved" style="color: #8b5cf6; margin-right:10px;"></i> Biztonsági Központ</h1>
            <div style="color: #9ca3af;">Véd a fiókodat és kezeld a jelszavaidat, serialokat.</div>
        </div>

        <?php if($successMsg): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= $successMsg ?></div><?php endif; ?>
        <?php if($errorMsg): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $errorMsg ?></div><?php endif; ?>

        <div class="settings-grid">
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-user-lock" style="color:#2ecc71;"></i> Fiók Biztonsági Adatai</div>
                <div class="data-row">
                    <span class="data-label">Felhasználónév</span>
                    <span class="data-value"><?= htmlspecialchars($account['username']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">E-mail cím</span>
                    <span class="data-value" style="color:#3b82f6;"><?= htmlspecialchars($account['email'] ?? $account['emailAddress'] ?? 'Nincs megadva') ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Regisztráció Dátuma</span>
                    <span class="data-value"><?= isset($account['registerDate']) ? date('Y.m.d.', strtotime($account['registerDate'])) : 'Ismeretlen' ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Utolsó IP Címed</span>
                    <span class="data-value"><?= htmlspecialchars($account['ip'] ?? $account['lastIP'] ?? 'Ismeretlen') ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Jelenlegi MTA Serial</span>
                    <span class="data-value" style="color:#e67e22; font-size: 13px;"><?= htmlspecialchars($account['serial'] ?? $account['mtaserial'] ?? 'Nincs rögzítve') ?></span>
                </div>
                
                <div style="margin-top: 30px; padding: 15px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; color: #f59e0b; font-size: 13px; line-height: 1.5;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 18px; margin-bottom: 10px; display:block;"></i>
                    <strong>Biztonsági jótanács:</strong> Soha ne használd a szerveren ugyanazt a jelszót, amit más szervereken, vagy az email fiókodhoz használsz! Az adminisztrátorok soha nem fogják elkérni a jelszavadat.
                </div>
            </div>

            <div class="card" style="border-color: rgba(139, 92, 246, 0.3);">
                <div class="card-title"><i class="fa-solid fa-key" style="color:#8b5cf6;"></i> Jelszó Megváltoztatása</div>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label>Jelenlegi jelszó</label>
                        <input type="password" name="old_password" required placeholder="Add meg a jelenlegi jelszavad...">
                    </div>
                    <div class="form-group">
                        <label>Új jelszó <span style="color:#ef4444; font-weight:normal; text-transform:none;">(Min. 6 karakter)</span></label>
                        <input type="password" name="new_password" required placeholder="Adj meg egy erős, új jelszót...">
                    </div>
                    <div class="form-group">
                        <label>Új jelszó megerősítése</label>
                        <input type="password" name="new_password_confirm" required placeholder="Írd be újra az új jelszót...">
                    </div>
                    <button type="submit" class="btn-submit btn-purple"><i class="fa-solid fa-floppy-disk"></i> Jelszó Cseréje</button>
                </form>
            </div>
        </div>

        <div class="card" style="border-color: rgba(230, 126, 34, 0.3);">
            <div class="card-title"><i class="fa-solid fa-desktop" style="color:#e67e22;"></i> Serial Váltási Kérelem (MTA)</div>
            
            <?php if ($lastRequest && $lastRequest['status'] == 0): ?>
                <div class="alert alert-info">
                    <i class="fa-solid fa-clock"></i> Van egy folyamatban lévő kérelmed az adminok felé! Kérlek, légy türelemmel, hamarosan elbírálják.
                </div>
            <?php else: ?>
                <?php if ($lastRequest && $lastRequest['status'] == 2): ?>
                    <div class="alert alert-error" style="background:transparent; padding:0; margin-bottom: 20px;"><i class="fa-solid fa-xmark"></i> Legutóbbi kérelmedet az adminok elutasították. Próbáld újra, pontosabb indoklással!</div>
                <?php endif; ?>
                
                <p style="color: #9ca3af; font-size: 14px; margin-bottom: 25px; line-height: 1.5;">Új számítógépet vettél vagy újratelepítetted a Windowst? Add meg az új MTA Serialodat (amit a játékba lépésnél kapsz meg), és indokold meg a váltást. Az adminok átnézik a kérelmed.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="request_serial">
                    <div class="settings-grid" style="margin-bottom: 0;">
                        <div class="form-group">
                            <label>Új MTA Serial (Pontosan 32 karakter)</label>
                            <input type="text" name="new_serial" required placeholder="PL: 1A2B3C4D5E6F7G8H9I0J1K2L3M4N5O6P" minlength="32" maxlength="32" style="font-family: monospace;">
                        </div>
                        <div class="form-group">
                            <label>Váltás Indoka</label>
                            <textarea name="reason" required placeholder="Írd le részletesen, miért szeretnél serialt váltani..."></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit btn-warning" style="max-width: 300px; margin-top: 10px;">Kérelem Beküldése <i class="fa-solid fa-paper-plane"></i></button>
                </form>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>