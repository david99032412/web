<?php
// forum/admin_edit_user.php - XENFORO ADMIN PANEL DIZÁJN
require 'config.php'; require 'functions.php';

$loggedIn = isLoggedIn();
if (!$loggedIn) { header("Location: login.php"); exit; }

$myId = $_SESSION['user_id'];
$stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
$stmtMe->execute([$myId]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);

// SZIGORÚ ELLENŐRZÉS
if (!$me || $me['adminLevel'] <= 0) {
    die("Nincs jogosultságod az Admin Panel megtekintéséhez!");
}

$id = (int)($_GET['id'] ?? 0);
$lvl = $me['adminLevel'];
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newRank = (int)$_POST['adminLevel'];
    $newPosts = (int)$_POST['forum_posts'];
    $newWarn = (int)$_POST['warn_level'];
    $newTitle = clean($_POST['forum_title']);

    // Csak Tulajdonos állíthat be 8-nál nagyobb rangot
    if ($newRank >= 8 && $lvl < 11) {
        $msg = "<div style='background: rgba(218, 54, 51, 0.1); border-left: 3px solid #da3633; color: #ff7b72; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-triangle-exclamation'></i> Csak a Tulajdonos adhat ilyen magas rangot!</div>";
    } else {
        // Frissítés
        $stmt = $pdo->prepare("UPDATE accounts SET adminLevel = ?, forum_posts = ?, warn_level = ?, forum_title = ? WHERE accountId = ?");
        $stmt->execute([$newRank, $newPosts, $newWarn, $newTitle, $id]);
        
        $msg = "<div style='background: rgba(46, 160, 67, 0.1); border-left: 3px solid #2ea043; color: #3fb950; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-check'></i> Játékos adatai sikeresen frissítve!</div>";
    }
}

$stmt = $pdo->prepare("SELECT * FROM accounts WHERE accountId = ?");
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) die("Nincs ilyen tag az adatbázisban.");
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Szerkesztés: <?= htmlspecialchars($u['username']) ?> - Admin Panel</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-input { width: 100%; padding: 10px; background: #0a0a0a; border: 1px solid var(--border-color); color: var(--text-normal); border-radius: 4px; box-sizing: border-box; font-family: inherit; margin-bottom: 15px;}
        .admin-label { display: block; font-size: 13px; color: var(--text-light); font-weight: bold; margin-bottom: 5px; }
    </style>
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
                <a href="admin_users.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-light); font-weight:bold;"><i class="fa-solid fa-users" style="width:20px;"></i> Felhasználó kezelő</a>
                <a href="admin_reports.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-triangle-exclamation" style="width:20px;"></i> Jelentések</a>
                <a href="mod_logs.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-clipboard-list" style="width:20px;"></i> Moderátori Napló</a>
            </div>
        </div>
    </aside>

    <main class="p-body-main">
        
        <div class="p-breadcrumbs" style="margin-bottom: 20px;">
            <a href="admin.php" style="color: #c0392b; font-weight: bold; font-size: 14px; background: rgba(192, 57, 43, 0.1); padding: 8px 12px; border-radius: 4px; border: 1px solid #c0392b;"><i class="fa-solid fa-arrow-left"></i> Vissza a Vezérlőpultra (admin.php)</a>
            <span style="margin: 0 10px; color: var(--text-muted);">|</span>
            <a href="admin_users.php" style="color: var(--text-muted); font-size: 14px;">Vissza a Felhasználó keresőhöz</a>
        </div>

        <?= $msg ?>

        <div class="block-container">
            <h2 class="block-header" style="background: linear-gradient(0deg, #7c241c 0%, #c0392b 100%); border-bottom-color:#da3633;">
                <i class="fa-solid fa-user-pen"></i> <?= htmlspecialchars($u['username']) ?> adatainak szerkesztése
            </h2>
            <div class="block-body" style="padding: 25px;">
                
                <form method="POST">
                    <div style="display:flex; gap:20px;">
                        <div style="flex:1;">
                            <label class="admin-label">Admin Szint (Rang ID)</label>
                            <input type="number" name="adminLevel" value="<?= $u['adminLevel'] ?? $u['AdminLevel'] ?? 0 ?>" min="0" max="11" class="admin-input">
                        </div>
                        <div style="flex:1;">
                            <label class="admin-label">Posztok száma</label>
                            <input type="number" name="forum_posts" value="<?= $u['forum_posts'] ?>" class="admin-input">
                        </div>
                        <div style="flex:1;">
                            <label class="admin-label">Warn szint (%)</label>
                            <input type="number" name="warn_level" value="<?= $u['warn_level'] ?>" min="0" max="100" class="admin-input">
                        </div>
                    </div>

                    <label class="admin-label">Egyedi Fórum Cím (Titulus)</label>
                    <input type="text" name="forum_title" value="<?= htmlspecialchars($u['forum_title'] ?? '') ?>" class="admin-input">

                    <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 15px;">
                        <button type="submit" class="button" style="background:#2ea043;"><i class="fa-solid fa-floppy-disk"></i> Változtatások mentése</button>
                    </div>
                </form>

            </div>
        </div>

    </main>
</div>

</body>
</html>
