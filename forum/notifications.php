<?php
// forum/notifications.php - XENFORO ÉRTESÍTÉSEK
require 'config.php'; 
require 'functions.php';

$loggedIn = isLoggedIn();
if (!$loggedIn) { header("Location: login.php"); exit; }

$myId = $_SESSION['user_id'];

$me = null;
$stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
$stmtMe->execute([$myId]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);

// Értesítések lekérése
$stmt = $pdo->prepare("
    SELECT n.*, a.username, a.forum_avatar 
    FROM forum_notifications n 
    LEFT JOIN accounts a ON n.sender_id = a.accountId 
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC LIMIT 50
");
$stmt->execute([$myId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ha megnyitotta az oldalt, minden értesítést olvasottá teszünk
$pdo->prepare("UPDATE forum_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$myId]);

// Segédfüggvény az értesítés szövegének generálásához
function getNotificationText($pdo, $notif) {
    $text = "";
    $link = "#";
    $icon = "fa-bell";
    
    if ($notif['type'] == 'like') {
        // Keressük meg a posztot és a témát
        $pStmt = $pdo->prepare("SELECT p.thread_id, t.title FROM forum_posts p JOIN forum_threads t ON p.thread_id = t.id WHERE p.id = ?");
        $pStmt->execute([$notif['reference_id']]);
        $postData = $pStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($postData) {
            $text = "kedvelte a hozzászólásodat ebben a témában: <strong>" . htmlspecialchars($postData['title']) . "</strong>";
            $link = "thread.php?id=" . $postData['thread_id'] . "#post-" . $notif['reference_id'];
            $icon = "fa-thumbs-up";
        } else {
            $text = "kedvelte egy (már törölt) hozzászólásodat.";
        }
    } 
    elseif ($notif['type'] == 'reply') {
        $tStmt = $pdo->prepare("SELECT title FROM forum_threads WHERE id = ?");
        $tStmt->execute([$notif['reference_id']]);
        $title = $tStmt->fetchColumn();
        
        if ($title) {
            $text = "válaszolt a témádra: <strong>" . htmlspecialchars($title) . "</strong>";
            $link = "thread.php?id=" . $notif['reference_id'];
            $icon = "fa-reply";
        }
    }
    elseif ($notif['type'] == 'warn') {
        $text = "<span style='color:#da3633;'>figyelmeztetést (Warn) osztott ki neked! Kérjük, olvasd el a szabályzatot.</span>";
        $icon = "fa-triangle-exclamation";
    }

    return ['text' => $text, 'link' => $link, 'icon' => $icon];
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Értesítések - NorthSideRP</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notif-row { display: flex; padding: 15px; border-bottom: 1px solid var(--border-color); align-items: center; transition: 0.2s; background: var(--bg-block); }
        .notif-row:hover { background: var(--bg-block-alt); }
        .notif-unread { background: rgba(43, 143, 251, 0.05); border-left: 3px solid var(--primary-color); }
    </style>
</head>
<body>

<header class="p-nav">
    <div class="p-nav-logo">NorthSide <span>Fórum</span></div>
    <div class="p-nav-opposite">
        <a href="search.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-magnifying-glass"></i></a>
        <a href="pm.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-envelope"></i></a>
        <a href="notifications.php" class="p-navgroup-link icon-only" style="color:var(--primary-color);"><i class="fa-solid fa-bell"></i></a>
        <a href="profile.php?id=<?= $myId ?>" class="p-navgroup-link"><img src="<?= getAvatar($me) ?>" class="avatar-menu"> <?= htmlspecialchars($me['username']) ?></a>
        <a href="logout.php" class="p-navgroup-link icon-only" style="border-right: 1px solid var(--border-color);"><i class="fa-solid fa-power-off"></i></a>
    </div>
</header>
<div class="p-nav-sub"><a href="index.php">Új bejegyzések</a> <a href="search.php">Keresés a fórumban</a></div>

<div class="p-body">
    
    <aside class="p-body-sidebar" style="width: 250px;">
        <div class="block-container">
            <h3 class="block-minorHeader">Fiókod</h3>
            <div class="block-body" style="padding:0;">
                <a href="profile.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-user" style="width:20px;"></i> Profilod</a>
                <a href="settings.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-gear" style="width:20px;"></i> Beállítások</a>
                <a href="notifications.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-light); font-weight:bold;"><i class="fa-solid fa-bell" style="width:20px;"></i> Értesítések</a>
            </div>
        </div>
    </aside>

    <main class="p-body-main">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <span>Értesítések</span>
        </div>

        <div class="block-container">
            <h2 class="block-header"><i class="fa-solid fa-bell"></i> Értesítések</h2>
            <div class="block-body" style="padding:0;">
                
                <?php if(empty($notifications)): ?>
                    <div style="padding: 20px; text-align: center; color: var(--text-muted);">Jelenleg nincsenek értesítéseid.</div>
                <?php else: ?>
                    <?php foreach($notifications as $n): 
                        $info = getNotificationText($pdo, $n);
                        $unreadClass = ($n['is_read'] == 0) ? 'notif-unread' : '';
                    ?>
                        <div class="notif-row <?= $unreadClass ?>">
                            <div style="position:relative; margin-right:15px;">
                                <img src="<?= getAvatar($n) ?>" style="width: 48px; height: 48px; border-radius: 4px; object-fit: cover;">
                                <div style="position:absolute; bottom:-5px; right:-5px; background:var(--bg-block); border-radius:50%; width:20px; height:20px; display:flex; justify-content:center; align-items:center; font-size:10px; color:var(--text-muted);">
                                    <i class="fa-solid <?= $info['icon'] ?>"></i>
                                </div>
                            </div>
                            
                            <div style="flex: 1; min-width: 0; font-size:14px;">
                                <a href="<?= $info['link'] ?>" style="display: block; color: var(--text-light); margin-bottom: 3px; line-height:1.4;">
                                    <strong style="color:var(--primary-color);"><?= htmlspecialchars($n['username'] ?? 'Rendszer') ?></strong> <?= $info['text'] ?>
                                </a>
                                <div style="font-size: 12px; color: var(--text-muted);">
                                    <?= date("Y. M. d. H:i", strtotime($n['created_at'])) ?>
                                </div>
                            </div>
                            
                            <?php if($n['is_read'] == 0): ?>
                                <div style="width: 10px; height: 10px; background: var(--primary-color); border-radius: 50%;" title="Új"></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
            </div>
        </div>

    </main>
</div>

<footer class="p-footer">
    <div class="p-footer-inner"><div>&copy; 2026 NorthSideRP Fórum. Minden jog fenntartva.</div></div>
</footer>

</body>
</html>