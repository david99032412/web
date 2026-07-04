<?php
// forum/mod_logs.php - XENFORO ADMIN PANEL
require 'config.php'; require 'functions.php';

$loggedIn = isLoggedIn();
if (!$loggedIn) { header("Location: login.php"); exit; }

$myId = $_SESSION['user_id'];
$stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
$stmtMe->execute([$myId]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);

if (!$me || $me['adminLevel'] <= 0) {
    die("Nincs jogosultságod az Admin Panel megtekintéséhez!");
}

// Logok lekérése
try {
    $stmt = $pdo->query("SELECT l.*, a.username, a.forum_avatar, COALESCE(a.adminLevel, a.AdminLevel, 0) as adminLevel 
                         FROM forum_mod_logs l 
                         LEFT JOIN accounts a ON l.user_id = a.accountId 
                         ORDER BY l.created_at DESC LIMIT 100");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $logs = []; // Ha még nincs tábla, üres lista
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Moderátori Napló - Admin Panel</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .log-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        .log-table th { background: #111; padding: 12px 15px; color: var(--text-muted); font-weight: bold; border-bottom: 1px solid var(--border-color); text-transform: uppercase; font-size: 11px; }
        .log-table td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); color: var(--text-light); }
        .log-table tr:hover td { background: var(--bg-block-alt); }
    </style>
</head>
<body>

<header class="p-nav" style="border-bottom-color: #c0392b;">
    <div class="p-nav-logo" style="color:#c0392b;">NorthSide <span style="color:#fff;">ADMIN</span></div>
    <div class="p-nav-opposite">
        <a href="index.php" class="p-navgroup-link icon-only" title="Vissza a fórumra"><i class="fa-solid fa-right-from-bracket"></i> Kilépés a fórumba</a>
    </div>
</header>

<div class="p-body">
    
    <aside class="p-body-sidebar" style="width: 250px;">
        <div class="block-container">
            <h3 class="block-minorHeader" style="background: linear-gradient(0deg, #7c241c 0%, #c0392b 100%);">Navigáció</h3>
            <div class="block-body" style="padding:0;">
                <a href="admin.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-gauge" style="width:20px;"></i> Vezérlőpult</a>
                <a href="admin_users.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-users" style="width:20px;"></i> Felhasználó kezelő</a>
                <a href="admin_reports.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-triangle-exclamation" style="width:20px;"></i> Jelentések</a>
                <a href="mod_logs.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-light); font-weight:bold;"><i class="fa-solid fa-clipboard-list" style="width:20px;"></i> Moderátori Napló</a>
            </div>
        </div>
    </aside>

    <main class="p-body-main">
        
        <div class="p-breadcrumbs" style="margin-bottom: 20px;">
            <a href="admin.php" style="color: #c0392b; font-weight: bold; font-size: 14px; background: rgba(192, 57, 43, 0.1); padding: 8px 12px; border-radius: 4px; border: 1px solid #c0392b;"><i class="fa-solid fa-arrow-left"></i> Vissza a Vezérlőpultra (admin.php)</a>
        </div>

        <div class="block-container">
            <h2 class="block-header"><i class="fa-solid fa-clipboard-list"></i> Legutóbbi Moderátori Akciók (100)</h2>
            <div class="block-body" style="padding: 0; overflow-x: auto;">
                
                <?php if(empty($logs)): ?>
                    <div style="padding: 20px; text-align: center; color: var(--text-muted);">Még nem történt naplózott esemény.</div>
                <?php else: ?>
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Adminisztrátor / Moderátor</th>
                                <th>Elvégzett Művelet</th>
                                <th>Részletek (Célpont)</th>
                                <th>Dátum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): 
                                $rank = getUserRank($log['adminLevel']);
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <img src="<?= getAvatar($log) ?>" style="width:24px; height:24px; border-radius:2px; object-fit:cover;">
                                        <strong style="color:<?= $rank['color'] ?>;"><?= htmlspecialchars($log['username']) ?></strong>
                                    </div>
                                </td>
                                <td style="font-weight: bold; color: var(--primary-color);"><?= htmlspecialchars($log['action']) ?></td>
                                <td style="color: var(--text-muted);"><?= htmlspecialchars($log['target_info']) ?></td>
                                <td style="color: var(--text-muted);"><?= date("Y. M. d. H:i", strtotime($log['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            </div>
        </div>

    </main>
</div>

</body>
</html>
