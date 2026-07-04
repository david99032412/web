<?php
// forum/category.php - XENFORO 1:1 REPLIKA
require 'config.php'; require 'functions.php';

$catId = (int)($_GET['id'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$perPage = 20;

$loggedIn = isLoggedIn();
$myId = $_SESSION['user_id'] ?? 0;
$me = null;
$isMod = false;

if ($loggedIn) {
    $stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
    $stmtMe->execute([$myId]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);
    if ($me && $me['adminLevel'] > 0) $isMod = true;
}

// Kategória lekérése
$stmt = $pdo->prepare("SELECT * FROM forum_categories WHERE id = ?");
$stmt->execute([$catId]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) die("Kategória nem található! <a href='index.php'>Vissza</a>");

// Lapozás
$offset = ($page - 1) * $perPage;
$totalThreads = $pdo->prepare("SELECT COUNT(*) FROM forum_threads WHERE category_id = ?");
$totalThreads->execute([$catId]);
$totalPages = ceil($totalThreads->fetchColumn() / $perPage);

// Témák lekérése a létrehozó adataival
$stmt = $pdo->prepare("
    SELECT t.*, a.username, a.forum_avatar 
    FROM forum_threads t 
    LEFT JOIN accounts a ON t.author_id = a.accountId 
    WHERE t.category_id = ? 
    ORDER BY t.is_sticky DESC, t.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $catId, PDO::PARAM_INT);
$stmt->bindValue(2, $perPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($category['title']) ?> - NorthSideRP</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header class="p-nav">
    <div class="p-nav-logo">NorthSide <span>Fórum</span></div>
    <div class="p-nav-opposite">
        <?php if($loggedIn): ?>
            <a href="search.php" class="p-navgroup-link icon-only" title="Keresés a fórumban"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="pm.php" class="p-navgroup-link icon-only" title="Privát üzenetek"><i class="fa-solid fa-envelope"></i></a>
            <a href="notifications.php" class="p-navgroup-link icon-only" title="Értesítések"><i class="fa-solid fa-bell"></i></a>
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
    <main class="p-body-main" style="flex: 1; max-width: 1200px; margin: 0 auto;">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <a href="index.php">Fórum</a>
            <i class="fa-solid fa-angle-right"></i>
            <span><?= htmlspecialchars($category['title']) ?></span>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
            <h1 style="margin:0; font-size:24px; color:var(--text-light); font-weight:700; letter-spacing: -0.5px;"><?= htmlspecialchars($category['title']) ?></h1>
            <?php if($loggedIn): ?>
                <a href="post_thread.php?cat=<?= $catId ?>" class="button"><i class="fa-solid fa-pen"></i> Új Téma Nyitása</a>
            <?php endif; ?>
        </div>

        <div class="block-container">
            <div class="block-header">Témák</div>
            
            <?php if(empty($threads)): ?>
                <div style="padding: 20px; text-align: center; color: var(--text-muted);">Ebben a kategóriában még nincsenek témák. Legyél te az első!</div>
            <?php else: ?>
                <?php foreach($threads as $t): 
                    $repStmt = $pdo->prepare("SELECT COUNT(*) FROM forum_posts WHERE thread_id = ?");
                    $repStmt->execute([$t['id']]);
                    $replies = max(0, $repStmt->fetchColumn() - 1);
                ?>
                <div class="structItem">
                    <img src="<?= getAvatar($t) ?>" class="structItem-icon">
                    
                    <div class="structItem-main">
                        <?php if($t['is_locked']): ?><i class="fa-solid fa-lock" style="color:#da3633; font-size:12px; margin-right:5px;" title="Zárt téma"></i><?php endif; ?>
                        <?php if($t['is_sticky']): ?><i class="fa-solid fa-thumbtack" style="color:var(--primary-color); font-size:12px; margin-right:5px;" title="Kiemelt"></i><?php endif; ?>
                        <?php if(!empty($t['prefix'])): ?><span class="prefix-badge"><?= htmlspecialchars($t['prefix']) ?></span><?php endif; ?>
                        
                        <a href="thread.php?id=<?= $t['id'] ?>" class="structItem-title"><?= htmlspecialchars($t['title']) ?></a>
                        
                        <div class="structItem-meta">
                            Szerző: <a href="profile.php?id=<?= $t['author_id'] ?>" style="color:var(--text-light);"><?= htmlspecialchars($t['username'] ?? 'Ismeretlen') ?></a> &bull; 
                            <?= date("Y. M. d.", strtotime($t['created_at'])) ?>
                        </div>
                    </div>

                    <div class="structItem-stats">
                        Válaszok: <span style="color:var(--text-light); font-weight:bold;"><?= $replies ?></span><br>
                        Megtekintés: <span style="color:var(--text-light);"><?= $t['views'] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<footer class="p-footer">
    <div class="p-footer-inner">
        <div class="p-footer-row">
            <a href="#"><i class="fa-solid fa-paint-roller"></i> NorthSideRP Sötét Téma</a>
            <a href="#"><i class="fa-solid fa-globe"></i> Magyar (HU)</a>
        </div>
        <div class="p-footer-rowLinks">
            <a href="index.php">Főoldal</a>
            <a href="#">Kapcsolat</a>
            <a href="#">Szabályzatok</a>
            <a href="#">Súgó</a>
        </div>
    </div>
</footer>
<div class="p-footer-copyright">&copy; 2026 NorthSideRP Fórum. Minden jog fenntartva.</div>

</body>
</html>