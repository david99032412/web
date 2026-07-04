<?php
// ucp/map.php - ÉLŐ SZERVER TÉRKÉP (MINDENKIVEL ÉS RÉSZLETES SZÁMLÁLÓKKAL)
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$stmtAcc = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
$stmtAcc->execute([$_SESSION['user_username']]);
$account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

if (!$account) die("Hiba a fiók betöltésekor!");

$isAdmin = ((int)$account['adminLevel'] >= 7);
$accountId = $account['accountId'];

// Lekérjük a saját karaktereid ID-jét
$myChars = [];
try {
    $stmtMy = $pdo->prepare("SELECT characterId FROM characters WHERE accountId = ?");
    $stmtMy->execute([$accountId]);
    $myChars = $stmtMy->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Élő Térkép - NorthSide UCP</title>
    <script src="https://kit.fontawesome.com/122db0fdde.js" crossorigin="anonymous"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: rgb(26, 27, 31); color: #d1d5db; font-family: 'Inter', sans-serif; }
        
        .navbar { background: rgba(17, 18, 20, 0.95); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; backdrop-filter: blur(10px); }
        .navbar-brand { font-size: 20px; font-weight: 900; color: #fff; text-transform: uppercase; }
        .navbar-brand span { color: #1f6feb; }
        .nav-links a { color: #9ca3af; text-decoration: none; margin-left: 20px; font-size: 14px; font-weight: 500; transition: 0.2s; }
        .nav-links a:hover, .nav-links a.active { color: #fff; }

        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        
        .welcome-box { background: linear-gradient(135deg, rgba(31,111,235,0.15), transparent); border: 1px solid rgba(31,111,235,0.2); border-radius: 12px; padding: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .welcome-box h1 { color: #fff; font-size: 28px; font-weight: 800; margin: 0; }
        
        /* Dinamikus Jelmagyarázat Számlálókkal */
        .map-legend { display: flex; gap: 15px; background: rgba(0,0,0,0.2); padding: 15px 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); font-size: 12px; font-weight: bold; margin-bottom: 15px; justify-content: center; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; gap: 8px; color: #d1d5db; background: rgba(255,255,255,0.02); padding: 5px 10px; border-radius: 6px;}
        .legend-dot { width: 12px; height: 12px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.5); }

        .map-wrapper {
            position: relative;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            background: #1e293b;
            border: 2px solid rgba(31, 111, 235, 0.3);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            aspect-ratio: 1 / 1;
        }

        .map-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('https://raw.githubusercontent.com/multitheftauto/mtasa-resources/master/%5Badmin%5D/admin/client/images/map.png');
            background-size: cover;
            background-position: center;
            opacity: 0.8;
        }

        .blip {
            position: absolute;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 5px rgba(0,0,0,0.8);
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s ease;
        }

        .blip:hover { transform: translate(-50%, -50%) scale(1.5); z-index: 20; }

        /* TÍPUS SZÍNEK ÉS IKONOK BŐVÍTVE */
        .blip.player { background-color: #3b82f6; border: 2px solid #fff; z-index: 12;} /* Kék */
        .blip.npc { background-color: #ef4444; border: 2px solid #fff; width: 10px; height: 10px;} /* Piros */
        .blip.vehicle { background-color: #f59e0b; border: 2px solid #fff; z-index: 11;} /* Narancs */
        .blip.house { background-color: #ec4899; border: 2px solid #fff; z-index: 7;} /* Rózsaszín */
        .blip.business { background-color: #a855f7; border: 2px solid #fff; z-index: 7;} /* Lila */
        .blip.garage { background-color: #64748b; border: 2px solid #fff; z-index: 7;} /* Szürke */
        .blip.trash { background-color: #10b981; border: 1px solid #fff; width: 8px; height: 8px; border-radius: 2px; z-index: 5;} /* Zöld négyzet */
        .blip.casino { background-color: #8b5cf6; border: 1px solid #fff; z-index: 6;} 
        .blip.safe { background-color: #06b6d4; border: 1px solid #fff; width: 8px; height: 8px; z-index: 8;} /* Cián */
        .blip.generic { background-color: #cbd5e1; border: 1px solid #fff; width: 8px; height: 8px; z-index: 4;} 
        
        .blip.me { 
            background-color: #3fb950; 
            border: 2px solid #fff; 
            box-shadow: 0 0 15px #3fb950; 
            width: 18px; 
            height: 18px;
            animation: pulse 1.5s infinite;
            z-index: 15;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(63, 185, 80, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(63, 185, 80, 0); }
            100% { box-shadow: 0 0 0 0 rgba(63, 185, 80, 0); }
        }

        .blip-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.85);
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: 0.2s;
            margin-bottom: 8px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .blip:hover .blip-tooltip { opacity: 1; }
        .loading-text { text-align: center; margin-top: 20px; color: #9ca3af; font-weight: bold; }
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
            <a href="vehicles.php">Járműveim</a>
            <a href="map.php" class="active"><i class="fa-solid fa-map-location-dot"></i> Élő Térkép</a>
            <a href="../forum/">Fórum</a>
            <?php if($isAdmin): ?>
                <a href="adminpanel.php" style="color: #e3b341;"><i class="fa-solid fa-shield-halved"></i> Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php" style="color: #ef4444;"><i class="fa-solid fa-power-off"></i> Kilépés</a>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-box">
            <h1><i class="fa-solid fa-map-location-dot" style="color: #1f6feb; margin-right:10px;"></i> Élő Szerver Térkép</h1>
            <div style="color: #9ca3af; font-size: 14px;">Látható jelölők összesen: <span id="marker-count" style="color:#fff; font-weight:bold; background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 6px;">0 db</span></div>
        </div>

        <div class="map-legend">
            <div class="legend-item"><div class="legend-dot" style="background: #3fb950; box-shadow: 0 0 5px #3fb950;"></div> Te</div>
            <div class="legend-item"><div class="legend-dot" style="background: #3b82f6; box-shadow: 0 0 5px #3b82f6;"></div> Játékos: <span id="count-player" style="color:#fff;">0</span></div>
            <div class="legend-item"><div class="legend-dot" style="background: #f59e0b; box-shadow: 0 0 5px #f59e0b;"></div> Jármű: <span id="count-vehicle" style="color:#fff;">0</span></div>
            <div class="legend-item"><div class="legend-dot" style="background: #ef4444; box-shadow: 0 0 5px #ef4444;"></div> NPC (Ped): <span id="count-npc" style="color:#fff;">0</span></div>
            <div class="legend-item"><div class="legend-dot" style="background: #ec4899;"></div> Ház: <span id="count-house" style="color:#fff;">0</span></div>
            <div class="legend-item"><div class="legend-dot" style="background: #a855f7;"></div> Üzlet: <span id="count-business" style="color:#fff;">0</span></div>
            <div class="legend-item"><div class="legend-dot" style="background: #64748b;"></div> Garázs: <span id="count-garage" style="color:#fff;">0</span></div>
            <div class="legend-item"><div class="legend-dot" style="background: #10b981; border-radius: 2px;"></div> Kuka: <span id="count-trash" style="color:#fff;">0</span></div>
            <div class="legend-item"><div class="legend-dot" style="background: #8b5cf6;"></div> Blackjack: <span id="count-blackjack" style="color:#fff;">0</span></div>
            <div class="legend-item"><div class="legend-dot" style="background: #06b6d4;"></div> Széf/ATM: <span id="count-safe" style="color:#fff;">0</span></div>
        </div>

        <div class="map-wrapper">
            <div class="map-bg"></div>
            <div id="markers-container"></div>
        </div>
        
        <div class="loading-text" id="status-text"><i class="fa-solid fa-satellite-dish fa-spin"></i> Pozíciók lekérése...</div>
    </div>

    <script>
        const markersContainer = document.getElementById('markers-container');
        const markerCountEl = document.getElementById('marker-count');
        const statusText = document.getElementById('status-text');
        
        const myCharacterIds = <?= json_encode($myChars) ?>.map(id => parseInt(id, 10));

        const MAP_MIN = -3000;
        const MAP_MAX = 3000;
        const MAP_SIZE = MAP_MAX - MAP_MIN; 

        async function fetchMapData() {
            try {
                const response = await fetch('api_map.php');
                if (!response.ok) throw new Error('Hálózati hiba');
                const data = await response.json();
                
                markersContainer.innerHTML = '';
                
                let totalCount = 0;
                let counts = { player: 0, vehicle: 0, npc: 0, house: 0, business: 0, garage: 0, trash: 0, blackjack: 0, safe: 0 };

                data.forEach(item => {
                    let posX = parseFloat(item.posX) || 0;
                    let posY = parseFloat(item.posY) || 0;

                    let leftPercent = ((posX - MAP_MIN) / MAP_SIZE) * 100;
                    let topPercent = ((-posY - MAP_MIN) / MAP_SIZE) * 100;

                    if(leftPercent >= 0 && leftPercent <= 100 && topPercent >= 0 && topPercent <= 100) {
                        const dot = document.createElement('div');
                        
                        let charId = parseInt(item.characterId, 10) || 0;
                        let type = item.type || 'generic'; 
                        let isMe = myCharacterIds.includes(charId);

                        // KATEGORIZÁLÁS ÉS SZÁMOLÁS KIBŐVÍTVE (Az api_map.php-ból érkező típusok alapján!)
                        if (isMe && type === 'player') {
                            dot.className = 'blip me';
                        } else if (type === 'player' || type === 'characters') {
                            dot.className = 'blip player';
                            counts.player++;
                        } else if (type === 'npc' || type === 'peds' || type === 'shops') {
                            dot.className = 'blip npc';
                            counts.npc++;
                        } else if (type === 'vehicle' || type === 'vehicles') {
                            dot.className = 'blip vehicle';
                            counts.vehicle++;
                        } else if (type === 'house') {
                            dot.className = 'blip house';
                            counts.house++;
                        } else if (type === 'business') {
                            dot.className = 'blip business';
                            counts.business++;
                        } else if (type === 'garage') {
                            dot.className = 'blip garage';
                            counts.garage++;
                        } else if (type === 'trash' || type === 'trashes') {
                            dot.className = 'blip trash';
                            counts.trash++;
                        } else if (type === 'blackjack') {
                            dot.className = 'blip casino';
                            counts.blackjack++;
                        } else if (type === 'safe' || type === 'safes' || type === 'atms') {
                            dot.className = 'blip safe';
                            counts.safe++;
                        } else {
                            dot.className = 'blip generic';
                        }

                        dot.style.left = leftPercent + '%';
                        dot.style.top = topPercent + '%';

                        const nameRaw = item.name || 'Ismeretlen';
                        const nameFormatted = nameRaw.toString().replace(/_/g, ' ');
                        
                        // Járműveknél és Ingatlanoknál kiírjuk a típust a toolitpbe, hogy egyértelmű legyen!
                        let typeDisplay = type.toUpperCase();
                        dot.innerHTML = `<div class="blip-tooltip">${nameFormatted} <br><span style="color:#9ca3af; font-weight:normal; font-size:9px;">[${typeDisplay} | ID: #${charId}]</span></div>`;

                        markersContainer.appendChild(dot);
                        totalCount++;
                    }
                });

                // Számlálók frissítése a HTML-ben
                markerCountEl.innerText = totalCount + ' db';
                for (let key in counts) {
                    let el = document.getElementById('count-' + key);
                    if (el) el.innerText = counts[key];
                }

                statusText.innerHTML = '<span style="color:#3fb950;"><i class="fa-solid fa-check"></i> Adatbázis szinkronizálva (1 másodpercenként)</span>';
                
            } catch (error) {
                console.error("Map lekérési hiba:", error);
                statusText.innerHTML = '<span style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Szerver kapcsolat megszakadt!</span>';
            }
        }

        fetchMapData();
        setInterval(fetchMapData, 1000);
    </script>
</body>
</html>
