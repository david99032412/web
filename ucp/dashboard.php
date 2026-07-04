<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$stmtAcc = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
$stmtAcc->execute([$_SESSION['user_username']]);
$account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

$playerLicense = $account['license'] ?? '';

// --- STATISZTIKÁK LEKÉRÉSE A QBCORE SQL-BŐL ---
$totalWealth = 0;
$characterCount = 0;

try {
    $stmt = $pdo->prepare("SELECT money FROM players WHERE license = ?");
    $stmt->execute([$playerLicense]);
    $chars = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $characterCount = count($chars);

    foreach ($chars as $c) {
        $money = json_decode($c['money'], true);
        $totalWealth += (int)($money['cash'] ?? 0) + (int)($money['bank'] ?? 0);
    }
} catch (Exception $e) {}

// Leggazdagabbak toplistája (JSON dekódolással PHP-ban a biztos működésért)
$topWealth = [];
try {
    $res = $pdo->query("SELECT charinfo, money FROM players")->fetchAll(PDO::FETCH_ASSOC);
    foreach($res as $r) {
        $ci = json_decode($r['charinfo'], true);
        $mo = json_decode($r['money'], true);
        $topWealth[] = [
            'name' => ($ci['firstname'] ?? 'Ismeretlen') . ' ' . ($ci['lastname'] ?? ''),
            'total' => (int)($mo['cash'] ?? 0) + (int)($mo['bank'] ?? 0)
        ];
    }
    usort($topWealth, function($a, $b) { return $b['total'] <=> $a['total']; });
    $topWealth = array_slice($topWealth, 0, 5);
} catch (Exception $e) {}
?>
