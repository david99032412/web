<?php
// forum/post_thread.php - XENFORO DIZÁJN
require 'config.php'; require 'functions.php';

$loggedIn = isLoggedIn();
if (!$loggedIn) { header("Location: login.php"); exit; }

$catId = (int)($_GET['cat'] ?? 0);
$myId = $_SESSION['user_id'];

$me = null;
$stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
$stmtMe->execute([$myId]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM forum_categories WHERE id = ?");
$stmt->execute([$catId]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) die("Érvénytelen kategória!");

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = clean($_POST['title']);
    $content = $_POST['content'];
    $prefix = clean($_POST['prefix'] ?? '');
    
    if (empty($title) || empty($content)) {
        $error = "A cím és a tartalom megadása kötelező!";
    } else {
        // Téma létrehozása
        $stmtT = $pdo->prepare("INSERT INTO forum_threads (category_id, author_id, title, prefix) VALUES (?, ?, ?, ?)");
        if ($stmtT->execute([$catId, $myId, $title, $prefix])) {
            $threadId = $pdo->lastInsertId();
            
            // Kezdő poszt létrehozása
            $stmtP = $pdo->prepare("INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)");
            $stmtP->execute([$threadId, $myId, $content]);
            
            // Üzenet számláló növelése
            $pdo->prepare("UPDATE accounts SET forum_posts = forum_posts + 1 WHERE accountId = ?")->execute([$myId]);
            
            header("Location: thread.php?id=" . $threadId);
            exit;
        } else {
            $error = "Hiba történt a téma létrehozásakor.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Új téma nyitása - NorthSideRP</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .thread-input { width: 100%; padding: 12px; background: #0a0a0a; border: 1px solid var(--border-color); color: var(--text-normal); border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 14px; }
        .thread-input:focus { border-color: var(--primary-color); outline: none; }
        .thread-label { display: block; font-size: 13px; color: var(--text-muted); font-weight: bold; margin-bottom: 5px; }
    </style>
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
            <a href="category.php?id=<?= $category['id'] ?>"><?= htmlspecialchars($category['title']) ?></a>
            <i class="fa-solid fa-angle-right"></i>
            <span>Új téma nyitása</span>
        </div>

        <div class="block-container">
            <h2 class="block-header">Új téma nyitása: <?= htmlspecialchars($category['title']) ?></h2>
            <div class="block-body" style="padding: 25px;">
                
                <?php if($error): ?>
                    <div style="background: rgba(218, 54, 51, 0.1); border-left: 3px solid #da3633; color: #ff7b72; padding: 12px; margin-bottom: 20px; font-size: 14px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    
                    <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                        <div style="width: 150px;">
                            <label class="thread-label">Címke (Prefix)</label>
                            <select name="prefix" class="thread-input">
                                <option value="">(Nincs)</option>
                                <option value="Információ">Információ</option>
                                <option value="Közlemény">Közlemény</option>
                                <option value="Kérdés">Kérdés</option>
                                <option value="Segítség">Segítség</option>
                                <option value="Megoldva">Megoldva</option>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label class="thread-label">Téma Címe</label>
                            <input type="text" name="title" required class="thread-input" placeholder="Miről szól a téma?">
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="thread-label">Üzenet tartalma (BBCode használható)</label>
                        <div style="margin-bottom:8px; display:flex; gap:5px; background:var(--bg-block-alt); padding:8px; border:1px solid var(--border-color); border-radius:4px;">
                            <button type="button" onclick="insertTag('[b]','[/b]')" class="button" style="padding:5px 10px; background:#333;"><i class="fa-solid fa-bold"></i></button>
                            <button type="button" onclick="insertTag('[i]','[/i]')" class="button" style="padding:5px 10px; background:#333;"><i class="fa-solid fa-italic"></i></button>
                            <button type="button" onclick="insertTag('[img]','[/img]')" class="button" style="padding:5px 10px; background:#333;"><i class="fa-solid fa-image"></i></button>
                            <button type="button" onclick="insertTag('[url=link]','[/url]')" class="button" style="padding:5px 10px; background:#333;"><i class="fa-solid fa-link"></i></button>
                        </div>
                        <textarea name="content" id="editor" required class="thread-input" style="min-height: 250px; resize: vertical;" placeholder="Ide írd a bejegyzésed tartalmát..."></textarea>
                    </div>

                    <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 15px;">
                        <button type="submit" class="button"><i class="fa-solid fa-paper-plane"></i> Téma Létrehozása</button>
                    </div>

                </form>

            </div>
        </div>
    </main>
</div>

<footer class="p-footer">
    <div class="p-footer-inner"><div>&copy; 2026 NorthSideRP Fórum. Minden jog fenntartva.</div></div>
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