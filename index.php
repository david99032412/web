<?php
// /var/www/web/index.php - PRÉMIUM BOOTSTRAP 5 LANDING PAGE
require_once 'forum/config.php';
require_once 'forum/functions.php';

$serverIp = "94.156.37.91";
$serverPort = 22003;

// --- 1. MTA SZERVER STÁTUSZ LEKÉRDEZŐ FÜGGVÉNY (ASE PROTOKOLL) ---
if (!function_exists('readMtaString')) {
    function readMtaString(&$data) {
        if (!isset($data[0])) return "";
        $len = ord($data[0]) - 1;
        if ($len <= 0) { $data = substr($data, 1); return ""; }
        $str = substr($data, 1, $len);
        $data = substr($data, $len + 1);
        return $str;
    }
}

function getMtaServerStatus($ip, $port) {
    // Garantáljuk, hogy MINDIG egy tömböt adunk vissza!
    $offlineData = array('online' => false, 'players' => 0, 'max_players' => 0, 'name' => 'Offline');

    $queryPort = $port + 123; // Az MTA lekérdező portja mindig a game port + 123
    $fp = @fsockopen("udp://$ip", $queryPort, $errno, $errstr, 1); // 1 mp timeout
    
    if (!$fp) {
        return $offlineData; // Ha nem tud kapcsolódni, tömböt ad vissza (nem false-t!)
    }
    
    stream_set_timeout($fp, 1);
    fwrite($fp, "s");
    $data = fread($fp, 4096);
    fclose($fp);
    
    // Ha nem válaszol, vagy hibás a válasz
    if (empty($data) || strncmp($data, 'EYE1', 4) !== 0) {
        return $offlineData; // Itt is kötelezően tömböt adunk vissza
    }
    
    $data = substr($data, 4); // "EYE1" levágása
    
    $game = readMtaString($data);
    $port_str = readMtaString($data);
    $name = readMtaString($data);
    $gamemode = readMtaString($data);
    $map = readMtaString($data);
    $version = readMtaString($data);
    $passworded = readMtaString($data);
    $players = readMtaString($data);
    $maxplayers = readMtaString($data);
    
    return array(
        'online' => true,
        'players' => (int)$players,
        'max_players' => (int)$maxplayers,
        'name' => $name
    );
}

// --- HA A JS (AJAX) KÉRI LE A FRISSÍTÉST A HÁTTÉRBEN ---
if (isset($_GET['ajax_status'])) {
    header('Content-Type: application/json');
    echo json_encode(getMtaServerStatus($serverIp, $serverPort));
    exit;
}

// Oldal első betöltésekor lekérjük az adatokat
$serverStatus = getMtaServerStatus($serverIp, $serverPort);
$onlinePlayers = $serverStatus['online'] ? $serverStatus['players'] : 0;
$maxPlayers = $serverStatus['online'] ? $serverStatus['max_players'] : 300;

// --- 2. LEGFRISSEBB HÍREK A FÓRUMBÓL ---
try {
    $stmt = $pdo->query("
        SELECT t.id, t.title, t.created_at, a.username, 
               (SELECT content FROM forum_posts WHERE thread_id = t.id ORDER BY created_at ASC LIMIT 1) as content
        FROM forum_threads t
        JOIN accounts a ON t.author_id = a.accountId
        ORDER BY t.created_at DESC LIMIT 3
    ");
    $latestNews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $latestNews = [];
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Kezdőlap – NorthSideRP</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />

  <style>
    body { background-color: #0d1117 !important; overflow-x: hidden; }
    
    /* Navigáció */
    .navbar { background: rgba(13, 17, 23, 0.95) !important; backdrop-filter: blur(10px); }
    .navbar-brand { font-weight: 900; letter-spacing: 1px; text-transform: uppercase; }
    .navbar-brand span { color: #0d6efd; }
    
    /* Hero szekció VIDEÓS háttérrel */
    .hero-wrapper {
        position: relative;
        min-height: 80vh;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .hero-video {
        position: absolute;
        top: 50%;
        left: 50%;
        min-width: 100%;
        min-height: 100%;
        width: auto;
        height: auto;
        transform: translateX(-50%) translateY(-50%);
        z-index: 0;
        object-fit: cover;
        opacity: 0.3;
    }
    .hero-content {
        position: relative;
        z-index: 1;
        width: 100%;
    }

    /* Élő Szerver Státusz */
    .status-badge {
        display: inline-flex; align-items: center; gap: 10px; background: rgba(0,0,0,0.6);
        padding: 8px 20px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.1);
        margin-bottom: 20px; font-weight: 600; font-size: 0.95rem; transition: 0.3s;
    }
    .pulse-dot { width: 12px; height: 12px; background: #198754; border-radius: 50%; box-shadow: 0 0 12px #198754; animation: pulse 2s infinite; transition: 0.3s;}
    .pulse-dot.offline { background: #dc3545; box-shadow: 0 0 12px #dc3545; animation: none; }
    @keyframes pulse { 0% { transform: scale(0.95); opacity: 0.7; } 50% { transform: scale(1.3); opacity: 1; } 100% { transform: scale(0.95); opacity: 0.7; } }

    /* Funkció dobozok */
    .feature-box { transition: 0.3s; background: #161b22 !important; border-color: #30363d !important; }
    .feature-box:hover { transform: translateY(-10px); border-color: #0d6efd !important; box-shadow: 0 10px 20px rgba(13, 110, 253, 0.1); }
    
    /* Hírek kártyák */
    .news-card { transition: 0.3s; background: #161b22; border: 1px solid #30363d; border-radius: 8px; height: 100%; }
    .news-card:hover { transform: translateY(-5px); border-color: #0d6efd; }

    /* Lebegő Discord Gomb */
    .discord-float {
        position: fixed; bottom: 30px; right: 30px; background: #5865F2; color: white;
        width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center;
        justify-content: center; font-size: 30px; box-shadow: 0 10px 20px rgba(0,0,0,0.5);
        z-index: 1000; transition: 0.3s; text-decoration: none;
    }
    .discord-float:hover { transform: scale(1.1) rotate(-10deg); color: white; }
  </style>
</head>
<body class="text-white">

  <nav class="navbar navbar-expand-lg navbar-dark border-bottom border-secondary py-3 fixed-top">
    <div class="container">
      <a class="navbar-brand" href="index.php">NorthSide <span>RP</span></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto fw-semibold gap-2">
          <li class="nav-item"><a class="nav-link active" href="index.php">Kezdőlap</a></li>
          <li class="nav-item"><a class="nav-link" href="forum/index.php">Fórum</a></li>
          <li class="nav-item"><a class="nav-link" href="ucp/login.php">UCP</a></li>
          <li class="nav-item"><a class="nav-link" href="downloads.php">Letöltések</a></li>
          <li class="nav-item"><a class="nav-link" href="contact.php">Kapcsolat</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="hero-wrapper">
    <video autoplay loop muted playsinline class="hero-video">
        <source src="https://www.w3schools.com/html/mov_bbb.mp4" type="video/mp4">
    </video>
    
    <div class="hero-content text-center">
      <div class="container" data-aos="zoom-in" data-aos-duration="1000">
        
        <div class="status-badge" id="server-status-container" style="background: rgba(13, 17, 23, 0.8); border-color: <?= $serverStatus['online'] ? 'rgba(63, 185, 80, 0.5)' : 'rgba(239, 68, 68, 0.5)' ?>; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
            <div class="pulse-dot <?= $serverStatus['online'] ? '' : 'offline' ?>" id="server-dot" style="<?= $serverStatus['online'] ? '' : 'background: #ff4d4d; box-shadow: 0 0 10px #ff4d4d;' ?>"></div>
            <span id="server-text" style="color: <?= $serverStatus['online'] ? '#fff' : '#ff6b6b' ?>; font-weight: 800; letter-spacing: 0.5px;">
                <?= $serverStatus['online'] ? 'Szerver Online' : 'Szerver Offline' ?>
            </span>
            <span class="text-primary" style="margin: 0 10px; font-weight: 900;">|</span> 
            <span class="text-primary" style="font-weight: 900; font-size: 1.15em;" id="online-players"><?= $onlinePlayers ?></span> 
            <span class="text-primary" style="font-weight: 700;"> / <span id="max-players"><?= $maxPlayers ?></span> Játékos</span>
        </div>

        <h1 class="display-3 fw-bold mb-3">Üdvözlünk a <span class="text-primary">NorthSideRP</span> szerverén!</h1>
        <p class="lead mb-5 text-light" style="max-width: 700px; margin: 0 auto;">
          Csatlakozz a legújabb hazai MTA közösséghez! Regisztrálj, építsd a karaktered, alapíts frakciót, és légy része egy folyamatosan fejlődő történetnek!
        </p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
          <a href="forum/index.php" class="btn btn-primary btn-lg px-4 py-3 fw-bold shadow-lg">
              <i class="fa-solid fa-comments me-2"></i> Tovább a Fórumra
          </a>
          <a href="ucp/login.php" class="btn btn-outline-light btn-lg px-4 py-3 fw-bold">
              <i class="fa-solid fa-id-card me-2"></i> UCP Belépés
          </a>
        </div>
      </div>
    </div>
  </div>

<div class="container py-5 mt-4">
    <div class="row g-4 text-center">
      
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
        <div class="feature-box p-4 rounded h-100">
          <i class="fa-solid fa-users text-primary fs-1 mb-3"></i>
          <h3 class="h4 fw-bold">Közösség</h3>
          <p class="mb-0" style="color: #0d6efd;">Barátságos és segítőkész admincsapat vár, ahol a jó Roleplay élmény a legfontosabb szempont.</p>
        </div>
      </div>
      
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
        <div class="feature-box p-4 rounded h-100">
          <i class="fa-solid fa-car text-primary fs-1 mb-3"></i>
          <h3 class="h4 fw-bold">Fejlődés</h3>
          <p class="mb-0" style="color: #0d6efd;">Vásárolj járműveket, ingatlanokat, vagy alapíts saját frakciót és urald a város gazdaságát!</p>
        </div>
      </div>
      
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
        <div class="feature-box p-4 rounded h-100">
          <i class="fa-solid fa-server text-primary fs-1 mb-3"></i>
          <h3 class="h4 fw-bold">Stabil Háttér</h3>
          <p class="mb-0" style="color: #0d6efd;">Folyamatos szerverfejlesztések, optimalizált játékszerver és hibamentes UCP / Fórum rendszerek.</p>
        </div>
      </div>
      
    </div>
  </div>

  <div class="container py-5 border-top border-secondary">
    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-right">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-newspaper text-primary me-2"></i> Legfrissebb Hírek</h2>
        <a href="forum/index.php" class="btn btn-outline-primary btn-sm">Összes hír <i class="fa-solid fa-arrow-right ms-1"></i></a>
    </div>

    <div class="row g-4">
      <?php if(empty($latestNews)): ?>
          <div class="col-12 text-center py-4" style="color: #0d6efd;">Még nincsenek közzétett hírek a fórumban.</div>
      <?php else: ?>
          <?php $delay = 100; foreach($latestNews as $news): ?>
          <div class="col-md-4" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
            <div class="news-card p-4 d-flex flex-column">
              <div class="text-primary fw-bold mb-2" style="font-size: 0.85rem;">
                <i class="fa-regular fa-calendar"></i> <?= date("Y. m. d.", strtotime($news['created_at'])) ?>
              </div>
              <h5 class="fw-bold text-light mb-3"><?= htmlspecialchars($news['title']) ?></h5>
              <p class="text-muted small flex-grow-1">
                  <?= htmlspecialchars(mb_substr(strip_tags(preg_replace('/\[.*?\]/', '', $news['content'])), 0, 100)) ?>...
              </p>
              <div class="mt-3 border-top border-secondary pt-3 d-flex justify-content-between align-items-center">
                  <span class="text-muted small"><i class="fa-solid fa-user me-1"></i> <?= htmlspecialchars($news['username']) ?></span>
                  <a href="forum/thread.php?id=<?= $news['id'] ?>" class="text-primary text-decoration-none fw-semibold small">Elolvasom <i class="fa-solid fa-angle-right ms-1"></i></a>
              </div>
            </div>
          </div>
          <?php $delay += 100; endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <a href="https://discord.gg/SAJAT_DISCORD_LINK" target="_blank" class="discord-float" title="Csatlakozz a Discordunkhoz!">
      <i class="fa-brands fa-discord"></i>
  </a>

  <footer class="text-center py-4 border-top border-secondary text-muted mt-3" style="background: #07090c;">
    <div class="mb-2">
        <a href="#" target="_blank" class="text-muted me-3 fs-4 hover-primary transition"><i class="fa-brands fa-discord"></i></a>
        <a href="#" class="text-muted fs-4 hover-primary transition"><i class="fa-brands fa-facebook"></i></a>
    </div>
    &copy; <?= date('Y') ?> NorthSideRP. Minden jog fenntartva.
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
  <script>
    AOS.init({ duration: 800, once: true });

    function fetchServerStatus() {
        fetch('index.php?ajax_status=1')
            .then(response => response.json())
            .then(data => {
                const dot = document.getElementById('server-dot');
                const text = document.getElementById('server-text');
                const players = document.getElementById('online-players');
                const maxPlayers = document.getElementById('max-players');
                const box = document.getElementById('server-status-container');
                
                if (data.online) {
                    dot.className = 'pulse-dot';
                    dot.style.background = '#198754';
                    dot.style.boxShadow = '0 0 12px #198754';
                    text.innerText = 'Szerver Online';
                    text.style.color = '#ffffff'; 
                    players.innerText = data.players;
                    maxPlayers.innerText = data.max_players;
                    box.style.borderColor = 'rgba(63, 185, 80, 0.5)';
                } else {
                    dot.className = 'pulse-dot offline';
                    dot.style.background = '#ff4d4d'; 
                    dot.style.boxShadow = '0 0 10px #ff4d4d';
                    text.innerText = 'Szerver Offline';
                    text.style.color = '#ff6b6b'; 
                    players.innerText = '0';
                    maxPlayers.innerText = '0';
                    box.style.borderColor = 'rgba(239, 68, 68, 0.5)';
                }
            })
            .catch(error => console.error('Hiba a státusz lekérésekor:', error));
    }

    setInterval(fetchServerStatus, 10000);
  </script>
</body>
</html>