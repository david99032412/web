<?php
// forum/settings.php - XENFORO DIZÁJN
if (file_exists('config.php')) { require 'config.php'; require 'functions.php'; } 
else { require '../config.php'; require '../functions.php'; }

$loggedIn = isLoggedIn();
if (!$loggedIn) { header("Location: login.php"); exit; }

$uid = $_SESSION['user_id']; 
$msg = "";

$me = null;
$stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
$stmtMe->execute([$uid]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);

// Mentés kezelése
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $avatar = clean($_POST['avatar']);
    $signature = clean($_POST['signature']);
    $title = clean($_POST['title']);

    $stmt = $pdo->prepare("UPDATE accounts SET forum_avatar = ?, forum_signature = ?, forum_title = ? WHERE accountId = ?");
    if ($stmt->execute([$avatar, $signature, $title, $uid])) {
        $msg = "<div style='background: rgba(46, 160, 67, 0.1); border-left: 3px solid #2ea043; color: #3fb950; padding: 12px; margin-bottom: 20px; font-size: 14px;'><i class='fa-solid fa-check'></i> Sikeres mentés!</div>";
        // Frissítjük a memóriában lévő adatot is a megjelenítéshez
        $me['forum_avatar'] = $avatar; $me['forum_signature'] = $signature; $me['forum_title'] = $title;
    } else {
        $msg = "<div style='background: rgba(218, 54, 51, 0.1); border-left: 3px solid #da3633; color: #ff7b72; padding: 12px; margin-bottom: 20px; font-size: 14px;'><i class='fa-solid fa-triangle-exclamation'></i> Hiba történt a mentéskor.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Fiók Beállítások - NorthSideRP</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-input { width: 100%; padding: 10px; background: #0a0a0a; border: 1px solid var(--border-color); color: var(--text-normal); border-radius: 4px; box-sizing: border-box; font-family: inherit; }
        .settings-label { display: block; font-size: 13px; color: var(--text-muted); font-weight: bold; margin-bottom: 5px; }
    </style>
</head>
<body>

<header class="p-nav">
    <div class="p-nav-logo">NorthSide <span>Fórum</span></div>
    <div class="p-nav-opposite">
        <a href="search.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-magnifying-glass"></i></a>
        <a href="pm.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-envelope"></i></a>
        <a href="notifications.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-bell"></i></a>
        <a href="profile.php?id=<?= $uid ?>" class="p-navgroup-link"><img src="<?= getAvatar($me) ?>" class="avatar-menu"> <?= htmlspecialchars($me['username']) ?></a>
        <a href="logout.php" class="p-navgroup-link icon-only" style="border-right: 1px solid var(--border-color);"><i class="fa-solid fa-power-off"></i></a>
    </div>
</header>
<div class="p-nav-sub"><a href="index.php">Új bejegyzések</a> <a href="search.php">Keresés a fórumban</a></div>

<div class="p-body">
    <main class="p-body-main" style="max-width: 700px; margin: 0 auto; flex: 1;">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <span>Fiók Beállítások</span>
        </div>

        <div class="block-container">
            <h2 class="block-header">Fiók Beállítások</h2>
            <div class="block-body" style="padding: 25px;">
                
                <?= $msg ?>
                
                <form method="POST">
                    <div style="margin-bottom: 25px; display: flex; gap: 20px; align-items: flex-start;">
                        <img src="<?= getAvatar($me) ?>" style="width: 96px; height: 96px; border-radius: 4px; object-fit: cover; border: 1px solid var(--border-color);">
                        <div style="flex: 1;">
                            <label class="settings-label">Profilkép URL (pl. Imgur link)</label>
                            <input type="text" name="avatar" value="<?= htmlspecialchars($me['forum_avatar'] ?? '') ?>" placeholder="https://i.imgur.com/..." class="settings-input">
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">Illeszd be a kép közvetlen linkjét.</div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 25px;">
                        <label class="settings-label">Egyedi Titulus (A név alatt jelenik meg)</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($me['forum_title'] ?? '') ?>" placeholder="Pl. A legjobb rendőr" class="settings-input">
                    </div>

                    <div style="margin-bottom: 25px;">
                        <label class="settings-label">Aláírás (Megjelenik minden hozzászólásod alatt BBCode használható)</label>
                        <textarea name="signature" rows="5" class="settings-input" placeholder="Írd ide az aláírásodat..."><?= htmlspecialchars($me['forum_signature'] ?? '') ?></textarea>
                    </div>

                    <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 15px;">
                        <button type="submit" class="button"><i class="fa-solid fa-floppy-disk"></i> Változtatások mentése</button>
                    </div>
                </form>

            </div>
        </div>
    </main>
</div>

<footer class="p-footer">
    <div class="p-footer-inner"><div>&copy; 2026 NorthSideRP Fórum.</div></div>
</footer>
</body>
</html>