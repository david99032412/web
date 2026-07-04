<?php
// forum/admin_actions.php
require 'config.php'; require 'functions.php';

// Csak Admin 8 (Főadmin) és felette tilthat ki
if (!isLoggedIn() || $_SESSION['admin_level'] < 8) {
    die("Nincs jogosultságod ehhez a művelethez!");
}

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$adminId = $_SESSION['user_id'];

if ($id <= 0) exit(header("Location: admin.php"));

switch ($action) {
    case 'ban':
        // Kitiltás (Alapértelmezett indokkal, amit később a profilnál szerkeszthetsz)
        $stmt = $pdo->prepare("UPDATE accounts SET is_banned = 1, ban_reason = 'Súlyos szabályszegés' WHERE accountId = ?");
        $stmt->execute([$id]);
        logModAction($adminId, "Kitiltás (Fórum Ban)", "Célpont ID: $id");
        break;

    case 'unban':
        // Kitiltás feloldása
        $stmt = $pdo->prepare("UPDATE accounts SET is_banned = 0, ban_reason = NULL WHERE accountId = ?");
        $stmt->execute([$id]);
        logModAction($adminId, "Kitiltás feloldása", "Célpont ID: $id");
        break;

    case 'reset_posts':
        // Posztok nullázása (Csak Tulajdonosnak)
        if ($_SESSION['admin_level'] == 11) {
            $pdo->prepare("UPDATE accounts SET forum_posts = 0 WHERE accountId = ?")->execute([$id]);
            logModAction($adminId, "Posztok nullázása", "Célpont ID: $id");
        }
        break;
}

header("Location: admin.php");
exit;