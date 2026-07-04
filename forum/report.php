<?php
require 'config.php';
require 'functions.php';

if (!isLoggedIn()) { header("Location: login.php"); exit; }

$postId = (int)($_GET['post'] ?? 0);
$threadId = (int)($_GET['thread'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = clean($_POST['reason']);
    $userId = $_SESSION['user_id'];
    
    // Beszúrjuk az adatbázisba
    $stmt = $pdo->prepare("INSERT INTO forum_reports (post_id, user_id, reason) VALUES (?, ?, ?)");
    $stmt->execute([$postId, $userId, $reason]);
    
    header("Location: thread.php?id=$threadId&msg=reported");
    exit;
}
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) die("A hozzászólás nem található.");
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Jelentés - NorthSideRP</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body style="display:flex; align-items:center; justify-content:center; height:100vh;">

<div class="block" style="width:500px;">
    <div class="block-header" style="background:#e74c3c;">
        Jelentés küldése
    </div>
    <div class="block-container" style="padding:20px;">
        <p style="font-size:13px; color:#ccc;">
            Jelented <strong><?= htmlspecialchars($post['username']) ?></strong> hozzászólását.
            Kérjük, csak komoly esetben használd ezt a funkciót!
        </p>
        
        <form method="POST">
            <label style="display:block; margin-bottom:5px; font-size:12px; color:#888;">Indoklás:</label>
            <textarea name="reason" required style="width:100%; height:100px; background:#000; border:1px solid #333; color:#fff; padding:10px; margin-bottom:15px; box-sizing:border-box;"></textarea>
            
            <div style="text-align:right;">
                <a href="thread.php?id=<?= $threadId ?>" class="btn-small" style="color:#888; margin-right:10px;">Mégse</a>
                <button type="submit" class="label label--red" style="border:none; padding:8px 15px; cursor:pointer;">Jelentés elküldése</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>