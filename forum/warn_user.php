<?php
// forum/warn_user.php
require 'config.php';
require 'functions.php';

// Csak adminoknak!
if (!isLoggedIn() || !canModerate()) {
    die("Nincs jogosultságod ehhez!");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId = (int)$_POST['user_id'];
    $reason = clean($_POST['reason']);
    $percent = (int)$_POST['percent'];
    $adminId = $_SESSION['user_id'];

    // Megnézzük a jelenlegi szintet
    $stmt = $pdo->prepare("SELECT warn_level, username FROM accounts WHERE accountId = ?");
    $stmt->execute([$targetId]);
    $user = $stmt->fetch();

    if ($user) {
        $newLevel = $user['warn_level'] + $percent;
        $isBanned = 0;

        // Ha eléri a 100%-ot, automatikus BAN
        if ($newLevel >= 100) {
            $newLevel = 100;
            $isBanned = 1;
        }

        // Frissítés az adatbázisban
        $update = $pdo->prepare("UPDATE accounts SET warn_level = ?, is_banned = ? WHERE accountId = ?");
        $update->execute([$newLevel, $isBanned, $targetId]);

        // Naplózás (opcionális, ha van mod_logs tábla)
        // logModAction($adminId, "WARN $percent%", "User: {$user['username']} - Indok: $reason");

        // Visszairányítás a profilra
        header("Location: profile.php?id=$targetId&msg=warned");
        exit;
    }
}
header("Location: index.php");