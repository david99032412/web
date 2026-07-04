<?php
// forum/online.php - ONLINE TAGOK OLDAL
require 'config.php'; require 'functions.php';

$loggedIn = isLoggedIn();
$myId = $_SESSION['user_id'] ?? 0;

$me = null;
if ($loggedIn) {
    $stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
    $stmtMe->execute([$myId]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);
}

// 15 percen belüli aktivitás lekérése
$timeLimit = time() - (15 * 60); 
$stmt = $pdo->prepare("
    SELECT accountId, username, forum_avatar, COALESCE(adminLevel, AdminLevel, 0) as adminLevel, last_activity 
    FROM accounts 
    WHERE last_activity > ? 
    ORDER BY last_activity DESC
");
$stmt->execute([date('Y-m-d H:i:s', $timeLimit)]);
$onlineUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Jelenleg Online - NorthSideRP</title>
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
    <a href="new_posts.php">Új bejegyzések</a> 
    <a href="search.php">Keresés a fórumban</a>
    <a href="members.php">Regisztrált Tagok</a>
    <a href="online.php" style="color:var(--primary-color);">Jelenleg Online</a>
</div>

<div class="p-body">
    <main class="p-body-main" style="flex: 1; max-width: 1200px; margin: 0 auto;">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <span>Jelenleg Online Tagok</span>
        </div>

        <div class="block-container">
            <h2 class="block-header"><i class="fa-solid fa-wifi"></i> Jelenleg aktív felhasználók (<?= count($onlineUsers) ?>)</h2>
            
            <?php if(empty($onlineUsers)): ?>
                <div style="padding: 25px; text-align: center; color: var(--text-muted);">Jelenleg senki sincs online a fórumban.</div>
            <?php else: ?>
                <div class="member-grid">
                    <?php foreach($onlineUsers as $ou): 
                        $rank = getUserRank($ou['adminLevel']);
                    ?>
                    <div class="member-card">
                        <div style="position:relative;">
                            <img src="<?= getAvatar($ou) ?>" style="width: 56px; height: 56px; border-radius: 4px; object-fit: cover; border: 1px solid var(--border-color);">
                            <div style="position: absolute; bottom: -3px; right: -3px; width: 14px; height: 14px; background: #51a351; border: 2px solid var(--bg-block); border-radius: 50%;" title="Online"></div>
                        </div>
                        
                        <div style="flex: 1; min-width:0;">
                            <a href="profile.php?id=<?= $ou['accountId'] ?>" style="font-size: 16px; font-weight: bold; color: <?= $rank['color'] ?>; display:block; margin-bottom: 2px;">
                                <?= htmlspecialchars($ou['username']) ?>
                            </a>
                            <div style="font-size: 11px; font-weight: 600; text-transform:uppercase; color: <?= $rank['color'] ?>; margin-bottom: 5px;">
                                <?= $rank['name'] ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-muted);">
                                <i class="fa-regular fa-clock"></i> <?= time_elapsed_string($ou['last_activity']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<footer class="p-footer">
    <div class="p-footer-inner"><div>&copy; 2026 NorthSideRP Fórum.</div></div>
</footer>
</body>
</html>
