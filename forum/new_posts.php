<?php
// forum/new_posts.php - ÚJ BEJEGYZÉSEK HÍRFOLYAM
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

// A legutóbbi 30 poszt lekérése az egész fórumból
$stmt = $pdo->query("
    SELECT p.id as post_id, p.content, p.created_at, t.id as thread_id, t.title as thread_title, a.username, a.forum_avatar, c.title as cat_title
    FROM forum_posts p
    JOIN forum_threads t ON p.thread_id = t.id
    JOIN forum_categories c ON t.category_id = c.id
    JOIN accounts a ON p.user_id = a.accountId
    ORDER BY p.created_at DESC
    LIMIT 30
");
$latestPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Új bejegyzések - NorthSideRP Fórum</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .new-post-row { display: flex; padding: 15px; border-bottom: 1px solid var(--border-color); transition: 0.2s; background: var(--bg-block); }
        .new-post-row:hover { background: var(--bg-block-alt); }
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
    <a href="new_posts.php" style="color:var(--primary-color);">Új bejegyzések</a> 
    <a href="search.php">Keresés a fórumban</a>
    <a href="members.php">Regisztrált Tagok</a>
</div>

<div class="p-body">
    <main class="p-body-main" style="flex: 1; max-width: 1000px; margin: 0 auto;">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <span>Új bejegyzések</span>
        </div>

        <div class="block-container">
            <h2 class="block-header"><i class="fa-solid fa-bolt"></i> Legfrissebb Fórum Aktivitás</h2>
            
            <?php if(empty($latestPosts)): ?>
                <div style="padding: 20px; text-align: center; color: var(--text-muted);">Jelenleg nincs új bejegyzés a fórumban.</div>
            <?php else: ?>
                <?php foreach($latestPosts as $post): ?>
                    <div class="new-post-row">
                        <img src="<?= getAvatar($post) ?>" style="width: 48px; height: 48px; border-radius: 4px; object-fit: cover; margin-right: 15px;">
                        
                        <div style="flex: 1; min-width: 0;">
                            <a href="thread.php?id=<?= $post['thread_id'] ?>#post-<?= $post['post_id'] ?>" style="font-size: 16px; font-weight: bold; color: var(--text-light); display: block; margin-bottom: 3px;">
                                <?= htmlspecialchars($post['thread_title']) ?>
                            </a>
                            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 5px;">
                                Szerző: <span style="color:var(--text-normal);"><?= htmlspecialchars($post['username']) ?></span> &bull; 
                                Kategória: <span style="color:var(--text-normal);"><?= htmlspecialchars($post['cat_title']) ?></span> &bull; 
                                <?= time_elapsed_string($post['created_at']) ?>
                            </div>
                            <div style="font-size: 13px; color: var(--text-muted); line-height: 1.5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;">
                                <?= htmlspecialchars(strip_tags(parseBBCode($post['content']))) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
        </div>
    </main>
</div>

<footer class="p-footer">
    <div class="p-footer-inner"><div>&copy; 2026 NorthSideRP Fórum.</div></div>
</footer>
</body>
</html>
