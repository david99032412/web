<?php
// forum/delete_post.php - JAVÍTOTT VERZIÓ
require 'config.php';
require 'functions.php';

// Hibák megjelenítése, hogy lássuk mi a baj
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isLoggedIn()) { header("Location: login.php"); exit; }

$postId = (int)($_GET['id'] ?? 0);
$uid = $_SESSION['user_id'];
$isMod = canModerate(); // Admin jog

// 1. Poszt adatainak lekérése
$stmt = $pdo->prepare("SELECT * FROM forum_posts WHERE id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) die("HIBA: A hozzászólás nem található (lehet már törölve lett).");

// 2. Jogosultság ellenőrzése
// Saját maga VAGY Admin törölhet
if ($post['user_id'] != $uid && !$isMod) {
    die("HIBA: Nincs jogosultságod törölni ezt a hozzászólást!");
}

$threadId = $post['thread_id'];

// 3. Ellenőrizzük, hogy ez-e a Nyitóposzt (Téma indító)
// Megkeressük a legelső posztot a témában
$firstPostStmt = $pdo->prepare("SELECT id FROM forum_posts WHERE thread_id = ? ORDER BY created_at ASC LIMIT 1");
$firstPostStmt->execute([$threadId]);
$firstPostId = $firstPostStmt->fetchColumn();

try {
    // HA A NYITÓPOSZTOT TÖRLI -> AZ EGÉSZ TÉMA TÖRLÉSE
    if ($postId == $firstPostId) {
        
        if (!$isMod) die("HIBA: A teljes témát csak Adminisztrátor törölheti!");

        // Először töröljük az összes választ a témából
        $pdo->prepare("DELETE FROM forum_posts WHERE thread_id = ?")->execute([$threadId]);

        // Majd töröljük magát a témát
        $pdo->prepare("DELETE FROM forum_threads WHERE id = ?")->execute([$threadId]);

        // Vissza a főoldalra
        header("Location: index.php");
        exit;

    } else {
        // HA CSAK EGY SIMA VÁLASZT TÖRÖL
        $pdo->prepare("DELETE FROM forum_posts WHERE id = ?")->execute([$postId]);
        
        // Frissítjük a téma statisztikáját (opcionális, de jó ha van)
        // (Itt nincs account update, hogy ne legyen lassú)

        // Vissza a témához
        header("Location: thread.php?id=$threadId");
        exit;
    }

} catch (PDOException $e) {
    // Ha SQL hiba van, kiírjuk
    die("ADATBÁZIS HIBA TÖRLÉSKOR: " . $e->getMessage());
}
?>