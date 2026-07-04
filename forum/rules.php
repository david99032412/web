<?php
// forum/rules.php - XENFORO SZABÁLYZATOK OLDAL
require 'config.php'; 
require 'functions.php';

$loggedIn = isLoggedIn();
$myId = $_SESSION['user_id'] ?? 0;

$me = null;
if ($loggedIn) {
    $stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
    $stmtMe->execute([$myId]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Szabályzatok - NorthSideRP</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header class="p-nav">
    <div class="p-nav-logo">NorthSide <span>Fórum</span></div>
    <div class="p-nav-opposite">
        <?php if($loggedIn): ?>
            <a href="search.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="pm.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-envelope"></i></a>
            <a href="notifications.php" class="p-navgroup-link icon-only"><i class="fa-solid fa-bell"></i></a>
            <a href="profile.php?id=<?= $myId ?>" class="p-navgroup-link"><img src="<?= getAvatar($me) ?>" class="avatar-menu"> <?= htmlspecialchars($me['username']) ?></a>
            <a href="logout.php" class="p-navgroup-link icon-only" style="border-right: 1px solid var(--border-color);"><i class="fa-solid fa-power-off"></i></a>
        <?php else: ?>
            <a href="login.php" class="p-navgroup-link" style="border:none;">Belépés</a>
            <a href="register.php" class="button">Regisztráció</a>
        <?php endif; ?>
    </div>
</header>
<div class="p-nav-sub">
    <a href="index.php">Új bejegyzések</a> 
    <a href="search.php">Keresés a fórumban</a>
    <a href="rules.php" style="color:var(--primary-color);">Szabályzatok</a>
</div>

<div class="p-body">
    
    <aside class="p-body-sidebar" style="width: 250px;">
        <div class="block-container">
            <h3 class="block-minorHeader">Információk</h3>
            <div class="block-body" style="padding:0;">
                <a href="rules.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-light); font-weight:bold;"><i class="fa-solid fa-book" style="width:20px;"></i> Fórum Szabályzat</a>
                <a href="#" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-server" style="width:20px;"></i> Szerver Szabályzat</a>
                <a href="#" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-users" style="width:20px;"></i> Frakció Szabályzat</a>
            </div>
        </div>
    </aside>

    <main class="p-body-main">
        
        <div class="p-breadcrumbs">
            <a href="index.php"><i class="fa-solid fa-house"></i> Kezdőlap</a>
            <i class="fa-solid fa-angle-right"></i>
            <span>Szabályzatok</span>
        </div>

        <div class="block-container">
            <h2 class="block-header"><i class="fa-solid fa-scale-balanced"></i> Általános Fórum Szabályzat</h2>
            <div class="block-body" style="padding: 25px; line-height: 1.8; color: var(--text-light); font-size: 14px;">
                
                <p>Üdvözlünk a NorthSideRP hivatalos fórumán! Ahhoz, hogy a közösségünk kulturált és segítőkész maradjon, kérjük, tartsd be az alábbi szabályokat. A szabályok nem ismerete nem mentesít a büntetés alól!</p>

                <h3 style="color: var(--primary-color); margin-top: 25px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">1. Általános Viselkedés</h3>
                <ul style="margin-left: 20px; color: var(--text-normal);">
                    <li>Tilos a másik játékos szidása, minősítése (OOC stílus).</li>
                    <li>Tilos a trágár, obszcén kifejezések használata.</li>
                    <li>Tilos más közösségek, szerverek hirdetése (Hirdetés).</li>
                    <li>Tisztelettel fordulj az Adminisztrátorok és a Moderátorok felé.</li>
                </ul>

                <h3 style="color: var(--primary-color); margin-top: 25px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">2. Téma nyitás és Hozzászólások</h3>
                <ul style="margin-left: 20px; color: var(--text-normal);">
                    <li>Mielőtt új témát nyitsz, használd a <a href="search.php" style="color:var(--primary-color);">Keresőt</a>, hogy meggyőződj róla, nincs-e már hasonló téma.</li>
                    <li>Tilos a "Spam" (egymás utáni felesleges üzenetek küldése) és a téma indokolatlan "Up"-olása.</li>
                    <li>A Panaszkönyvekbe és Unban kérelmekbe KIZÁRÓLAG az érintettek, illetve a bíráló Adminisztrátor írhat.</li>
                </ul>

                <h3 style="color: var(--primary-color); margin-top: 25px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">3. Profil és Aláírás</h3>
                <ul style="margin-left: 20px; color: var(--text-normal);">
                    <li>Az avatarodon (profilképeden) és az aláírásodban tilos pornográf, politikai vagy másokat sértő tartalmat elhelyezni.</li>
                    <li>A profilkép nem tartalmazhat villogó, zavaró GIF-eket.</li>
                </ul>

                <div style="background: rgba(218, 54, 51, 0.1); border-left: 3px solid #da3633; padding: 15px; margin-top: 30px; color: var(--text-normal);">
                    <strong style="color: #ff7b72;"><i class="fa-solid fa-triangle-exclamation"></i> Figyelmeztetések (Warn rendszer)</strong><br>
                    A szabályok megszegéséért a moderátorok Figyelmeztetést (Warn) oszthatnak ki (10-50% közötti mértékben). Amennyiben a figyelmeztetési szinted eléri a <strong>100%</strong>-ot, a fórum fiókod automatikusan és véglegesen kitiltásra kerül!
                </div>

            </div>
        </div>

    </main>
</div>

<footer class="p-footer">
    <div class="p-footer-inner"><div>&copy; 2026 NorthSideRP Fórum.</div></div>
</footer>
</body>
</html>
