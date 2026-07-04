<?php
// ucp/adminpanel.php - VEZÉRLŐPULT (TÖKÉLETESÍTETT DINAMIKUS LÉTREHOZÁSSAL ÉS JAVÍTOTT DIZÁJNNAL)
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$stmt = $pdo->prepare("SELECT accountId, adminLevel, username FROM accounts WHERE username = ?");
$stmt->execute([$_SESSION['user_username']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$me || (int)$me['adminLevel'] < 7) { die("<div style='background:#1a1b1f; color:#ef4444; padding:40px; text-align:center; font-family:sans-serif;'><h2>Nincs jogosultságod!</h2><a href='dashboard.php' style='color:#0d6efd;'>Vissza</a></div>"); }

$isAdmin = true;
$isOwner = ((int)$me['adminLevel'] >= 11); 

$activeGroupId = isset($_GET['manage_id']) ? (int)$_GET['manage_id'] : null;
$activeCharId = isset($_GET['edit_char']) ? (int)$_GET['edit_char'] : null;
$activeAccId = isset($_GET['edit_acc']) ? (int)$_GET['edit_acc'] : null;
$activeTicketId = isset($_GET['view_ticket']) ? (int)$_GET['view_ticket'] : null;

$successMsg = '';
$errorMsg = '';

// --- NYITOTT JEGYEK SZÁMOLÁSA A MENÜHÖZ ---
$openTicketsCount = 0;
try {
    $openTicketsCount = $pdo->query("SELECT COUNT(*) FROM ucp_tickets WHERE status = 'open'")->fetchColumn();
} catch(Exception $e) {}

// --- JÁRMŰ NEVEK FÜGGVÉNYE ---
if (!function_exists('getVehicleName')) {
    function getVehicleName($modelId) {
        $cars = [400 => 'BMW X6M', 401 => 'Ford Focus RS Mk2', 402 => 'Pontiac Firebird', 403 => 'Linerunner', 404 => 'Mercedes-Benz G-Class W463', 405 => 'Chevrolet Tahoe', 406 => 'Dumper', 407 => 'Mercedes-Benz Atego Firetruck', 408 => 'Trashmaster', 409 => 'Tesla Model Y', 410 => 'Maserati GranTurismo', 411 => 'Ferrari LaFerrari', 412 => 'Koenigsegg Agera RS', 413 => 'Ford Econoline', 414 => 'Mule', 415 => 'Mercedes-Benz AMG One', 416 => 'Mercedes-Benz Sprinter Ambulance', 417 => 'Leviathan', 418 => 'Volkswagen Caravelle 2018', 419 => 'Toyota Supra A90', 420 => 'BMW 3 series G20', 421 => 'Cadillac Escalade', 422 => 'Bobcat', 423 => 'McLaren W1', 451 => 'Lamborghini Huracan', 560 => 'Mitsubishi Lancer EVO X']; 
        return $cars[$modelId] ?? "Jármű ($modelId)";
    }
}

// --- WEBOLDAL HIBANAPLÓ (AJAX) ---
if (isset($_GET['ajax_web_logs'])) {
    $logFile = '/var/log/apache2/error.log'; 
    if (!file_exists($logFile)) $logFile = $_SERVER['DOCUMENT_ROOT'] . '/var/log/apache2/error.log';
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!empty($lines)) {
            $lastLines = array_slice($lines, -30);
            $fmt = [];
            foreach ($lastLines as $line) {
                $safe = htmlspecialchars($line);
                if (strpos($safe, 'PHP Fatal error') !== false) $safe = str_replace('PHP Fatal error', '<strong style="color:#ef4444;">PHP Fatal error</strong>', $safe);
                $fmt[] = $safe;
            }
            echo implode("<br><hr style='border-color:rgba(255,255,255,0.05); margin:5px 0;'>", $fmt);
        } else echo '<span style="color:#9ca3af">Minden tökéletes, nincs PHP hiba! ✅</span>';
    } else echo '<span style="color:#ef4444">Nem találom a szerver log fájlját.</span>';
    exit;
}

if (isset($_GET['clear_web_logs'])) {
    $logFile = '/var/log/apache2/error.log'; 
    if (!file_exists($logFile)) $logFile = $_SERVER['DOCUMENT_ROOT'] . '/var/log/apache2/error.log';
    if (file_exists($logFile) && is_writable($logFile)) file_put_contents($logFile, ''); 
    echo "ok"; exit;
}

$groupPk = 'id';
try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM `groups`");
    $cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($cols)) $groupPk = $cols[0]['Field'];
} catch (Exception $e) {}

function detectFactionName($dbRow, $pk) {
    $name = $dbRow['name'] ?? "Szervezet #" . ($dbRow[$pk] ?? '??');
    $prefix = trim($dbRow['groupPrefix'] ?? '');
    if (!empty($prefix)) return $name . " (" . strtoupper($prefix) . ")";
    return $name;
}

function formatShortNumber($num) {
    if ($num > 1000000) return round($num/1000000, 1).'M';
    if ($num > 1000) return round($num/1000, 1).'k';
    return number_format((float)$num);
}

// --- POSZT KÉRELMEK FELDOLGOZÁSA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- PRÉMIUM PONT (PP) GYORS KIOSZTÁS ---
    if ($_POST['action'] === 'give_pp') {
        $targetName = trim($_POST['target_username']);
        $ppAmount = (int)$_POST['pp_amount'];
        
        if ($targetName && $ppAmount !== 0) {
            try {
                $check = $pdo->prepare("SELECT accountId, premiumPoints FROM accounts WHERE username = ?");
                $check->execute([$targetName]);
                $targetAcc = $check->fetch(PDO::FETCH_ASSOC);
                
                if ($targetAcc) {
                    $newPP = max(0, $targetAcc['premiumPoints'] + $ppAmount);
                    $pdo->prepare("UPDATE accounts SET premiumPoints = ? WHERE accountId = ?")->execute([$newPP, $targetAcc['accountId']]);
                    $successMsg = "Sikeresen adtál/elvettél $ppAmount PP-t. Új egyenleg: $newPP PP ($targetName fiókján).";
                } else {
                    $errorMsg = "Nem található ilyen nevű felhasználó!";
                }
            } catch(Exception $e) { $errorMsg = "Adatbázis hiba PP adásakor."; }
        }
    }

    // --- KUPON RENDSZER FUNKCIÓK ---
    if ($_POST['action'] === 'add_coupon') {
        $cName = trim($_POST['coupon_name']);
        $cType = $_POST['coupon_type'];
        $cUsage = (int)$_POST['coupon_usage'];
        $cVal = (int)$_POST['coupon_value'];
        $cItem = (int)($_POST['coupon_item'] ?? 0);
        
        if ($cName && $cType && $cUsage > 0 && $cVal > 0) {
            try {
                $check = $pdo->prepare("SELECT dbID FROM coupons WHERE couponName = ?");
                $check->execute([$cName]);
                if ($check->rowCount() > 0) {
                    $errorMsg = "Már létezik ilyen kódú kupon az adatbázisban!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO coupons (couponName, couponType, couponUsage, couponValue, couponUsed, couponItem) VALUES (?, ?, ?, ?, '[ [ ] ]', ?)");
                    $stmt->execute([$cName, $cType, $cUsage, $cVal, $cItem]);
                    $successMsg = "A kupon ($cName) sikeresen legenerálva és beváltásra kész!";
                }
            } catch (Exception $e) { $errorMsg = "Hiba a kupon generálásakor: " . $e->getMessage(); }
        } else {
            $errorMsg = "Kérlek tölts ki minden mezőt helyesen a kuponhoz!";
        }
    }

    if ($_POST['action'] === 'delete_coupon') {
        $cId = (int)$_POST['coupon_id'];
        try {
            $pdo->prepare("DELETE FROM coupons WHERE dbID = ?")->execute([$cId]);
            $successMsg = "A kupon sikeresen törölve lett!";
        } catch (Exception $e) { $errorMsg = "Hiba a kupon törlésekor: " . $e->getMessage(); }
    }

    // --- ÚJ: 100% DINAMIKUS TÁBLA LÉTREHOZÁS (Ingatlan, Cég, MDC) ---
    if ($_POST['action'] === 'add_dynamic') {
        $table = $_POST['table_name'] ?? '';
        $fields = $_POST['fields'] ?? [];
        $allowedTables = ['interiors', 'companies', 'mdc_warrants'];
        
        if (in_array($table, $allowedTables) && !empty($fields)) {
            try {
                $columns = array_keys($fields);
                $values = array_values($fields);
                $placeholders = array_fill(0, count($fields), '?');
                
                $sql = "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $pdo->prepare($sql)->execute($values);
                $successMsg = "Új rekord sikeresen hozzáadva a(z) $table táblához!";
            } catch (Exception $e) {
                $errorMsg = "Hiba a hozzáadáskor: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Érvénytelen mentési kísérlet vagy üres adatok!";
        }
    }

    // --- DINAMIKUS TÁBLA TÖRLÉS (Ingatlan, Cég, MDC) ---
    if ($_POST['action'] === 'delete_dynamic') {
        $table = $_POST['table_name'] ?? '';
        $pk = $_POST['pk_name'] ?? '';
        $id = $_POST['item_id'] ?? '';
        $allowedTables = ['interiors', 'companies', 'mdc_warrants'];
        
        if (in_array($table, $allowedTables) && $pk && $id !== '') {
            try {
                $pdo->prepare("DELETE FROM `$table` WHERE `$pk` = ?")->execute([$id]);
                $successMsg = "Az adat sikeresen törölve a(z) $table táblából!";
            } catch (Exception $e) {
                $errorMsg = "Hiba a törlésnél: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Érvénytelen törlési kísérlet!";
        }
    }

    // TICKET (HIBAJEGY) FUNKCIÓK
    if ($_POST['action'] === 'reply_ticket') {
        $tid = (int)$_POST['ticket_id'];
        $msg = trim($_POST['message']);
        if ($msg && $tid) {
            try {
                $pdo->prepare("INSERT INTO ucp_ticket_messages (ticket_id, sender_id, is_admin, message) VALUES (?, ?, 1, ?)")->execute([$tid, $me['accountId'], $msg]);
                $pdo->prepare("UPDATE ucp_tickets SET status = 'answered', updated_at = NOW() WHERE id = ?")->execute([$tid]);
                $successMsg = "Válasz sikeresen elküldve a játékosnak!";
            } catch(Exception $e) { $errorMsg = "Hiba az üzenet küldésekor."; }
        }
    }
    if ($_POST['action'] === 'close_ticket') {
        $tid = (int)$_POST['ticket_id'];
        if ($tid) {
            try {
                $pdo->prepare("UPDATE ucp_tickets SET status = 'closed', updated_at = NOW() WHERE id = ?")->execute([$tid]);
                $successMsg = "A hibajegyet sikeresen lezártad!";
                $activeTicketId = null; 
            } catch(Exception $e) { $errorMsg = "Hiba a lezáráskor."; }
        }
    }
    if ($_POST['action'] === 'delete_ticket') {
        $tid = (int)$_POST['ticket_id'];
        if ($tid) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM ucp_ticket_messages WHERE ticket_id = ?")->execute([$tid]);
                $pdo->prepare("DELETE FROM ucp_tickets WHERE id = ?")->execute([$tid]);
                $pdo->commit();
                $successMsg = "A lezárt hibajegy és az üzenetei VÉGLEGESEN törölve lettek az adatbázisból!";
                $activeTicketId = null; 
            } catch(Exception $e) {
                $pdo->rollBack();
                $errorMsg = "Hiba a jegy törlésekor."; 
            }
        }
    }

    // TÖRLÉSI FUNKCIÓK
    if ($_POST['action'] === 'delete_item') {
        $type = $_POST['item_type'] ?? '';
        $idToDelete = (int)$_POST['item_id'];
        $rconProvided = trim($_POST['rcon_code'] ?? '');
        
        if (!defined('RCON_MASTER_CODE')) {
            $errorMsg = "A rendszer nincs megfelelően konfigurálva (RCON_MASTER_CODE hiányzik a config.php-ból).";
        } elseif ($rconProvided !== RCON_MASTER_CODE) {
            $errorMsg = "Hibás Mesterjelszó (RCON)! A törlés elutasítva.";
        } else {
            try {
                if ($type === 'character') {
                    $pdo->prepare("DELETE FROM characters WHERE characterId = ?")->execute([$idToDelete]);
                    $successMsg = "A karakter (#$idToDelete) véglegesen törölve lett az adatbázisból.";
                    $activeCharId = null; 
                } elseif ($type === 'vehicle') {
                    $pdo->prepare("DELETE FROM vehicles WHERE dbID = ?")->execute([$idToDelete]);
                    $successMsg = "A jármű (#$idToDelete) véglegesen törölve lett az adatbázisból.";
                } elseif ($type === 'faction') {
                    if (!$isOwner) {
                        $errorMsg = "A frakciók törléséhez Tulajdonos (11-es) admin szint szükséges!";
                    } else {
                        $pdo->prepare("DELETE FROM `groups` WHERE `$groupPk` = ?")->execute([$idToDelete]);
                        $successMsg = "A frakció (#$idToDelete) véglegesen törölve lett az adatbázisból.";
                        $activeGroupId = null;
                    }
                }
            } catch (Exception $e) { $errorMsg = "Adatbázis hiba a törlés során: " . $e->getMessage(); }
        }
    }

    // FIÓK KEZELÉS
    if ($_POST['action'] === 'update_account') {
        try {
            $pdo->prepare("UPDATE accounts SET adminLevel = ?, premiumPoints = ? WHERE accountId = ?")->execute([(int)$_POST['adminLevel'], (int)$_POST['premiumPoints'], (int)$_POST['acc_id']]);
            $successMsg = "A fiók adatai sikeresen frissítve!";
        } catch (Exception $e) { $errorMsg = "Hiba a mentésnél: " . $e->getMessage(); }
    }

    if ($_POST['action'] === 'ban_account') {
        try {
            $targetSerial = $_POST['serial'];
            $targetName = $_POST['username']; 
            $reason = $_POST['reason'];
            $hours = (int)$_POST['hours'];
            $expire = ($hours === 0) ? 0 : time() + ($hours * 3600); 

            $stmt = $pdo->prepare("INSERT INTO bans (playerName, adminName, banReason, serial, expireTimestamp) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$targetName, $me['username'], $reason, $targetSerial, $expire]);
            
            $successMsg = "A játékos sikeresen kitiltva a szerverről!";
        } catch (Exception $e) { $errorMsg = "Hiba a kitiltás során: " . $e->getMessage(); }
    }

    // SERIAL KEZELÉS
    if ($_POST['action'] === 'approve_serial') {
        $pdo->prepare("UPDATE accounts SET serial = ? WHERE accountId = ?")->execute([$_POST['new_serial'], (int)$_POST['acc_id']]);
        $pdo->prepare("UPDATE serial_requests SET status = 1 WHERE id = ?")->execute([(int)$_POST['req_id']]);
        $successMsg = "A Serial kérelem elfogadva! A játékos sorozatszáma frissült.";
    }
    if ($_POST['action'] === 'deny_serial') {
        $pdo->prepare("UPDATE serial_requests SET status = 2 WHERE id = ?")->execute([(int)$_POST['req_id']]);
        $successMsg = "A Serial kérelem sikeresen elutasítva!";
    }
    
    if ($_POST['action'] === 'update_char') {
        try {
            $pdo->beginTransaction();
            $sql = "UPDATE characters SET name = ?, money = ?, bankMoney = ?, skin = ?, health = ?, armor = ?, hunger = ?, thirst = ?, playedMinutes = ?, interior = ?, dimension = ?, posX = ?, posY = ?, posZ = ?, jailTime = ?, jailReason = ? WHERE characterId = ?";
            $pdo->prepare($sql)->execute([
                $_POST['name'], $_POST['money'], $_POST['bankMoney'], $_POST['skin'], 
                $_POST['health'], $_POST['armor'], $_POST['hunger'], $_POST['thirst'], 
                $_POST['playedMinutes'], $_POST['interior'], $_POST['dimension'],
                $_POST['posX'], $_POST['posY'], $_POST['posZ'],
                $_POST['jailTime'], $_POST['jailReason'],
                $activeCharId
            ]);
            
            if(isset($_POST['premiumPoints'])) {
                $accId = (int)$_POST['account_id'];
                $pdo->prepare("UPDATE accounts SET premiumPoints = ? WHERE accountId = ?")->execute([(int)$_POST['premiumPoints'], $accId]);
            }
            
            $pdo->commit();
            $successMsg = "Karakter és fiók adatai sikeresen mentve!";
        } catch (Exception $e) { 
            $pdo->rollBack();
            $errorMsg = "Hiba a mentésnél: " . $e->getMessage(); 
        }
    }
    
    if ($_POST['action'] === 'update_group') {
        try {
            $pdo->prepare("UPDATE `groups` SET name = ?, groupPrefix = ?, description = ?, balance = ? WHERE `$groupPk` = ?")->execute([$_POST['new_name'], $_POST['new_prefix'], $_POST['new_desc'], $_POST['new_balance'], $activeGroupId]);
            $successMsg = "Szervezet alapadatai frissítve!";
        } catch (Exception $e) { $errorMsg = "Hiba a mentésnél: " . $e->getMessage(); }
    }
    
    if (in_array($_POST['action'], ['add_member', 'update_rank', 'kick_member'])) {
        $grpStmt = $pdo->prepare("SELECT groupPrefix FROM `groups` WHERE `$groupPk` = ?");
        $grpStmt->execute([$activeGroupId]);
        $grp = $grpStmt->fetch(PDO::FETCH_ASSOC);
        $activeGroupPrefix = $grp ? $grp['groupPrefix'] : '';

        if ($activeGroupPrefix) {
            if ($_POST['action'] === 'add_member') {
                $check = $pdo->prepare("SELECT characterId FROM characters WHERE name = ?");
                $check->execute([trim($_POST['character_name'])]);
                $charData = $check->fetch(PDO::FETCH_ASSOC);
                
                if ($charData) {
                    $charId = $charData['characterId'];
                    $checkMem = $pdo->prepare("SELECT * FROM groupmembers WHERE characterId = ? AND groupPrefix = ?");
                    $checkMem->execute([$charId, $activeGroupPrefix]);
                    if ($checkMem->rowCount() == 0) {
                        $pdo->prepare("INSERT INTO groupmembers (characterId, groupPrefix, rank, isLeader) VALUES (?, ?, 1, 0)")->execute([$charId, $activeGroupPrefix]);
                        $successMsg = "A játékos sikeresen felvéve a szervezetbe!";
                    } else { $errorMsg = "A játékos már tagja a szervezetnek!"; }
                } else { $errorMsg = "Nem található ilyen nevű karakter!"; }
            }
            if ($_POST['action'] === 'update_rank') {
                $pdo->prepare("UPDATE groupmembers SET rank = ? WHERE characterId = ? AND groupPrefix = ?")->execute([(int)$_POST['new_rank'], (int)$_POST['member_id'], $activeGroupPrefix]);
                $successMsg = "Rang sikeresen módosítva!";
            }
            if ($_POST['action'] === 'kick_member') {
                $pdo->prepare("DELETE FROM groupmembers WHERE characterId = ? AND groupPrefix = ?")->execute([(int)$_POST['member_id'], $activeGroupPrefix]);
                $successMsg = "Játékos kirúgva!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Szerver Dashboard - Admin</title>
    <script src="https://kit.fontawesome.com/122db0fdde.js" crossorigin="anonymous"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: rgb(26, 27, 31); color: #d1d5db; font-family: 'Inter', sans-serif; }
        .navbar { background: rgba(17, 18, 20, 0.95); border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .navbar-brand { font-size: 20px; font-weight: 900; color: #fff; }
        .navbar-brand span { color: #e3b341; }
        .nav-links a { color: #9ca3af; text-decoration: none; margin-left: 20px; font-size: 14px; font-weight: 500; transition: 0.2s;}
        .nav-links a:hover, .nav-links a.active { color: #fff; }
        .main-content { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        
        .card { background: rgb(33, 35, 40); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; margin-bottom: 20px; overflow: hidden; }
        .card-header { padding: 15px 20px; font-weight: bold; display: flex; justify-content: space-between; align-items: center;}
        .card-body { padding: 20px; }

        .accordion { background: rgb(33, 35, 40); color: #e3b341; cursor: pointer; padding: 15px 20px; width: 100%; border: 1px solid rgba(255,255,255,0.05); text-align: left; font-weight: bold; margin-bottom: 5px; border-radius: 6px; font-size: 16px; transition: 0.2s;}
        .accordion.active, .accordion:hover { background: rgba(227, 179, 65, 0.1); }
        .panel { padding: 0 15px; background: rgb(26, 27, 31); max-height: 0; overflow: hidden; transition: max-height 0.2s ease-out; border-left: 3px solid #e3b341; margin-bottom: 10px;}
        
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; padding: 20px 0; }
        .action-box { background: rgb(33, 35, 40); border: 1px solid rgba(255,255,255,0.05); border-radius: 6px; padding: 20px; }
        
        input, select, textarea { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: white; padding: 12px; border-radius: 6px; width: 100%; margin-bottom: 15px; font-family: inherit; outline: none; }
        input:focus, textarea:focus, select:focus { border-color: #e3b341; }
        textarea { resize: vertical; min-height: 100px; }
        select option { background: rgb(33, 35, 40); color: #fff; }
        
        .search-bar { margin: 15px 0; border-color: #3b82f6; }
        
        .btn-save { background: #e3b341; color: #000; padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 5px; text-transform: uppercase; }
        .btn-small { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; color: white; font-weight: bold;}
        .btn-blue { background: #3b82f6; }
        .btn-green { background: #2ecc71; }
        .btn-red { background: #ef4444; }
        label { color: #9ca3af; font-size: 0.85em; display: block; margin-bottom: 5px; font-weight:bold; }
        
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: left; }
        
        .chat-container { display: flex; flex-direction: column; gap: 15px; max-height: 400px; overflow-y: auto; padding-right: 10px; margin-bottom: 20px; }
        .msg-bubble { padding: 15px; border-radius: 12px; max-width: 80%; font-size: 14px; line-height: 1.5; }
        .msg-player { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); align-self: flex-start; border-bottom-left-radius: 0; }
        .msg-admin { background: rgba(227, 179, 65, 0.1); border: 1px solid rgba(227, 179, 65, 0.3); color: #fff; align-self: flex-end; border-bottom-right-radius: 0; }
        .msg-author { font-size: 11px; color: #9ca3af; margin-bottom: 8px; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px; }
    </style>
</head>
<body>
    
    <nav class="navbar">
        <div class="navbar-brand">NorthSide <span>ADMIN</span></div>
        <div class="nav-links">
            <a href="dashboard.php">Kezdőlap</a>
            <a href="profile.php">Profilom</a>
            <a href="characters.php">Karakterek</a>
            <a href="adminpanel.php" class="active" style="color: #e3b341;"><i class="fa-solid fa-shield-halved"></i> Vezérlőpult</a>
            <a href="ads.php">Hirdetések</a>
            <a href="invoices.php">Számlák</a>
            <a href="logout.php" style="color: #ef4444;"><i class="fa-solid fa-power-off"></i> Kilépés</a>
        </div>
    </nav>

    <div class="main-content">
        
        <?php if ($successMsg): ?><div style="background: rgba(46,160,67,0.15); border: 1px solid #2ea043; color: #3fb950; padding: 15px; border-radius: 6px; margin-bottom: 20px;"><i class="fa-solid fa-check"></i> <?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg): ?><div style="background: rgba(248,81,73,0.15); border: 1px solid #da3633; color: #f85149; padding: 15px; border-radius: 6px; margin-bottom: 20px;"><i class="fa-solid fa-xmark"></i> <?= $errorMsg ?></div><?php endif; ?>

        <?php if ($activeTicketId): ?>
            <div class="card" style="border-color: #3b82f6;">
                <?php
                try {
                    $stmtT = $pdo->prepare("SELECT t.*, a.username FROM ucp_tickets t JOIN accounts a ON t.accountId = a.accountId WHERE t.id = ?");
                    $stmtT->execute([$activeTicketId]);
                    $t = $stmtT->fetch(PDO::FETCH_ASSOC);
                    
                    if ($t) {
                        $stmtM = $pdo->prepare("SELECT tm.*, a.username, a.adminLevel FROM ucp_ticket_messages tm JOIN accounts a ON tm.sender_id = a.accountId WHERE tm.ticket_id = ? ORDER BY tm.created_at ASC");
                        $stmtM->execute([$activeTicketId]);
                        $messages = $stmtM->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="card-header" style="background: rgba(59, 130, 246, 0.1); color:#3b82f6; font-size: 18px;">
                    <div><span style="color:#fff;">#<?= $t['id'] ?></span> <?= htmlspecialchars($t['subject']) ?></div>
                    <a href="adminpanel.php" class="btn-small" style="background: #374151; color: #fff; text-decoration: none;"><i class="fa-solid fa-arrow-left"></i> Vissza</a>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 20px; color:#9ca3af; font-size: 13px;">
                        Kategória: <strong style="color:#8b5cf6;"><?= htmlspecialchars($t['category'] ?? 'Általános') ?></strong> | 
                        Játékos: <strong style="color:#fff;"><?= htmlspecialchars($t['username']) ?></strong> | 
                        Állapot: <?= $t['status'] === 'open' ? '<span style="color:#ef4444; font-weight:bold;">Válaszra vár</span>' : ($t['status'] === 'answered' ? '<span style="color:#2ecc71; font-weight:bold;">Megválaszolva</span>' : '<span style="color:#9ca3af; font-weight:bold;">Lezárva</span>') ?>
                    </div>
                    
                    <div class="chat-container">
                        <?php foreach($messages as $m): 
                            $isAdminMsg = ($m['is_admin'] == 1);
                            $rankName = getAdminTitle($m['adminLevel']);
                        ?>
                        <div class="msg-bubble <?= $isAdminMsg ? 'msg-admin' : 'msg-player' ?>">
                            <div class="msg-author">
                                <?= $isAdminMsg ? "<i class='fa-solid fa-shield'></i> ".$m['username']." ($rankName)" : "<i class='fa-solid fa-user'></i> ".$m['username']." (Játékos)" ?>
                            </div>
                            <div style="white-space: pre-wrap;"><?= htmlspecialchars($m['message']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr style="border:0; border-top:1px solid rgba(255,255,255,0.05); margin: 20px 0;">
                    
                    <?php if ($t['status'] === 'open' || $t['status'] === 'answered'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="reply_ticket">
                            <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                            <label>Válasz küldése a játékosnak:</label>
                            <textarea name="message" required placeholder="Ide írd a választ..."></textarea>
                            <button type="submit" class="btn-save btn-blue"><i class="fa-solid fa-paper-plane"></i> Üzenet Elküldése</button>
                        </form>
                        
                        <form method="POST" style="margin-top: 15px;" onsubmit="return confirm('Biztosan lezárod az ügyet?');">
                            <input type="hidden" name="action" value="close_ticket">
                            <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn-save btn-green" style="background:#2ea043; color:#fff;"><i class="fa-solid fa-lock"></i> Hibajegy Lezárása (Megoldva)</button>
                        </form>
                    <?php else: ?>
                        <div style="text-align:center; padding: 20px; color:#9ca3af;">
                            <i class="fa-solid fa-lock" style="font-size:32px; opacity:0.5; margin-bottom:10px; display:block;"></i>
                            Ez a hibajegy már le van zárva. 
                        </div>
                        <form method="POST" style="margin-top: 15px; text-align:center;" onsubmit="return confirm('VIGYÁZAT! Biztosan véglegesen törlöd ezt a lezárt jegyet az adatbázisból?');">
                            <input type="hidden" name="action" value="delete_ticket">
                            <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn-save btn-red" style="background:transparent; border:1px solid #ef4444; color:#ef4444; width: auto; padding: 10px 20px;"><i class="fa-solid fa-trash"></i> Jegy Végleges Törlése az adatbázisból</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php 
                    } else { echo "<div class='card-body'>Hibajegy nem található.</div>"; }
                } catch(Exception $e){} ?>
            </div>

        <?php elseif ($activeAccId): ?>
            <div style="background: rgb(33, 35, 40); padding: 30px; border-radius: 12px; margin-top: 20px;">
                <?php
                try {
                    $accStmt = $pdo->prepare("SELECT * FROM accounts WHERE accountId = ?");
                    $accStmt->execute([$activeAccId]);
                    $a = $accStmt->fetch(PDO::FETCH_ASSOC);
                    if ($a):
                ?>
                <h2><i class="fa-solid fa-user-shield" style="color:#e3b341;"></i> Fiók: <?= htmlspecialchars($a['username']) ?> <span style="color:#9ca3af; font-size:14px;">(#<?= $a['accountId'] ?>)</span> <a href="adminpanel.php" style="float:right; color:#9ca3af; text-decoration:none;">&times; Vissza</a></h2>
                <hr style="border:0; border-top:1px solid rgba(255,255,255,0.05); margin: 15px 0 25px 0;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
                    <div class="action-box">
                        <h3 style="color:#3b82f6; margin-bottom:20px; margin-top:0;"><i class="fa-solid fa-pen-to-square"></i> Alapadatok és Jogosultságok</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_account">
                            <input type="hidden" name="acc_id" value="<?= $a['accountId'] ?>">
                            <label>Admin Szint (0 - 11):</label><input type="number" name="adminLevel" value="<?= $a['adminLevel'] ?>" min="0" max="11" required>
                            <label>Prémium Pont (PP):</label><input type="number" name="premiumPoints" value="<?= $a['premiumPoints'] ?? 0 ?>" min="0" required>
                            <button type="submit" class="btn-save btn-blue">MENTÉS</button>
                        </form>
                    </div>
                    <div class="action-box" style="border-color: rgba(239, 68, 68, 0.3);">
                        <h3 style="color:#ef4444; margin-bottom:20px; margin-top:0;"><i class="fa-solid fa-ban"></i> Fiók Kitiltása (Ban)</h3>
                        <form method="POST" onsubmit="return confirm('Biztosan kitiltod ezt a játékost a szerverről?');">
                            <input type="hidden" name="action" value="ban_account">
                            <input type="hidden" name="acc_id" value="<?= $a['accountId'] ?>">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($a['username']) ?>">
                            <input type="hidden" name="serial" value="<?= htmlspecialchars($a['serial'] ?? '') ?>">
                            <label>Kitiltás Indoka:</label><input type="text" name="reason" required placeholder="Pl.: Súlyos NonRP">
                            <label>Időtartam (Óra):</label>
                            <div style="display:flex; gap: 10px; align-items:center;">
                                <input type="number" name="hours" required value="0" min="0" style="margin-bottom:0;">
                                <span style="color:#9ca3af; font-size:12px; white-space:nowrap;">(0 = Örök Ban)</span>
                            </div>
                            <button type="submit" class="btn-save btn-red" style="margin-top: 25px;"><i class="fa-solid fa-gavel"></i> KITILTÁS KIOSZTÁSA</button>
                        </form>
                    </div>
                </div>
                <?php endif; } catch(Exception $e){} ?>
            </div>

        <?php elseif ($activeCharId): ?>
            <div style="background: rgb(33, 35, 40); padding: 30px; border-radius: 12px; margin-top: 20px;">
                <h2><i class="fa-solid fa-user-pen" style="color:#e3b341;"></i> Karakter Szerkesztése (#<?= $activeCharId ?>) <a href="adminpanel.php" style="float:right; color:#9ca3af; text-decoration:none;">&times; Vissza</a></h2>
                <hr style="border:0; border-top:1px solid rgba(255,255,255,0.05); margin: 15px 0;">
                
                <?php 
                try {
                    $char = $pdo->prepare("SELECT c.*, a.premiumPoints, a.accountId FROM characters c LEFT JOIN accounts a ON c.accountId = a.accountId WHERE c.characterId = ?");
                    $char->execute([$activeCharId]);
                    $c = $char->fetch(PDO::FETCH_ASSOC);
                ?>
                <form method="POST">
                    <input type="hidden" name="action" value="update_char">
                    <input type="hidden" name="account_id" value="<?= $c['accountId'] ?? 0 ?>">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                        
                        <div class="action-box">
                            <h3 style="color:#3b82f6; margin-top:0;"><i class="fa-solid fa-address-card"></i> Alapadatok</h3>
                            <label>Karakter neve:</label><input type="text" name="name" value="<?= htmlspecialchars($c['name'] ?? '') ?>">
                            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                                <div><label>Készpénz ($):</label><input type="number" name="money" value="<?= $c['money'] ?? 0 ?>"></div>
                                <div><label>Bank ($):</label><input type="number" name="bankMoney" value="<?= $c['bankMoney'] ?? 0 ?>"></div>
                                <div><label style="color:#e3b341;">Prémium (PP):</label><input type="number" name="premiumPoints" value="<?= $c['premiumPoints'] ?? 0 ?>"></div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                <div><label>Játszott perc:</label><input type="number" name="playedMinutes" value="<?= $c['playedMinutes'] ?? 0 ?>"></div>
                                <div><label>Skin ID:</label><input type="number" name="skin" value="<?= $c['skin'] ?? 0 ?>"></div>
                            </div>
                        </div>

                        <div class="action-box">
                            <h3 style="color:#2ecc71; margin-top:0;"><i class="fa-solid fa-heart-pulse"></i> Állapot</h3>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                <div><label>Élet (%):</label><input type="number" step="0.1" name="health" value="<?= $c['health'] ?? 100 ?>"></div>
                                <div><label>Páncél (%):</label><input type="number" step="0.1" name="armor" value="<?= $c['armor'] ?? 0 ?>"></div>
                                <div><label>Éhség (%):</label><input type="number" step="0.1" name="hunger" value="<?= $c['hunger'] ?? 100 ?>"></div>
                                <div><label>Szomj (%):</label><input type="number" step="0.1" name="thirst" value="<?= $c['thirst'] ?? 100 ?>"></div>
                            </div>
                        </div>

                        <div class="action-box">
                            <h3 style="color:#e67e22; margin-top:0;"><i class="fa-solid fa-location-dot"></i> Pozíció</h3>
                            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                                <div><label>X koord:</label><input type="number" step="any" name="posX" value="<?= $c['posX'] ?? 0 ?>"></div>
                                <div><label>Y koord:</label><input type="number" step="any" name="posY" value="<?= $c['posY'] ?? 0 ?>"></div>
                                <div><label>Z koord:</label><input type="number" step="any" name="posZ" value="<?= $c['posZ'] ?? 0 ?>"></div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                <div><label>Interior:</label><input type="number" name="interior" value="<?= $c['interior'] ?? 0 ?>"></div>
                                <div><label>Dimenzió:</label><input type="number" name="dimension" value="<?= $c['dimension'] ?? 0 ?>"></div>
                            </div>
                        </div>

                        <div class="action-box" style="border-color: rgba(239, 68, 68, 0.3);">
                            <h3 style="color:#ef4444; margin-top:0;"><i class="fa-solid fa-bars-staggered"></i> Admin Jail</h3>
                            <label>Büntetés Ideje (Perc, 0 = Nincs):</label>
                            <input type="number" name="jailTime" value="<?= $c['jailTime'] ?? 0 ?>">
                            <label>Jail Indok (Opcionális):</label>
                            <input type="text" name="jailReason" value="<?= htmlspecialchars($c['jailReason'] ?? '') ?>" placeholder="Miért lett berakva?">
                        </div>

                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 20px;">
                        <button type="submit" class="btn-save btn-blue" style="flex: 3; font-size: 16px;"><i class="fa-solid fa-floppy-disk"></i> MINDEN ADAT MENTÉSE</button>
                        <button type="button" class="btn-save" style="flex: 1; background:#ef4444; color:#fff;" onclick="promptAndSubmitChar(this, 'Kérlek add meg az RCON jelszót a karakter végleges törléséhez:')"><i class="fa-solid fa-trash"></i> KARAKTER TÖRLÉSE</button>
                    </div>
                </form>
                
                <form id="hidden-delete-form" method="POST" style="display:none;">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="item_type" value="character">
                    <input type="hidden" name="item_id" value="<?= $activeCharId ?>">
                    <input type="hidden" name="rcon_code" id="hidden-rcon-input" value="">
                </form>
                <?php } catch(Exception $e){} ?>
            </div>

        <?php elseif ($activeGroupId): ?>
            <div style="background: rgb(33, 35, 40); padding: 30px; border-radius: 12px; margin-top: 20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom:15px; margin-bottom:20px;">
                    <?php 
                    $group = $pdo->prepare("SELECT * FROM `groups` WHERE `$groupPk` = ?");
                    $group->execute([$activeGroupId]);
                    $g = $group->fetch(PDO::FETCH_ASSOC);
                    
                    $rawRanks = $g['rankNames'] ?? '';
                    $parsedRanks = [];
                    $decoded = json_decode($rawRanks, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        if (isset($decoded[0]) && is_array($decoded[0])) $decoded = $decoded[0];
                        foreach ($decoded as $k => $v) {
                            $rName = is_array($v) ? $v[0] : (string)$v;
                            $rId = (is_numeric($k) && isset($decoded[0])) ? $k + 1 : $k;
                            $parsedRanks[$rId] = $rName;
                        }
                    } elseif (strpos($rawRanks, ',') !== false && strpos($rawRanks, '{') === false && strpos($rawRanks, '[') === false) {
                        $parts = explode(',', $rawRanks);
                        foreach ($parts as $k => $v) $parsedRanks[$k + 1] = trim($v);
                    }
                    if (empty($parsedRanks)) { for($i=1; $i<=15; $i++) $parsedRanks[$i] = "Rang " . $i; }
                    ?>
                    <h2 style="margin:0;"><span style="color:#3b82f6;">Frakció Szerkesztése:</span> <?= detectFactionName($g, $groupPk) ?> (ID: <?= $activeGroupId ?>)</h2>
                    <a href="adminpanel.php" style="background: #374151; color: #fff; padding: 10px 15px; border-radius: 6px; text-decoration: none; font-weight: bold;"><i class="fa-solid fa-arrow-left"></i> Vissza</a>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:30px;">
                    <div style="display:flex; flex-direction:column; gap:20px;">
                        <div class="action-box">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_group">
                                <label>Frakció Neve:</label>
                                <input type="text" name="new_name" value="<?= htmlspecialchars($g['name'] ?? '') ?>" required>
                                <label>Frakció Prefix (Rövidítés):</label>
                                <input type="text" name="new_prefix" value="<?= htmlspecialchars($g['groupPrefix'] ?? '') ?>" required>
                                <label>Frakció Leírása:</label>
                                <input type="text" name="new_desc" value="<?= htmlspecialchars($g['description'] ?? '') ?>">
                                <label>Frakció Kassza ($):</label>
                                <input type="number" name="new_balance" value="<?= $g['balance'] ?? 0 ?>">
                                <button type="submit" class="btn-save btn-blue"><i class="fa-solid fa-floppy-disk"></i> ADATOK FRISSÍTÉSE</button>
                            </form>
                        </div>
                        
                        <div class="action-box">
                            <h3 style="color:#3b82f6; margin-top:0;">Új tag felvétele</h3>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="add_member">
                                <input type="hidden" name="faction_prefix" value="<?= $g['groupPrefix'] ?>">
                                <label>Karakter pontos neve (pl. John_Doe):</label>
                                <input type="text" name="character_name" required placeholder="Név...">
                                <button type="submit" class="btn-save btn-green">HOZZÁADÁS A FRAKCIÓHOZ</button>
                            </form>
                        </div>

                        <?php if($isOwner): ?>
                        <div class="action-box" style="border-color: rgba(239, 68, 68, 0.3);">
                            <form method="POST" style="margin:0;" onsubmit="return confirm('VIGYÁZAT! A frakció VÉGLEGESEN TÖRLŐDIK az adatbázisból! Biztos vagy benne?');">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="item_type" value="faction">
                                <input type="hidden" name="item_id" value="<?= $activeGroupId ?>">
                                <button type="button" class="btn-save" style="background:#ef4444; color:#fff;" onclick="promptAndSubmit(this, 'Kérlek add meg a rendszer Mesterjelszavát (RCON) a FRAKCIÓ törléséhez:')"><i class="fa-solid fa-trash"></i> FRAKCIÓ TÖRLÉSE</button>
                                <input type="hidden" name="rcon_code" class="rcon-input" value="">
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="action-box" style="align-self: start;">
                        <h3 style="color:#e3b341; margin-top:0;"><i class="fa-solid fa-users"></i> Frakció Tagok</h3>
                        <div style="max-height: 500px; overflow-y: auto;">
                            <?php
                            $stmtMem = $pdo->prepare("SELECT c.characterId, c.name, gm.rank, gm.isLeader FROM groupmembers gm JOIN characters c ON gm.characterId = c.characterId WHERE gm.groupPrefix = ? ORDER BY gm.rank DESC");
                            $stmtMem->execute([$g['groupPrefix']]);
                            $members = $stmtMem->fetchAll(PDO::FETCH_ASSOC);
                            
                            if(empty($members)): ?>
                                <p style="color:#9ca3af; text-align:center;">Nincsenek tagok a szervezetben.</p>
                            <?php else: ?>
                                <table class="data-table">
                                    <thead><tr><th>Név</th><th>Rang</th><th>Műveletek</th></tr></thead>
                                    <tbody>
                                        <?php foreach($members as $m): ?>
                                        <tr>
                                            <td style="font-weight:bold; color:#fff;"><?= str_replace('_', ' ', htmlspecialchars($m['name'])) ?> <?= $m['isLeader'] ? '<i class="fa-solid fa-crown" style="color:#e3b341; font-size:10px;"></i>' : '' ?></td>
                                            <td>
                                                <form method="POST" style="margin:0; display:flex; gap:5px;">
                                                    <input type="hidden" name="action" value="update_rank">
                                                    <input type="hidden" name="faction_prefix" value="<?= $g['groupPrefix'] ?>">
                                                    <input type="hidden" name="member_id" value="<?= $m['characterId'] ?>">
                                                    <select name="new_rank" style="margin:0; padding:4px;">
                                                        <?php foreach ($parsedRanks as $rId => $rName): ?>
                                                            <option value="<?= $rId ?>" <?= ($m['rank'] == $rId) ? 'selected' : '' ?>><?= $rId ?>. <?= htmlspecialchars($rName) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn-small btn-blue"><i class="fa-solid fa-save"></i></button>
                                                </form>
                                            </td>
                                            <td style="text-align:right;">
                                                <form method="POST" style="margin:0;" onsubmit="return confirm('Biztosan kirúgod ezt a tagot?');">
                                                    <input type="hidden" name="action" value="kick_member">
                                                    <input type="hidden" name="faction_prefix" value="<?= $g['groupPrefix'] ?>">
                                                    <input type="hidden" name="member_id" value="<?= $m['characterId'] ?>">
                                                    <button type="submit" class="btn-small btn-red"><i class="fa-solid fa-user-xmark"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <button class="accordion"><i class="fa-solid fa-headset"></i> Support Jegyek Kezelése <?php if($openTicketsCount>0) echo "<span style='background:#ef4444; color:#fff; padding:2px 8px; border-radius:10px; font-size:12px; margin-left:10px;'>$openTicketsCount Új</span>"; ?></button>
            <div class="panel">
                <div style="padding: 20px 0;">
                    <?php
                    try {
                        $tickets = $pdo->query("SELECT t.*, a.username FROM ucp_tickets t JOIN accounts a ON t.accountId = a.accountId ORDER BY t.updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                        if (empty($tickets)):
                    ?>
                        <div style="color:#9ca3af; text-align:center; padding: 20px;">Nincs egyetlen hibajegy sem a rendszerben. 🎉</div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead><tr><th>ID</th><th>Kategória</th><th>Játékos</th><th>Tárgy</th><th>Állapot</th><th style="text-align:right;">Műveletek</th></tr></thead>
                            <tbody>
                                <?php foreach($tickets as $t): ?>
                                <tr style="background: rgba(0,0,0,0.2);">
                                    <td style="color:#3b82f6; font-weight:bold;">#<?= $t['id'] ?></td>
                                    <td style="color:#8b5cf6; font-weight:bold; font-size:13px;"><?= htmlspecialchars($t['category'] ?? 'Általános') ?></td>
                                    <td style="color:#fff;"><i class="fa-solid fa-user" style="color:#9ca3af;"></i> <?= htmlspecialchars($t['username']) ?></td>
                                    <td style="color:#d1d5db; max-width: 300px;"><?= htmlspecialchars($t['subject']) ?></td>
                                    <td>
                                        <?php if($t['status'] === 'open'): ?>
                                            <span style="background:rgba(239, 68, 68, 0.2); color:#ef4444; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:bold;">Válaszra Vár</span>
                                        <?php elseif($t['status'] === 'answered'): ?>
                                            <span style="background:rgba(46, 204, 113, 0.2); color:#2ecc71; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:bold;">Megválaszolva</span>
                                        <?php else: ?>
                                            <span style="background:rgba(255, 255, 255, 0.1); color:#9ca3af; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:bold;">Lezárva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <a href="?view_ticket=<?= $t['id'] ?>" class="btn-small btn-blue" style="text-decoration:none;"><i class="fa-solid fa-eye"></i> Kezelés</a>
                                        
                                        <?php if($t['status'] === 'closed'): ?>
                                        <form method="POST" style="display:inline-block; margin:0; margin-left:5px;" onsubmit="return confirm('Biztosan VÉGLEGESEN törlöd ezt a jegyet az adatbázisból?');">
                                            <input type="hidden" name="action" value="delete_ticket">
                                            <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn-small btn-red" title="Végleges törlés"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php 
                        endif;
                    } catch (Exception $e) { echo "<div style='color:#f59e0b;'>A ticket tábla még nem jött létre.</div>"; }
                    ?>
                </div>
            </div>

            <button class="accordion"><i class="fa-solid fa-star"></i> Prémium (PP) Kezelés</button>
            <div class="panel">
                <div style="padding: 20px 0; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="action-box" style="border-color: #e3b341;">
                        <h3 style="color:#e3b341; margin-top:0; margin-bottom: 15px;"><i class="fa-solid fa-gift"></i> PP Kiosztása / Elvétele</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="give_pp">
                            <label>Játékos (Fiók) Neve:</label>
                            <input type="text" name="target_username" required placeholder="Pl. JohnDoe">
                            
                            <label>Mennyiség (PP) <span style="color:#9ca3af; font-weight:normal;">(Mínusz érték is lehet)</span>:</label>
                            <input type="number" name="pp_amount" required placeholder="Pl. 1000 vagy -500">
                            
                            <button type="submit" class="btn-save btn-blue" style="margin-top: 10px;"><i class="fa-solid fa-check"></i> Végrehajtás</button>
                        </form>
                    </div>
                    
                    <div class="action-box">
                        <h3 style="color:#3b82f6; margin-top:0; margin-bottom: 15px;"><i class="fa-solid fa-chart-line"></i> Top 10 PP Milliomos (Fiókok)</h3>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <table class="data-table">
                                <thead><tr><th>Fiók Neve</th><th style="text-align:right;">PP Egyenleg</th></tr></thead>
                                <tbody>
                                    <?php
                                    try {
                                        $topPP = $pdo->query("SELECT username, premiumPoints FROM accounts WHERE premiumPoints > 0 ORDER BY premiumPoints DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
                                        if (empty($topPP)) { echo "<tr><td colspan='2' style='text-align:center; color:#9ca3af;'>Senkinek nincs PP-je.</td></tr>"; }
                                        else {
                                            foreach($topPP as $t): 
                                    ?>
                                    <tr style="background: rgba(0,0,0,0.2);">
                                        <td style="font-weight:bold; color:#fff;"><i class="fa-solid fa-user" style="color:#9ca3af; margin-right:5px;"></i> <?= htmlspecialchars($t['username']) ?></td>
                                        <td style="font-weight:bold; color:#e3b341; text-align:right;"><?= number_format($t['premiumPoints'], 0, '', ' ') ?> PP</td>
                                    </tr>
                                    <?php endforeach; 
                                        }
                                    } catch(Exception $e) { echo "<tr><td colspan='2' style='color:#ef4444; text-align:center;'>Hiba a lekérdezéskor.</td></tr>"; }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <button class="accordion"><i class="fa-solid fa-ticket"></i> Kuponok / Promóciós Kódok Kezelése</button>
            <div class="panel">
                <div style="padding: 20px 0; display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
                    <div class="action-box" style="border-color: #e3b341;">
                        <h3 style="color:#e3b341; margin-top:0; margin-bottom: 15px;"><i class="fa-solid fa-plus-circle"></i> Új Kupon Generálása</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_coupon">
                            <label>Kupon Kódja (pl. NYAR2026):</label>
                            <input type="text" name="coupon_name" required placeholder="Kupon neve/kódja">
                            
                            <label>Típus:</label>
                            <select name="coupon_type" id="couponTypeSelect" required onchange="toggleItemField()">
                                <option value="pp">Prémium Pont (PP)</option>
                                <option value="money">Készpénz ($)</option>
                                <option value="item">Tárgy (Item)</option>
                            </select>
                            
                            <label>Érték / Darabszám:</label>
                            <input type="number" name="coupon_value" required value="1" min="1">
                            
                            <div id="couponItemDiv" style="display:none;">
                                <label style="color:#8b5cf6;">Tárgy (Item) ID-ja:</label>
                                <input type="number" name="coupon_item" value="0" min="0">
                            </div>
                            
                            <label>Hányszor lehessen felhasználni?</label>
                            <input type="number" name="coupon_usage" required value="1" min="1">
                            
                            <button type="submit" class="btn-save btn-green" style="margin-top: 10px;"><i class="fa-solid fa-magic"></i> Kupon Létrehozása</button>
                        </form>
                    </div>
                    
                    <div class="action-box">
                        <h3 style="color:#3b82f6; margin-top:0; margin-bottom: 15px;"><i class="fa-solid fa-list"></i> Jelenlegi Kuponok (coupons)</h3>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <table class="data-table">
                                <thead><tr><th>Kód</th><th>Típus</th><th>Érték</th><th>Felhasználás</th><th style="text-align:right;">Művelet</th></tr></thead>
                                <tbody>
                                    <?php
                                    try {
                                        $coupons = $pdo->query("SELECT * FROM coupons ORDER BY dbID DESC")->fetchAll(PDO::FETCH_ASSOC);
                                        if (empty($coupons)) { echo "<tr><td colspan='5' style='text-align:center; color:#9ca3af;'>Nincsenek aktív kuponok.</td></tr>"; }
                                        else {
                                            foreach($coupons as $c): 
                                                $cType = $c['couponType'];
                                                $badgeColor = ($cType === 'pp') ? '#e3b341' : (($cType === 'money') ? '#2ecc71' : '#8b5cf6');
                                                $badgeLabel = ($cType === 'pp') ? 'Prémium Pont' : (($cType === 'money') ? 'Készpénz' : 'Item (ID: '.$c['couponItem'].')');
                                                $valFormat = ($cType === 'money') ? '$'.number_format($c['couponValue'], 0, '',' ') : $c['couponValue'];
                                    ?>
                                    <tr style="background: rgba(0,0,0,0.2);">
                                        <td style="font-weight:bold; color:#fff; font-family:monospace;"><?= htmlspecialchars($c['couponName']) ?></td>
                                        <td><span style="background: <?= $badgeColor ?>22; color: <?= $badgeColor ?>; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; border: 1px solid <?= $badgeColor ?>44;"><?= $badgeLabel ?></span></td>
                                        <td style="font-weight:bold; color:#d1d5db;"><?= $valFormat ?></td>
                                        <td style="font-size:12px; color:#9ca3af;">Max: <span style="color:#fff;"><?= $c['couponUsage'] ?></span></td>
                                        <td style="text-align:right;">
                                            <form method="POST" style="margin:0;" onsubmit="return confirm('Biztosan törlöd ezt a kupont? Többet nem fogják tudni beváltani!');">
                                                <input type="hidden" name="action" value="delete_coupon">
                                                <input type="hidden" name="coupon_id" value="<?= $c['dbID'] ?>">
                                                <button type="submit" class="btn-small btn-red"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; 
                                        }
                                    } catch(Exception $e) { echo "<tr><td colspan='5' style='color:#ef4444; text-align:center;'>A coupons tábla nem elérhető.</td></tr>"; }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $extraTables = [
                ['table' => 'interiors', 'name' => 'Ingatlanok (Interiors)', 'icon' => 'fa-house-chimney'],
                ['table' => 'companies', 'name' => 'Cégek (Companies)', 'icon' => 'fa-building'],
                ['table' => 'mdc_warrants', 'name' => 'MDC - Körözési Lista', 'icon' => 'fa-handcuffs']
            ];
            ?>
            <?php foreach($extraTables as $ext): ?>
                <button class="accordion"><i class="fa-solid <?= $ext['icon'] ?>"></i> <?= $ext['name'] ?> Kezelése</button>
                <div class="panel">
                    <div style="padding: 20px 0; max-height: 400px; overflow-y: auto;">
                        <?php
                        try {
                            $stmtCol = $pdo->query("SHOW COLUMNS FROM `{$ext['table']}`");
                            $cols = $stmtCol->fetchAll(PDO::FETCH_ASSOC);
                            $pk = '';
                            $formFields = [];
                            foreach($cols as $c) {
                                if($c['Key'] === 'PRI' || strpos(strtolower($c['Extra']), 'auto_increment') !== false) {
                                    $pk = $c['Field'];
                                } else {
                                    $formFields[] = $c['Field'];
                                }
                            }
                            if(empty($pk)) $pk = $cols[0]['Field']; // Fallback
                            
                            // Dinamikus hozzáadó űrlap
                            echo "<div class='action-box' style='margin-bottom: 20px; padding: 15px; border-color: rgba(59, 130, 246, 0.3); background: rgba(59, 130, 246, 0.05);'>";
                            echo "<h4 style='margin-top:0; color:#3b82f6;'><i class='fa-solid fa-plus'></i> Új Hozzáadása: {$ext['name']}</h4>";
                            if($ext['table'] === 'interiors') echo "<p style='font-size:11px; color:#ef4444; margin-top:-10px; margin-bottom:10px;'><b>VIGYÁZAT:</b> Az ingatlanok weben keresztüli létrehozása veszélyes, pontos koordinátákat igényel!</p>";
                            
                            echo "<form method='POST' style='display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin:0;'>";
                            echo "<input type='hidden' name='action' value='add_dynamic'>";
                            echo "<input type='hidden' name='table_name' value='{$ext['table']}'>";
                            
                            foreach($formFields as $field) {
                                echo "<div>";
                                echo "<label style='color:#9ca3af; font-size:11px; text-transform:uppercase; margin-bottom:5px; display:block;'>" . htmlspecialchars($field) . "</label>";
                                echo "<input type='text' name='fields[" . htmlspecialchars($field) . "]' required placeholder='" . htmlspecialchars($field) . "' style='margin-bottom:0;'>";
                                echo "</div>";
                            }
                            echo "<div style='grid-column: 1 / -1;'>";
                            echo "<button type='submit' class='btn-save btn-green' style='margin:0; max-width: 250px;'><i class='fa-solid fa-check'></i> Rögzítés az adatbázisban</button>";
                            echo "</div>";
                            echo "</form></div>";
                            
                            $rows = $pdo->query("SELECT * FROM `{$ext['table']}` ORDER BY `$pk` DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
                            
                            if(empty($rows)) {
                                echo "<div style='color:#9ca3af; text-align:center;'>Nincs adat a(z) {$ext['table']} táblában.</div>";
                            } else {
                                echo "<table class='data-table'><thead><tr>";
                                $keys = array_keys($rows[0]);
                                $displayKeys = array_slice($keys, 0, 6);
                                foreach($displayKeys as $k) echo "<th>" . htmlspecialchars($k) . "</th>";
                                echo "<th style='text-align:right;'>Művelet</th></tr></thead><tbody>";
                                
                                foreach($rows as $r) {
                                    echo "<tr style='background: rgba(0,0,0,0.2);'>";
                                    foreach($displayKeys as $k) {
                                        $val = (string)$r[$k];
                                        if(strlen($val) > 40) $val = substr($val, 0, 37).'...';
                                        echo "<td>" . htmlspecialchars($val) . "</td>";
                                    }
                                    echo "<td style='text-align:right;'>
                                        <form method='POST' style='margin:0;' onsubmit=\"return confirm('VIGYÁZAT! Véglegesen törlöd az adatot a szerverről. Folytatod?');\">
                                            <input type='hidden' name='action' value='delete_dynamic'>
                                            <input type='hidden' name='table_name' value='{$ext['table']}'>
                                            <input type='hidden' name='pk_name' value='$pk'>
                                            <input type='hidden' name='item_id' value='".$r[$pk]."'>
                                            <button type='submit' class='btn-small btn-red'><i class='fa-solid fa-trash'></i></button>
                                        </form>
                                    </td></tr>";
                                }
                                echo "</tbody></table>";
                            }
                        } catch (Exception $e) {
                            echo "<div style='color:#ef4444; text-align:center;'>A(z) {$ext['table']} tábla nem található az adatbázisban.</div>";
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <button class="accordion"><i class="fa-solid fa-desktop"></i> Felfüggesztett Serial Váltások</button>
            <div class="panel">
                <div style="padding: 20px 0;">
                    <?php
                    try {
                        $pendingReqs = $pdo->query("SELECT r.*, a.username FROM serial_requests r JOIN accounts a ON r.accountId = a.accountId WHERE r.status = 0 ORDER BY r.id ASC")->fetchAll(PDO::FETCH_ASSOC);
                        if (empty($pendingReqs)):
                    ?>
                        <div style="color:#9ca3af; text-align:center; padding: 20px;">Nincs jelenleg egyetlen elbírálásra váró Serial kérelem sem. 🎉</div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead><tr><th>Fiók</th><th>Indoklás</th><th>Régi Serial</th><th>Új Serial</th><th style="text-align:right;">Műveletek</th></tr></thead>
                            <tbody>
                                <?php foreach($pendingReqs as $r): ?>
                                <tr style="background: rgba(0,0,0,0.2);">
                                    <td style="font-weight:bold; color:#3b82f6;"><?= htmlspecialchars($r['username']) ?> <br><span style="font-size:11px; color:#6b7280;"><?= $r['requestDate'] ?? '' ?></span></td>
                                    <td style="color:#d1d5db; max-width: 250px; font-style: italic;">"<?= htmlspecialchars($r['reason']) ?>"</td>
                                    <td style="font-family:monospace; color:#ef4444; font-size: 11px;"><?= htmlspecialchars($r['oldSerial']) ?></td>
                                    <td style="font-family:monospace; color:#2ecc71; font-size: 11px;"><?= htmlspecialchars($r['newSerial']) ?></td>
                                    <td style="text-align:right;">
                                        <form method="POST" style="display:inline-block; margin:0;">
                                            <input type="hidden" name="action" value="approve_serial">
                                            <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="acc_id" value="<?= $r['accountId'] ?>">
                                            <input type="hidden" name="new_serial" value="<?= $r['newSerial'] ?>">
                                            <button type="submit" class="btn-small btn-green" title="Elfogadás"><i class="fa-solid fa-check"></i> Elfogad</button>
                                        </form>
                                        <form method="POST" style="display:inline-block; margin:0; margin-left:5px;">
                                            <input type="hidden" name="action" value="deny_serial">
                                            <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn-small btn-red" title="Elutasítás"><i class="fa-solid fa-xmark"></i> Elvet</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php 
                        endif;
                    } catch (Exception $e) { echo "<div style='color:#f59e0b;'>A Serial kérelmek tábla még nem jött létre.</div>"; }
                    ?>
                </div>
            </div>

            <button class="accordion"><i class="fa-solid fa-users"></i> Játékosok / Fiókok Kezelése</button>
            <div class="panel">
                <div style="padding: 20px 0; max-height: 500px; overflow-y: auto;">
                    <input type="text" id="search-accounts" class="search-bar" onkeyup="filterTable('search-accounts', 'accounts-table')" placeholder="Keresés játékosnév alapján...">
                    <table class="data-table" id="accounts-table">
                        <thead>
                            <tr><th>ID</th><th>Játékos Neve</th><th>Email cím</th><th>Regisztráció</th><th>Admin Szint</th><th style="text-align:right;">Műveletek</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $accStmt = $pdo->query("SELECT * FROM accounts ORDER BY accountId DESC LIMIT 1000");
                                $accountsList = $accStmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach($accountsList as $accRow):
                                    $rawDate = $accRow['created_at'] ?? $accRow['registerDate'] ?? 'Ismeretlen';
                                    $fmtDate = ($rawDate !== 'Ismeretlen') ? date('Y.m.d. H:i', strtotime($rawDate)) : $rawDate;
                            ?>
                            <tr style="background: rgba(0,0,0,0.2);">
                                <td style="color:#9ca3af;">#<?= $accRow['accountId'] ?></td>
                                <td style="font-weight:bold; color:#fff;"><?= htmlspecialchars($accRow['username']) ?></td>
                                <td style="color:#9ca3af; font-size: 13px;"><?= htmlspecialchars($accRow['email'] ?? 'Nincs') ?></td>
                                <td style="color:#9ca3af; font-size: 13px;"><?= $fmtDate ?></td>
                                <td style="color:#3b82f6; font-weight:bold;"><?= $accRow['adminLevel'] ?></td>
                                <td style="text-align:right;">
                                    <a href="?edit_acc=<?= $accRow['accountId'] ?>" class="btn-small btn-blue" style="text-decoration:none;"><i class="fa-solid fa-pen"></i> Kezelés</a>
                                </td>
                            </tr>
                            <?php endforeach; } catch (Exception $e) {} ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <button class="accordion"><i class="fa-solid fa-gavel"></i> Banlista (Kitiltások)</button>
            <div class="panel">
                <div style="padding: 20px 0; max-height: 400px; overflow-y: auto;">
                    <input type="text" id="search-bans" class="search-bar" onkeyup="filterTable('search-bans', 'bans-table')" placeholder="Keresés kitiltott név alapján...">
                    <table class="data-table" id="bans-table">
                        <thead><tr><th>Kitiltott Név / Serial</th><th>Admin</th><th>Indok</th><th>Lejárat</th></tr></thead>
                        <tbody>
                            <?php
                            try {
                                $bans = $pdo->query("SELECT * FROM bans ORDER BY 1 DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
                                if(empty($bans)) { echo "<tr><td colspan='4' style='text-align:center;'>Nincs aktív kitiltás.</td></tr>"; }
                                else {
                                    foreach($bans as $b): 
                                        $expire = $b['expireTimestamp'] ?? 0;
                                        $isPerma = ($expire == 0);
                                    ?>
                                    <tr>
                                        <td style="color:#ef4444; font-weight:bold;"><?= htmlspecialchars($b['playerName'] ?? $b['serial'] ?? 'Ismeretlen') ?></td>
                                        <td style="color:#e3b341;"><?= htmlspecialchars($b['adminName'] ?? 'Rendszer') ?></td>
                                        <td><?= htmlspecialchars($b['banReason'] ?? 'Nincs indok') ?></td>
                                        <td style="color:#9ca3af;">
                                            <?= $isPerma ? '<span style="background: rgba(248,81,73,0.2); color:#ff7b72; padding:2px 6px; border-radius:4px;"><i class="fa-solid fa-infinity"></i> Örök</span>' : date('Y.m.d H:i', $expire) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; 
                                }
                            } catch(Exception $e) { echo "<tr><td colspan='4' style='color:#ef4444; text-align:center;'>A bans tábla nem található az adatbázisban.</td></tr>"; }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <button class="accordion"><i class="fa-solid fa-bars-staggered"></i> Aktív Börtönbüntetések (Admin Jailek)</button>
            <div class="panel">
                <div style="padding: 20px 0; max-height: 400px; overflow-y: auto;">
                    <input type="text" id="search-jails" class="search-bar" onkeyup="filterTable('search-jails', 'jails-table')" placeholder="Keresés karakter alapján...">
                    <table class="data-table" id="jails-table">
                        <thead><tr><th>ID</th><th>Karakter Név</th><th>Indok</th><th>Hátralévő Idő</th></tr></thead>
                        <tbody>
                            <?php
                            try {
                                $stmtJail = $pdo->query("SELECT characterId, name, jailTime, jailReason FROM characters WHERE jailTime > 0 ORDER BY characterId DESC");
                                $jailsList = $stmtJail->fetchAll(PDO::FETCH_ASSOC);
                                if(empty($jailsList)) { echo "<tr><td colspan='4' style='text-align:center;'>Nincs egyetlen aktív admin jail sem.</td></tr>"; }
                                else {
                                    foreach($jailsList as $j): ?>
                                    <tr>
                                        <td style="color:#9ca3af;">#<?= $j['characterId'] ?></td>
                                        <td style="font-weight:bold; color:#fff;"><?= htmlspecialchars(str_replace('_', ' ', $j['name'])) ?></td>
                                        <td style="font-style:italic; color:#d1d5db;">"<?= htmlspecialchars($j['jailReason']) ?>"</td>
                                        <td><span style="color:#ef4444; font-weight:bold;"><?= $j['jailTime'] ?> perc</span></td>
                                    </tr>
                                    <?php endforeach;
                                }
                            } catch(Exception $e) { echo "<tr><td colspan='4' style='color:#ef4444; text-align:center;'>Adatbázis hiba: " . $e->getMessage() . "</td></tr>"; } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <button class="accordion"><i class="fa-solid fa-user-pen"></i> Karakterek Kezelése (Szerkesztés & Törlés)</button>
            <div class="panel">
                <div class="grid-container">
                    <div style="grid-column: 1 / -1;"><input type="text" id="search-chars" class="search-bar" onkeyup="filterDivs('search-chars', 'char-boxes', 'char-box')" placeholder="Keresés karakter neve alapján..."></div>
                    <div id="char-boxes" style="display: contents;">
                        <?php
                        try {
                            $chars = $pdo->query("SELECT characterId, name, money FROM characters ORDER BY characterId DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($chars as $c): ?>
                            <div class="action-box char-box">
                                <div class="search-target" style="font-weight:bold; color:#e67e22; font-size:1.1em;"><?= htmlspecialchars(str_replace('_', ' ', $c['name'])) ?></div>
                                <div style="font-size:0.8em; color:#9ca3af; margin: 5px 0 15px 0;">ID: <?= $c['characterId'] ?> | $<?= number_format($c['money'], 0, '.', ' ') ?></div>
                                <div style="display: flex; gap: 10px;">
                                    <a href="?edit_char=<?= $c['characterId'] ?>" class="btn-save" style="display:block; text-align:center; text-decoration:none; padding:10px; flex: 1;">SZERKESZTÉS</a>
                                    <form method="POST" style="margin:0; display:flex;" onsubmit="return confirm('VIGYÁZAT! A karakter véglegesen törlődik. Folytatod?');">
                                        <input type="hidden" name="action" value="delete_item">
                                        <input type="hidden" name="item_type" value="character">
                                        <input type="hidden" name="item_id" value="<?= $c['characterId'] ?>">
                                        <button type="button" class="btn-save" style="background:#ef4444; color:#fff;" onclick="promptAndSubmit(this, 'Kérlek add meg az RCON jelszót a törléshez:')"><i class="fa-solid fa-trash"></i></button>
                                        <input type="hidden" name="rcon_code" class="rcon-input" value="">
                                    </form>
                                </div>
                            </div>
                            <?php endforeach;
                        } catch (Exception $e) {} ?>
                    </div>
                </div>
            </div>

            <button class="accordion"><i class="fa-solid fa-car"></i> Járművek Kezelése (Törlés)</button>
            <div class="panel">
                <div class="grid-container">
                    <div style="grid-column: 1 / -1;"><input type="text" id="search-vehs" class="search-bar" onkeyup="filterDivs('search-vehs', 'veh-boxes', 'veh-box')" placeholder="Keresés jármű ID, modell vagy tulajdonos ID alapján..."></div>
                    <div id="veh-boxes" style="display: contents;">
                        <?php
                        try {
                            $vehs = $pdo->query("SELECT dbID, modelId, characterId FROM vehicles ORDER BY dbID DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($vehs as $v): ?>
                            <div class="action-box veh-box">
                                <div class="search-target" style="font-weight:bold; color:#3b82f6; font-size:1.1em;">Jármű #<?= $v['dbID'] ?> - <?= getVehicleName($v['modelId']) ?></div>
                                <div style="font-size:0.8em; color:#9ca3af; margin: 5px 0 15px 0;">Tulajdonos ID: <?= $v['characterId'] ?></div>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('VIGYÁZAT! A jármű véglegesen törlődik. Folytatod?');">
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="item_type" value="vehicle">
                                    <input type="hidden" name="item_id" value="<?= $v['dbID'] ?>">
                                    <button type="button" class="btn-save" style="background:#ef4444; color:#fff;" onclick="promptAndSubmit(this, 'Kérlek add meg az RCON jelszót a törléshez:')"><i class="fa-solid fa-trash"></i> TÖRLÉS ADATBÁZISBÓL</button>
                                    <input type="hidden" name="rcon_code" class="rcon-input" value="">
                                </form>
                            </div>
                            <?php endforeach;
                        } catch (Exception $e) {} ?>
                    </div>
                </div>
            </div>

            <button class="accordion"><i class="fa-solid fa-users-gear"></i> Szervezetek Kezelése</button>
            <div class="panel">
                <div class="grid-container">
                    <?php
                    try {
                        $factions = $pdo->query("SELECT * FROM `groups`")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($factions as $f): 
                            $factionId = $f[$groupPk] ?? array_values($f)[0];
                        ?>
                        <div class="action-box">
                            <div style="font-weight:bold; color:#3b82f6;"><?= detectFactionName($f, $groupPk) ?></div>
                            <div style="color:#2ecc71; font-size:0.9em; margin-top:5px;">Kassza: $<?= formatShortNumber($f['balance'] ?? 0) ?></div>
                            <a href="?manage_id=<?= $factionId ?>" class="btn-save" style="display:block; text-align:center; text-decoration:none; padding:10px; margin-top:15px; background: #3b82f6;">KEZELÉS</a>
                        </div>
                        <?php endforeach;
                    } catch (Exception $e) {} ?>
                </div>
            </div>

            <button class="accordion"><i class="fa-solid fa-terminal"></i> Élő Szerver Konzol (MTA Server.log)</button>
            <div class="panel">
                <div style="padding: 20px 0;">
                    <div id="console-box" style="height: 300px; background: #0a0a0a; color: #fff; font-family: monospace; padding: 15px; overflow-y: auto; border-radius: 6px; font-size: 13px; border: 1px solid #333;">Konzol betöltése az api_stats.php-n keresztül...</div>
                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <input type="text" id="cmd-input" placeholder="RCON Parancs (pl. /say Hello) - Fejlesztés alatt" style="margin:0; flex:1; font-family: monospace;">
                        <button onclick="sendCommand()" class="btn-save btn-blue" style="margin:0; width:auto; padding:0 20px;"><i class="fa-solid fa-paper-plane"></i> Küldés</button>
                    </div>
                </div>
            </div>

            <button class="accordion"><i class="fa-solid fa-bug"></i> Szerver Debug / Figyelmeztetések (Warnings)</button>
            <div class="panel">
                <div style="padding: 20px 0;">
                    <div id="debug-box" style="height: 250px; background: rgba(0,0,0,0.3); color: #e3b341; font-family: monospace; padding: 15px; overflow-y: auto; border-radius: 6px; font-size: 13px; border: 1px solid rgba(227, 179, 65, 0.3);">Debug adatok betöltése...</div>
                </div>
            </div>

            <button class="accordion"><i class="fa-solid fa-globe"></i> Weboldal Hibanapló (PHP)</button>
            <div class="panel">
                <div style="padding: 20px 0;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
                        <div style="color: #9b59b6; font-weight: bold; font-size:1.1em;"><i class="fa-solid fa-spider"></i> UCP WEBOLDAL Hibák</div>
                        <button onclick="clearWebLogs()" class="btn-small btn-red" style="padding: 8px 15px;"><i class="fa-solid fa-trash"></i> Napló kiürítése</button>
                    </div>
                    <div id="ucp-web-errors" style="height: 250px; background:rgba(0,0,0,0.3); padding:15px; border-radius:6px; font-family:monospace; overflow-y:auto; color:#d946ef; border: 1px solid rgba(155, 89, 182, 0.3); font-size:12px; line-height:1.5;">Kattints a frissítéshez, ha vannak hibák...</div>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <script>
        var acc = document.getElementsByClassName("accordion");
        for (var i = 0; i < acc.length; i++) {
            acc[i].addEventListener("click", function() {
                this.classList.toggle("active");
                var panel = this.nextElementSibling;
                panel.style.maxHeight = panel.style.maxHeight ? null : panel.scrollHeight + "px";
            });
        }

        function toggleItemField() {
            let typeSelect = document.getElementById('couponTypeSelect');
            if(!typeSelect) return;
            let type = typeSelect.value;
            let itemDiv = document.getElementById('couponItemDiv');
            if(itemDiv) {
                if(type === 'item') { itemDiv.style.display = 'block'; } 
                else { itemDiv.style.display = 'none'; }
            }
        }

        function filterTable(inputId, tableId) {
            let input = document.getElementById(inputId);
            let filter = input.value.toLowerCase();
            let table = document.getElementById(tableId);
            if (!table) return;
            let tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) { 
                let text = tr[i].innerText.toLowerCase();
                tr[i].style.display = text.includes(filter) ? "" : "none";
            }
        }

        function filterDivs(inputId, containerId, boxClass) {
            let filter = document.getElementById(inputId).value.toLowerCase();
            let container = document.getElementById(containerId);
            if (!container) return;
            let boxes = container.getElementsByClassName(boxClass);

            for (let i = 0; i < boxes.length; i++) {
                let text = boxes[i].querySelector('.search-target').innerText.toLowerCase();
                boxes[i].style.display = text.includes(filter) ? "block" : "none";
            }
        }

        function promptAndSubmit(buttonElement, message) {
            let rcon = prompt(message);
            if (rcon !== null && rcon.trim() !== '') {
                let form = buttonElement.closest('form');
                form.querySelector('.rcon-input').value = rcon;
                form.submit();
            }
        }

        function promptAndSubmitChar(buttonElement, message) {
            let rcon = prompt(message);
            if (rcon !== null && rcon.trim() !== '') {
                document.getElementById('hidden-rcon-input').value = rcon;
                document.getElementById('hidden-delete-form').submit();
            }
        }

        function updateConsole() {
            fetch('api_stats.php?req=console')
                .then(res => res.json())
                .then(data => {
                    const box = document.getElementById('console-box');
                    if (box && data.lines) {
                        box.innerHTML = data.lines.join('<br>');
                        box.scrollTop = box.scrollHeight;
                    }
                }).catch(e => {});
        }

        function updateDebug() {
            fetch('api_stats.php?req=debug_logs')
                .then(res => res.json())
                .then(data => {
                    const warnBox = document.getElementById('debug-box');
                    if(warnBox && data.warnings) {
                        warnBox.innerHTML = data.warnings.length === 0 ? '<span style="color:#9ca3af">Nincs WARNING.</span>' : data.warnings.join('<br><br>');
                    }
                }).catch(e => {});
        }

        function sendCommand() {
            const input = document.getElementById('cmd-input');
            const cmd = input.value.trim();
            if (!cmd) return;
            fetch('api_stats.php?req=send_cmd', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cmd: cmd })
            });
            input.value = '';
        }

        function fetchWebLogs() {
            fetch('adminpanel.php?ajax_web_logs=1')
                .then(res => res.text())
                .then(data => {
                    const box = document.getElementById('ucp-web-errors');
                    if(box) box.innerHTML = data;
                }).catch(e => {});
        }

        function clearWebLogs() {
            if(confirm("Biztosan törölni akarod a weboldal hibanaplóját?")) {
                fetch('adminpanel.php?clear_web_logs=1')
                    .then(() => { document.getElementById('ucp-web-errors').innerHTML = "Törölve..."; })
                    .catch(e => {});
            }
        }
        
        <?php if (!$activeGroupId && !$activeCharId && !$activeAccId && !$activeTicketId): ?>
            setInterval(updateConsole, 3000);
            setInterval(updateDebug, 5000);
            setInterval(fetchWebLogs, 10000);
            updateConsole(); updateDebug(); fetchWebLogs();
        <?php endif; ?>
    </script>
</body>
</html>