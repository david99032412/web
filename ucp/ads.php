<?php
// ucp/ads.php - ÉLŐ TELEFONOS HIRDETÉSEK (SZIGORÍTOTT ADATMEGJELENÍTÉS)
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$stmtAcc = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
$stmtAcc->execute([$_SESSION['user_username']]);
$account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

if (!$account) die("Hiba a fiók betöltésekor!");
$isAdmin = ((int)$account['adminLevel'] >= 7);

$ads = [];
try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM phoneads");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Csak a létező oszlopokat vesszük ki, NINCSENEK "Ismeretlen" helyettesítők!
    $textCol = in_array('text', $cols) ? 'text' : (in_array('message', $cols) ? 'message' : null);
    $nameCol = in_array('name', $cols) ? 'name' : (in_array('sender', $cols) ? 'sender' : (in_array('creator', $cols) ? 'creator' : null));
    $numCol  = in_array('number', $cols) ? 'number' : (in_array('phone', $cols) ? 'phone' : (in_array('phonenumber', $cols) ? 'phonenumber' : null));
    $typeCol = in_array('type', $cols) ? 'type' : (in_array('isDarkweb', $cols) ? 'isDarkweb' : null);
    $dateCol = in_array('date', $cols) ? 'date' : (in_array('created_at', $cols) ? 'created_at' : null);

    if ($textCol) {
        $selName = $nameCol ? "`$nameCol`" : "NULL";
        $selNum  = $numCol ? "`$numCol`" : "NULL";
        $selType = $typeCol ? "`$typeCol`" : "NULL";
        $selDate = $dateCol ? "`$dateCol`" : "NULL";

        // Csak azokat hozzuk le, amiknek van VALÓS szövege
        $sql = "SELECT id, `$textCol` as ad_text, $selName as ad_name, $selNum as ad_number, $selType as ad_type, $selDate as ad_date FROM phoneads WHERE `$textCol` IS NOT NULL AND TRIM(`$textCol`) != '' ORDER BY id DESC LIMIT 100";
        $ads = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Hirdetések - NorthSide UCP</title>
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
        .welcome-box { background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), transparent); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 12px; padding: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .welcome-box h1 { color: #fff; font-size: 28px; font-weight: 800; margin: 0; }

        .ads-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .ad-card { background: rgb(33, 35, 40); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; transition: 0.2s; position: relative; overflow: hidden;}
        .ad-card:hover { transform: translateY(-5px); border-color: #f59e0b; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        
        .ad-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;}
        .ad-sender-name { font-weight: 900; color: #fff; font-size: 16px; margin-bottom: 3px; display: flex; align-items: center; gap: 8px;}
        .ad-sender-phone { color: #f59e0b; font-family: monospace; font-size: 13px; font-weight: bold; background: rgba(245, 158, 11, 0.1); padding: 2px 8px; border-radius: 4px; width: fit-content;}
        .ad-date { font-size: 11px; color: #6e7681; margin-top: 5px; }
        
        .ad-text { font-size: 14px; color: #d1d5db; line-height: 1.6; word-wrap: break-word; font-style: italic; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 8px;}
        
        .ad-card.darkweb { background: #111; border-color: rgba(139, 92, 246, 0.3); }
        .ad-card.darkweb:hover { border-color: #8b5cf6; box-shadow: 0 5px 15px rgba(139, 92, 246, 0.2); }
        .ad-card.darkweb .ad-sender-name { color: #8b5cf6; }
        .ad-card.darkweb .ad-text { color: #a78bfa; background: rgba(139, 92, 246, 0.05); border: 1px dashed rgba(139, 92, 246, 0.2);}
        
        .badge { font-size: 10px; padding: 3px 6px; border-radius: 4px; font-weight: bold; text-transform: uppercase; position: absolute; top: 20px; right: 20px;}
        .badge-normal { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-darkweb { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.3); }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">NorthSide <span>UCP</span></div>
        <div class="nav-links">
            <a href="dashboard.php">Kezdőlap</a>
            <a href="profile.php">Profilom</a>
            <a href="characters.php">Karakterek</a>
            <a href="factions.php">Frakcióim</a>
            <a href="support.php">Support</a>
            <a href="vehicles.php">Járművek</a>
            <a href="map.php">Térkép</a>
            <a href="ads.php" class="active">Hirdetések</a>
            <a href="invoices.php">Számlák</a>
            <?php if($isAdmin): ?><a href="adminpanel.php" style="color: #e3b341;">Admin Panel</a><?php endif; ?>
            <a href="logout.php" style="color: #ef4444;">Kilépés</a>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-box">
            <h1><i class="fa-solid fa-bullhorn" style="color: #f59e0b; margin-right:10px;"></i> Élő Apróhirdetések</h1>
            <div style="color: #9ca3af; font-size: 16px;">Legutóbbi <span style="color:#fff; font-weight:bold;"><?= count($ads) ?> db</span> hirdetés</div>
        </div>

        <?php if(empty($ads)): ?>
            <div style="text-align: center; padding: 80px 20px; background: rgb(33, 35, 40); border: 1px dashed rgba(255,255,255,0.1); border-radius: 12px; color: #9ca3af;">
                <i class="fa-solid fa-comment-slash" style="font-size: 64px; opacity: 0.3; margin-bottom: 20px;"></i>
                <h3 style="color:#fff; font-size: 20px;">Nincsenek aktív hirdetések</h3>
            </div>
        <?php else: ?>
            <div class="ads-grid">
                <?php foreach($ads as $ad): 
                    $isDark = ($ad['ad_type'] == 1 || $ad['ad_type'] === 'darkweb' || $ad['ad_type'] === 'illegal');
                ?>
                <div class="ad-card <?= $isDark ? 'darkweb' : '' ?>">
                    <?php if($isDark): ?>
                        <span class="badge badge-darkweb"><i class="fa-solid fa-skull"></i> Dark Web</span>
                    <?php else: ?>
                        <span class="badge badge-normal">Közhírré Tétel</span>
                    <?php endif; ?>
                    
                    <div class="ad-header">
                        <div>
                            <?php if(!empty($ad['ad_name']) && !$isDark): ?>
                                <div class="ad-sender-name"><i class="fa-solid fa-user"></i> <?= htmlspecialchars(str_replace('_', ' ', $ad['ad_name'])) ?></div>
                            <?php elseif($isDark): ?>
                                <div class="ad-sender-name"><i class="fa-solid fa-user-secret"></i> Ismeretlen Hirdető</div>
                            <?php endif; ?>
                            
                            <?php if(!empty($ad['ad_number']) && !$isDark): ?>
                                <div class="ad-sender-phone"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($ad['ad_number']) ?></div>
                            <?php endif; ?>
                            
                            <?php if(!empty($ad['ad_date'])): ?>
                                <div class="ad-date"><i class="fa-solid fa-clock"></i> Feladva: <?= $ad['ad_date'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="ad-text">
                        "<?= nl2br(htmlspecialchars($ad['ad_text'])) ?>"
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
