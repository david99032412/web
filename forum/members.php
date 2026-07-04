<?php
// forum/members.php - TAGOK LISTÁJA (XENFORO STÍLUS)
require 'config.php'; 
require 'functions.php';

$loggedIn = isLoggedIn();
$myId = $_SESSION['user_id'] ?? 0;

$me = null;
if ($loggedIn) {
    $stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
    $stmtMe->execute([$myId]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);
}

// Tagok lekérése (legtöbb fórum poszttal rendelkezők elöl)
$stmt = $pdo->query("
    SELECT accountId, username, forum_avatar, forum_posts, COALESCE(adminLevel, AdminLevel, 0) as adminLevel, last_activity
    FROM accounts 
    ORDER BY forum_posts DESC 
    LIMIT 50
");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Regisztrált Tagok - NorthSideRP</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .member-card { background: var(--bg-block); border: 1px solid var(--border-color); border-radius: 4px; padding: 15px; display: flex; align-items: center; gap: 15px; transition: 0.2s; }
        .member-card:hover { background: var(--bg-block-alt); transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .member-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; padding: 15px; }
    </style>
</head>
<body>

<header class="p-nav">
    <div class="p-nav-logo">NorthSide <span>Fórum</span></div>
    <div class="p-nav-opposite">
        <?php if($loggedIn): ?>
            <a href="search.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="pm.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-envelope"></i></a>
            <a href="notifications.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-bell"></i></a>
            <a href="profile.php?id=<?= $myId ?>" class="p-navgroup-link"><img src="<?= getAvatar($me) ?>" class="avatar-menu"> <?= htmlspecialchars($me['username']) ?></a>
            <a href="logout.php" class="p-navgroup-link icon-only" style="border-right: 1px solid var(--border-color);"><i class="fa-solid fa-power-off"></i></a>
        <?php else: ?>
            <a href="login.php" class="p-navgroup-link" style="border:none;">Belépés</a>
            <a href="register.php" class="button">Regisztráció</a>
        <?php endif; ?>
    </div>
</header>
<div class="p-nav-sub">
    <a href="index.php">Új bejegyzések</a> 
    <a href="search.php">Keresés a fórumban</a>
    <a href="members.php" style="color:var(--primary-color);">Regisztrált Tagok</a>
</div>

<div class="p-body">
    <main class="p-body-main" style="flex: 1; max-width: 1200px; margin: 0 auto;">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <span>Tagok</span>
        </div>

        <div class="block-container">
            <h2 class="block-header"><i class="fa-solid fa-users"></i> Aktív Fórumozók</h2>
            
            <div class="member-grid">
                <?php foreach($members as $m): 
                    $rank = getUserRank($m['adminLevel']);
                    $isOnline = (strtotime($m['last_activity'] ?? '2000-01-01') > (time() - 15 * 60));
                ?>
                <div class="member-card">
                    <div style="position:relative;">
                        <img src="<?= getAvatar($m) ?>" style="width: 64px; height: 64px; border-radius: 4px; object-fit: cover; border: 1px solid var(--border-color);">
                        <?php if($isOnline): ?><div style="position: absolute; bottom: -3px; right: -3px; width: 14px; height: 14px; background: #51a351; border: 2px solid var(--bg-block); border-radius: 50%;" title="Online"></div><?php endif; ?>
                    </div>
                    
                    <div style="flex: 1; min-width:0;">
                        <a href="profile.php?id=<?= $m['accountId'] ?>" style="font-size: 16px; font-weight: bold; color: <?= $rank['color'] ?>; display:block; margin-bottom: 2px;">
                            <?= htmlspecialchars($m['username']) ?>
                        </a>
                        <div style="font-size: 11px; font-weight: 600; text-transform:uppercase; color: <?= $rank['color'] ?>; margin-bottom: 5px;">
                            <?= $rank['name'] ?>
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            Üzenetek: <span style="color:var(--text-light); font-weight:bold;"><?= number_format($m['forum_posts'], 0, '', ' ') ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>
</div>

<footer class="p-footer">
    <div class="p-footer-inner"><div>&copy; 2026 NorthSideRP Fórum.</div></div>
</footer>
</body>
</html>
