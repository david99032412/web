<?php
// forum/pm.php - XENFORO PRIVÁT ÜZENETEK RENDSZERE
require 'config.php'; 
require 'functions.php';

$loggedIn = isLoggedIn();
if (!$loggedIn) { header("Location: login.php"); exit; }

$myId = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'inbox';
$msg = "";

$me = null;
$stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
$stmtMe->execute([$myId]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);

// --- ÚJ ÜZENET KÜLDÉSE (Feldolgozás) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action == 'new') {
    $toUsername = clean($_POST['to_user']);
    $title = clean($_POST['title']);
    $message = $_POST['message'];

    // Megkeressük a címzettet
    $stmt = $pdo->prepare("SELECT accountId FROM accounts WHERE username = ?");
    $stmt->execute([$toUsername]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receiver) {
        $msg = "<div style='background: rgba(218, 54, 51, 0.1); border-left: 3px solid #da3633; color: #ff7b72; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-triangle-exclamation'></i> Nincs ilyen nevű felhasználó!</div>";
    } elseif (empty($title) || empty($message)) {
        $msg = "<div style='background: rgba(218, 54, 51, 0.1); border-left: 3px solid #da3633; color: #ff7b72; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-triangle-exclamation'></i> Minden mezőt ki kell tölteni!</div>";
    } else {
        $stmtIns = $pdo->prepare("INSERT INTO forum_pms (sender_id, receiver_id, title, message) VALUES (?, ?, ?, ?)");
        if ($stmtIns->execute([$myId, $receiver['accountId'], $title, $message])) {
            header("Location: pm.php?action=sent&success=1");
            exit;
        }
    }
}

// Sikeres küldés üzenet
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $msg = "<div style='background: rgba(46, 160, 67, 0.1); border-left: 3px solid #2ea043; color: #3fb950; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-check'></i> Üzenet sikeresen elküldve!</div>";
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Privát Üzenetek - NorthSideRP</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .pm-input { width: 100%; padding: 12px; background: #0a0a0a; border: 1px solid var(--border-color); color: var(--text-normal); border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 14px; margin-bottom:15px; }
        .pm-input:focus { border-color: var(--primary-color); outline: none; }
        .pm-label { display: block; font-size: 13px; color: var(--text-muted); font-weight: bold; margin-bottom: 5px; }
        .pm-unread { font-weight: bold !important; color: #fff !important; }
        .pm-row { display: flex; padding: 15px; border-bottom: 1px solid var(--border-color); align-items: center; transition: 0.2s; }
        .pm-row:hover { background: var(--bg-block-alt); }
    </style>
</head>
<body>

<header class="p-nav">
    <div class="p-nav-logo">NorthSide <span>Fórum</span></div>
    <div class="p-nav-opposite">
        <a href="search.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-magnifying-glass"></i></a>
        <a href="pm.php" class="p-navgroup-link icon-only" style="color:var(--primary-color);"><i class="fa-solid fa-envelope"></i></a>
        <a href="notifications.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-bell"></i></a>
        <a href="profile.php?id=<?= $myId ?>" class="p-navgroup-link"><img src="<?= getAvatar($me) ?>" class="avatar-menu"> <?= htmlspecialchars($me['username']) ?></a>
        <a href="logout.php" class="p-navgroup-link icon-only" style="border-right: 1px solid var(--border-color);"><i class="fa-solid fa-power-off"></i></a>
    </div>
</header>
<div class="p-nav-sub"><a href="index.php">Új bejegyzések</a> <a href="search.php">Keresés a fórumban</a></div>

<div class="p-body">
    
    <aside class="p-body-sidebar" style="width: 250px;">
        <div class="block-container">
            <h3 class="block-minorHeader">Üzenetek</h3>
            <div class="block-body" style="padding:0;">
                <a href="pm.php?action=inbox" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color: <?= $action=='inbox'?'var(--text-light); font-weight:bold;':'var(--text-muted);' ?>"><i class="fa-solid fa-inbox" style="width:20px;"></i> Beérkező levelek</a>
                <a href="pm.php?action=sent" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color: <?= $action=='sent'?'var(--text-light); font-weight:bold;':'var(--text-muted);' ?>"><i class="fa-solid fa-paper-plane" style="width:20px;"></i> Elküldött levelek</a>
                <a href="pm.php?action=new" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color: <?= $action=='new'?'var(--text-light); font-weight:bold;':'var(--text-muted);' ?>"><i class="fa-solid fa-pen" style="width:20px;"></i> Új üzenet írása</a>
            </div>
        </div>
    </aside>

    <main class="p-body-main">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <span>Privát Üzenetek</span>
        </div>

        <?= $msg ?>

        <div class="block-container">
            
            <?php if ($action == 'inbox'): 
                $stmt = $pdo->prepare("SELECT p.*, a.username, a.forum_avatar FROM forum_pms p JOIN accounts a ON p.sender_id = a.accountId WHERE p.receiver_id = ? ORDER BY p.created_at DESC");
                $stmt->execute([$myId]);
                $pms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
                <div class="block-header"><i class="fa-solid fa-inbox"></i> Beérkező levelek</div>
                <div class="block-body" style="padding:0;">
                    <?php if(empty($pms)): ?>
                        <div style="padding: 20px; text-align: center; color: var(--text-muted);">A postaládád üres.</div>
                    <?php else: ?>
                        <?php foreach($pms as $pm): ?>
                            <div class="pm-row">
                                <img src="<?= getAvatar($pm) ?>" style="width: 40px; height: 40px; border-radius: 4px; object-fit: cover; margin-right: 15px;">
                                <div style="flex: 1; min-width: 0;">
                                    <a href="pm.php?action=read&id=<?= $pm['id'] ?>" class="<?= $pm['is_read'] == 0 ? 'pm-unread' : '' ?>" style="font-size: 15px; display: block; color: var(--text-light); margin-bottom: 3px;">
                                        <?= htmlspecialchars($pm['title']) ?>
                                    </a>
                                    <div style="font-size: 12px; color: var(--text-muted);">
                                        Feladó: <span style="color:var(--text-light);"><?= htmlspecialchars($pm['username']) ?></span> &bull; <?= date("Y. M. d. H:i", strtotime($pm['created_at'])) ?>
                                    </div>
                                </div>
                                <?php if($pm['is_read'] == 0): ?>
                                    <div style="width: 10px; height: 10px; background: var(--primary-color); border-radius: 50%;" title="Olvasatlan"></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            <?php elseif ($action == 'sent'): 
                $stmt = $pdo->prepare("SELECT p.*, a.username, a.forum_avatar FROM forum_pms p JOIN accounts a ON p.receiver_id = a.accountId WHERE p.sender_id = ? ORDER BY p.created_at DESC");
                $stmt->execute([$myId]);
                $pms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
                <div class="block-header"><i class="fa-solid fa-paper-plane"></i> Elküldött levelek</div>
                <div class="block-body" style="padding:0;">
                    <?php if(empty($pms)): ?>
                        <div style="padding: 20px; text-align: center; color: var(--text-muted);">Még nem küldtél levelet senkinek.</div>
                    <?php else: ?>
                        <?php foreach($pms as $pm): ?>
                            <div class="pm-row">
                                <img src="<?= getAvatar($pm) ?>" style="width: 40px; height: 40px; border-radius: 4px; object-fit: cover; margin-right: 15px;">
                                <div style="flex: 1; min-width: 0;">
                                    <a href="pm.php?action=read&id=<?= $pm['id'] ?>" style="font-size: 15px; display: block; color: var(--text-light); margin-bottom: 3px;">
                                        <?= htmlspecialchars($pm['title']) ?>
                                    </a>
                                    <div style="font-size: 12px; color: var(--text-muted);">
                                        Címzett: <span style="color:var(--text-light);"><?= htmlspecialchars($pm['username']) ?></span> &bull; <?= date("Y. M. d. H:i", strtotime($pm['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            <?php elseif ($action == 'new'): 
                $prefillTo = htmlspecialchars($_GET['to'] ?? '');
                $prefillTitle = htmlspecialchars($_GET['title'] ?? '');
            ?>
                <div class="block-header"><i class="fa-solid fa-pen"></i> Új privát üzenet írása</div>
                <div class="block-body" style="padding: 25px;">
                    <form method="POST" action="pm.php?action=new">
                        <label class="pm-label">Címzett (Játékosnév)</label>
                        <input type="text" name="to_user" value="<?= $prefillTo ?>" required class="pm-input" placeholder="Kinek küldöd?">

                        <label class="pm-label">Üzenet Tárgya</label>
                        <input type="text" name="title" value="<?= $prefillTitle ?>" required class="pm-input" placeholder="Miről szól az üzenet?">

                        <label class="pm-label">Üzenet (BBCode engedélyezett)</label>
                        <textarea name="message" required class="pm-input" style="min-height: 200px; resize: vertical;" placeholder="Írd ide a leveled tartalmát..."></textarea>

                        <div style="text-align: right;">
                            <button type="submit" class="button"><i class="fa-solid fa-paper-plane"></i> Üzenet elküldése</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($action == 'read'): 
                $pmId = (int)$_GET['id'];
                $stmt = $pdo->prepare("SELECT p.*, a.username, a.accountId, a.forum_avatar, a.forum_title, COALESCE(a.adminLevel, a.AdminLevel, 0) as adminLevel FROM forum_pms p JOIN accounts a ON p.sender_id = a.accountId WHERE p.id = ? AND (p.receiver_id = ? OR p.sender_id = ?)");
                $stmt->execute([$pmId, $myId, $myId]);
                $pm = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$pm) {
                    echo "<div style='padding:20px;'>A levél nem található, vagy nincs jogosultságod olvasni!</div>";
                } else {
                    // Ha a címzett én vagyok, jelöljük olvasottnak
                    if ($pm['receiver_id'] == $myId && $pm['is_read'] == 0) {
                        $pdo->prepare("UPDATE forum_pms SET is_read = 1 WHERE id = ?")->execute([$pmId]);
                    }
                    $rank = getUserRank($pm['adminLevel']);
            ?>
                <div class="block-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span><?= htmlspecialchars($pm['title']) ?></span>
                    <a href="pm.php" style="color:#fff; font-size:12px; font-weight:normal;"><i class="fa-solid fa-arrow-left"></i> Vissza a levelekhez</a>
                </div>
                
                <div class="block-body" style="padding: 15px;">
                    <div class="message" style="margin-bottom:0;">
                        <div class="message-user">
                            <div class="message-avatar-wrapper">
                                <img src="<?= getAvatar($pm) ?>" class="message-avatar">
                            </div>
                            <a href="profile.php?id=<?= $pm['accountId'] ?>" class="message-username" style="color: <?= $rank['color'] ?>;"><?= htmlspecialchars($pm['username']) ?></a>
                            <div class="message-userTitle" style="background: <?= $rank['color'] ?>;"><?= $rank['name'] ?></div>
                        </div>

                        <div class="message-content">
                            <div class="message-header">
                                <span><?= date("Y. M. d. H:i", strtotime($pm['created_at'])) ?></span>
                            </div>
                            <div class="message-body">
                                <?= parseBBCode($pm['message']) ?>
                            </div>
                            <div class="message-footer">
                                <div class="actionBar-left"></div>
                                <div class="actionBar-right">
                                    <?php if ($pm['sender_id'] != $myId): ?>
                                        <a href="pm.php?action=new&to=<?= htmlspecialchars($pm['username']) ?>&title=Re: <?= htmlspecialchars($pm['title']) ?>"><i class="fa-solid fa-reply"></i> Válasz írása</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } endif; ?>

        </div>
    </main>
</div>

<footer class="p-footer">
    <div class="p-footer-inner"><div>&copy; 2026 NorthSideRP Fórum. Minden jog fenntartva.</div></div>
</footer>

</body>
</html>
