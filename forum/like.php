<?php
// forum/moderate.php - TÉMÁK ZÁRÁSA ÉS KIEMELÉSE
require 'config.php';
require 'functions.php';

if (!isLoggedIn()) { header("Location: login.php"); exit; }

$myId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$threadId = (int)($_GET['id'] ?? 0);

// Admin jogosultság ellenőrzése (Csak 1-es szint felettiek moderálhatnak témát)
$stmtMe = $pdo->prepare("SELECT COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
$stmtMe->execute([$myId]);
$adminLevel = $stmtMe->fetchColumn();

if ($adminLevel <= 0) {
    die("Nincs moderátori jogosultságod!");
}

if ($threadId > 0) {
    // 1. Téma Lekérése
    $stmt = $pdo->prepare("SELECT is_locked, is_sticky FROM forum_threads WHERE id = ?");
    $stmt->execute([$threadId]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($thread) {
        // 2. Műveletek végrehajtása
        if ($action == 'lock') {
            // Ha zárva van, nyitjuk. Ha nyitva van, zárjuk.
            $newState = ($thread['is_locked'] == 1) ? 0 : 1;
            $pdo->prepare("UPDATE forum_threads SET is_locked = ? WHERE id = ?")->execute([$newState, $threadId]);
        }
        elseif ($action == 'pin') {
            // Ha ki van emelve, levesszük. Ha nincs, kiemeljük.
            $newState = ($thread['is_sticky'] == 1) ? 0 : 1;
            $pdo->prepare("UPDATE forum_threads SET is_sticky = ? WHERE id = ?")->execute([$newState, $threadId]);
        }
        elseif ($action == 'delete') {
            // Téma és a hozzá tartozó posztok végleges törlése
            if ($adminLevel >= 4) { // Törölni mondjuk csak 4-es admintól lehessen
                $pdo->prepare("DELETE FROM forum_posts WHERE thread_id = ?")->execute([$threadId]);
                $pdo->prepare("DELETE FROM forum_threads WHERE id = ?")->execute([$threadId]);
                header("Location: index.php"); // Törlés után főoldal
                exit;
            } else {
                die("Téma törléséhez magasabb Admin szint szükséges!");
            }
        }
    }
}

// Visszairányítás a témához
header("Location: thread.php?id=" . $threadId);
exit;
?>

