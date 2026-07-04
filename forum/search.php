<?php
// forum/search.php - XENFORO DIZÁJN
require 'config.php'; require 'functions.php';

$loggedIn = isLoggedIn();
$myId = $_SESSION['user_id'] ?? 0;

$me = null;
if ($loggedIn) {
    $stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
    $stmtMe->execute([$myId]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);
}

$query = trim($_GET['q'] ?? '');
$results = [];

if (!empty($query)) {
    // Keresés a témák címeiben és a hozzászólások tartalmában
    $searchQuery = "%" . $query . "%";
    $stmt = $pdo->prepare("
        SELECT p.id as post_id, p.content, p.created_at, t.id as thread_id, t.title as thread_title, a.username, a.forum_avatar
        FROM forum_posts p
        JOIN forum_threads t ON p.thread_id = t.id
        JOIN accounts a ON p.user_id = a.accountId
        WHERE t.title LIKE ? OR p.content LIKE ?
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$searchQuery, $searchQuery]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Keresés - NorthSideRP Fórum</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header class="p-nav">
    <div class="p-nav-logo">NorthSide <span>Fórum</span></div>
    <div class="p-nav-opposite">
        <?php if($loggedIn): ?>
            <a href="search.php" class="p-navgroup-link icon-only" title="Keresés a fórumban"><i class="fa-solid fa-magnifying-glass"></i></a>
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
<div class="p-nav-sub"><a href="index.php">Új bejegyzések</a> <a href="search.php">Keresés a fórumban</a></div>

<div class="p-body">
    <main class="p-body-main" style="max-width: 900px; margin: 0 auto; flex: 1;">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <span>Keresés</span>
        </div>

        <div class="block-container">
            <h2 class="block-header"><i class="fa-solid fa-magnifying-glass"></i> Keresés a fórumban</h2>
            <div class="block-body" style="padding: 25px;">
                
                <form method="GET" action="search.php" style="display:flex; gap:10px; margin-bottom: 30px;">
                    <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Kulcsszó (pl. frakció, szabályzat, stb.)..." required style="flex:1; padding: 12px; background: #0a0a0a; border: 1px solid var(--border-color); color: var(--text-normal); border-radius: 4px; font-size: 14px;">
                    <button type="submit" class="button" style="padding: 0 20px;"><i class="fa-solid fa-search"></i> Keresés</button>
                </form>

                <?php if(!empty($query)): ?>
                    <h3 style="margin-top:0; border-bottom:1px solid var(--border-color); padding-bottom:10px; font-size: 15px; color:var(--text-light);">Keresési eredmények: "<?= htmlspecialchars($query) ?>"</h3>
                    
                    <?php if(empty($results)): ?>
                        <div style="padding: 20px; text-align: center; color: var(--text-muted);">Nem találtunk semmit a megadott kulcsszóra.</div>
                    <?php else: ?>
                        <?php foreach($results as $res): ?>
                            <div style="display:flex; gap:15px; margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid var(--border-color);">
                                <img src="<?= getAvatar($res) ?>" style="width:48px; height:48px; border-radius:4px; object-fit:cover;">
                                <div style="flex:1;">
                                    <a href="thread.php?id=<?= $res['thread_id'] ?>#post-<?= $res['post_id'] ?>" style="font-size: 16px; font-weight:bold; color:var(--text-light);"><?= htmlspecialchars($res['thread_title']) ?></a>
                                    <div style="font-size: 12px; color:var(--text-muted); margin-top:3px; margin-bottom:8px;">
                                        Szerző: <span style="color:var(--text-normal);"><?= htmlspecialchars($res['username']) ?></span> &bull; <?= date("Y. M. d. H:i", strtotime($res['created_at'])) ?>
                                    </div>
                                    <div style="font-size: 13px; color:var(--text-muted); line-height: 1.5; background: var(--bg-block-alt); padding: 10px; border-radius: 4px;">
                                        <?= htmlspecialchars(mb_substr(strip_tags(parseBBCode($res['content'])), 0, 150)) ?>...
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>

    </main>
</div>

<footer class="p-footer">
    <div class="p-footer-inner"><div>&copy; 2026 NorthSideRP Fórum.</div></div>
</footer>
</body>
</html>