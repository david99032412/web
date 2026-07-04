<?php
// ucp/support.php - HIBAJEGY RENDSZER (KATEGÓRIA VÁLASZTÁSSAL)
require_once 'config.php';
require_once 'functions.php';

date_default_timezone_set('Europe/Budapest');

if (!isLoggedIn()) redirect('login.php');

$stmtAcc = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
$stmtAcc->execute([$_SESSION['user_username']]);
$account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

if (!$account) die("Hiba a fiók betöltésekor!");
$isAdmin = ((int)$account['adminLevel'] >= 7);
$accountId = $account['accountId'];

$successMsg = '';
$errorMsg = '';

// --- AUTOMATIKUS TÁBLA LÉTREHOZÁS ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ucp_tickets` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `accountId` int(11) NOT NULL,
        `category` varchar(100) NOT NULL DEFAULT 'Általános',
        `subject` varchar(255) NOT NULL,
        `status` varchar(50) NOT NULL DEFAULT 'open',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `ucp_ticket_messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `ticket_id` int(11) NOT NULL,
        `sender_id` int(11) NOT NULL,
        `is_admin` tinyint(1) NOT NULL DEFAULT '0',
        `message` text NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `ticket_id` (`ticket_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Ha régi tábla van kategória nélkül, hozzáadjuk
    $pdo->exec("ALTER TABLE `ucp_tickets` ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) NOT NULL DEFAULT 'Általános' AFTER `accountId`");
} catch (Exception $e) {}

// --- TICKET LÉTREHOZÁSA (KATEGÓRIÁVAL) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_ticket') {
    $category = trim($_POST['category'] ?? 'Általános');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($subject) || empty($message)) {
        $errorMsg = "Minden mezőt kötelező kitölteni!";
    } elseif (strlen($subject) > 100) {
        $errorMsg = "A tárgy túl hosszú (maximum 100 karakter).";
    } else {
        try {
            $pdo->beginTransaction();
            $stmtT = $pdo->prepare("INSERT INTO ucp_tickets (accountId, category, subject, status) VALUES (?, ?, ?, 'open')");
            $stmtT->execute([$accountId, $category, $subject]);
            $ticketId = $pdo->lastInsertId();

            $stmtM = $pdo->prepare("INSERT INTO ucp_ticket_messages (ticket_id, sender_id, is_admin, message) VALUES (?, ?, 0, ?)");
            $stmtM->execute([$ticketId, $accountId, $message]);

            $pdo->commit();
            $successMsg = "A hibajegyet sikeresen rögzítettük a(z) '$category' kategóriában!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = "Adatbázis hiba történt a rögzítés során.";
        }
    }
}

// --- VÁLASZ KÜLDÉSE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_ticket') {
    $ticketId = (int)$_POST['ticket_id'];
    $message = trim($_POST['message'] ?? '');

    if (!empty($message)) {
        try {
            $check = $pdo->prepare("SELECT id, status FROM ucp_tickets WHERE id = ? AND accountId = ?");
            $check->execute([$ticketId, $accountId]);
            $ticket = $check->fetch(PDO::FETCH_ASSOC);

            if ($ticket) {
                if ($ticket['status'] === 'closed') {
                    $errorMsg = "Ebbe a lezárt jegybe már nem írhatsz új üzenetet.";
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare("INSERT INTO ucp_ticket_messages (ticket_id, sender_id, is_admin, message) VALUES (?, ?, 0, ?)")->execute([$ticketId, $accountId, $message]);
                    $pdo->prepare("UPDATE ucp_tickets SET status = 'open', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
                    $pdo->commit();
                    $successMsg = "A válaszodat rögzítettük!";
                }
            } else {
                $errorMsg = "Érvénytelen hibajegy.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errorMsg = "Hiba a válasz rögzítésekor.";
        }
    }
}

// --- TICKET LEZÁRÁSA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_ticket') {
    $ticketId = (int)$_POST['ticket_id'];
    try {
        $check = $pdo->prepare("SELECT id FROM ucp_tickets WHERE id = ? AND accountId = ?");
        $check->execute([$ticketId, $accountId]);
        if ($check->rowCount() > 0) {
            $pdo->prepare("UPDATE ucp_tickets SET status = 'closed', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
            $successMsg = "A hibajegyet sikeresen lezártad.";
        }
    } catch (Exception $e) {
        $errorMsg = "Hiba a jegy lezárásakor.";
    }
}

// Lekérjük a saját jegyeket
$myTickets = [];
try {
    $stmtTickets = $pdo->prepare("SELECT * FROM ucp_tickets WHERE accountId = ? ORDER BY updated_at DESC");
    $stmtTickets->execute([$accountId]);
    $myTickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$viewTicketId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$activeTicket = null;
$ticketMessages = [];

if ($viewTicketId > 0) {
    try {
        $stmtT = $pdo->prepare("SELECT * FROM ucp_tickets WHERE id = ? AND accountId = ?");
        $stmtT->execute([$viewTicketId, $accountId]);
        $activeTicket = $stmtT->fetch(PDO::FETCH_ASSOC);

        if ($activeTicket) {
            $stmtM = $pdo->prepare("
                SELECT tm.*, a.username, a.adminLevel
                FROM ucp_ticket_messages tm 
                JOIN accounts a ON tm.sender_id = a.accountId 
                WHERE tm.ticket_id = ? 
                ORDER BY tm.created_at ASC
            ");
            $stmtM->execute([$viewTicketId]);
            $ticketMessages = $stmtM->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - NorthSide UCP</title>
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
        
        .welcome-box { background: linear-gradient(135deg, rgba(13, 110, 253, 0.15), transparent); border: 1px solid rgba(13, 110, 253, 0.2); border-radius: 12px; padding: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .welcome-box h1 { color: #fff; font-size: 28px; font-weight: 800; margin: 0; }
        
        .card { background: rgb(33, 35, 40); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; overflow: hidden; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .card-header { padding: 20px 25px; background: rgba(0,0,0,0.2); border-bottom: 1px solid rgba(255,255,255,0.05); font-weight: bold; color: #fff; font-size: 16px; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 25px; }
        
        .ticket-box { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; padding: 20px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; transition: 0.2s; text-decoration: none; color: inherit; }
        .ticket-box:last-child { margin-bottom: 0; }
        .ticket-box:hover { border-color: #0d6efd; background: rgba(13, 110, 253, 0.05); transform: translateX(5px); }
        
        .ticket-title { font-weight: 900; color: #fff; font-size: 16px; margin-bottom: 8px; }
        .ticket-meta { color: #9ca3af; font-size: 12px; display: flex; gap: 15px; }
        
        .badge { padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-cat { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.2); }
        .badge-open { background: rgba(46, 204, 113, 0.1); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.2); }
        .badge-answered { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        .badge-closed { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        .chat-container { display: flex; flex-direction: column; gap: 20px; max-height: 500px; overflow-y: auto; padding-right: 10px; }
        .msg-bubble { padding: 18px; border-radius: 12px; max-width: 85%; line-height: 1.6; font-size: 14px; position: relative; }
        .msg-user { background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 110, 253, 0.3); margin-left: auto; border-bottom-right-radius: 4px; color: #fff; }
        .msg-admin { background: rgba(227, 179, 65, 0.1); border: 1px solid rgba(227, 179, 65, 0.3); margin-right: auto; border-bottom-left-radius: 4px; color: #fff; }
        .msg-header { font-size: 11px; color: #9ca3af; margin-bottom: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px; }
        
        /* KATEGÓRIA VÁLASZTÓ (SELECT) STÍLUS */
        input, select, textarea { width: 100%; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 15px; border-radius: 8px; font-family: inherit; margin-bottom: 15px; outline: none; transition: 0.2s; }
        input:focus, select:focus, textarea:focus { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2); }
        select option { background: rgb(33, 35, 40); color: #fff; }
        textarea { min-height: 120px; resize: vertical; }
        
        .btn { padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; color: white; font-weight: bold; text-transform: uppercase; font-size: 13px; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px;}
        .btn-blue { background: #0d6efd; }
        .btn-blue:hover { background: #0b5ed7; transform: translateY(-2px); }
        .btn-red { background: transparent; border: 1px solid #ef4444; color: #ef4444; }
        .btn-red:hover { background: rgba(239, 68, 68, 0.1); }
        .alert { padding: 15px; border-radius: 8px; font-weight: bold; margin-bottom: 25px; display: flex; align-items: center; gap: 10px;}
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
            <a href="support.php" class="active">Support</a>
            <a href="vehicles.php">Járművek</a>
            <a href="map.php">Térkép</a>
            <a href="ads.php">Hirdetések</a>
            <a href="invoices.php">Számlák</a>
            <?php if($isAdmin): ?><a href="adminpanel.php" style="color: #e3b341;">Admin Panel</a><?php endif; ?>
            <a href="logout.php" style="color: #ef4444;">Kilépés</a>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-box">
            <h1><i class="fa-solid fa-headset" style="color: #0d6efd; margin-right:10px;"></i> Support Rendszer</h1>
            <div style="color: #9ca3af;">Kérdésed van vagy hibát találtál? Nyiss egy jegyet!</div>
        </div>

        <?php if ($successMsg): ?><div class="alert" style="background: rgba(46,160,67,0.15); border: 1px solid #2ea043; color: #3fb950;"><i class="fa-solid fa-check-circle"></i> <?= $successMsg ?></div><?php endif; ?>
        <?php if ($errorMsg): ?><div class="alert" style="background: rgba(248,81,73,0.15); border: 1px solid #da3633; color: #f85149;"><i class="fa-solid fa-triangle-exclamation"></i> <?= $errorMsg ?></div><?php endif; ?>

        <?php if ($activeTicket): ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <span style="color:#0d6efd; margin-right: 10px;">#<?= $activeTicket['id'] ?></span> <?= htmlspecialchars($activeTicket['subject']) ?>
                        <span class="badge badge-cat" style="margin-left: 10px;"><?= htmlspecialchars($activeTicket['category'] ?? 'Általános') ?></span>
                    </div>
                    <div style="display:flex; align-items:center; gap:20px;">
                        <?php if ($activeTicket['status'] === 'open'): ?>
                            <span class="badge badge-open">Nyitott</span>
                        <?php elseif ($activeTicket['status'] === 'answered'): ?>
                            <span class="badge badge-answered">Megválaszolva</span>
                        <?php else: ?>
                            <span class="badge badge-closed">Lezárva</span>
                        <?php endif; ?>
                        <a href="support.php" style="color:#9ca3af; text-decoration:none; font-size:13px; font-weight:normal;"><i class="fa-solid fa-arrow-left"></i> Vissza a listához</a>
                    </div>
                </div>
                
                <div class="card-body" style="background: rgba(0,0,0,0.1);">
                    <div class="chat-container">
                        <?php foreach($ticketMessages as $msg): 
                            $isMe = ($msg['sender_id'] == $accountId && $msg['is_admin'] == 0);
                            $msgDate = date('Y.m.d. H:i', strtotime($msg['created_at']));
                            
                            // ÚJ: PONTOS RANG KIÍRATÁSA A JÁTÉKOSNAK IS
                            $rankName = getAdminTitle($msg['adminLevel']);
                        ?>
                            <div class="msg-bubble <?= $isMe ? 'msg-user' : 'msg-admin' ?>">
                                <div class="msg-header">
                                    <?php if($isMe): ?>
                                        <i class="fa-solid fa-user"></i> Te írtad <span style="float:right; font-weight:normal;"><i class="fa-regular fa-clock"></i> <?= $msgDate ?></span>
                                    <?php else: ?>
                                        <span style="color:#e3b341;"><i class="fa-solid fa-shield"></i> <?= htmlspecialchars($msg['username']) ?> (<?= $rankName ?>)</span> <span style="float:right; color:#9ca3af; font-weight:normal;"><i class="fa-regular fa-clock"></i> <?= $msgDate ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="white-space: pre-wrap;"><?= htmlspecialchars($msg['message']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if ($activeTicket['status'] !== 'closed'): ?>
                <div class="card-body" style="border-top: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.2);">
                    <form method="POST">
                        <input type="hidden" name="action" value="reply_ticket">
                        <input type="hidden" name="ticket_id" value="<?= $activeTicket['id'] ?>">
                        <textarea name="message" required placeholder="Írd ide a válaszod..."></textarea>
                        <div style="display:flex; justify-content: space-between; align-items: center;">
                            <button type="submit" class="btn btn-blue"><i class="fa-solid fa-paper-plane"></i> Válasz Küldése</button>
                        </div>
                    </form>
                    
                    <form method="POST" style="margin-top:20px; text-align:right;" onsubmit="return confirm('Biztosan lezárod a jegyet? Ezt követően nem tudsz több üzenetet küldeni ide.');">
                        <input type="hidden" name="action" value="close_ticket">
                        <input type="hidden" name="ticket_id" value="<?= $activeTicket['id'] ?>">
                        <button type="submit" class="btn btn-red"><i class="fa-solid fa-lock"></i> Hibajegy lezárása (Megoldva)</button>
                    </form>
                </div>
                <?php else: ?>
                <div class="card-body" style="border-top: 1px solid rgba(255,255,255,0.05); text-align:center; color:#9ca3af; padding: 30px;">
                    <i class="fa-solid fa-lock" style="font-size:32px; opacity:0.5; margin-bottom:15px; display:block;"></i>
                    Ez a hibajegy le lett zárva. Nem tudsz rá többé válaszolni.
                </div>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <div style="display:grid; grid-template-columns: 1fr; gap:25px; align-items:start;">
                
                <?php if (!empty($myTickets)): ?>
                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-list"></i> Folyamatban lévő és Korábbi Jegyeid</div>
                    <div class="card-body" style="padding: 20px;">
                        <?php foreach($myTickets as $t): ?>
                            <a href="?view=<?= $t['id'] ?>" class="ticket-box">
                                <div>
                                    <div class="ticket-title">
                                        <span style="color:#0d6efd; margin-right:8px;">#<?= $t['id'] ?></span> <?= htmlspecialchars($t['subject']) ?>
                                    </div>
                                    <div class="ticket-meta">
                                        <span><i class="fa-solid fa-folder-open"></i> <?= htmlspecialchars($t['category'] ?? 'Általános') ?></span>
                                        <span><i class="fa-solid fa-calendar-plus"></i> Nyitva: <?= date('Y.m.d.', strtotime($t['created_at'])) ?></span>
                                        <span><i class="fa-solid fa-clock-rotate-left"></i> Frissítve: <?= date('Y.m.d. H:i', strtotime($t['updated_at'])) ?></span>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($t['status'] === 'open'): ?>
                                        <span class="badge badge-open">Nyitott</span>
                                    <?php elseif ($t['status'] === 'answered'): ?>
                                        <span class="badge badge-answered">Admin Válaszolt</span>
                                    <?php else: ?>
                                        <span class="badge badge-closed">Lezárva</span>
                                    <?php endif; ?>
                                    <i class="fa-solid fa-chevron-right" style="color:#9ca3af; margin-left:15px;"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card" style="border-color: rgba(13, 110, 253, 0.3);">
                    <div class="card-header" style="background: rgba(13, 110, 253, 0.1); color: #0d6efd;"><i class="fa-solid fa-pen"></i> Új Hibajegy Nyitása</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="new_ticket">
                            
                            <label style="color:#9ca3af; font-size:12px; font-weight:bold; margin-bottom:8px; display:block; text-transform:uppercase;">Milyen ügyben keresel minket?</label>
                            <select name="category" required>
                                <option value="Általános">Általános (Kérdés, Segítségkérés)</option>
                                <option value="Mapper">Mapper (Hiba a pályán, mapolás igénylése)</option>
                                <option value="Fejlesztő">Fejlesztő (Bug report, Script hiba)</option>
                                <option value="Admin Panasz">Admin Panasz</option>
                                <option value="Frakció Ügy">Frakció Ügy (Pályázat, Leader kérés)</option>
                                <option value="Vagyon Visszaigénylés">Vagyon Visszaigénylés</option>
                            </select>
                            
                            <label style="color:#9ca3af; font-size:12px; font-weight:bold; margin-bottom:8px; display:block; text-transform:uppercase;">Tárgy / Probléma röviden</label>
                            <input type="text" name="subject" required placeholder="Pl.: Bugos a járművem, RP folytatás kérés, stb." maxlength="100">
                            
                            <label style="color:#9ca3af; font-size:12px; font-weight:bold; margin-bottom:8px; display:block; text-transform:uppercase;">Részletes leírás</label>
                            <textarea name="message" required placeholder="Írd le részletesen, miben segíthetünk..."></textarea>
                            
                            <button type="submit" class="btn btn-blue" style="width:100%; padding: 15px; font-size: 14px;"><i class="fa-solid fa-paper-plane"></i> Hibajegy Beküldése az Adminoknak</button>
                        </form>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </div>
</body>
</html>