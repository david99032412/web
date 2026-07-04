<?php
// ucp/premium.php - PRÉMIUM PONT (PP) VÁSÁRLÁS ÉS TÁJÉKOZTATÓ
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$stmtAcc = $pdo->prepare("SELECT accountId, username, premiumPoints, adminLevel FROM accounts WHERE username = ?");
$stmtAcc->execute([$_SESSION['user_username']]);
$account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

if (!$account) die("Hiba a fiók betöltésekor!");
$isAdmin = ((int)$account['adminLevel'] >= 7);
$pp = (int)$account['premiumPoints'];
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Prémium - NorthSide UCP</title>
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
        
        .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
        
        .pp-header { background: linear-gradient(135deg, rgba(227, 179, 65, 0.15), rgba(26, 27, 31, 1)); border: 1px solid rgba(227, 179, 65, 0.3); border-radius: 12px; padding: 40px; text-align: center; margin-bottom: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .pp-header h1 { color: #fff; font-size: 36px; font-weight: 900; margin-bottom: 10px; }
        .pp-header .balance { font-size: 48px; font-weight: 900; color: #e3b341; text-shadow: 0 0 20px rgba(227, 179, 65, 0.4); margin: 20px 0; }
        
        .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-bottom: 40px; }
        .price-card { background: rgb(33, 35, 40); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 30px; text-align: center; transition: 0.3s; position: relative; overflow: hidden; }
        .price-card:hover { transform: translateY(-10px); border-color: #e3b341; box-shadow: 0 15px 30px rgba(0,0,0,0.4); }
        .price-card.popular { border-color: #e3b341; background: linear-gradient(180deg, rgba(227, 179, 65, 0.05) 0%, rgb(33, 35, 40) 100%); }
        .price-card.popular .badge { position: absolute; top: 15px; right: -30px; background: #e3b341; color: #000; font-weight: bold; font-size: 11px; padding: 5px 30px; transform: rotate(45deg); text-transform: uppercase; }
        
        .p-icon { font-size: 48px; margin-bottom: 20px; }
        .p-title { color: #fff; font-size: 24px; font-weight: 900; margin-bottom: 10px; }
        .p-pp { color: #e3b341; font-size: 32px; font-weight: 900; margin-bottom: 5px; }
        .p-price { color: #9ca3af; font-size: 16px; margin-bottom: 20px; }
        
        .p-features { list-style: none; margin-bottom: 30px; text-align: left; }
        .p-features li { padding: 10px 0; border-bottom: 1px dashed rgba(255,255,255,0.05); font-size: 14px; color: #d1d5db; display: flex; align-items: center; gap: 10px; }
        .p-features li i { color: #3fb950; }
        
        .buy-btn { display: inline-block; width: 100%; padding: 15px; background: #0d6efd; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; text-transform: uppercase; transition: 0.2s; }
        .buy-btn:hover { background: #0b5ed7; }
        .buy-btn.gold { background: #e3b341; color: #000; }
        .buy-btn.gold:hover { background: #d29922; }
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
            <a href="premium.php" class="active" style="color:#e3b341;"><i class="fa-solid fa-star"></i> Prémium</a>
            <a href="settings.php"><i class="fa-solid fa-gear"></i> Beállítások</a>
            <?php if($isAdmin): ?><a href="adminpanel.php" style="color: #ef4444;">Admin Panel</a><?php endif; ?>
            <a href="logout.php" style="color: #ef4444;">Kilépés</a>
        </div>
    </nav>

    <div class="container">
        <div class="pp-header">
            <h1>Jelenlegi Prémium Egyenleged</h1>
            <div class="balance"><?= number_format($pp, 0, '', ' ') ?> PP</div>
            <p style="color:#9ca3af; font-size:16px; max-width: 600px; margin: 0 auto;">A Prémium Pontokból (PP) a szerveren játékon belül vásárolhatsz egyedi járműveket, peteket, skineket és kényelmi funkciókat. Köszönjük, hogy támogatod a szerver fenntartását!</p>
        </div>

        <div class="pricing-grid">
            <div class="price-card">
                <div class="p-icon" style="color: #cd7f32;"><i class="fa-solid fa-coins"></i></div>
                <div class="p-title">Bronz Csomag</div>
                <div class="p-pp">1 500 PP</div>
                <div class="p-price">2 000 Ft</div>
                <ul class="p-features">
                    <li><i class="fa-solid fa-check"></i> Azonnali jóváírás</li>
                    <li><i class="fa-solid fa-check"></i> Elég egy kisebb autóra</li>
                    <li><i class="fa-solid fa-check"></i> Támogatói rang Discordon</li>
                </ul>
                <a href="support.php" class="buy-btn">Vásárlás Ticketben</a>
            </div>

            <div class="price-card popular">
                <div class="badge">Legjobb</div>
                <div class="p-icon" style="color: #e3b341;"><i class="fa-solid fa-sack-dollar"></i></div>
                <div class="p-title">Arany Csomag</div>
                <div class="p-pp">5 000 PP</div>
                <div class="p-price">5 000 Ft</div>
                <ul class="p-features">
                    <li><i class="fa-solid fa-check"></i> +500 PP Bónusz Ajándék</li>
                    <li><i class="fa-solid fa-check"></i> Prémium tuningokhoz ideális</li>
                    <li><i class="fa-solid fa-check"></i> Kiemelt Támogatói rang</li>
                </ul>
                <a href="support.php" class="buy-btn gold">Vásárlás Ticketben</a>
            </div>

            <div class="price-card">
                <div class="p-icon" style="color: #3b82f6;"><i class="fa-regular fa-gem"></i></div>
                <div class="p-title">Gyémánt Csomag</div>
                <div class="p-pp">12 000 PP</div>
                <div class="p-price">10 000 Ft</div>
                <ul class="p-features">
                    <li><i class="fa-solid fa-check"></i> Brutális +2000 PP Bónusz</li>
                    <li><i class="fa-solid fa-check"></i> Egyedi limitált járművekhez</li>
                    <li><i class="fa-solid fa-check"></i> VIP Támogatói státusz</li>
                </ul>
                <a href="support.php" class="buy-btn">Vásárlás Ticketben</a>
            </div>
        </div>

        <div style="background: rgb(33, 35, 40); padding: 30px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 20px;">
            <i class="fa-brands fa-discord" style="font-size: 48px; color: #5865F2;"></i>
            <div>
                <h3 style="color:#fff; margin-bottom:5px;">Hogyan tudok vásárolni?</h3>
                <p style="color:#9ca3af; font-size:14px; line-height: 1.5;">Jelenleg a rendszerünk automatikus bankkártyás fizetést nem támogat. Kattints bármelyik gombra a <b>Support (Hibajegy)</b> menü megnyitásához, nyiss egy jegyet "Pénzügy / Vagyon" kategóriában, és írd le, melyik csomagot szeretnéd. Az adminjaink megadják a PayPal vagy bankszámla adatokat, és azonnal jóváírják a PP-t a fiókodon!</p>
            </div>
        </div>
    </div>
</body>
</html>