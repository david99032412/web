<?php
// forum/index.php - OPTIMALIZÁLT ÉS BIZTONSÁGOS VERZIÓ
require 'config.php'; 
require 'functions.php';

$loggedIn = isLoggedIn();
$username = $_SESSION['user_username'] ?? 'Vendég';
$myId = $_SESSION['user_id'] ?? 0;
$me = null;

if ($loggedIn) {
    $stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
    $stmtMe->execute([$myId]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);
}

// 1. Fő kategóriák lekérése
try {
    $stmt = $pdo->query("SELECT * FROM forum_categories WHERE parent_id = 0 ORDER BY display_order ASC");
    $mainCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $mainCategories = []; 
}

// 2. Statisztikák (Optimalizált, egyszeri futtatás)
$totalThreads = $pdo->query("SELECT COUNT(*) FROM forum_threads")->fetchColumn() ?: 0;
$totalPosts   = $pdo->query("SELECT COUNT(*) FROM forum_posts")->fetchColumn() ?: 0;
$totalUsers   = $pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn() ?: 0;
$latestUser   = $pdo->query("SELECT accountId, username FROM accounts ORDER BY accountId DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// 3. Online felhasználók
$timeLimit = time() - (15 * 60); 
$onlineStmt = $pdo->prepare("SELECT username, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE last_activity > ?");
$onlineStmt->execute([date('Y-m-d H:i:s', $timeLimit)]);
$onlineUsers = $onlineStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Legújabb bejegyzések
$latestPostsStmt = $pdo->query("
    SELECT p.*, t.title, a.username, a.forum_avatar 
    FROM forum_posts p 
    JOIN forum_threads t ON p.thread_id = t.id 
    JOIN accounts a ON p.user_id = a.accountId 
    ORDER BY p.created_at DESC LIMIT 5
");
$latestPosts = $latestPostsStmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Előkészített (Prepared) lekérdezések a cikluson KÍVÜL (Teljesítmény optimalizálás)
$subStmt = $pdo->prepare("SELECT * FROM forum_categories WHERE parent_id = ? ORDER BY display_order ASC");
$tCountStmt = $pdo->prepare("SELECT COUNT(*) FROM forum_threads WHERE category_id = ?");
$pCountStmt = $pdo->prepare("SELECT COUNT(p.id) FROM forum_posts p JOIN forum_threads t ON p.thread_id = t.id WHERE t.category_id = ?");
$lastPostStmt = $pdo->prepare("
    SELECT p.created_at, a.username, a.forum_avatar, t.title, t.id as thread_id
    FROM forum_posts p
    JOIN forum_threads t ON p.thread_id = t.id
    JOIN accounts a ON p.user_id = a.accountId
    WHERE t.category_id = ?
    ORDER BY p.created_at DESC LIMIT 1
");

// Olvasatlan értesítések lekérése, ha be van jelentkezve
$unreadPms = 0;
$unreadNotifs = 0;
if ($loggedIn) {
    $pmC = $pdo->prepare("SELECT COUNT(*) FROM forum_pms WHERE receiver_id = ? AND is_read = 0"); 
    $pmC->execute([$myId]); $unreadPms = $pmC->fetchColumn();
    
    $notC = $pdo->prepare("SELECT COUNT(*) FROM forum_notifications WHERE user_id = ? AND is_read = 0"); 
    $notC->execute([$myId]); $unreadNotifs = $notC->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>NorthSideRP - Fórum</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .shoutbox-msg { font-size: 13px; line-height: 1.5; word-wrap: break-word; }
        .shoutbox-time { color: var(--text-muted); font-size: 11px; margin-right: 5px; }
        .shoutbox-input-container { display: flex; gap: 10px; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border-color); }
        .shoutbox-input { flex: 1; padding: 8px 12px; background: #0a0a0a; border: 1px solid var(--border-color); color: var(--text-normal); border-radius: 4px; font-family: inherit; }
        .shoutbox-input:focus { border-color: var(--primary-color); outline: none; }
        .shoutbox-btn { padding: 8px 16px; background: var(--primary-color); color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .shoutbox-btn:hover { filter: brightness(1.1); }
    </style>
</head>
<body>

<header class="p-nav">
    <div class="p-nav-logo">NorthSide <span>Fórum</span></div>
    <div class="p-nav-opposite">
        <?php if($loggedIn): ?>
            <a href="search.php" class="p-navgroup-link icon-only" title="Keresés a fórumban"><i class="fa-solid fa-magnifying-glass"></i></a>
            
            <a href="pm.php" class="p-navgroup-link icon-only" title="Privát üzenetek">
                <i class="fa-solid fa-envelope"></i>
                <?php if($unreadPms > 0): ?><span class="badge-alert"><?= $unreadPms ?></span><?php endif; ?>
            </a>
            
            <a href="notifications.php" class="p-navgroup-link icon-only" title="Értesítések">
                <i class="fa-solid fa-bell"></i>
                <?php if($unreadNotifs > 0): ?><span class="badge-alert"><?= $unreadNotifs ?></span><?php endif; ?>
            </a>
            
            <a href="profile.php?id=<?= $myId ?>" class="p-navgroup-link">
                <img src="<?= getAvatar($me) ?>" class="avatar-menu">
                <?= htmlspecialchars($username) ?>
            </a>
            <a href="logout.php" class="p-navgroup-link icon-only" style="border-right: 1px solid var(--border-color);" title="Kijelentkezés"><i class="fa-solid fa-power-off"></i></a>
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
    <a href="online.php"><i class="fa-solid fa-wifi" style="color:#2ea043;"></i> Jelenleg Online</a>
</div>

<div class="p-body">
    <main class="p-body-main">
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <span>Fórum</span>
        </div>

        <div class="block-container">
            <h3 class="block-minorHeader"><i class="fa-solid fa-comments"></i> Élő Üzenőfal</h3>
            <div class="block-body" style="padding: 15px;">
                <div id="shoutbox-messages" style="height: 250px; overflow-y: auto; padding: 10px; background: #0f0f0f; border: 1px inset var(--border-color); border-radius: 4px; display: flex; flex-direction: column; gap: 8px;">
                    <div style="color:var(--text-muted); text-align:center; margin-top: 100px;">Üzenetek betöltése... <i class="fa-solid fa-spinner fa-spin"></i></div>
                </div>
                
                <?php if($loggedIn): ?>
                <form id="shoutbox-form" class="shoutbox-input-container">
                    <input type="text" id="shoutbox-input" class="shoutbox-input" placeholder="Írj egy üzenetet ide..." autocomplete="off" maxlength="250" required>
                    <button type="submit" class="shoutbox-btn"><i class="fa-solid fa-paper-plane"></i> Küldés</button>
                </form>
                <?php else: ?>
                <div style="text-align:center; padding-top:15px; margin-top:10px; border-top:1px solid var(--border-color); color:var(--text-muted); font-size:13px;">
                    A csevegéshez <a href="login.php" style="color:var(--primary-color); font-weight:bold;">be kell jelentkezned</a>!
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach($mainCategories as $cat): ?>
        <div class="block-container">
            <h2 class="block-header"><?= htmlspecialchars($cat['title']) ?></h2>
            
            <?php
            $subStmt->execute([$cat['id']]);
            $subs = $subStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($subs as $sub):
                $tCountStmt->execute([$sub['id']]);
                $threadsNum = $tCountStmt->fetchColumn();

                $pCountStmt->execute([$sub['id']]);
                $postsNum = $pCountStmt->fetchColumn();

                $lastPostStmt->execute([$sub['id']]);
                $lastPost = $lastPostStmt->fetch(PDO::FETCH_ASSOC);
            ?>
            
            <div class="node-body">
                <div class="node-icon">
                    <?php if(strpos(strtolower($sub['title']), 'jármű') !== false): ?>
                        <i class="fa-solid fa-car"></i>
                    <?php elseif(strpos(strtolower($sub['title']), 'ingatlan') !== false): ?>
                        <i class="fa-solid fa-house"></i>
                    <?php elseif(strpos(strtolower($sub['title']), 'szabályzat') !== false): ?>
                        <i class="fa-solid fa-scale-balanced"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-comments"></i>
                    <?php endif; ?>
                </div>
                
                <div class="node-main">
                    <a href="category.php?id=<?= $sub['id'] ?>" class="node-title"><?= htmlspecialchars($sub['title']) ?></a>
                    <div class="node-description"><?= htmlspecialchars($sub['description']) ?></div>
                </div>

                <div class="node-stats">
                    <dl><dt>Témák:</dt> <dd><?= number_format($threadsNum, 0, '', ' ') ?></dd></dl>
                    <dl><dt>Üzenetek:</dt> <dd><?= number_format($postsNum, 0, '', ' ') ?></dd></dl>
                </div>

                <div class="node-extra" style="<?= !$lastPost ? 'justify-content: center; align-items: center;' : '' ?>">
                    <?php if($lastPost): ?>
                        <img src="<?= getAvatar($lastPost) ?>" class="node-extra-icon" alt="Avatar">
                        <div class="node-extra-row">
                            <div style="font-size:11px; color:var(--text-muted); margin-bottom:2px;">Legutóbbi:</div>
                            <a href="thread.php?id=<?= $lastPost['thread_id'] ?>" class="node-extra-title" title="<?= htmlspecialchars($lastPost['title']) ?>"><?= htmlspecialchars($lastPost['title']) ?></a>
                            <div class="node-extra-date">
                                <?= date("Y.m.d. H:i", strtotime($lastPost['created_at'])) ?>
                                <span class="node-extra-user" style="margin-left: 5px;">&bull; <?= htmlspecialchars($lastPost['username']) ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="color:#555; font-style:italic; font-size: 13px; text-align: center; width: 100%;">
                            - Még nincs üzenet -
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </main>

    <aside class="p-body-sidebar">
        <div class="block-container">
            <h3 class="block-minorHeader" style="background: linear-gradient(0deg, #121212 0%, #5865F2 200%); border-bottom-color: #5865F2;"><i class="fa-brands fa-discord"></i> Discord Szerverünk</h3>
            <div class="block-body" style="padding: 0; overflow: hidden; border-radius: 0 0 4px 4px;">
                <iframe src="https://discord.com/widget?id=1406333258312061168&theme=dark" width="100%" height="300" allowtransparency="true" frameborder="0" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts" style="display:block;"></iframe>
            </div>
        </div>

        <div class="block-container">
            <h3 class="block-minorHeader">Legújabb bejegyzések</h3>
            <div class="block-body" style="padding: 10px;">
                <?php if(empty($latestPosts)): ?>
                    <div style="color:var(--text-muted); font-size:12px; text-align:center;">Még nincs aktivitás.</div>
                <?php else: ?>
                    <?php foreach($latestPosts as $lp): ?>
                    <div style="display:flex; gap:10px; margin-bottom:12px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                        <img src="<?= getAvatar($lp) ?>" style="width:32px; height:32px; border-radius:2px; object-fit:cover; border:1px solid var(--border-color);">
                        <div style="flex:1; min-width:0; font-size:12px;">
                            <a href="thread.php?id=<?= $lp['thread_id'] ?>#post-<?= $lp['id'] ?>" style="color:var(--text-light); font-weight:600; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($lp['title']) ?>">
                                <?= htmlspecialchars($lp['title']) ?>
                            </a>
                            <div style="color:var(--text-muted); margin-top:2px;">
                                <span style="color:var(--text-main);"><?= htmlspecialchars($lp['username']) ?></span> &bull; <?= time_elapsed_string($lp['created_at']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="block-container">
            <h3 class="block-minorHeader">Online Játékosok (<?= count($onlineUsers) ?>)</h3>
            <div class="block-body" style="line-height: 1.8;">
                <?php if(empty($onlineUsers)): ?>
                    <span style="color:var(--text-muted);">Jelenleg senki sincs online.</span>
                <?php else: ?>
                    <?php 
                    $onlineList = [];
                    foreach($onlineUsers as $ou) {
                        $rank = getUserRank($ou['adminLevel'] ?? 0);
                        // Feltételezve, hogy a getUserRank ad vissza egy színt, ha nem, állíts be egy alapértelmezettet
                        $color = $rank['color'] ?? '#ffffff';
                        $onlineList[] = "<strong style='color:{$color}'>" . htmlspecialchars($ou['username']) . "</strong>";
                    }
                    echo implode(", ", $onlineList);
                    ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="block-container">
            <h3 class="block-minorHeader">Fórum Statisztika</h3>
            <div class="block-body">
                <dl class="pairs"><dt>Témák:</dt> <dd><?= number_format($totalThreads, 0, '', ' ') ?></dd></dl>
                <dl class="pairs"><dt>Üzenetek:</dt> <dd><?= number_format($totalPosts, 0, '', ' ') ?></dd></dl>
                <dl class="pairs"><dt>Tagok:</dt> <dd><?= number_format($totalUsers, 0, '', ' ') ?></dd></dl>
                <?php if($latestUser): ?>
                    <dl class="pairs"><dt>Legújabb tag:</dt> <dd><a href="profile.php?id=<?= $latestUser['accountId'] ?>" style="color:var(--primary-color); font-weight:bold;"><?= htmlspecialchars($latestUser['username']) ?></a></dd></dl>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if($loggedIn && isset($me['adminLevel']) && $me['adminLevel'] > 0): ?>
        <div class="block-container">
            <h3 class="block-minorHeader">Moderáció</h3>
            <div class="block-body" style="padding:10px;">
                <a href="admin.php" class="button" style="width:100%; background: #c0392b; box-shadow: 0 0 10px rgba(192, 57, 43, 0.4); text-align: center;"><i class="fa-solid fa-shield-halved"></i> Admin Panel</a>
            </div>
        </div>
        <?php endif; ?>
    </aside>
</div>

<footer class="p-footer">
    <div class="p-footer-inner">
        <div class="p-footer-rowLinks">
            <a href="index.php">Főoldal</a>
            <a href="rules.php">Szabályzatok</a>
        </div>
    </div>
</footer>
<div class="p-footer-copyright">&copy; 2026 NorthSideRP Fórum. Minden jog fenntartva.</div>

<script>
// Biztonságos HTML kódolás (XSS védelem)
function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}

document.addEventListener("DOMContentLoaded", function() {
    const sbContainer = document.getElementById('shoutbox-messages');
    const sbForm = document.getElementById('shoutbox-form');
    const sbInput = document.getElementById('shoutbox-input');

    function loadShoutbox() {
        fetch('shoutbox_api.php?action=get')
            .then(response => response.json())
            .then(data => {
                sbContainer.innerHTML = '';
                
                data.reverse().forEach(msg => {
                    let nameColor = msg.adminLevel > 0 ? 'var(--primary-color)' : 'var(--text-light)';
                    let timeStr = msg.created_at.substring(11, 16);
                    
                    let avatarUrl = msg.forum_avatar ? escapeHTML(msg.forum_avatar) : `https://ui-avatars.com/api/?name=${escapeHTML(msg.username)}&background=2b2b2b&color=fff`;
                    
                    sbContainer.innerHTML += `
                        <div class="shoutbox-msg" style="display:flex; align-items:flex-start; gap:10px; padding: 4px 0;">
                            <img src="${avatarUrl}" style="width:28px; height:28px; border-radius:4px; object-fit:cover; border: 1px solid var(--border-color);">
                            <div style="flex:1;">
                                <span class="shoutbox-time">[${timeStr}]</span>
                                <a href="profile.php?id=${msg.user_id}" style="color: ${nameColor}; font-weight:bold; text-decoration:none;">${escapeHTML(msg.username)}:</a> 
                                <span style="color: var(--text-normal);">${escapeHTML(msg.message)}</span>
                            </div>
                        </div>
                    `;
                });
                
                sbContainer.scrollTop = sbContainer.scrollHeight;
            })
            .catch(err => console.error("Shoutbox hiba:", err));
    }

    if (sbForm) {
        sbForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const text = sbInput.value.trim();
            if (!text) return;
            
            const formData = new FormData();
            formData.append('message', text);

            fetch('shoutbox_api.php?action=send', {
                method: 'POST',
                body: formData
            }).then(() => {
                sbInput.value = ''; 
                loadShoutbox(); 
            });
        });
    }

    loadShoutbox();
    setInterval(loadShoutbox, 5000);
});
</script>

</body>
</html>