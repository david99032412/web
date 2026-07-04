<?php
// forum/edit_profile.php - XENFORO DIZÁJN
require 'config.php';
require 'functions.php';

$loggedIn = isLoggedIn();
if (!$loggedIn) { header("Location: login.php"); exit; }

$myId = $_SESSION['user_id'];
$myAdminLevel = $_SESSION['admin_level'] ?? 0;

$me = null;
$stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
$stmtMe->execute([$myId]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);

// Kinek a profilját szerkesztjük? 
$targetId = isset($_GET['id']) ? (int)$_GET['id'] : $myId;

// JOGOSULTSÁG ELLENŐRZÉS
$isOwner = ($targetId == $myId);
$isAdmin = ($myAdminLevel >= 4); // 4-es szinttől szerkeszthet mást

if (!$isOwner && !$isAdmin) {
    die("Ehhez nincs jogod! Csak a saját profilodat szerkesztheted.");
}

// ADATOK LEKÉRÉSE
$stmt = $pdo->prepare("SELECT * FROM accounts WHERE accountId = ?");
$stmt->execute([$targetId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die("A felhasználó nem található.");

$msg = '';

// MENTÉS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ALAP ADATOK (Mindenki szerkesztheti)
    $avatar = clean($_POST['forum_avatar']);
    $signature = $_POST['forum_signature'];
    
    $newPass = $_POST['password'] ?? '';

    if (!empty($newPass)) {
        $hashed = md5($newPass);
        $pdo->prepare("UPDATE accounts SET forum_avatar=?, forum_signature=?, password=? WHERE accountId=?")
            ->execute([$avatar, $signature, $hashed, $targetId]);
    } else {
        $pdo->prepare("UPDATE accounts SET forum_avatar=?, forum_signature=? WHERE accountId=?")
            ->execute([$avatar, $signature, $targetId]);
    }

    // 2. ADMIN ADATOK (Csak Admin módosíthatja ezeket a részeket máson)
    if ($isAdmin) {
        $about = $_POST['about'] ?? '';
        $f_title = clean($_POST['forum_title'] ?? '');
        $pdo->prepare("UPDATE accounts SET about=?, forum_title=? WHERE accountId=?")
            ->execute([$about, $f_title, $targetId]);
    }

    $msg = "<div style='background: rgba(46, 160, 67, 0.1); border-left: 3px solid #2ea043; color: #3fb950; padding: 12px; margin-bottom: 20px; font-size: 14px;'><i class='fa-solid fa-check'></i> Profil sikeresen frissítve!</div>";
    
    // Újratöltjük az adatokat a formba
    $stmt->execute([$targetId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Profil Szerkesztése - NorthSideRP</title>
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
        <a href="profile.php?id=<?= $myId ?>" class="p-navgroup-link"><img src="<?= getAvatar($me) ?>" class="avatar-menu"> <?= htmlspecialchars($me['username']) ?></a>
        <a href="logout.php" class="p-navgroup-link icon-only" style="border-right: 1px solid var(--border-color);"><i class="fa-solid fa-power-off"></i></a>
    </div>
</header>
<div class="p-nav-sub"><a href="index.php">Új bejegyzések</a> <a href="search.php">Keresés a fórumban</a></div>

<div class="p-body">
    <main class="p-body-main" style="max-width: 800px; margin: 0 auto; flex: 1;">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <a href="profile.php?id=<?= $user['accountId'] ?>"><?= htmlspecialchars($user['username']) ?></a>
            <i class="fa-solid fa-angle-right"></i>
            <span>Szerkesztés</span>
        </div>

        <div class="block-container">
            <h2 class="block-header"><i class="fa-solid fa-user-pen"></i> <?= htmlspecialchars($user['username']) ?> profiljának szerkesztése</h2>
            <div class="block-body" style="padding: 25px;">
                
                <?= $msg ?>
                
                <form method="POST">
                    
                    <div style="margin-bottom: 20px; display: flex; gap: 20px; align-items: flex-start;">
                        <img src="<?= getAvatar($user) ?>" style="width: 80px; height: 80px; border-radius: 4px; object-fit: cover; border: 1px solid var(--border-color);">
                        <div style="flex: 1;">
                            <label class="settings-label">Profilkép URL (Avatar)</label>
                            <input type="text" name="forum_avatar" value="<?= htmlspecialchars($user['forum_avatar'] ?? '') ?>" class="settings-input">
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="settings-label">Aláírás (BBCode)</label>
                        <textarea name="forum_signature" rows="4" class="settings-input"><?= htmlspecialchars($user['forum_signature'] ?? '') ?></textarea>
                    </div>

                    <?php if($isAdmin): ?>
                    <div style="border-top: 1px solid var(--border-color); margin-top: 30px; padding-top: 20px; margin-bottom: 20px;">
                        <h3 style="color: #da3633; font-size: 14px; margin-bottom: 15px;"><i class="fa-solid fa-shield-halved"></i> Adminisztrátori beállítások</h3>
                        
                        <div style="margin-bottom: 20px;">
                            <label class="settings-label">Egyedi Titulus</label>
                            <input type="text" name="forum_title" value="<?= htmlspecialchars($user['forum_title'] ?? '') ?>" class="settings-input">
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label class="settings-label">Bemutatkozás (BBCode)</label>
                            <textarea name="about" rows="6" class="settings-input"><?= htmlspecialchars($user['about'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="border-top: 1px dashed var(--border-color); margin-top: 20px; padding-top: 20px; margin-bottom: 20px;">
                        <label class="settings-label">Jelszó módosítása (Hagyd üresen, ha nem akarod cserélni)</label>
                        <input type="password" name="password" placeholder="Új jelszó megadása..." class="settings-input">
                    </div>

                    <div style="text-align: right;">
                        <a href="profile.php?id=<?= $user['accountId'] ?>" style="color:var(--text-muted); margin-right: 15px;"><i class="fa-solid fa-arrow-left"></i> Vissza a profilhoz</a>
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