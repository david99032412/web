<?php
// forum/admin.php - XENFORO ADMIN PANEL (TELJES VERZIÓ)
require 'config.php'; require 'functions.php';

$loggedIn = isLoggedIn();
if (!$loggedIn) { header("Location: login.php"); exit; }

$myId = $_SESSION['user_id'];
$me = null;
$stmtMe = $pdo->prepare("SELECT *, COALESCE(adminLevel, AdminLevel, 0) as adminLevel FROM accounts WHERE accountId = ?");
$stmtMe->execute([$myId]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);

// SZIGORÚ ELLENŐRZÉS
if (!$me || $me['adminLevel'] <= 0) {
    die("Nincs jogosultságod az Admin Panel megtekintéséhez!");
}

$msg = "";

// ÚJ KATEGÓRIA / FÓRUM RÉSZ LÉTREHOZÁSA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_category') {
    $title = clean($_POST['title']);
    $desc = clean($_POST['description']);
    $parent = (int)$_POST['parent_id'];
    $order = (int)$_POST['display_order'];

    $stmt = $pdo->prepare("INSERT INTO forum_categories (title, description, parent_id, display_order) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$title, $desc, $parent, $order])) {
        $msg = "<div style='background: rgba(46, 160, 67, 0.1); border-left: 3px solid #2ea043; color: #3fb950; padding: 12px; margin-bottom: 20px;'><i class='fa-solid fa-check'></i> Kategória sikeresen létrehozva!</div>";
    }
}

// Fő kategóriák lekérése a legördülőhöz
$parentsStmt = $pdo->query("SELECT * FROM forum_categories WHERE parent_id = 0 ORDER BY display_order ASC");
$parents = $parentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Statisztikák az Adminnak
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn(),
    'threads' => $pdo->query("SELECT COUNT(*) FROM forum_threads")->fetchColumn(),
    'posts' => $pdo->query("SELECT COUNT(*) FROM forum_posts")->fetchColumn()
];

// Aktív jelentések száma a menühöz
try {
    $activeReports = $pdo->query("SELECT COUNT(*) FROM forum_reports")->fetchColumn();
} catch(Exception $e) {
    $activeReports = 0;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - NorthSideRP</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-input { width: 100%; padding: 10px; background: #0a0a0a; border: 1px solid var(--border-color); color: var(--text-normal); border-radius: 4px; box-sizing: border-box; font-family: inherit; margin-bottom: 15px;}
        .admin-label { display: block; font-size: 13px; color: var(--text-light); font-weight: bold; margin-bottom: 5px; }
    </style>
</head>
<body>

<header class="p-nav" style="border-bottom-color: #c0392b;">
    <div class="p-nav-logo" style="color:#c0392b;">NorthSide <span style="color:#fff;">ADMIN</span></div>
    <div class="p-nav-opposite">
        <a href="index.php" class="p-navgroup-link icon-only" title="Vissza a fórumra"><i class="fa-solid fa-right-from-bracket"></i> Kilépés a fórumba</a>
    </div>
</header>

<div class="p-body">
    
    <aside class="p-body-sidebar" style="width: 250px;">
        <div class="block-container">
            <h3 class="block-minorHeader" style="background: linear-gradient(0deg, #7c241c 0%, #c0392b 100%);">Navigáció</h3>
            <div class="block-body" style="padding:0;">
                <a href="admin.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-light); font-weight:bold;"><i class="fa-solid fa-gauge" style="width:20px;"></i> Vezérlőpult</a>
                
                <a href="admin_users.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-users" style="width:20px;"></i> Felhasználó kezelő</a>
                
                <a href="admin_reports.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);">
                    <i class="fa-solid fa-triangle-exclamation" style="width:20px;"></i> Jelentések 
                    <?php if($activeReports > 0): ?>
                        <span style="background:#da3633; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?= $activeReports ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="mod_logs.php" style="display:block; padding: 12px 15px; border-bottom:1px solid var(--border-color); color:var(--text-muted);"><i class="fa-solid fa-clipboard-list" style="width:20px;"></i> Moderátori Napló</a>
            </div>
        </div>
    </aside>

    <main class="p-body-main">
        
        <?= $msg ?>

        <div style="display:flex; gap:20px; margin-bottom: 20px;">
            <div class="block-container" style="flex:1; margin:0; padding:20px; text-align:center; border-top: 3px solid var(--primary-color);">
                <i class="fa-solid fa-users" style="font-size:30px; color:var(--text-muted); margin-bottom:10px;"></i>
                <div style="font-size:24px; font-weight:bold; color:var(--text-light);"><?= $stats['users'] ?></div>
                <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase;">Regisztrált tag</div>
            </div>
            <div class="block-container" style="flex:1; margin:0; padding:20px; text-align:center; border-top: 3px solid #f39c12;">
                <i class="fa-solid fa-file-lines" style="font-size:30px; color:var(--text-muted); margin-bottom:10px;"></i>
                <div style="font-size:24px; font-weight:bold; color:var(--text-light);"><?= $stats['threads'] ?></div>
                <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase;">Összes téma</div>
            </div>
            <div class="block-container" style="flex:1; margin:0; padding:20px; text-align:center; border-top: 3px solid #27ae60;">
                <i class="fa-solid fa-comments" style="font-size:30px; color:var(--text-muted); margin-bottom:10px;"></i>
                <div style="font-size:24px; font-weight:bold; color:var(--text-light);"><?= $stats['posts'] ?></div>
                <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase;">Összes hozzászólás</div>
            </div>
        </div>

        <div class="block-container">
            <h2 class="block-header"><i class="fa-solid fa-folder-plus"></i> Új Fórum Kategória Létrehozása</h2>
            <div class="block-body" style="padding: 25px;">
                
                <form method="POST">
                    <input type="hidden" name="action" value="create_category">
                    
                    <div style="display:flex; gap:20px;">
                        <div style="flex:1;">
                            <label class="admin-label">Kategória Neve</label>
                            <input type="text" name="title" required class="admin-input" placeholder="pl. Hivatalos Közlemények">
                        </div>
                        <div style="width:150px;">
                            <label class="admin-label">Sorrend (Szám)</label>
                            <input type="number" name="display_order" required class="admin-input" value="10">
                        </div>
                    </div>

                    <label class="admin-label">Leírás (A kategória alatt kisbetűvel jelenik meg)</label>
                    <input type="text" name="description" class="admin-input" placeholder="Ide írhatsz egy rövid ismertetőt...">

                    <label class="admin-label">Szülő Kategória (Hova kerüljön?)</label>
                    <select name="parent_id" class="admin-input">
                        <option value="0">--- Főcsoport (Felső, nagy kategória sáv) ---</option>
                        <?php foreach($parents as $p): ?>
                            <option value="<?= $p['id'] ?>">- <?= htmlspecialchars($p['title']) ?> (Al-kategória ide)</option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: -10px; margin-bottom: 20px;">
                        Ha a <strong>Főcsoportot</strong> választod, az csak egy fejléc (pl. "Illegális Frakciók"). Ahhoz, hogy írni lehessen bele, létre kell hoznod egy al-kategóriát (pl. "Utcai bandák") és szülőként kiválasztani a főcsoportot.
                    </div>

                    <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 15px;">
                        <button type="submit" class="button" style="background:#2ea043;"><i class="fa-solid fa-plus"></i> Létrehozás</button>
                    </div>
                </form>

            </div>
        </div>

    </main>
</div>

</body>
</html>
