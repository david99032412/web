<?php
// forum/profile.php - FULLOS XENFORO PROFIL
require 'config.php'; require 'functions.php';

$loggedIn = isLoggedIn();
$myId = $_SESSION['user_id'] ?? 0;

$me = null;
if ($loggedIn) {
    $stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
    $stmtMe->execute([$myId]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);
}

$targetId = (int)($_GET['id'] ?? $myId);
if ($targetId <= 0) die("Érvénytelen profil!");

// Profil tulajdonosának lekérése
$stmt = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
$stmt->execute([$targetId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die("A felhasználó nem található!");

$rank = getUserRank($user['adminLevel']);
$isOnline = (strtotime($user['last_activity'] ?? '2000-01-01') > (time() - 15 * 60));

// Legutóbbi posztok lekérése a profilhoz
$postStmt = $pdo->prepare("
    SELECT p.*, t.title as thread_title 
    FROM forum_posts p 
    JOIN forum_threads t ON p.thread_id = t.id 
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC LIMIT 10
");
$postStmt->execute([$targetId]);
$recentPosts = $postStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($user['username']) ?> - NorthSideRP Profil</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-cover { height: 200px; background: linear-gradient(to bottom, #1a1a1a, #0a0a0a); position: relative; border-radius: 4px 4px 0 0; border: 1px solid var(--border-color); border-bottom: none; }
        .profile-avatar-wrapper { position: absolute; bottom: -40px; left: 30px; }
        .profile-avatar { width: 120px; height: 120px; border-radius: 4px; border: 4px solid var(--bg-block); object-fit: cover; background: var(--bg-block); }
        .online-dot { position: absolute; bottom: 5px; right: 5px; width: 20px; height: 20px; background: #2ea043; border: 3px solid var(--bg-block); border-radius: 50%; }
        .profile-header-info { margin-left: 170px; padding-top: 20px; padding-bottom: 20px; }
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
<div class="p-nav-sub"><a href="index.php">Új bejegyzések</a> <a href="search.php">Keresés a fórumban</a> <a href="members.php">Regisztrált Tagok</a></div>

<div class="p-body">
    <main class="p-body-main" style="max-width: 1000px; margin: 0 auto; flex: 1;">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <a href="members.php">Tagok</a>
            <i class="fa-solid fa-angle-right"></i>
            <span><?= htmlspecialchars($user['username']) ?></span>
        </div>

        <div class="profile-cover">
            <div class="profile-avatar-wrapper">
                <img src="<?= getAvatar($user) ?>" class="profile-avatar">
                <?php if($isOnline): ?><div class="online-dot" title="Jelenleg Online"></div><?php endif; ?>
            </div>
        </div>
        
        <div class="block-container" style="border-top: none; border-radius: 0 0 4px 4px; margin-top: 0;">
            <div class="block-body profile-header-info" style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1 style="margin: 0; font-size: 28px; color: <?= $rank['color'] ?>;">
                        <?= htmlspecialchars($user['username']) ?>
                        <?php if($user['is_banned']): ?><span style="background:#da3633; color:#fff; font-size:12px; padding:2px 6px; border-radius:4px; vertical-align:middle; margin-left:10px;">KITILTVA</span><?php endif; ?>
                    </h1>
                    <div style="font-size: 14px; font-weight: bold; color: <?= $rank['color'] ?>; margin-top: 5px;"><?= $rank['name'] ?></div>
                    <?php if(!empty($user['forum_title'])): ?>
                        <div style="font-size: 13px; color: var(--text-muted); margin-top: 3px;"><?= htmlspecialchars($user['forum_title']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <?php if($loggedIn && $myId != $targetId): ?>
                        <a href="pm.php?action=new&to=<?= htmlspecialchars($user['username']) ?>" class="button"><i class="fa-solid fa-envelope"></i> Privát Üzenet</a>
                    <?php endif; ?>
                    <?php if($loggedIn && ($myId == $targetId || $me['adminLevel'] >= 4)): ?>
                        <a href="edit_profile.php?id=<?= $targetId ?>" class="button" style="background: #333;"><i class="fa-solid fa-user-pen"></i> Szerkesztés</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="display: flex; border-top: 1px solid var(--border-color); background: var(--bg-block-alt);">
                <div style="flex: 1; padding: 15px; text-align: center; border-right: 1px solid var(--border-color);">
                    <div style="font-size: 20px; font-weight: bold; color: var(--text-light);"><?= number_format($user['forum_posts'], 0, '', ' ') ?></div>
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Üzenetek</div>
                </div>
                <div style="flex: 1; padding: 15px; text-align: center; border-right: 1px solid var(--border-color);">
                    <div style="font-size: 20px; font-weight: bold; color: var(--text-light);"><?= date("Y.m.d", strtotime($user['created_at'] ?? 'now')) ?></div>
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Csatlakozott</div>
                </div>
                <div style="flex: 1; padding: 15px; text-align: center;">
                    <div style="font-size: 20px; font-weight: bold; color: var(--text-light);"><?= $isOnline ? '<span style="color:#2ea043;">Online</span>' : date("Y.m.d H:i", strtotime($user['last_activity'] ?? 'now')) ?></div>
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Utolsó aktivitás</div>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 20px; margin-top: 20px;">
            
            <div style="flex: 1;">
                <?php if(!empty($user['about'])): ?>
                <div class="block-container">
                    <h3 class="block-minorHeader">Rólam</h3>
                    <div class="block-body" style="padding: 15px; font-size: 14px; line-height: 1.6;">
                        <?= parseBBCode($user['about']) ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if(!empty($user['forum_signature'])): ?>
                <div class="block-container">
                    <h3 class="block-minorHeader">Aláírás</h3>
                    <div class="block-body" style="padding: 15px; font-size: 13px; color: var(--text-muted);">
                        <?= parseBBCode($user['forum_signature']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div style="width: 400px;">
                <div class="block-container">
                    <h3 class="block-minorHeader">Legutóbbi hozzászólások</h3>
                    <div class="block-body" style="padding: 0;">
                        <?php if(empty($recentPosts)): ?>
                            <div style="padding: 15px; color: var(--text-muted); font-size: 13px; text-align: center;">Még nem írt a fórumba.</div>
                        <?php else: ?>
                            <?php foreach($recentPosts as $rp): ?>
                                <div style="padding: 12px 15px; border-bottom: 1px solid var(--border-color);">
                                    <div style="font-size: 13px; margin-bottom: 5px;">
                                        Ide írt: <a href="thread.php?id=<?= $rp['thread_id'] ?>#post-<?= $rp['id'] ?>" style="color: var(--text-light); font-weight: bold;"><?= htmlspecialchars($rp['thread_title']) ?></a>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted); background: var(--bg-page); padding: 8px; border-radius: 4px; border: 1px solid var(--border-color);">
                                        <?= htmlspecialchars(mb_substr(strip_tags(parseBBCode($rp['content'])), 0, 80)) ?>...
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px; text-align: right;">
                                        <?= time_elapsed_string($rp['created_at']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    </main>
</div>

<footer class="p-footer">
    <div class="p-footer-inner"><div>&copy; 2026 NorthSideRP Fórum. Minden jog fenntartva.</div></div>
</footer>
</body>
</html>
