<?php
// forum/edit_post.php - XENFORO DIZÁJN
require 'config.php';
require 'functions.php';

$loggedIn = isLoggedIn();
if (!$loggedIn) { header("Location: login.php"); exit; }

$postId = (int)($_GET['id'] ?? 0);
$myId = $_SESSION['user_id'];
$isAdmin = canModerate(); 

$me = null;
$stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
$stmtMe->execute([$myId]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);

// Poszt lekérése
$stmt = $pdo->prepare("SELECT p.*, t.title as thread_title FROM forum_posts p JOIN forum_threads t ON p.thread_id = t.id WHERE p.id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) die("A hozzászólás nem található.");

// JOGOSULTSÁG
if ($post['user_id'] != $myId && !$isAdmin) {
    die("Nincs jogod szerkeszteni ezt a hozzászólást!");
}

// MENTÉS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'];
    $sql = "UPDATE forum_posts SET content = ? WHERE id = ?";
    $pdo->prepare($sql)->execute([$content, $postId]);
    header("Location: thread.php?id=" . $post['thread_id'] . "#post-" . $postId);
    exit;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Szerkesztés - NorthSideRP</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header class="p-nav">
    <div class="p-nav-logo">NorthSide <span>Fórum</span></div>
    <div class="p-nav-opposite">
        <a href="search.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-magnifying-glass"></i></a>
        <a href="pm.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-envelope"></i></a>
        <a href="notifications.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-bell"></i></a>
        <a href="profile.php?id=<?= $myId ?>" class="p-navgroup-link"><img src="<?= getAvatar($me) ?>" class="avatar-menu"> <?= htmlspecialchars($me['username']) ?></a>
        <a href="logout.php" class="p-navgroup-link icon-only" style="border-right: 1px solid var(--border-color);"><i class="fa-solid fa-power-off"></i></a>
    </div>
</header>
<div class="p-nav-sub"><a href="index.php">Új bejegyzések</a> <a href="search.php">Keresés a fórumban</a></div>

<div class="p-body">
    <main class="p-body-main" style="max-width: 900px; margin: 0 auto; flex: 1;">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <a href="thread.php?id=<?= $post['thread_id'] ?>"><?= htmlspecialchars($post['thread_title']) ?></a>
            <i class="fa-solid fa-angle-right"></i>
            <span>Szerkesztés</span>
        </div>

        <div class="block-container">
            <h2 class="block-header">Hozzászólás Szerkesztése</h2>
            <div class="block-body" style="padding: 20px;">
                
                <div style="margin-bottom:10px; display:flex; gap:5px; background:var(--bg-block-alt); padding:8px; border:1px solid var(--border-color); border-radius:4px;">
                    <button type="button" onclick="insertTag('[b]','[/b]')" class="button" style="padding:5px 10px; background:#333;"><i class="fa-solid fa-bold"></i></button>
                    <button type="button" onclick="insertTag('[i]','[/i]')" class="button" style="padding:5px 10px; background:#333;"><i class="fa-solid fa-italic"></i></button>
                    <button type="button" onclick="insertTag('[code]','[/code]')" class="button" style="padding:5px 10px; background:#333;"><i class="fa-solid fa-code"></i></button>
                </div>

                <form method="POST">
                    <textarea name="content" id="editor" required style="width:100%; min-height:250px; background:#0a0a0a; color:var(--text-normal); border:1px solid var(--border-color); padding:15px; border-radius:4px; font-family:inherit; font-size:14px; margin-bottom: 15px; resize: vertical;"><?= htmlspecialchars($post['content']) ?></textarea>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <a href="thread.php?id=<?= $post['thread_id'] ?>" style="color:var(--text-muted);"><i class="fa-solid fa-arrow-left"></i> Mégse</a>
                        <button type="submit" class="button"><i class="fa-solid fa-floppy-disk"></i> Mentés</button>
                    </div>
                </form>

            </div>
        </div>
    </main>
</div>

<footer class="p-footer">
    <div class="p-footer-inner"><div>&copy; 2026 NorthSideRP Fórum.</div></div>
</footer>

<script>
function insertTag(open, close) {
    var ta = document.getElementById("editor");
    var start = ta.selectionStart;
    var end = ta.selectionEnd;
    var text = ta.value.substring(start, end);
    ta.value = ta.value.substring(0, start) + open + text + close + ta.value.substring(end);
    ta.focus();
    ta.selectionStart = start + open.length;
    ta.selectionEnd = ta.selectionStart + text.length;
}
</script>
</body>
</html>