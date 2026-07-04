<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Ha nincs bejelentkezve (user_username session kulcs alapján)
if (!isLoggedIn()) {
    redirect('../ucp/login.php');
}

// Opcionális: ellenőrizzük, hogy létezik-e még a felhasználó
$stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE username = ?");
$stmt->execute([$_SESSION['user_username']]);
if ($stmt->fetchColumn() == 0) {
    session_destroy();
    redirect('../ucp/login.php?expired=1');
}
?>