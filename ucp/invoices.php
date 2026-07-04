<?php
// ucp/invoices.php - SZÁMLÁK ÉS BÍRSÁGOK (SZIGORÍTOTT ADATMEGJELENÍTÉS)
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$stmtAcc = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
$stmtAcc->execute([$_SESSION['user_username']]);
$account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

if (!$account) die("Hiba a fiók betöltésekor!");
$isAdmin = ((int)$account['adminLevel'] >= 7);
$accountId = $account['accountId'];

$successMsg = ''; $errorMsg = '';

// Saját karakterek lekérése
$myChars = [];
$charIds = [];
try {
    $stmtChars = $pdo->prepare("SELECT characterId, name, bankMoney FROM characters WHERE accountId = ?");
    $stmtChars->execute([$accountId]);
    $myChars = $stmtChars->fetchAll(PDO::FETCH_ASSOC);
    foreach ($myChars as $c) {
        $charIds[] = $c['characterId'];
    }
} catch (Exception $e) {}

// BEFIZETÉS LOGIKA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_invoice') {
    $invoiceId = (int)$_POST['invoice_id'];
    $charId = (int)$_POST['char_id'];
    $amount = (int)$_POST['amount'];

    if (in_array($charId, $charIds)) {
        try {
            $checkChar = $pdo->prepare("SELECT bankMoney FROM characters WHERE characterId = ?");
            $checkChar->execute([$charId]);
            $charData = $checkChar->fetch(PDO::FETCH_ASSOC);

            if ($charData && $charData['bankMoney'] >= $amount) {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE characters SET bankMoney = bankMoney - ? WHERE characterId = ?")->execute([$amount, $charId]);
                $pdo->prepare("DELETE FROM serviceinvoices WHERE id = ?")->execute([$invoiceId]);
                $pdo->commit();
                $successMsg = "A számlát ($" . number_format($amount, 0, '', ' ') . ") sikeresen befizetted a bankkártyádról!";
                
                foreach ($myChars as &$c) {
                    if ($c['characterId'] == $charId) $c['bankMoney'] -= $amount;
                }
            } else {
                $errorMsg = "Nincs elegendő fedezet a karaktered bankszámláján a befizetéshez!";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errorMsg = "Hiba történt a tranzakció során: " . $e->getMessage();
        }
    } else {
        $errorMsg = "Érvénytelen karakter!";
    }
}

// Számlák lekérése SZIGORÚAN csak a saját karakterekre
$invoices = [];
if (!empty($charIds)) {
    try {
        $colsStmt = $pdo->query("SHOW COLUMNS FROM serviceinvoices");
        $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $charIdCol = null;
        $possibleCols = ['characterId', 'charId', 'owner', 'ownerId', 'player', 'playerId'];
        foreach ($possibleCols as $pc) {
            if (in_array($pc, $cols)) { $charIdCol = $pc; break; }
        }
        
        if ($charIdCol) {
            $placeholders = implode(',', array_fill(0, count($charIds), '?'));
            $stmtInv = $pdo->prepare("SELECT * FROM serviceinvoices WHERE `$charIdCol` IN ($placeholders) ORDER BY id DESC");
            $stmtInv->execute($charIds);
            $invoices = $stmtInv->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}

function getCharNameById($id, $myChars) {
    foreach ($myChars as $c) {
        if ($c['characterId'] == $id) return str_replace('_', ' ', $c['name']);
    }
    return "Ismeretlen";
}
function getCharBankById($id, $myChars) {
    foreach ($myChars as $c) {
        if ($c['characterId'] == $id) return $c['bankMoney'];
    }
    return 0;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Számláim - NorthSide UCP</title>
    <script src="https://kit.fontawesome.com/122db0fdde.js" crossorigin="anonymous"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: rgb(26, 27, 31); color: #d1d5db; font-family: 'Inter', sans-serif; }
        .navbar { background: rgba(17, 18, 20, 0.95); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; backdrop-filter: blur(10px); }
        .navbar-brand { font-size: 20px; font-weight: 900; color: #fff; text-transform: uppercase; }
        .navbar-brand span { color: #0d6efd; }
        .nav-links a { color: #9ca3af; text-decoration: none; margin-left: 20px; font-size: 14px; font-weight: 500; transition: 0.2s; }
        .nav-links a:hover, .nav-links a.active { color: #fff; }
        
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .welcome-box { background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), transparent); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; padding: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .welcome-box h1 { color: #fff; font-size: 28px; font-weight: 800; margin: 0; }

        .invoice-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; }
        
        .invoice-card { background: rgb(33, 35, 40); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; overflow: hidden; transition: 0.2s; position: relative; }
        .invoice-card:hover { transform: translateY(-5px); border-color: rgba(239, 68, 68, 0.3); box-shadow: 0 10px 20px rgba(0,0,0,0.3); }
        
        .inv-header { background: rgba(239, 68, 68, 0.1); padding: 15px 20px; border-bottom: 1px solid rgba(239, 68, 68, 0.2); display: flex; justify-content: space-between; align-items: center; }
        .inv-id { font-weight: 900; color: #ef4444; font-family: monospace; font-size: 16px; }
        .inv-amount { font-size: 20px; font-weight: 900; color: #fff; }
        
        .inv-body { padding: 20px; display: grid; gap: 15px; }
        .data-row { display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.05); padding-bottom: 10px; font-size: 13px;}
        .data-row:last-child { border-bottom: none; padding-bottom: 0;}
        .data-label { color: #9ca3af; font-weight: bold; text-transform: uppercase; font-size: 11px; letter-spacing: 1px;}
        .data-value { color: #fff; font-weight: bold; text-align: right; max-width: 60%; word-wrap: break-word;}
        
        .inv-footer { padding: 20px; background: rgba(0,0,0,0.2); border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;}
        
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; color: white; font-weight: bold; text-transform: uppercase; font-size: 13px; transition: 0.2s; width: 100%; display: flex; justify-content: center; gap: 10px; align-items: center;}
        .btn-green { background: #3fb950; }
        .btn-green:hover { background: #2ea043; }
        .btn:disabled { background: #6e7681; cursor: not-allowed; opacity: 0.5; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">NorthSide <span>UCP</span></div>
        <div class="nav-links">
            <a href="dashboard.php">Kezdőlap</a>
            <a href="profile.php">Profilom</a>
            <a href="characters.php">Karakterek</a>
            <a href="factions.php">Frakcióim</a>
            <a href="support.php">Support</a>
            <a href="vehicles.php">Járművek</a>
            <a href="map.php">Térkép</a>
            <a href="ads.php">Hirdetések</a>
            <a href="invoices.php" class="active">Számlák</a>
            <?php if($isAdmin): ?><a href="adminpanel.php" style="color: #e3b341;">Admin Panel</a><?php endif; ?>
            <a href="logout.php" style="color: #ef4444;">Kilépés</a>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-box">
            <h1><i class="fa-solid fa-file-invoice-dollar" style="color: #ef4444; margin-right:10px;"></i> Befizetetlen Csekkek</h1>
            <div style="color: #9ca3af; font-size: 16px;">Nyitott számlák: <span style="color:#fff; font-weight:bold;"><?= count($invoices) ?> db</span></div>
        </div>

        <?php if ($successMsg): ?><div style="background: rgba(46,160,67,0.15); border: 1px solid #2ea043; color: #3fb950; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight:bold;"><i class="fa-solid fa-check"></i> <?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg): ?><div style="background: rgba(248,81,73,0.15); border: 1px solid #da3633; color: #f85149; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight:bold;"><i class="fa-solid fa-xmark"></i> <?= $errorMsg ?></div><?php endif; ?>

        <?php if(empty($invoices)): ?>
            <div style="text-align: center; padding: 80px 20px; background: rgb(33, 35, 40); border: 1px dashed rgba(255,255,255,0.1); border-radius: 12px; color: #9ca3af;">
                <i class="fa-solid fa-check-double" style="font-size: 64px; color: #3fb950; margin-bottom: 20px; opacity: 0.8;"></i>
                <h3 style="color:#fff; font-size: 20px;">Nincs befizetetlen számlád!</h3>
                <p style="margin-top: 10px;">Minden karaktered tartozása rendezve van.</p>
            </div>
        <?php else: ?>
            <div class="invoice-grid">
                <?php foreach($invoices as $inv): 
                    $amount = (int)($inv['amount'] ?? $inv['price'] ?? 0);
                    
                    // CSAK AKKOR MUTATJUK, HA LÉTEZIK
                    $reason = $inv['reason'] ?? $inv['description'] ?? null;
                    $issuer = $inv['issuer'] ?? $inv['creator'] ?? $inv['officer'] ?? $inv['issuedBy'] ?? $inv['faction'] ?? null;
                    $dateRaw = $inv['date'] ?? $inv['created_at'] ?? $inv['time'] ?? $inv['timestamp'] ?? null;
                    
                    $charIdCol = null;
                    $possibleCols = ['characterId', 'charId', 'owner', 'ownerId', 'player', 'playerId'];
                    foreach ($possibleCols as $pc) {
                        if (isset($inv[$pc])) { $charIdCol = $pc; break; }
                    }
                    $charId = $inv[$charIdCol] ?? 0;
                    $charName = getCharNameById($charId, $myChars);
                    $charBank = getCharBankById($charId, $myChars);
                    $canPay = ($charBank >= $amount);
                    
                    $dateFormatted = null;
                    if (is_numeric($dateRaw) && $dateRaw > 1000000000) {
                        $dateFormatted = date('Y.m.d. H:i', $dateRaw);
                    } elseif ($dateRaw) {
                        $dateFormatted = $dateRaw;
                    }
                ?>
                <div class="invoice-card">
                    <div class="inv-header">
                        <div class="inv-id">CSEKK #<?= $inv['id'] ?></div>
                        <div class="inv-amount">-$<?= number_format($amount, 0, '', ' ') ?></div>
                    </div>
                    <div class="inv-body">
                        <?php if($reason): ?>
                        <div class="data-row">
                            <div class="data-label"><i class="fa-solid fa-circle-exclamation"></i> Bírság / Számla Oka</div>
                            <div class="data-value" style="color:#ef4444;"><?= htmlspecialchars($reason) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($issuer): ?>
                        <div class="data-row">
                            <div class="data-label"><i class="fa-solid fa-building-shield"></i> Kiállító (Hatóság)</div>
                            <div class="data-value"><?= htmlspecialchars($issuer) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($dateFormatted): ?>
                        <div class="data-row">
                            <div class="data-label"><i class="fa-solid fa-clock"></i> Kiállítás Ideje</div>
                            <div class="data-value"><?= htmlspecialchars($dateFormatted) ?></div>
                        </div>
                        <?php endif; ?>

                        <div class="data-row">
                            <div class="data-label"><i class="fa-solid fa-user-tag"></i> Érintett Karakter</div>
                            <div class="data-value"><?= $charName ?></div>
                        </div>
                        <div class="data-row">
                            <div class="data-label"><i class="fa-solid fa-building-columns"></i> Banki Egyenleg</div>
                            <div class="data-value" style="<?= $canPay ? 'color:#3fb950;' : 'color:#ef4444;' ?>">$<?= number_format($charBank, 0, '', ' ') ?></div>
                        </div>
                    </div>
                    <div class="inv-footer">
                        <form method="POST" style="margin:0; width:100%;" onsubmit="return confirm('Biztosan kifizeted a számlát a bankkártyádról?');">
                            <input type="hidden" name="action" value="pay_invoice">
                            <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                            <input type="hidden" name="char_id" value="<?= $charId ?>">
                            <input type="hidden" name="amount" value="<?= $amount ?>">
                            <button type="submit" class="btn btn-green" <?= $canPay ? '' : 'disabled title="Nincs elég pénz a bankban"' ?>>
                                <i class="fa-solid fa-credit-card"></i> Fizetés Bankkártyával
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
