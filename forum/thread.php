<?php
// forum/thread.php - XENFORO 1:1 REPLIKA (JAVÍTOTT)
require 'config.php'; require 'functions.php';

$threadId = (int)($_GET['id'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$perPage = 15; 

$loggedIn = isLoggedIn();
$myId = $_SESSION['user_id'] ?? 0;

$me = null;
$isMod = false; // Alapból senki sem admin
if ($loggedIn) {
    $stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
    $stmtMe->execute([$myId]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);
    
    // 100% PONTOS ADMIN ELLENŐRZÉS AZ ADATBÁZISBÓL (Mint a főoldalon!)
    if ($me && $me['adminLevel'] > 0) {
        $isMod = true;
    }
}

// Téma adatok lekérése (Author avatar is benne van)
$stmt = $pdo->prepare("SELECT t.*, c.title as cat_title, c.id as cat_id, a.username as author_name, a.forum_avatar as author_avatar FROM forum_threads t LEFT JOIN forum_categories c ON t.category_id = c.id LEFT JOIN accounts a ON t.author_id = a.accountId WHERE t.id = ?");
$stmt->execute([$threadId]);
$thread = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$thread) die("Téma nem található!");
if ($page == 1) $pdo->prepare("UPDATE forum_threads SET views = views + 1 WHERE id = ?")->execute([$threadId]);

// Poszt beküldése
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loggedIn && !$thread['is_locked']) {
    $msg = $_POST['message'];
    if (!empty($msg)) {
        $pdo->prepare("INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)")->execute([$threadId, $myId, $msg]);
        $pdo->prepare("UPDATE accounts SET forum_posts = forum_posts + 1 WHERE accountId = ?")->execute([$myId]);
        header("Location: thread.php?id=$threadId&page=9999"); exit;
    }
}

// Posztok lekérése
$offset = ($page - 1) * $perPage;
$sql = "SELECT p.*, a.username, a.accountId, a.forum_avatar, a.forum_posts, a.last_activity, a.forum_signature, COALESCE(a.adminLevel, a.AdminLevel, 0) as adminLevel,
        (SELECT COUNT(*) FROM forum_likes l JOIN forum_posts p2 ON l.post_id = p2.id WHERE p2.user_id = a.accountId) as reaction_score
        FROM forum_posts p 
        JOIN accounts a ON p.user_id = a.accountId 
        WHERE p.thread_id = ? ORDER BY p.created_at ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$threadId, $perPage, $offset]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($thread['title']) ?> - NorthSideRP</title>
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
            <a href="logout.php" class="p-navgroup-link icon-only" style="border-right: 1px solid var(--border-color);" title="Kijelentkezés"><i class="fa-solid fa-power-off"></i></a>
        <?php else: ?>
            <a href="login.php" class="p-navgroup-link" style="border:none;">Belépés</a>
            <a href="register.php" class="button">Regisztráció</a>
        <?php endif; ?>
    </div>
</header>

<div class="p-nav-sub">
    <a href="index.php">Új bejegyzések</a>
    <a href="search.php">Keresés a fórumban</a>
</div>

<div class="p-body">
    <main class="p-body-main" style="flex: 1; max-width: 100%;">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <a href="category.php?id=<?= $thread['cat_id'] ?>"><?= htmlspecialchars($thread['cat_title']) ?></a>
            <i class="fa-solid fa-angle-right"></i>
            <span><?= htmlspecialchars($thread['title']) ?></span>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 5px;">
            <h1 style="margin:0; font-size:24px; color:var(--text-light); font-weight:600; letter-spacing: -0.5px;">
                <?php if($thread['is_locked']): ?><i class="fa-solid fa-lock" style="color:#da3633; font-size:18px;"></i><?php endif; ?>
                <?php if(!empty($thread['prefix'])): ?><span class="prefix-badge"><?= htmlspecialchars($thread['prefix']) ?></span><?php endif; ?>
                <?= htmlspecialchars($thread['title']) ?>
            </h1>
            <?php if($isMod): ?>
                <div>
                    <a href="moderate.php?action=lock&id=<?= $thread['id'] ?>" class="button" style="background:<?= $thread['is_locked'] ? '#2ea043' : '#da3633' ?>;"><?= $thread['is_locked'] ? 'Nyitás' : 'Zárás' ?></a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="p-description">
            <img src="<?= htmlspecialchars($thread['author_avatar'] ?? 'https://ui-avatars.com/api/?name=User&background=2b2b2b&color=fff') ?>" alt="Avatar">
            <span><i class="fa-solid fa-user"></i> <a href="#"><?= htmlspecialchars($thread['author_name'] ?? 'Ismeretlen') ?></a></span>
            <span>&bull;</span>
            <span><i class="fa-solid fa-clock"></i> <?= date("Y. M. d.", strtotime($thread['created_at'])) ?></span>
        </div>

        <?php foreach($posts as $index => $post): 
            $rank = getUserRank($post['adminLevel']);
            // Online ellenőrzés (15 perc)
            $isOnline = (strtotime($post['last_activity'] ?? '2000-01-01') > (time() - 15 * 60));
        ?>
        <div class="message" id="post-<?= $post['id'] ?>">
            
            <div class="message-user">
                <div class="message-avatar-wrapper">
                    <img src="<?= getAvatar($post) ?>" class="message-avatar">
                    <?php if($isOnline): ?><div class="message-online-dot" title="Online"></div><?php endif; ?>
                </div>
                
                <a href="profile.php?id=<?= $post['accountId'] ?>" class="message-username" style="color: <?= $rank['color'] ?>;">
                    <?= htmlspecialchars($post['username']) ?>
                </a>
                <div class="message-userTitle" style="background: <?= $rank['color'] ?>;"><?= $rank['name'] ?></div>
                
                <div class="message-userExtras">
                    <dl><dt>Csatlakozott:</dt> <dd>N/A</dd></dl>
                    <dl><dt>Üzenetek:</dt> <dd><?= number_format($post['forum_posts'], 0, '', ' ') ?></dd></dl>
                    <dl><dt>Reakció pontszám:</dt> <dd><?= number_format($post['reaction_score'], 0, '', ' ') ?></dd></dl>
                </div>
            </div>

            <div class="message-content">
                <div class="message-header">
                    <span><?= date("Y. M. d. H:i", strtotime($post['created_at'])) ?></span>
                    <a href="#post-<?= $post['id'] ?>">#<?= $offset + $index + 1 ?></a>
                </div>
                
                <div class="message-body">
                    <?= parseBBCode($post['content']) ?>
                    
                    <?php if(!empty($post['forum_signature'])): ?>
                        <div class="message-signature">
                            <?= parseBBCode($post['forum_signature']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="message-footer">
                    <div class="actionBar-left">
                        <?php if($loggedIn && $myId != $post['accountId']): ?>
                            <a href="report.php?post=<?= $post['id'] ?>&thread=<?= $threadId ?>">Jelentés</a>
                        <?php endif; ?>
                    </div>
                    <div class="actionBar-right">
                        <?php if($loggedIn): ?>
                            <a href="like.php?post=<?= $post['id'] ?>&thread=<?= $threadId ?>"><i class="fa-solid fa-thumbs-up"></i> Kedvelés</a>
                            <a href="#" onclick="insertQuote('<?= htmlspecialchars($post['username']) ?>', '<?= htmlspecialchars(strip_tags($post['content'])) ?>'); return false;"><i class="fa-solid fa-quote-left"></i> Idézet</a>
                            <?php if(!$thread['is_locked']): ?>
                                <a href="#replyBox"><i class="fa-solid fa-reply"></i> Válasz</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if($myId == $post['accountId'] || $isMod): ?>
                            <a href="edit_post.php?id=<?= $post['id'] ?>"><i class="fa-solid fa-pen"></i> Szerkesztés</a>
                            <a href="delete-post.php?id=<?= $post['id'] ?>" style="color:#da3633;"><i class="fa-solid fa-trash"></i> Törlés</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if($loggedIn && !$thread['is_locked']): ?>
            <div class="quickReply" id="replyBox">
                <img src="<?= getAvatar($me) ?>" class="quickReply-avatar">
                <div class="quickReply-editor">
                    <form method="POST">
                        <textarea name="message" id="editor" required placeholder="Válasz írása..."></textarea>
                        <div style="text-align: right;">
                            <button type="submit" class="button"><i class="fa-solid fa-paper-plane"></i> Válasz elküldése</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif($thread['is_locked']): ?>
            <div class="block-container" style="padding:20px; text-align:center; color:#e74c3c; margin-top:20px; background: rgba(231, 76, 60, 0.05); border-color: #e74c3c;">
                <i class="fa-solid fa-lock" style="font-size:24px; margin-bottom:10px;"></i>
                <h3 style="margin:0;">Ez a téma le van zárva.</h3>
                <p style="margin:5px 0 0 0; font-size:13px;">További hozzászólások nem küldhetők.</p>
            </div>
        <?php endif; ?>

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
            <a href="#"><i class="fa-solid fa-rss"></i></a>
        </div>
    </div>
</footer>
<div class="p-footer-copyright">
    Forum software by XenForo&reg; &copy; 2010-2020 XenForo Ltd. (Másolat)<br>
    &copy; 2026 NorthSideRP Fórum. Minden jog fenntartva.
</div>

<script>
function insertQuote(user, text) {
    var ta = document.getElementById("editor");
    if(ta) {
        ta.value += '[quote="' + user + '"]' + text + '[/quote]\\n';
        ta.focus();
        ta.selectionStart = ta.value.length;
    }
}
</script>

</body>
</html>