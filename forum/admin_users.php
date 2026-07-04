<?php
// forum/admin_users.php - ADMIN FELHASZNÁLÓ KEZELŐ
require 'config.php'; require 'functions.php';

$loggedIn = isLoggedIn();
if (!$loggedIn) { header("Location: login.php"); exit; }

$myId = $_SESSION['user_id'];
$stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
$stmtMe->execute([$myId]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);

// SZIGORÚ ELLENŐRZÉS
if (!$me || $me['adminLevel'] <= 0) {
    die("Nincs jogosultságod az Admin Panel megtekintéséhez!");
}

$msg = "";
$searchQuery = $_GET['q'] ?? '';
$users = [];

// Ha keresett valakit
if (!empty($searchQuery)) {
    $stmt = $pdo->prepare("SELECT accountId, username, forum_avatar, emailAddress, warn_level, is_banned, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE username LIKE ? LIMIT 20");
    $stmt->execute(["%" . $searchQuery . "%"]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// GYORS MŰVELETEK (WARN / BAN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $targetId = (int)$_POST['target_id'];
    
    if ($_POST['action'] == 'warn') {
        $percent = (int)$_POST['warn_percent'];
        
        $wStmt = $pdo->prepare("SELECT warn_level FROM accounts WHERE accountId = ?");
        $wStmt->execute([$targetId]);
        $currentWarn = $wStmt->fetchColumn();
        
        $newWarn = $currentWarn + $percent;
        $isBanned = 0;
        
        if ($newWarn >= 100) {
            $newWarn = 100;
            $isBanned = 1; // 100%-nál auto ban
        }
        
        $pdo->prepare("UPDATE accounts SET warn_level = ?, is_banned = ? WHERE accountId = ?")->execute([$newWarn, $isBanned, $targetId]);
        $msg = "<div style='background: rgba(46, 160, 67, 0.1); border-left: 3px solid #2ea043; color: #3fb950; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-check'></i> Figyelmeztetés (Warn) sikeresen kiosztva! Jelenlegi szint: $newWarn%</div>";
    }
    
    elseif ($_POST['action'] == 'ban') {
        $reason = clean($_POST['ban_reason']);
        $pdo->prepare("UPDATE accounts SET is_banned = 1, ban_reason = ? WHERE accountId = ?")->execute([$reason, $targetId]);
        $msg = "<div style='background: rgba(218, 54, 51, 0.1); border-left: 3px solid #da3633; color: #ff7b72; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-ban'></i> Felhasználó sikeresen kitiltva!</div>";
    }
    
    elseif ($_POST['action'] == 'unban') {
        $pdo->prepare("UPDATE accounts SET is_banned = 0, ban_reason = '', warn_level = 0 WHERE accountId = ?")->execute([$targetId]);
        $msg = "<div style='background: rgba(46, 160, 67, 0.1); border-left: 3px solid #2ea043; color: #3fb950; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-check'></i> Kitiltás és Warn sikeresen törölve!</div>";
    }
    
    if (!empty($searchQuery)) {
        $stmt->execute(["%" . $searchQuery . "%"]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Felhasználó Kezelő - Admin Panel</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-input { width: 100%; padding: 10px; background: #0a0a0a; border: 1px solid var(--border-color); color: var(--text-normal); border-radius: 4px; box-sizing: border-box; font-family: inherit;}
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
                <a href="admin_users.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-light); font-weight:bold;"><i class="fa-solid fa-users" style="width:20px;"></i> Felhasználó kezelő</a>
                <a href="admin_reports.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-triangle-exclamation" style="width:20px;"></i> Jelentések</a>
                <a href="mod_logs.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-clipboard-list" style="width:20px;"></i> Moderátori Napló</a>
            </div>
        </div>
    </aside>

    <main class="p-body-main">
        
        <div class="p-breadcrumbs" style="margin-bottom: 20px;">
            <a href="admin.php" style="color: #c0392b; font-weight: bold; font-size: 14px; background: rgba(192, 57, 43, 0.1); padding: 8px 12px; border-radius: 4px; border: 1px solid #c0392b;"><i class="fa-solid fa-arrow-left"></i> Vissza a Vezérlőpultra (admin.php)</a>
        </div>

        <?= $msg ?>

        <div class="block-container">
            <h2 class="block-header"><i class="fa-solid fa-user-magnifying-glass"></i> Felhasználó Keresése</h2>
            <div class="block-body" style="padding: 25px;">
                <form method="GET" action="admin_users.php" style="display:flex; gap:10px;">
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Írd be a játékos nevét..." class="admin-input" style="margin:0;">
                    <button type="submit" class="button" style="padding: 0 20px;"><i class="fa-solid fa-search"></i> Keresés</button>
                </form>
            </div>
        </div>

        <?php if(!empty($searchQuery)): ?>
        <div class="block-container">
            <h2 class="block-header">Találatok</h2>
            <div class="block-body" style="padding: 0;">
                <?php if(empty($users)): ?>
                    <div style="padding: 20px; text-align: center; color: var(--text-muted);">Nincs találat.</div>
                <?php else: ?>
                    <?php foreach($users as $u): 
                        $rank = getUserRank($u['adminLevel']);
                    ?>
                    <div style="padding: 15px; border-bottom: 1px solid var(--border-color); display: flex; align-items: flex-start; gap: 15px;">
                        <img src="<?= getAvatar($u) ?>" style="width: 64px; height: 64px; border-radius: 4px; object-fit: cover;">
                        
                        <div style="flex: 1;">
                            <a href="profile.php?id=<?= $u['accountId'] ?>" target="_blank" style="font-size: 16px; font-weight: bold; color: <?= $rank['color'] ?>; display:block; margin-bottom:5px;">
                                <?= htmlspecialchars($u['username']) ?>
                                <?php if($u['is_banned']): ?><span style="background:#da3633; color:#fff; font-size:10px; padding:2px 5px; border-radius:3px; vertical-align:top; margin-left:5px;">KITILTVA</span><?php endif; ?>
                            </a>
                            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 10px;">
                                ID: <?= $u['accountId'] ?> &bull; Warn szint: <strong style="color:<?= $u['warn_level'] > 50 ? '#da3633' : 'var(--primary-color)' ?>;"><?= $u['warn_level'] ?>%</strong>
                            </div>

                            <div style="display:flex; gap:10px;">
                                <a href="admin_edit_user.php?id=<?= $u['accountId'] ?>" class="button" style="background:#333; font-size:11px; padding: 5px 10px;"><i class="fa-solid fa-user-pen"></i> Profil Szerkesztése</a>
                                
                                <?php if(!$u['is_banned']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="warn">
                                        <input type="hidden" name="target_id" value="<?= $u['accountId'] ?>">
                                        <select name="warn_percent" style="background:#0a0a0a; border:1px solid var(--border-color); color:var(--text-light); padding:4px; border-radius:3px;">
                                            <option value=\"10\">Warn +10%</option>
                                            <option value=\"20\">Warn +20%</option>
                                            <option value=\"50\">Warn +50%</option>
                                        </select>
                                        <button type="submit" class="button" style="background:#f39c12; font-size:11px; padding: 5px 10px;">Kiosztás</button>
                                    </form>

                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Biztosan kitiltod ezt a felhasználót?');">
                                        <input type="hidden" name="action" value="ban">
                                        <input type="hidden" name="target_id" value="<?= $u['accountId'] ?>">
                                        <input type="text" name="ban_reason" placeholder="Kitiltás indoka..." required style="background:#0a0a0a; border:1px solid var(--border-color); color:var(--text-light); padding:4px; border-radius:3px; width:150px;">
                                        <button type="submit" class="button" style="background:#da3633; font-size:11px; padding: 5px 10px;"><i class="fa-solid fa-ban"></i> Ban</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="unban">
                                        <input type="hidden" name="target_id" value="<?= $u['accountId'] ?>">
                                        <button type="submit" class="button" style="background:#2ea043; font-size:11px; padding: 5px 10px;"><i class="fa-solid fa-unlock"></i> Unban (Kitiltás feloldása)</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

</body>
</html>
