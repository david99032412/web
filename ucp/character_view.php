<?php
// ucp/character_view.php - KARAKTER RÉSZLETES NÉZETE (MINDENTUDÓ ADATLAP)
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$charId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($charId <= 0) die("Érvénytelen karakter ID!");

// Fiók ID lekérése a jogosultság ellenőrzéshez
$stmtAcc = $pdo->prepare("SELECT accountId, adminLevel FROM accounts WHERE username = ?");
$stmtAcc->execute([$_SESSION['user_username']]);
$account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

if (!$account) die("Hiba a fiók betöltésekor!");
$accountId = $account['accountId'];
$isAdmin = ((int)($account['adminLevel'] ?? 0) >= 7);

$successMsg = '';
$errorMsg = '';
$sqlError = '';

// --- Frakció név és prefix lekérdezés segédfüggvény ---
if (!function_exists('detectFactionName')) {
    function detectFactionName($dbRow, $pk) {
        $name = $dbRow['name'] ?? "Szervezet #" . ($dbRow[$pk] ?? '??');
        $prefix = trim($dbRow['groupPrefix'] ?? '');
        if (!empty($prefix)) return $name . " (" . strtoupper($prefix) . ")";
        return $name;
    }
}

// 1. KARAKTER ALAPADATOK LEKÉRÉSE
try {
    $stmtChar = $pdo->prepare("SELECT * FROM characters WHERE characterId = ?");
    $stmtChar->execute([$charId]);
    $char = $stmtChar->fetch(PDO::FETCH_ASSOC);
    
    if (!$char) {
        die("<div style='background:#1a1b1f; color:#ef4444; padding:40px; text-align:center; font-family:sans-serif;'><h2>Karakter nem található!</h2><a href='dashboard.php' style='color:#0d6efd;'>Vissza a kezdőlapra</a></div>");
    }
    
    // Biztonsági ellenőrzés: Csak a sajátját nézheti, KIVÉVE ha Admin
    if ($char['accountId'] != $accountId && !$isAdmin) {
        die("<div style='background:#1a1b1f; color:#ef4444; padding:40px; text-align:center; font-family:sans-serif;'><h2>Nincs jogosultságod megtekinteni ezt a karaktert!</h2><a href='dashboard.php' style='color:#0d6efd;'>Vissza</a></div>");
    }
    
} catch (Exception $e) {
    die("Adatbázis hiba a karakter betöltésekor: " . $e->getMessage());
}

// 2. FRAKCIÓ ADATOK LEKÉRÉSE (Ha van)
$factionData = null;
try {
    // Megpróbáljuk lekérni a groupmembers és groups táblákból
    $stmtFac = $pdo->prepare("
        SELECT gm.rank, gm.isLeader, g.name, g.groupPrefix 
        FROM groupmembers gm 
        JOIN `groups` g ON gm.groupPrefix = g.groupPrefix 
        WHERE gm.characterId = ? LIMIT 1
    ");
    $stmtFac->execute([$charId]);
    $factionData = $stmtFac->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ha nem létezik a tábla vagy a kapcsolat, csendben elnyeljük a hibát
    $sqlError .= " Frakció hiba. ";
}

// 3. INVENTORY TÁRGYAK LEKÉRÉSE (Ha létezik az items tábla)
$inventory = [];
$weapons = [];
try {
    $stmtItems = $pdo->prepare("SELECT * FROM items WHERE ownerType = 'character' AND ownerId = ?");
    $stmtItems->execute([$charId]);
    $allItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($allItems as $item) {
        // Feltételezzük, hogy az fegyverek itemId-ja valahol 1 és 46 között van (GTA SA alap fegyver ID-k)
        // Vagy külön típus azonosító van. Ezt a szervered logikájához kell igazítani.
        // Itt most egy egyszerű szétválasztást csinálunk példaként:
        if (isset($item['itemType']) && $item['itemType'] == 'weapon') {
            $weapons[] = $item;
        } else {
            $inventory[] = $item;
        }
    }
} catch (Exception $e) {
    // Ha nincs items tábla, nem omlik össze az oldal
    $sqlError .= " Inventory hiba. ";
}

// 4. JÁRMŰVEK LEKÉRÉSE (Kikötjük az adott karakter járműveit)
$myVehicles = [];
try {
    $stmtVehs = $pdo->prepare("SELECT * FROM vehicles WHERE characterId = ?");
    $stmtVehs->execute([$charId]);
    $myVehicles = $stmtVehs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sqlError .= " Jármű hiba. ";
}

// Skin renderelő URL (A rendes GTA SA skineket húzza be ID alapján)
$skinUrl = "https://assets.open.mp/assets/images/skins/" . (int)($char['skin'] ?? 0) . ".png";
if ((int)($char['skin'] ?? 0) == 0) $skinUrl = "https://assets.open.mp/assets/images/skins/0.png"; // CJ alapértelmezett

function formatMoney($num) { return number_format((float)$num, 0, '.', ' '); }
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(str_replace('_', ' ', $char['name'])) ?> - Részletek</title>
    <script src="https://kit.fontawesome.com/122db0fdde.js" crossorigin="anonymous"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: rgb(26, 27, 31); color: #d1d5db; font-family: 'Inter', sans-serif; }
        
        .navbar { background: rgba(17, 18, 20, 0.95); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; backdrop-filter: blur(10px); flex-wrap: wrap; gap: 10px;}
        .navbar-brand { font-size: 20px; font-weight: 900; color: #fff; text-transform: uppercase; }
        .navbar-brand span { color: #0d6efd; }
        .nav-links { display: flex; flex-wrap: wrap; gap: 15px; }
        .nav-links a { color: #9ca3af; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.2s; }
        .nav-links a:hover, .nav-links a.active { color: #fff; }
        
        .container { max-width: 1200px; margin: 40px auto; padding: 0 15px; }
        
        .header-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 20px;}
        .header-title { font-size: 32px; font-weight: 900; color: #fff; display: flex; align-items: center; gap: 15px;}
        .char-id-badge { background: rgba(13, 110, 253, 0.2); color: #0d6efd; padding: 5px 12px; border-radius: 8px; font-size: 16px; font-weight: bold; border: 1px solid rgba(13, 110, 253, 0.3);}
        
        .admin-edit-btn { background: #3b82f6; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; text-transform: uppercase; font-size: 13px;}
        .admin-edit-btn:hover { background: #2563eb; transform: translateY(-2px);}
        
        .grid-layout { display: grid; grid-template-columns: 300px 1fr; gap: 30px; align-items: start; }
        @media (max-width: 900px) { .grid-layout { grid-template-columns: 1fr; } }
        
        /* Bal oldali panel (Skin és Alapadatok) */
        .profile-sidebar { display: flex; flex-direction: column; gap: 20px; }
        
        .skin-card { background: linear-gradient(180deg, rgba(33, 35, 40, 1) 0%, rgba(17, 18, 20, 1) 100%); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 30px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); position: relative; overflow: hidden;}
        .skin-card::after { content: ''; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 200px; height: 200px; background: rgba(13, 110, 253, 0.1); filter: blur(50px); z-index: 0; border-radius: 50%;}
        .skin-image { height: 250px; object-fit: contain; position: relative; z-index: 1; filter: drop-shadow(0 10px 15px rgba(0,0,0,0.5));}
        .skin-id { margin-top: 15px; color: #9ca3af; font-weight: bold; font-size: 14px; position: relative; z-index: 1;}
        
        .card { background: rgb(33, 35, 40); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .card-header { padding: 18px 25px; background: rgba(0,0,0,0.2); border-bottom: 1px solid rgba(255,255,255,0.05); font-weight: 800; color: #fff; font-size: 15px; display: flex; align-items: center; gap: 10px; }
        .card-body { padding: 25px; }
        
        .data-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed rgba(255,255,255,0.05); font-size: 14px;}
        .data-row:last-child { border-bottom: none; padding-bottom: 0; }
        .data-label { color: #9ca3af; font-weight: 500; }
        .data-value { color: #fff; font-weight: bold; text-align: right;}
        .data-value.green { color: #2ecc71; }
        .data-value.blue { color: #3b82f6; }
        .data-value.yellow { color: #e3b341; }
        .data-value.red { color: #ef4444; }
        
        /* Állapotsávok (Health, Armor) */
        .stat-bar-container { margin-bottom: 15px; }
        .stat-bar-container:last-child { margin-bottom: 0; }
        .stat-bar-header { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 12px; font-weight: bold; text-transform: uppercase;}
        .stat-bar-bg { width: 100%; height: 10px; background: rgba(0,0,0,0.3); border-radius: 5px; overflow: hidden; }
        .stat-bar-fill { height: 100%; border-radius: 5px; transition: width 0.5s ease-out; }
        .fill-health { background: linear-gradient(90deg, #ef4444, #f87171); box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);}
        .fill-armor { background: linear-gradient(90deg, #d1d5db, #ffffff); box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);}
        .fill-hunger { background: linear-gradient(90deg, #e67e22, #f39c12); box-shadow: 0 0 10px rgba(230, 126, 34, 0.5);}
        .fill-thirst { background: linear-gradient(90deg, #3b82f6, #60a5fa); box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);}
        
        /* Pénzügyi dobozok (Grid) */
        .finance-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .finance-box { background: rgba(0,0,0,0.2); border: 1px solid rgba(46, 204, 113, 0.2); border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 15px; }
        .finance-icon { width: 50px; height: 50px; background: rgba(46, 204, 113, 0.1); color: #2ecc71; border-radius: 10px; display: flex; justify-content: center; align-items: center; font-size: 24px; }
        .finance-details h4 { color: #9ca3af; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;}
        .finance-details .amount { color: #fff; font-size: 24px; font-weight: 900; }
        .finance-details .amount span { color: #2ecc71; }
        
        /* Tárgyak és Járművek listája */
        .item-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
        .item-box { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; padding: 15px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 10px; transition: 0.2s;}
        .item-box:hover { border-color: rgba(13, 110, 253, 0.3); background: rgba(13, 110, 253, 0.05); transform: translateY(-3px);}
        .item-icon { font-size: 32px; color: #9ca3af; }
        .item-name { color: #fff; font-weight: bold; font-size: 13px; word-wrap: break-word;}
        .item-count { color: #0d6efd; font-size: 11px; font-weight: 900; background: rgba(13, 110, 253, 0.2); padding: 2px 8px; border-radius: 10px;}
        
        /* Harmonika stílusok a kinyitható fülekhez */
        .accordion { background: rgb(33, 35, 40); color: #fff; cursor: pointer; padding: 18px 25px; width: 100%; border: 1px solid rgba(255,255,255,0.05); text-align: left; font-weight: 800; margin-bottom: 10px; border-radius: 12px; font-size: 15px; transition: 0.2s; display: flex; align-items: center; gap: 10px;}
        .accordion.active, .accordion:hover { background: rgba(13, 110, 253, 0.1); border-color: rgba(13, 110, 253, 0.3); color: #0d6efd;}
        .panel { padding: 0 25px; background: rgb(26, 27, 31); max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; margin-bottom: 20px;}
        .panel-inner { padding: 20px 0; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-brand">NorthSide <span>UCP</span></div>
        <div class="nav-links">
            <a href="dashboard.php">Kezdőlap</a>
            <a href="profile.php">Profilom</a>
            <a href="characters.php" class="active">Karakterek</a>
            <a href="factions.php">Frakcióim</a>
            <a href="support.php">Support</a>
            <a href="vehicles.php">Járművek</a>
            <a href="premium.php"><i class="fa-solid fa-star" style="color:#e3b341;"></i> Prémium</a>
            <a href="settings.php"><i class="fa-solid fa-gear"></i> Beállítások</a>
            <?php if($isAdmin): ?><a href="adminpanel.php" style="color: #ef4444;">Admin Panel</a><?php endif; ?>
            <a href="logout.php" style="color: #ef4444;">Kilépés</a>
        </div>
    </nav>

    <div class="container">
        
        <div class="header-bar">
            <div class="header-title">
                <?= htmlspecialchars(str_replace('_', ' ', $char['name'])) ?> 
                <span class="char-id-badge">ID: <?= $char['characterId'] ?></span>
            </div>
            
            <div style="display:flex; gap: 15px; align-items:center;">
                <a href="characters.php" style="color:#9ca3af; text-decoration:none; font-weight:bold; font-size:14px;"><i class="fa-solid fa-arrow-left"></i> Vissza</a>
                
                <?php if($isAdmin): ?>
                    <a href="adminpanel.php?edit_char=<?= $char['characterId'] ?>" class="admin-edit-btn">
                        <i class="fa-solid fa-pen-to-square"></i> Admin Szerkesztés
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid-layout">
            
            <div class="profile-sidebar">
                
                <div class="skin-card">
                    <img src="<?= $skinUrl ?>" alt="Skin <?= $char['skin'] ?>" class="skin-image" onerror="this.src='https://assets.open.mp/assets/images/skins/0.png'">
                    <div class="skin-id">Model ID: <span style="color:#fff;"><?= $char['skin'] ?? 0 ?></span></div>
                </div>
                
                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-id-card" style="color:#3b82f6;"></i> Személyes Adatok</div>
                    <div class="card-body">
                        <div class="data-row">
                            <span class="data-label">Karakter Neve</span>
                            <span class="data-value"><?= htmlspecialchars(str_replace('_', ' ', $char['name'])) ?></span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Játszott Idő</span>
                            <span class="data-value blue"><?= floor(($char['playedMinutes'] ?? 0) / 60) ?> óra</span>
                        </div>
                        
                        <?php if ($factionData): ?>
                            <div class="data-row">
                                <span class="data-label">Szervezet</span>
                                <span class="data-value" style="color:#8b5cf6; font-weight:900;"><?= htmlspecialchars($factionData['name']) ?></span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Rang</span>
                                <span class="data-value"><?= $factionData['rank'] ?>. Rang <?= $factionData['isLeader'] ? '<i class="fa-solid fa-crown" style="color:#e3b341; font-size:12px; margin-left:5px;"></i>' : '' ?></span>
                            </div>
                        <?php else: ?>
                            <div class="data-row">
                                <span class="data-label">Szervezet</span>
                                <span class="data-value" style="color:#9ca3af; font-weight:normal;">Civil (Munkanélküli)</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="data-row" style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px;">
                            <span class="data-label">Jelenlegi Pozíció</span>
                            <span class="data-value" style="font-family:monospace; font-size:12px; color:#9ca3af;">
                                X: <?= round($char['posX'] ?? 0, 1) ?> <br>
                                Y: <?= round($char['posY'] ?? 0, 1) ?> <br>
                                Z: <?= round($char['posZ'] ?? 0, 1) ?>
                            </span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Interior / Dimenzió</span>
                            <span class="data-value"><?= $char['interior'] ?? 0 ?> / <?= $char['dimension'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if (($char['jailTime'] ?? 0) > 0): ?>
                <div class="card" style="border-color: rgba(239, 68, 68, 0.4);">
                    <div class="card-header" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;"><i class="fa-solid fa-bars-staggered"></i> Aktív Börtönbüntetés</div>
                    <div class="card-body">
                        <div style="text-align:center;">
                            <div style="font-size: 32px; font-weight: 900; color: #ef4444; margin-bottom: 5px;"><?= $char['jailTime'] ?> perc</div>
                            <div style="color: #9ca3af; font-size: 13px; font-style: italic;">"<?= htmlspecialchars($char['jailReason'] ?? 'Nincs indok') ?>"</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>

            <div class="profile-main">
                
                <div class="finance-grid">
                    <div class="finance-box">
                        <div class="finance-icon"><i class="fa-solid fa-wallet"></i></div>
                        <div class="finance-details">
                            <h4>Készpénz</h4>
                            <div class="amount"><span>$</span><?= formatMoney($char['money'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="finance-box">
                        <div class="finance-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;"><i class="fa-solid fa-building-columns"></i></div>
                        <div class="finance-details">
                            <h4>Bankszámla</h4>
                            <div class="amount"><span style="color:#3b82f6;">$</span><?= formatMoney($char['bankMoney'] ?? 0) ?></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-heart-pulse" style="color:#ef4444;"></i> Fizikai Állapot</div>
                    <div class="card-body">
                        <?php 
                        $health = min(100, max(0, (float)($char['health'] ?? 100))); 
                        $armor = min(100, max(0, (float)($char['armor'] ?? 0))); 
                        $hunger = min(100, max(0, (float)($char['hunger'] ?? 100))); 
                        $thirst = min(100, max(0, (float)($char['thirst'] ?? 100))); 
                        ?>
                        
                        <div class="stat-bar-container">
                            <div class="stat-bar-header">
                                <span style="color:#ef4444;"><i class="fa-solid fa-heart"></i> Életerő</span>
                                <span style="color:#fff;"><?= $health ?>%</span>
                            </div>
                            <div class="stat-bar-bg"><div class="stat-bar-fill fill-health" style="width: <?= $health ?>%;"></div></div>
                        </div>
                        
                        <div class="stat-bar-container">
                            <div class="stat-bar-header">
                                <span style="color:#d1d5db;"><i class="fa-solid fa-shield"></i> Páncélzat</span>
                                <span style="color:#fff;"><?= $armor ?>%</span>
                            </div>
                            <div class="stat-bar-bg"><div class="stat-bar-fill fill-armor" style="width: <?= $armor ?>%;"></div></div>
                        </div>
                        
                        <div class="stat-bar-container">
                            <div class="stat-bar-header">
                                <span style="color:#e67e22;"><i class="fa-solid fa-burger"></i> Éhség</span>
                                <span style="color:#fff;"><?= $hunger ?>%</span>
                            </div>
                            <div class="stat-bar-bg"><div class="stat-bar-fill fill-hunger" style="width: <?= $hunger ?>%;"></div></div>
                        </div>
                        
                        <div class="stat-bar-container">
                            <div class="stat-bar-header">
                                <span style="color:#3b82f6;"><i class="fa-solid fa-droplet"></i> Szomjúság</span>
                                <span style="color:#fff;"><?= $thirst ?>%</span>
                            </div>
                            <div class="stat-bar-bg"><div class="stat-bar-fill fill-thirst" style="width: <?= $thirst ?>%;"></div></div>
                        </div>
                    </div>
                </div>

                <button class="accordion"><i class="fa-solid fa-car" style="color:#e3b341;"></i> Birtokolt Járművek (<?= count($myVehicles) ?> db)</button>
                <div class="panel">
                    <div class="panel-inner">
                        <?php if (empty($myVehicles)): ?>
                            <div style="text-align:center; color:#9ca3af; padding: 20px;">Ez a karakter jelenleg nem birtokol egyetlen járművet sem.</div>
                        <?php else: ?>
                            <div class="item-grid">
                                <?php foreach($myVehicles as $veh): ?>
                                    <div class="item-box">
                                        <div class="item-icon"><i class="fa-solid fa-car-side"></i></div>
                                        <div class="item-name"><?= getVehicleName($veh['modelId'] ?? 400) ?></div>
                                        <div style="color:#9ca3af; font-size:11px; font-family:monospace;">ID: #<?= $veh['dbID'] ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="accordion"><i class="fa-solid fa-box-open" style="color:#8b5cf6;"></i> Inventory - Tárgyak (<?= count($inventory) ?> db)</button>
                <div class="panel">
                    <div class="panel-inner">
                        <?php if (empty($inventory)): ?>
                            <div style="text-align:center; color:#9ca3af; padding: 20px;">Az inventory üres.</div>
                        <?php else: ?>
                            <div class="item-grid">
                                <?php foreach($inventory as $item): ?>
                                    <div class="item-box">
                                        <div class="item-icon"><i class="fa-solid fa-cube"></i></div>
                                        <div class="item-name">Tárgy ID: <?= $item['itemId'] ?? '?' ?></div>
                                        <div class="item-count"><?= $item['itemValue'] ?? 1 ?> db</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="accordion"><i class="fa-solid fa-gun" style="color:#ef4444;"></i> Fegyverek és Lőszerek (<?= count($weapons) ?> db)</button>
                <div class="panel">
                    <div class="panel-inner">
                        <?php if (empty($weapons)): ?>
                            <div style="text-align:center; color:#9ca3af; padding: 20px;">Nincs fegyver a karakternél.</div>
                        <?php else: ?>
                            <div class="item-grid">
                                <?php foreach($weapons as $arm): ?>
                                    <div class="item-box" style="border-color: rgba(239, 68, 68, 0.2);">
                                        <div class="item-icon" style="color:#ef4444;"><i class="fa-solid fa-crosshairs"></i></div>
                                        <div class="item-name">Fegyver ID: <?= $arm['itemId'] ?? '?' ?></div>
                                        <div class="item-count" style="background: rgba(239, 68, 68, 0.2); color:#ef4444;"><?= $arm['itemValue'] ?? 1 ?> töltény</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var acc = document.getElementsByClassName("accordion");
            for (var i = 0; i < acc.length; i++) {
                
                // Első kattintásnál az első panel nyitva legyen (opcionális UX)
                if (i === 0) {
                    acc[i].classList.add("active");
                    var panel = acc[i].nextElementSibling;
                    panel.style.maxHeight = panel.scrollHeight + "px";
                }

                acc[i].addEventListener("click", function() {
                    this.classList.toggle("active");
                    var panel = this.nextElementSibling;
                    if (panel.style.maxHeight) {
                        panel.style.maxHeight = null;
                    } else {
                        panel.style.maxHeight = panel.scrollHeight + "px";
                    }
                });
            }
        });
    </script>
</body>
</html>