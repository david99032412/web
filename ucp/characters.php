<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$stmtAcc = $pdo->prepare("SELECT license FROM accounts WHERE username = ?");
$stmtAcc->execute([$_SESSION['user_username']]);
$license = $stmtAcc->fetchColumn();

$stmt = $pdo->prepare("SELECT citizenid, charinfo, money, job, last_updated FROM players WHERE license = ?");
$stmt->execute([$license]);
$characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php foreach ($characters as $c): 
    $info = json_decode($c['charinfo'], true);
    $money = json_decode($c['money'], true);
    $job = json_decode($c['job'], true);
?>
    <div class="char-card">
        <h3><?= htmlspecialchars($info['firstname'] . " " . $info['lastname']) ?></h3>
        <p>Munka: <?= htmlspecialchars($job['label']) ?></p>
        <p>Vagyon: $<?= number_format($money['cash'] + $money['bank']) ?></p>
        <a href="character_view.php?citizenid=<?= $c['citizenid'] ?>">Részletek</a>
    </div>
<?php endforeach; ?>
