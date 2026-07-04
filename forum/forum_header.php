<?php
// forum_header.php - EGYSÉGESÍTETT FEJLÉC ÉS NAVIGÁCIÓ
require_once 'config.php';
require_once 'functions.php';

// Ha nincs beállítva egyedi cím, adunk egy alapértelmezettet
$pageTitle = $pageTitle ?? 'NorthSideRP Fórum';

// Lekérjük a bejelentkezett felhasználó adatait a menühöz
$me = null;
if (isLoggedIn()) {
    $stmtMe = $pdo->prepare("SELECT username, forum_avatar, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
    $stmtMe->execute([$_SESSION['user_id']]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header class="p-nav">
    <div class="p-nav-logo">NorthSide <span>Fórum</span></div>
    <div class="p-nav-opposite">
        <a href="search.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-magnifying-glass"></i></a>
        
        <?php if ($me): ?>
            <a href="pm.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-envelope"></i></a>
            <a href="notifications.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-bell"></i></a>
            <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="p-navgroup-link">
                <img src="<?= getAvatar($me) ?>" class="avatar-menu" alt="Avatar"> <?= htmlspecialchars($me['username']) ?>
            </a>
            <a href="logout.php" class="p-navgroup-link icon-only" style="border-right: 1px solid var(--border-color);"><i class="fa-solid fa-power-off"></i></a>
        <?php else: ?>
            <a href="login.php" class="p-navgroup-link"><i class="fa-solid fa-right-to-bracket"></i> Bejelentkezés</a>
            <a href="register.php" class="p-navgroup-link"><i class="fa-solid fa-user-plus"></i> Regisztráció</a>
        <?php endif; ?>
    </div>
</header>
<div class="p-nav-sub">
    <a href="index.php">Főoldal</a> 
    <a href="index.php?action=new_posts">Új bejegyzések</a> 
    <a href="search.php">Keresés</a>
</div>

<div class="p-body">
    <main class="p-body-main" style="max-width: 1200px; margin: 0 auto; flex: 1; padding: 20px;">
