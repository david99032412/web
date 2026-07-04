<?php
// ucp/factions.php - SAJÁT FRAKCIÓK ÉS LEADER PANEL
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$stmtAcc = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
$stmtAcc->execute([$_SESSION['user_username']]);
$account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

if (!$account) die("Hiba a fiók betöltésekor!");
$isAdmin = ((int)$account['adminLevel'] >= 7);
$accountId = $account['accountId'];

$successMsg = '';
$errorMsg = '';

// --- RANG DEKÓDOLÓ FÜGGVÉNY (Biztonságosan) ---
if (!function_exists('getFactionRankName')) {
    function getFactionRankName($ranksData, $rankId) {
        if (!$ranksData) return "Rang " . $rankId;
        $decoded = json_decode($ranksData, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (isset($decoded[0]) && is_array($decoded[0])) $decoded = $decoded[0];
            if (isset($decoded[$rankId])) return is_array($decoded[$rankId]) ? $decoded[$rankId][0] : (string)$decoded[$rankId];
            if (isset($decoded[(string)$rankId])) return is_array($decoded[(string)$rankId]) ? $decoded[(string)$rankId][0] : (string)$decoded[(string)$rankId];
            if (isset($decoded[$rankId - 1])) return is_array($decoded[$rankId - 1]) ? $decoded[$rankId - 1][0] : (string)$decoded[$rankId - 1];
        }
        if (strpos($ranksData, ',') !== false && strpos($ranksData, '{') === false && strpos($ranksData, '[') === false) {
            $parts = explode(',', $ranksData);
            if (isset($parts[$rankId])) return trim($parts[$rankId]); 
            if (isset($parts[$rankId - 1])) return trim($parts[$rankId - 1]); 
        }
        return "Rang " . $rankId;
    }
}

// --- FRAKCIÓ NÉV KIÍRÓ FÜGGVÉNY ---
if (!function_exists('detectFactionName')) {
    function detectFactionName($dbRow, $pk) {
        $name = $dbRow['name'] ?? "Szervezet #" . ($dbRow[$pk] ?? '??');
        $prefix = trim($dbRow['groupPrefix'] ?? '');
        if (!empty($prefix)) return $name . " (" . strtoupper($prefix) . ")";
        return $name;
    }
}

// 1. Lekérjük, hogy a játékos karakterei milyen frakciókban vannak
$myFactions = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.characterId, c.name as charName, gm.groupPrefix, gm.rank, gm.isLeader, g.* FROM characters c 
        JOIN groupmembers gm ON c.characterId = gm.characterId 
        JOIN `groups` g ON gm.groupPrefix = g.groupPrefix 
        WHERE c.accountId = ?
    ");
    $stmt->execute([$accountId]);
    $myFactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $errorMsg = "Hiba a frakciók lekérésekor: " . $e->getMessage(); }

// 2. POST MŰVELETEK (Ha a játékos LEADER VAGY ADMIN és módosít valamit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $targetPrefix = $_POST['faction_prefix'] ?? '';
    $targetMemberId = (int)($_POST['member_id'] ?? 0);
    
    $isReallyLeader = false;
    
    // Admin override: Ha admin, mindenképp szerkeszthet
    if ($isAdmin) {
        $isReallyLeader = true;
    } else {
        foreach ($myFactions as $mf) {
            if ($mf['groupPrefix'] === $targetPrefix && $mf['isLeader'] == 1) {
                $isReallyLeader = true; break;
            }
        }
    }

    if ($isReallyLeader) {
        // --- MEGLÉVŐ FUNKCIÓK MEGTARTVA ---
        if ($_POST['action'] === 'update_rank') {
            $newRank = (int)$_POST['new_rank'];
            $pdo->prepare("UPDATE groupmembers SET rank = ? WHERE characterId = ? AND groupPrefix = ?")->execute([$newRank, $targetMemberId, $targetPrefix]);
            $successMsg = "A tag rangja sikeresen frissítve!";
        }
        if ($_POST['action'] === 'kick_member') {
            $pdo->prepare("DELETE FROM groupmembers WHERE characterId = ? AND groupPrefix = ?")->execute([$targetMemberId, $targetPrefix]);
            $successMsg = "A tagot sikeresen eltávolítottad a szervezetből!";
        }
        
        // --- HOZZÁADOTT ÚJ FUNKCIÓK ---
        if ($_POST['action'] === 'add_member') {
            $check = $pdo->prepare("SELECT characterId FROM characters WHERE name = ?");
            $check->execute([trim($_POST['character_name'])]);
            $charData = $check->fetch(PDO::FETCH_ASSOC);
            
            if ($charData) {
                $charId = $charData['characterId'];
                $checkMem = $pdo->prepare("SELECT * FROM groupmembers WHERE characterId = ? AND groupPrefix = ?");
                $checkMem->execute([$charId, $targetPrefix]);
                if ($checkMem->rowCount() == 0) {
                    $pdo->prepare("INSERT INTO groupmembers (characterId, groupPrefix, rank, isLeader) VALUES (?, ?, 1, 0)")->execute([$charId, $targetPrefix]);
                    $successMsg = "A játékos sikeresen felvéve a szervezetbe!";
                } else { $errorMsg = "A játékos már tagja a szervezetnek!"; }
            } else { $errorMsg = "Nem található ilyen nevű karakter!"; }
        }
        
        if ($_POST['action'] === 'update_group_info') {
            // A Leader a nevét és a prefixet nem változtathatja (az admin dolga), csak a leírást és a kasszát!
            $pdo->prepare("UPDATE `groups` SET description = ?, balance = ? WHERE groupPrefix = ?")->execute([$_POST['new_desc'], $_POST['new_balance'], $targetPrefix]);
            $successMsg = "Frakció adatok sikeresen frissítve!";
        }
    } else {
        $errorMsg = "Nincs jogosultságod módosítani ezt a szervezetet!";
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Saját Frakcióim - NorthSide UCP</title>
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
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        
        .welcome-box { background: linear-gradient(135deg, rgba(13, 110, 253, 0.15), transparent); border: 1px solid rgba(13, 110, 253, 0.2); border-radius: 12px; padding: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .welcome-box h1 { color: #fff; font-size: 28px; font-weight: 800; margin: 0; }
        
        .accordion { background: rgb(33, 35, 40); color: #fff; cursor: pointer; padding: 20px; width: 100%; border: 1px solid rgba(255,255,255,0.05); text-align: left; font-weight: bold; margin-bottom: 5px; border-radius: 8px; font-size: 16px; transition: 0.3s; display: flex; justify-content: space-between; align-items: center; }
        .accordion:hover { background: rgba(13, 110, 253, 0.05); border-color: rgba(13, 110, 253, 0.3); }
        .accordion.active { background: rgba(13, 110, 253, 0.1); border-color: rgba(13, 110, 253, 0.5); border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
        
        .panel { padding: 0 20px; background: rgb(26, 27, 31); max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; border-left: 3px solid #0d6efd; border-right: 1px solid rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.05); border-radius: 0 0 8px 8px; margin-bottom: 15px; }
        
        .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .data-table th, .data-table td { padding: 12px; border-bottom: 1px dashed rgba(255,255,255,0.05); text-align: left; }
        .data-table th { color: #9ca3af; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; border-bottom-style: solid; }
        
        .btn { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; color: white; font-weight: bold; font-size: 12px; }
        .btn-blue { background: #0d6efd; }
        .btn-green { background: #2ecc71; }
        .btn-red { background: #ef4444; }
        select, input[type="text"], input[type="number"] { background: rgba(0,0,0,0.4); color: #fff; border: 1px solid rgba(255,255,255,0.15); padding: 8px; border-radius: 4px; outline: none; width: 100%; }
        input:focus { border-color: #0d6efd; }
        label { font-size: 12px; color: #9ca3af; display: block; margin-bottom: 5px; margin-top: 10px; font-weight: bold; text-transform: uppercase; }

        .chevron { transition: transform 0.3s; color: #9ca3af; }
        .accordion.active .chevron { transform: rotate(180deg); color: #0d6efd; }
        
        .action-box { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); border-radius: 6px; padding: 15px; margin-bottom: 15px; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-brand">NorthSide <span>RP</span></div>
        <div class="nav-links">
            <a href="dashboard.php">Kezdőlap</a>
            <a href="profile.php">Profilom</a>
            <a href="characters.php">Karakterek</a>
            <a href="factions.php" class="active">Frakcióim</a>
            <a href="map.php">Élő Térkép</a>
            <a href="../forum/">Fórum</a>
            <?php if($isAdmin): ?>
                <a href="adminpanel.php" style="color: #e3b341;"><i class="fa-solid fa-shield-halved"></i> Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php" style="color: #ef4444;"><i class="fa-solid fa-power-off"></i> Kilépés</a>
        </div>
    </nav>

    <div class="container">
        
        <?php if ($isAdmin): ?>
            <div style="background: rgba(227, 179, 65, 0.1); border: 1px solid rgba(227, 179, 65, 0.3); padding: 15px 25px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="color: #e3b341; margin: 0 0 5px 0;"><i class="fa-solid fa-shield"></i> Adminisztrátori Felülírás</h3>
                    <p style="color: #9ca3af; margin: 0; font-size: 13px;">Mivel adminisztrátor vagy, automatikusan Leader jogosultságod van a szervezeteid kezeléséhez.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="welcome-box">
            <h1><i class="fa-solid fa-users-gear" style="color: #0d6efd; margin-right:10px;"></i> Szervezeteim</h1>
            <div style="color: #9ca3af; font-size: 16px;">Aktív tagságok: <span style="color:#fff; font-weight:bold;"><?= count($myFactions) ?> db</span></div>
        </div>

        <?php if ($successMsg): ?><div style="background: rgba(46,160,67,0.15); border: 1px solid #2ea043; color: #3fb950; padding: 15px; border-radius: 6px; margin-bottom: 20px;"><i class="fa-solid fa-check"></i> <?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg): ?><div style="background: rgba(248,81,73,0.15); border: 1px solid #da3633; color: #f85149; padding: 15px; border-radius: 6px; margin-bottom: 20px;"><i class="fa-solid fa-xmark"></i> <?= $errorMsg ?></div><?php endif; ?>

        <?php if(empty($myFactions)): ?>
            <div style="text-align: center; padding: 80px 20px; background: rgb(33, 35, 40); border: 1px dashed rgba(255,255,255,0.1); border-radius: 12px; color: #9ca3af;">
                <i class="fa-solid fa-building-user" style="font-size: 64px; opacity: 0.3; margin-bottom: 20px;"></i>
                <h3 style="color:#fff; font-size: 20px;">Nem vagy tagja egyetlen frakciónak sem.</h3>
                <p style="margin-top: 10px;">Keresd fel a szerveren a szervezetek vezetőit a csatlakozáshoz!</p>
            </div>
        <?php else: ?>
            
            <?php foreach($myFactions as $fac): 
                $isLeader = ($fac['isLeader'] == 1 || $isAdmin);
                $pk = isset($fac['id']) ? 'id' : (isset($fac['dbId']) ? 'dbId' : array_key_first($fac));
                $factionName = detectFactionName($fac, $pk);
                
                $rawRanks = $fac['rankNames'] ?? '';
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
            
            <button class="accordion">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <i class="fa-solid fa-users" style="color: #0d6efd; font-size: 20px;"></i>
                    <span>
                        <span style="color:#fff; font-weight:bold; font-size:16px;"><?= htmlspecialchars($factionName) ?></span>
                        <div style="font-size: 12px; color: #9ca3af; font-weight: normal; margin-top: 4px;">Saját Karaktered: <?= str_replace('_', ' ', htmlspecialchars($fac['charName'])) ?></div>
                    </span>
                </div>
                <div style="display: flex; align-items: center; gap: 20px; font-size: 14px; font-weight: normal; color: #9ca3af;">
                    <div>Rangod: <span style="color:#fff; font-weight:bold;"><?= getFactionRankName($fac['rankNames'], $fac['rank']) ?></span></div>
                    <div><?= $isLeader ? '<span style="color:#e3b341; font-weight:bold;"><i class="fa-solid fa-crown"></i> ' . ($isAdmin ? 'Leader / Admin Jog' : 'Leader') . '</span>' : '<span style="color:#3b82f6; font-weight:bold;"><i class="fa-solid fa-user"></i> Tag</span>' ?></div>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </div>
            </button>
            
            <div class="panel">
                <div style="padding: 20px 0;">
                    
                    <?php if($isLeader): ?>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom: 25px;">
                            <div class="action-box">
                                <h3 style="color:#fff; margin-top:0; margin-bottom:15px; font-size:16px;"><i class="fa-solid fa-pen-to-square" style="color:#3b82f6;"></i> Frakció Adatok</h3>
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_group_info">
                                    <input type="hidden" name="faction_prefix" value="<?= $fac['groupPrefix'] ?>">
                                    <label>Frakció Leírása</label>
                                    <input type="text" name="new_desc" value="<?= htmlspecialchars($fac['description'] ?? '') ?>">
                                    <label>Frakció Kassza ($)</label>
                                    <input type="number" name="new_balance" value="<?= $fac['balance'] ?? 0 ?>">
                                    <button type="submit" class="btn btn-blue" style="width:100%; margin-top:10px;"><i class="fa-solid fa-floppy-disk"></i> Mentés</button>
                                </form>
                            </div>
                            
                            <div class="action-box">
                                <h3 style="color:#fff; margin-top:0; margin-bottom:15px; font-size:16px;"><i class="fa-solid fa-user-plus" style="color:#2ecc71;"></i> Új Tag Felvétele</h3>
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_member">
                                    <input type="hidden" name="faction_prefix" value="<?= $fac['groupPrefix'] ?>">
                                    <label>Karakter Pontos Neve (pl. John_Doe)</label>
                                    <input type="text" name="character_name" required placeholder="Név megadása...">
                                    <button type="submit" class="btn btn-green" style="width:100%; margin-top:10px;"><i class="fa-solid fa-plus"></i> Hozzáadás</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                        $stmtMem = $pdo->prepare("SELECT c.characterId, c.name, gm.rank, gm.isLeader FROM groupmembers gm JOIN characters c ON gm.characterId = c.characterId WHERE gm.groupPrefix = ? ORDER BY gm.rank DESC");
                        $stmtMem->execute([$fac['groupPrefix']]);
                        $members = $stmtMem->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <h3 style="color:#fff; font-size:16px; margin-bottom:10px;"><i class="fa-solid fa-users" style="color:#9ca3af;"></i> Tagok Listája</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Karakter Név</th>
                                <th>Jelenlegi Rang</th>
                                <th>Státusz</th>
                                <?php if($isLeader): ?><th style="text-align:right;">Vezetői Műveletek</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($members as $m): ?>
                            <tr>
                                <td style="font-weight:bold; color:#fff;"><?= str_replace('_', ' ', htmlspecialchars($m['name'])) ?></td>
                                <td><?= getFactionRankName($fac['rankNames'], $m['rank']) ?></td>
                                <td><?= $m['isLeader'] ? '<span style="color:#e3b341; font-weight:bold; font-size:12px;"><i class="fa-solid fa-crown"></i> Leader</span>' : '<span style="color:#9ca3af; font-size:12px;">Tag</span>' ?></td>
                                
                                <?php if($isLeader): ?>
                                <td style="text-align:right; display:flex; justify-content:flex-end; gap:10px;">
                                    <?php if($m['characterId'] !== $fac['characterId'] || $isAdmin): ?>
                                        <form method="POST" style="margin:0; display:flex; gap:5px;">
                                            <input type="hidden" name="action" value="update_rank">
                                            <input type="hidden" name="faction_prefix" value="<?= $fac['groupPrefix'] ?>">
                                            <input type="hidden" name="member_id" value="<?= $m['characterId'] ?>">
                                            <select name="new_rank" style="width: auto;">
                                                <?php foreach ($parsedRanks as $rId => $rName): ?>
                                                    <option value="<?= $rId ?>" <?= ($m['rank'] == $rId) ? 'selected' : '' ?>><?= $rId ?>. <?= htmlspecialchars($rName) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-blue" title="Rang Módosítása"><i class="fa-solid fa-save"></i></button>
                                        </form>
                                        
                                        <form method="POST" style="margin:0;" onsubmit="return confirm('Biztosan kirúgod ezt a tagot?');">
                                            <input type="hidden" name="action" value="kick_member">
                                            <input type="hidden" name="faction_prefix" value="<?= $fac['groupPrefix'] ?>">
                                            <input type="hidden" name="member_id" value="<?= $m['characterId'] ?>">
                                            <button type="submit" class="btn btn-red" title="Kirúgás a frakcióból"><i class="fa-solid fa-user-xmark"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:#6e7681; font-size:12px; font-style:italic; padding: 8px;">Saját karakter</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <script>
        var acc = document.getElementsByClassName("accordion");
        for (var i = 0; i < acc.length; i++) {
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
    </script>
</body>
</html>
