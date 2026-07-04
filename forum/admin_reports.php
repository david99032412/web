<?php
// forum/admin_reports.php - XENFORO ADMIN PANEL
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

$msg = "";

// Jelentés törlése (lezárása)
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM forum_reports WHERE id = ?")->execute([$delId]);
    $msg = "<div style='background: rgba(46, 160, 67, 0.1); border-left: 3px solid #2ea043; color: #3fb950; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-check'></i> Jelentés lezárva / törölve!</div>";
}

// Jelentések lekérése
$sql = "SELECT r.*, u.username as reporter_name, u.forum_avatar, p.content, p.thread_id, a.username as target_name 
        FROM forum_reports r 
        JOIN accounts u ON r.user_id = u.accountId 
        LEFT JOIN forum_posts p ON r.post_id = p.id 
        LEFT JOIN accounts a ON p.user_id = a.accountId
        ORDER BY r.created_at DESC";
$reports = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Jelentések - Admin Panel</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <a href="admin_reports.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-light); font-weight:bold;"><i class="fa-solid fa-triangle-exclamation" style="width:20px;"></i> Jelentések (<?= count($reports) ?>)</a>
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
            <h2 class="block-header" style="background: linear-gradient(0deg, #7c241c 0%, #c0392b 100%); border-bottom-color:#da3633;"><i class="fa-solid fa-triangle-exclamation"></i> Beérkezett Jelentések</h2>
            <div class="block-body" style="padding: 0;">
                
                <?php if(empty($reports)): ?>
                    <div style="padding: 25px; text-align: center; color: var(--text-muted); font-size: 15px;"><i class="fa-solid fa-check-circle" style="color:#2ea043; font-size:24px; display:block; margin-bottom:10px;"></i> Nincs aktív jelentés. Minden rendben!</div>
                <?php else: ?>
                    <?php foreach($reports as $r): ?>
                        <div style="padding: 15px; border-bottom: 1px solid var(--border-color); display: flex; gap: 15px;">
                            <img src="<?= getAvatar($r) ?>" style="width: 48px; height: 48px; border-radius: 4px; object-fit: cover;">
                            
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <div style="font-size: 14px; color: var(--text-muted);">
                                        Jelentő: <strong style="color: var(--text-light);"><?= htmlspecialchars($r['reporter_name']) ?></strong> &bull; <?= time_elapsed_string($r['created_at']) ?>
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="thread.php?id=<?= $r['thread_id'] ?>#post-<?= $r['post_id'] ?>" class="button" style="padding: 4px 10px; font-size: 11px; background: #333;" target="_blank"><i class="fa-solid fa-eye"></i> Megtekintés</a>
                                        <a href="admin_reports.php?delete=<?= $r['id'] ?>" class="button" style="padding: 4px 10px; font-size: 11px; background: #2ea043;" onclick="return confirm('Késznek jelölöd és lezárod ezt a jelentést?');"><i class="fa-solid fa-check"></i> Megoldva</a>
                                    </div>
                                </div>
                                
                                <div style="background: rgba(218, 54, 51, 0.05); border-left: 3px solid #da3633; padding: 10px; border-radius: 3px; font-size: 13px; color: var(--text-light); margin-bottom: 10px;">
                                    <strong>Indoklás:</strong> <?= nl2br(htmlspecialchars($r['reason'])) ?>
                                </div>
                                
                                <div style="font-size: 12px; color: var(--text-muted); background: var(--bg-block-alt); padding: 10px; border: 1px solid var(--border-color); border-radius: 3px;">
                                    Jelentett játékos: <span style="color:#da3633; font-weight:bold;"><?= htmlspecialchars($r['target_name'] ?? 'Ismeretlen') ?></span><br>
                                    <i>"<?= htmlspecialchars(mb_substr(strip_tags(parseBBCode($r['content'] ?? '')), 0, 150)) ?>..."</i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>

    </main>
</div>

</body>
</html>
