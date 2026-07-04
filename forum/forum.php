<h1 style="background:red; color:white; font-size:50px; z-index:9999; position:relative;">EZ AZ ÚJ FÁJL!</h1>
<?php
// Fórum Főoldal - JAVÍTOTT DESIGN
if (file_exists('config.php')) { require 'config.php'; require 'functions.php'; } 
else { require '../config.php'; require '../functions.php'; }

$loggedIn = isLoggedIn();
$username = $_SESSION['user_username'] ?? 'Vendég';

// Adatok betöltése (Hibakezeléssel)
try {
    $stmt = $pdo->query("SELECT * FROM forum_categories ORDER BY display_order ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalThreads = $pdo->query("SELECT COUNT(*) FROM forum_threads")->fetchColumn();
    $totalPosts = $pdo->query("SELECT COUNT(*) FROM forum_posts")->fetchColumn();
} catch (Exception $e) {
    $categories = []; $totalThreads = 0; $totalPosts = 0;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Fórum - NorthSideRP</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- STABILIZÁLT CSS (Nem csúszik el) --- */
        * { box-sizing: border-box; } /* Ez akadályozza meg a szétcsúszást */
        body { background-color: #0d1117; color: #c9d1d9; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; }

        /* Fő Konténer - Flexbox */
        .forum-wrapper {
            max-width: 1250px;
            margin: 30px auto;
            padding: 0 20px;
            display: flex;       /* EGYMÁS MELLÉ RAKJA A KÉT HASÁBOT */
            gap: 25px;           /* Távolság köztük */
            align-items: flex-start;
        }

        /* Bal oldal (Tartalom) */
        main {
            flex: 1;             /* Kitölti a rendelkezésre álló helyet */
            min-width: 0;        /* FONTOS: Megakadályozza a túlfolyást */
        }

        /* Jobb oldal (Oldalsáv) */
        aside {
            width: 300px;        /* Fix szélesség */
            flex-shrink: 0;      /* Nem engedjük összenyomni */
        }

        /* Kategória Doboz */
        .category-container {
            background: #161b22; border: 1px solid #30363d; border-radius: 6px;
            margin-bottom: 25px; overflow: hidden;
        }
        .category-name {
            background: #21262d; padding: 12px 20px; color: #58a6ff; font-weight: 700;
            text-transform: uppercase; border-bottom: 1px solid #30363d; font-size: 14px;
        }

        /* Sorok */
        .forum-node {
            display: flex; align-items: center; padding: 15px;
            border-bottom: 1px solid #30363d; gap: 15px; transition: 0.2s;
        }
        .forum-node:last-child { border-bottom: none; }
        .forum-node:hover { background: #1c2128; }

        .node-icon { font-size: 24px; color: #8b949e; min-width: 50px; text-align: center; }
        .node-icon.new { color: #58a6ff; }
        
        .node-info { flex: 1; } /* Kitölti a helyet */
        .node-info a { color: #e6edf3; text-decoration: none; font-size: 16px; font-weight: 600; display: block; }
        .node-info .desc { font-size: 13px; color: #8b949e; margin-top: 2px; }
        
        .node-stats { text-align: right; font-size: 12px; color: #8b949e; min-width: 100px; }

        /* Widgetek */
        .sidebar-widget {
            background: #161b22; border: 1px solid #30363d; border-radius: 6px;
            padding: 15px; margin-bottom: 20px;
        }
        .sidebar-widget h3 {
            font-size: 14px; margin: 0 0 15px 0; border-bottom: 1px solid #30363d;
            padding-bottom: 10px; color: #f0f6fc;
        }
        
        .btn-action { display: block; width: 100%; padding: 10px; background: #238636; color: white; text-align: center; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 13px; margin-bottom: 5px; }
        .btn-action:hover { background: #2ea043; }
        .btn-logout { background: #da3633; } .btn-logout:hover { background: #b62324; }

        /* MOBIL NÉZET - Egymás alá rakja */
        @media (max-width: 900px) {
            .forum-wrapper { flex-direction: column; }
            aside { width: 100%; }
            .node-stats { display: none; } /* Mobilon rejtjük a statot, hogy ne legyen zsúfolt */
        }
    </style>
</head>
<body>

<header style="background:#21262d; border-bottom:1px solid #30363d; padding:15px 20px; display:flex; justify-content:space-between; align-items:center;">
    <div style="font-size: 20px; font-weight: bold; color: #58a6ff;">NorthSideRP Fórum</div>
    <nav>
        <a href="../index.php" style="color: white; text-decoration: none; margin-right: 20px; font-size:14px;">Főoldal</a>
        <a href="../ucp/login.php" style="color: white; text-decoration: none; font-size:14px;">UCP</a>
    </nav>
</header>

<div class="forum-wrapper">
    <main>
        <div class="category-container">
            <div class="category-name">Fórum Kategóriák</div>
            <?php if(empty($categories)): ?>
                <div style="padding:20px; text-align:center; color:#8b949e;">Nincsenek kategóriák.</div>
            <?php else: ?>
                <?php foreach($categories as $cat): 
                    try {
                        $cnt = $pdo->prepare("SELECT COUNT(*) FROM forum_threads WHERE category_id=?");
                        $cnt->execute([$cat['id']]); $count = $cnt->fetchColumn();
                    } catch(Exception $e) { $count = 0; }
                ?>
                <div class="forum-node">
                    <div class="node-icon <?= $count>0?'new':'' ?>"><i class="fa-solid fa-comments"></i></div>
                    <div class="node-info">
                        <a href="category.php?id=<?= $cat['id'] ?>"><?= htmlspecialchars($cat['title']) ?></a>
                        <div class="desc"><?= htmlspecialchars($cat['description']) ?></div>
                    </div>
                    <div class="node-stats">
                        <?= $count ?> Téma
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <aside>
        <div class="sidebar-widget">
            <?php if($loggedIn): ?>
                <h3>Profilom</h3>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:15px;">
                    <img src="https://ui-avatars.com/api/?name=<?= $username ?>&background=238636&color=fff" style="width:40px; border-radius:4px;">
                    <div>
                        <div style="font-weight: bold; font-size:14px;"><?= htmlspecialchars($username) ?></div>
                        <div style="font-size: 11px; color: #3fb950;">Online</div>
                    </div>
                </div>
                <a href="../ucp/profile.php" class="btn-action" style="background:#58a6ff;">UCP Megnyitása</a>
                <a href="logout.php" class="btn-action btn-logout">Kijelentkezés</a>
            <?php else: ?>
                <h3>Bejelentkezés</h3>
                <p style="font-size: 13px; color:#8b949e;">Jelentkezz be a hozzászóláshoz!</p>
                <a href="login.php" class="btn-action">Belépés</a>
                <a href="register.php" class="btn-action" style="background:transparent; border:1px solid #30363d;">Regisztráció</a>
            <?php endif; ?>
        </div>

        <div class="sidebar-widget">
            <h3>Statisztika</h3>
            <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px;"><span>Témák:</span> <strong><?= $totalThreads ?></strong></div>
            <div style="display:flex; justify-content:space-between; font-size:13px;"><span>Posztok:</span> <strong><?= $totalPosts ?></strong></div>
        </div>
    </aside>
</div>

</body>
</html>