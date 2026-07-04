<?php
// ucp/profile.php - PROFIL ÉS BEÁLLÍTÁSOK
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$sessionUser = $_SESSION['user_username'];

$stmt = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
$stmt->execute([$sessionUser]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) die("Felhasználó nem található a rendszerben.");

// Adatok kinyerése
$accountId = $account['accountId'] ?? 0; // <--- EZ A SOR JAVÍTJA A HIBÁT
$displayName = $account['username'] ?? 'Ismeretlen';
$email = $account['email'] ?? 'Nincs megadva';
$adminLvl = (int)($account['adminLevel'] ?? 0);
$pPont = (int)($account['premiumPoints'] ?? 0);
$serial = $account['serial'] ?? 'Nincs rögzítve';
$lastIP = $account['ip'] ?? $account['lastIP'] ?? 'Nincs rögzítve';

// Okos dátum formázó
$rawDate = $account['registerDate'] ?? null;
$formattedDate = 'Nincs rögzítve';
if (!empty($rawDate) && strtotime($rawDate)) {
    $formattedDate = date('Y. m. d. H:i', strtotime($rawDate));
}

$isAdmin = ($adminLvl >= 7);
$successMsg = '';
$errorMsg = '';

// Automatikus tábla a Serial kérelmeknek, ha nem létezne
$pdo->exec("CREATE TABLE IF NOT EXISTS `serial_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `accountId` int(11) NOT NULL,
    `oldSerial` varchar(32) NOT NULL,
    `newSerial` varchar(32) NOT NULL,
    `reason` text NOT NULL,
    `status` int(1) NOT NULL DEFAULT 0,
    `requestDate` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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
        if (md5($oldPass) === $account['password'] || $oldPass === $account['password'] || password_verify($oldPass, $account['password'])) {
            $isOldPassValid = true;
        }

        if (!$isOldPassValid) {
            $errorMsg = "A jelenlegi jelszó helytelen!";
        } else {
            // MTA JELSZÓ TITKOSÍTÁS (Alapból MD5)
            $hashedNewPass = md5($newPass); 
            $stmtUpdate = $pdo->prepare("UPDATE accounts SET password = ? WHERE accountId = ?");
            if ($stmtUpdate->execute([$hashedNewPass, $account['accountId']])) {
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
    $checkPending->execute([$account['accountId']]);
    
    if ($checkPending->rowCount() > 0) {
        $errorMsg = "Már van egy folyamatban lévő kérelmed! Kérlek várj az elbírálásra.";
    } elseif (strlen($newSerial) !== 32) {
        $errorMsg = "A megadott MTA Serialnak pontosan 32 karakter hosszúnak kell lennie!";
    } elseif (empty($reason) || strlen($reason) < 10) {
        $errorMsg = "Kérlek add meg a váltás indokát részletesebben (pl. újratelepítés, új gép)!";
    } else {
        $stmtReq = $pdo->prepare("INSERT INTO serial_requests (accountId, oldSerial, newSerial, reason, status) VALUES (?, ?, ?, ?, 0)");
        if ($stmtReq->execute([$account['accountId'], $serial, $newSerial, $reason])) {
            $successMsg = "Serial váltási kérelem beküldve! Az adminok hamarosan elbírálják.";
        } else { 
            $errorMsg = "Hiba történt a kérelem beküldésekor."; 
        }
    }
}

// Aktív kérelem lekérdezése
$activeReq = $pdo->prepare("SELECT * FROM serial_requests WHERE accountId = ? ORDER BY id DESC LIMIT 1");
$activeReq->execute([$account['accountId']]);
$lastRequest = $activeReq->fetch(PDO::FETCH_ASSOC);

function getProfileAdminTitle($level) {
    if ($level >= 11) return 'Tulajdonos';
    if ($level >= 8) return 'Főadmin';
    if ($level >= 7) return 'Adminisztrátor';
    if ($level >= 1) return 'Adminsegéd';
    return 'Játékos';
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Profilom - NorthSide UCP</title>
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
        .welcome-box { background: linear-gradient(135deg, rgba(13, 110, 253, 0.15), transparent); border: 1px solid rgba(13, 110, 253, 0.2); border-radius: 12px; padding: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .welcome-box h1 { color: #fff; font-size: 28px; font-weight: 800; margin: 0; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr; gap: 25px; align-items: start;}
        @media (min-width: 992px) { .grid-2 { grid-template-columns: 2fr 3fr; } }
        
        .profile-column { display: grid; gap: 25px; }
        .card { background: rgb(33, 35, 40); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 0; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.2);}
        
        .card-header { padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 15px; background: rgba(0,0,0,0.2);}
        .card-header .primary-icon { width: 50px; height: 50px; background: rgba(13, 110, 253, 0.1); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 20px; color: #0d6efd; border: 2px solid rgba(13, 110, 253, 0.3); }
        .card-header .warning-icon { width: 50px; height: 50px; background: rgba(230, 126, 34, 0.1); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 20px; color: #e67e22; border: 2px solid rgba(230, 126, 34, 0.3); }
        .card-header div { display: flex; flex-direction: column; flex: 1;}
        .card-title { font-size: 18px; font-weight: 700; color: #fff; margin: 0;}
        .card-header span { color: #9ca3af; font-size: 12px; }

        .card-body { padding: 25px; }

        .data-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed rgba(255,255,255,0.05); font-size: 14px; }
        .data-row:last-child { border-bottom: none; padding-bottom: 0;}
        .data-label { color: #9ca3af; display: flex; align-items: center; gap: 12px; font-weight: 500;}
        .data-label i { color: #0d6efd; font-size: 1.1em; width: 20px; text-align: center; }
        .data-value { color: #f3f4f6; font-weight: 600; }
        .data-value.monospace { font-family: monospace; color:#3fb950; font-size: 13px;}

        .pp-badge { background: rgba(241, 196, 15, 0.1); color: #f1c40f; padding: 4px 10px; border-radius: 6px; font-weight: 900; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid rgba(241, 196, 15, 0.2); width: fit-content; margin-top: 5px; }
        .admin-badge { background: rgba(231, 76, 60, 0.1); color: #e74c3c; padding: 4px 10px; border-radius: 6px; font-weight: 900; font-size: 12px; text-transform: uppercase; border: 1px solid rgba(231, 76, 60, 0.2); width: fit-content; margin-top: 5px; }

        .card form { padding: 25px; display: grid; gap: 15px; }
        .card input, .card textarea { width: 100%; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 12px 15px; border-radius: 6px; font-family: inherit; font-size: 14px; transition: 0.2s; }
        .card input:focus, .card textarea:focus { outline: none; border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2); }
        .card textarea { resize: vertical; min-height: 80px; }
        .card input.monospace { font-family: monospace; font-size: 13px; color: #3fb950;}

        .btn-submit { background: #0d6efd; color: #fff; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 1px;}
        .btn-submit:hover { background: #0b5ed7; transform: translateY(-2px); }
        .btn-warning { background: #e67e22; }
        .btn-warning:hover { background: #d35400; }

        .alert { padding: 15px; border-radius: 6px; font-weight: bold; font-size: 14px; display: flex; align-items: center; gap: 10px; margin-bottom: 25px;}
        .alert-success { background: rgba(46, 204, 113, 0.1); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.2); }
        .alert-error { background: rgba(231, 76, 60, 0.1); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.2); }
        .alert-info { background: rgba(52, 152, 219, 0.1); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.2); margin-bottom:0;}
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-brand">NorthSide <span>UCP</span></div>
        <div class="nav-links">
            <a href="dashboard.php">Kezdőlap</a>
            <a href="profile.php" class="active">Profilom</a>
            <a href="characters.php">Karakterek</a>
            <a href="factions.php">Frakcióim</a>
            <a href="map.php">Élő Térkép</a>
            <a href="../forum/">Fórum</a>
            <?php if($isAdmin): ?>
                <a href="adminpanel.php" style="color: #e3b341;"><i class="fa-solid fa-shield-halved"></i> Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php" style="color: #ef4444;"><i class="fa-solid fa-power-off"></i> Kilépés</a>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-box">
            <h1><i class="fa-solid fa-user-shield" style="color: #0d6efd; margin-right:10px;"></i> Profilom</h1>
            <div style="color: #9ca3af; font-size: 16px;">Fiók ID: <span style="background:rgba(13, 110, 253, 0.2); color:#0d6efd; padding:2px 8px; border-radius:4px; font-weight:bold;">#<?= $accountId ?></span></div>
        </div>

        <?php if ($successMsg): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= $errorMsg ?></div><?php endif; ?>

        <div class="grid-2">
            
            <div>
                <div class="card account-details">
                    <div class="card-header">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=0d6efd&color=fff&size=50&rounded=true&bold=true" alt="Avatar" style="border: 2px solid rgba(13, 110, 253, 0.3);">
                        <div>
                            <div class="card-title"><?= htmlspecialchars($displayName) ?></div>
                            <span>Fiók ID: #<?= $accountId ?></span>
                            <div style="display:flex; gap:10px; flex-wrap: wrap;">
                                <div class="pp-badge"><i class="fa-solid fa-coins"></i> <?= number_format($pPont, 0, '', ' ') ?> PP</div>
                                <?php if($adminLvl > 0): ?>
                                    <div class="admin-badge"><i class="fa-solid fa-shield"></i> <?= getProfileAdminTitle($adminLvl) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="data-row">
                            <span class="data-label"><i class="fa-solid fa-envelope"></i> E-mail Cím</span>
                            <span class="data-value"><?= htmlspecialchars($email) ?></span>
                        </div>
                        <div class="data-row">
                            <span class="data-label"><i class="fa-solid fa-calendar-days"></i> Regisztráció</span>
                            <span class="data-value"><?= $formattedDate ?></span>
                        </div>
                        <div class="data-row">
                            <span class="data-label"><i class="fa-solid fa-key"></i> Jelenlegi Serial</span>
                            <span class="data-value monospace"><?= htmlspecialchars($serial) ?></span>
                        </div>
                        <div class="data-row">
                            <span class="data-label"><i class="fa-solid fa-network-wired"></i> Utolsó IP Cím</span>
                            <span class="data-value monospace"><?= htmlspecialchars($lastIP) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-column">
                
                <div class="card password-card">
                    <div class="card-header">
                        <i class="fa-solid fa-lock primary-icon"></i>
                        <div class="card-title">Jelszó Megváltoztatása</div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <input type="password" name="old_password" required placeholder="Jelenlegi jelszó" autocomplete="current-password">
                        <input type="password" name="new_password" required placeholder="Új jelszó (Minimum 6 karakter)" minlength="6" autocomplete="new-password">
                        <input type="password" name="new_password_confirm" required placeholder="Új jelszó megerősítése" minlength="6" autocomplete="new-password">
                        <button type="submit" class="btn-submit">Módosítások mentése <i class="fa-solid fa-check"></i></button>
                    </form>
                </div>

                <div class="card serial-card">
                    <div class="card-header">
                        <i class="fa-solid fa-desktop warning-icon"></i>
                        <div class="card-title">Serial Váltási Kérelem</div>
                    </div>
                    
                    <?php if ($lastRequest && $lastRequest['status'] == 0): ?>
                        <div style="padding: 25px; padding-bottom: 0;">
                            <div class="alert alert-info"><i class="fa-solid fa-clock"></i> Van egy folyamatban lévő kérelmed! Kérlek várj az elbírálásra.</div>
                        </div>
                    <?php else: ?>
                        <?php if ($lastRequest && $lastRequest['status'] == 2): ?>
                            <div style="padding: 25px 25px 0 25px;">
                                <div class="alert alert-error" style="margin:0;"><i class="fa-solid fa-xmark"></i> Legutóbbi kérelmedet az adminok elutasították. Próbáld újra.</div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="request_serial">
                            <input type="text" name="new_serial" class="monospace" required placeholder="Ide írd be az ÚJ Serialodat (Pontosan 32 karakter)" minlength="32" maxlength="32">
                            <textarea name="reason" required placeholder="Kérlek indokold meg részletesen, miért szeretnél serialt váltani (pl. új gép, alaplap csere)..."></textarea>
                            <button type="submit" class="btn-submit btn-warning">Kérelem beküldése <i class="fa-solid fa-paper-plane"></i></button>
                        </form>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
