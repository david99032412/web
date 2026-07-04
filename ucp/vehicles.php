<?php
// ucp/vehicles.php - JÁRMŰVEK OLDAL (FULL ADATOK + BEÉPÍTETT AUTÓ NEVEK + LUA TUNING)
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$stmtAcc = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
$stmtAcc->execute([$_SESSION['user_username']]);
$account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

if (!$account) die("Hiba a fiók betöltésekor!");

$isAdmin = ((int)$account['adminLevel'] >= 7);
$accountId = $account['accountId'];

$dbError = "";
$successMsg = ""; // HOZZÁADVA: A sikerüzenet változója

// --- HOZZÁADVA: JÁRMŰ KEZELÉS POST KÉRÉSEK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $vehId = (int)$_POST['vehicle_id'];
    
    // Biztonsági ellenőrzés (Csak a saját autód, vagy ha admin vagy)
    $checkStmt = $pdo->prepare("SELECT v.*, c.accountId FROM vehicles v JOIN characters c ON v.characterId = c.characterId WHERE v.dbID = ?");
    $checkStmt->execute([$vehId]);
    $vData = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($vData && ($vData['accountId'] == $accountId || $isAdmin)) {
        if ($_POST['action'] === 'reset_pos') {
            try {
                $pdo->prepare("UPDATE vehicles SET posX = 0, posY = 0, posZ = 0 WHERE dbID = ?")->execute([$vehId]);
                $successMsg = "A jármű (#$vehId) pozíciója sikeresen visszaállítva a központba (0,0,0)!";
            } catch (Exception $e) { $dbError = "Hiba a mentés során: " . $e->getMessage(); }
        }
        if ($_POST['action'] === 'sell_state') {
            try {
                $pdo->prepare("DELETE FROM vehicles WHERE dbID = ?")->execute([$vehId]);
                $pdo->prepare("UPDATE characters SET bankMoney = bankMoney + 50000 WHERE characterId = ?")->execute([$vData['characterId']]);
                $successMsg = "A járművet (#$vehId) sikeresen eladtad az államnak! $50,000 jóváírva a karakter bankszámláján.";
            } catch (Exception $e) { $dbError = "Hiba az eladás során: " . $e->getMessage(); }
        }
    } else {
        $dbError = "Nincs jogosultságod a jármű módosításához!";
    }
}
// ----------------------------------------------

$vehicles = [];

try {
    $stmt = $pdo->prepare("
        SELECT v.*, c.name as ownerName 
        FROM vehicles v 
        JOIN characters c ON v.characterId = c.characterId 
        WHERE c.accountId = ?
        ORDER BY v.dbID DESC
    ");
    $stmt->execute([$accountId]);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $dbError = "SQL Hiba: " . $e->getMessage();
}

// 1. JÁRMŰ NEVEK LEKÉRÉSE (A TELJES MODDOLT LISTA)
function getVehicleName($modelId) {
    $cars = [
        400 => 'BMW X6M', 401 => 'Ford Focus RS Mk2', 402 => 'Pontiac Firebird', 403 => 'Linerunner', 
        404 => 'Mercedes-Benz G-Class W463', 405 => 'Chevrolet Tahoe', 406 => 'Dumper', 
        407 => 'Mercedes-Benz Atego Firetruck', 408 => 'Trashmaster', 409 => 'Tesla Model Y', 
        410 => 'Maserati GranTurismo', 411 => 'Ferrari LaFerrari', 412 => 'Koenigsegg Agera RS', 
        413 => 'Ford Econoline', 414 => 'Mule', 415 => 'Mercedes-Benz AMG One', 
        416 => 'Mercedes-Benz Sprinter Ambulance', 417 => 'Leviathan', 418 => 'Volkswagen Caravelle 2018', 
        419 => 'Toyota Supra A90', 420 => 'BMW 3 series G20', 421 => 'Cadillac Escalade', 422 => 'Bobcat', 
        423 => 'McLaren W1', 424 => 'Ariel Nomad', 425 => 'Hunter', 426 => 'Dodge Demon SRT', 
        427 => 'Enforcer', 428 => 'Securicar', 429 => 'Dodge Viper SRT GTS', 430 => 'Predator', 
        431 => 'Bus', 432 => 'Rhino', 433 => 'Barracks', 434 => "Ford Ratrod '34", 435 => 'Article Trailer', 
        436 => 'Previon', 437 => 'Coach', 438 => 'Nissan Silvia S15', 439 => "Dodge Charger '69 R/T", 
        440 => 'Rumpo', 441 => 'RC Bandit', 442 => 'Romero', 443 => 'Packer', 444 => 'Monster', 
        445 => 'BMW 5 series e60', 446 => 'Lampadati Toro', 447 => 'Seasparrow', 448 => 'Pizzaboy', 
        449 => 'Tram', 450 => 'Article Trailer 2', 451 => 'Lamborghini Huracan', 452 => 'Wellcraft 38 Scarab KV', 
        453 => 'Reefer', 454 => 'Tropic', 455 => 'Flatbed', 456 => 'Yankee', 457 => 'Caddy', 
        458 => 'Aston Martin Vanquish', 459 => 'Top Fun Van', 460 => 'Skimmer', 461 => 'PCJ-600', 
        462 => 'Yamaha Aerox', 463 => 'Harley Davidson Knucklehead', 464 => 'RC Baron', 465 => 'RC Raider', 
        466 => 'Alfa Romeo Giulia', 467 => 'BRABUS ROCKET 850 CLS 63', 468 => 'Sanchez', 469 => 'Sparrow', 
        470 => 'Audi Q7 Mk1', 471 => 'Quadbike', 472 => 'Coastg.', 473 => 'Dinghy', 474 => 'Porsche 911 GT3', 
        475 => 'McLaren Senna GTR', 476 => 'Rustler', 477 => 'Nissan 240SX SE', 478 => 'Walton', 
        479 => 'Mercedes-Benz 190E Evolution II', 480 => 'Porsche 911 Turbo S', 481 => 'BMX', 
        482 => 'Burrito', 483 => 'Barkas B1000-1', 484 => 'Marquis', 485 => 'Baggage', 486 => 'Dozer', 
        487 => 'Maverick', 488 => 'SAN News Maverick', 489 => 'Rancher', 490 => 'Lamborghini Urus', 
        491 => 'Porsche Taycan Turbo S', 492 => 'Toyota Camry V70', 493 => 'Jetmax', 494 => 'Bugatti Chiron', 
        495 => 'Ford F-150 Raptor', 496 => 'Koenigsegg Jesko Absolut', 497 => 'Police Maverick', 
        498 => 'Boxville', 499 => 'Bugatti La Voiture Noire', 500 => 'Jeep Wrangler', 501 => 'RC Goblin', 
        502 => 'Pagani Huayra', 503 => 'BMW M4 G82', 504 => 'Bloodring Banger', 505 => 'Rancher', 
        506 => 'McLaren P1', 507 => 'Mercedes-Benz S-Class W220', 508 => 'Brute Camper', 509 => 'Bike', 
        510 => 'Mountain Bike', 511 => 'Beagle', 512 => 'Cropduster', 513 => 'Stuntplane', 514 => 'Tanker', 
        515 => 'Roadtrain', 516 => 'Mercedes-Benz 300 SEL', 517 => 'BRABUS ROCKET 800 S63', 
        518 => 'Ferrari FXX-K', 519 => 'Shamal', 520 => 'Hydra', 521 => 'Ducati Desmosedici RR', 
        522 => 'Suzuki Hayabusa', 523 => 'HPV1000', 524 => 'Cement Truck', 525 => 'Ford F-550 Towtruck', 
        526 => 'Ferrari 812 Superfast', 527 => 'Ford Mustang GT 2015', 528 => 'Lenco BearCat G3', 
        529 => 'Dodge Charger Hellcat', 530 => 'Forklift', 531 => 'Tractor', 532 => 'Combine Harvester', 
        533 => 'Chevrolet Corvette C8', 534 => 'Lincoln Town Coupe', 535 => "Ford Pick Up Custom '51", 
        536 => "Ford Thunderbird '64", 537 => 'Freight', 538 => 'Brownstreak', 539 => 'Vortex', 
        540 => 'Subaru Impreza WRX STI', 541 => 'Chevrolet Camaro ZL1', 542 => "Honda CR-X SiR '90", 
        543 => 'Sadler', 544 => 'Fire Truck', 545 => 'Porsche 911 Mansory Evo 900', 546 => 'BMW M5 F90', 
        547 => 'Rolls Royce Wraith', 548 => 'Cargobob', 549 => 'BMW M8 Competition', 
        550 => 'Mercedes-Benz E-Class W210', 551 => 'Chevrolet C1500 454 SS', 552 => 'Utility Van', 
        553 => 'Nevada', 554 => 'Chevrolet Silverado 1500 LT', 555 => 'Ferrari 250 GTO', 
        556 => 'Monster', 557 => 'Monster', 558 => 'BMW 3 series e46', 559 => 'Toyota Supra Mk4', 
        560 => 'Mitsubishi Lancer EVO X', 561 => 'Mercedes-Benz CLS 63', 562 => 'Nissan Skyline R34 GT-R', 
        563 => 'Raindance', 564 => 'RC Tiger', 565 => 'Honda Civic ek9', 566 => 'Nissan 370Z', 
        567 => 'BRABUS ROCKET 900 GT 63 S', 568 => 'Bandito', 569 => 'Freight Flat', 570 => 'Streak Trailer', 
        571 => 'Kart', 572 => 'Mower', 573 => 'Dune', 574 => 'Sweeper', 575 => 'Broadway', 
        576 => 'Chevrolet Bel Air', 577 => 'AT-400', 578 => 'DFT-30', 579 => 'Jeep Grand Cherokee SRT8', 
        580 => 'Lamborghini Veneno', 581 => 'BF-400', 582 => 'Mercedes-Benz Sprinter', 583 => 'Tug', 
        584 => 'Petrol Trailer', 585 => 'Chevrolet Corvette C7', 586 => 'Harley Davidson Fat Boy', 
        587 => 'Nissan GT-R', 588 => 'BMW i8', 589 => 'Wiesmann GT', 590 => 'Freight Box', 
        591 => 'Article Trailer 3', 592 => 'Andromada', 593 => 'Dodo', 594 => 'RC Cam', 595 => 'Launch', 
        596 => 'Audi RS6 Avant C7', 597 => 'Skoda Octavia VRS Estate', 598 => 'BMW 5 series F11', 
        599 => 'Audi TT RS Coupe Mk3', 600 => 'Picador', 601 => 'S.W.A.T.', 602 => 'Mitsubishi Galant VR-4', 
        603 => 'Dodge Coronet 440', 604 => 'Mercedes-Benz GT 63 S', 605 => 'Ford F-150', 
        606 => 'Baggage Trailer', 607 => 'Baggage Trailer', 608 => 'Tug Stairs', 609 => 'Boxville', 
        610 => 'Farm Trailer', 611 => 'Utility Trailer'
    ];
    return $cars[$modelId] ?? "Ismeretlen Jármű ($modelId)";
}

// 2. TUNING SZINT DEKÓDOLÓ
function getTuningLevel($level, $part = 'general') {
    $level = (int)$level;
    if ($level == 0) return '<span style="color: #6e7681; font-weight: 500;">Gyári</span>';
    if ($part == 'turbo' && ($level == 4 || $level == 5)) return '<span style="color: #bf4b8a; font-weight: 800;">Egyedi Venom</span>';
    if ($part == 'ecu' && $level == 4) return '<span style="color: #bf4b8a; font-weight: 800;">Állítható Venom</span>';

    switch ($level) {
        case 1: return '<span style="color: #1f6feb; font-weight: 600;">Profi</span>';
        case 2: return '<span style="color: #e3b341; font-weight: 600;">Verseny</span>';
        case 3: return '<span style="color: #f85149; font-weight: 800;">Venom</span>';
        default: return '<span style="color: #6e7681; font-weight: 500;">Gyári</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saját Járműveim - NorthSide UCP</title>
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

        .container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        
        .welcome-box { background: linear-gradient(135deg, rgba(31,111,235,0.15), transparent); border: 1px solid rgba(31,111,235,0.2); border-radius: 12px; padding: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .welcome-box h1 { color: #fff; font-size: 28px; font-weight: 800; margin: 0; }
        
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 25px; }
        @media (max-width: 768px) { .grid-3 { grid-template-columns: 1fr; } }
        
        .card { background: rgb(33, 35, 40); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; transition: 0.2s; }
        .card:hover { border-color: rgba(31,111,235,0.5); transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        
        .card-header-box { padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.1); }
        .card-body-box { padding: 25px; display: flex; flex-direction: column; gap: 20px; }
        
        .plate { background: #eab308; color: #000; font-weight: 900; padding: 4px 10px; border-radius: 4px; border: 2px solid #000; font-size: 13px; letter-spacing: 1px; }
        
        .info-module { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); border-radius: 8px; padding: 15px; }
        .module-title { font-size: 11px; text-transform: uppercase; color: #1f6feb; font-weight: 800; letter-spacing: 1px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        
        .data-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed rgba(255,255,255,0.03); font-size: 13px; align-items: center; }
        .data-row:last-child { border-bottom: none; padding-bottom: 0; }
        .data-label { color: #9ca3af; }
        .data-value { color: #f3f4f6; font-weight: 600; text-align: right; }
        
        .hp-bar-bg { background-color: rgba(255,255,255,0.05); height: 6px; border-radius: 3px; overflow: hidden; margin-top: 5px; }
        .hp-bar-fill { height: 100%; border-radius: 3px; }
        
        .color-box { display: inline-block; width: 16px; height: 16px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.2); vertical-align: middle; margin-left: 5px; }
        .badge { font-size: 10px; padding: 3px 6px; border-radius: 4px; font-weight: bold; text-transform: uppercase; }
        .badge-green { background: rgba(63, 185, 80, 0.2); color: #3fb950; border: 1px solid rgba(63, 185, 80, 0.3); }
        .badge-red { background: rgba(248, 81, 73, 0.2); color: #ff7b72; border: 1px solid rgba(248, 81, 73, 0.3); }
        .badge-yellow { background: rgba(210, 153, 34, 0.2); color: #d29922; border: 1px solid rgba(210, 153, 34, 0.3); }
        
        /* TUNING GRID */
        .tuning-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .tuning-item { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 6px; padding: 8px 12px; font-size: 12px; display: flex; justify-content: space-between; align-items: center; }

        .alert-error { background: rgba(248, 81, 73, 0.1); border: 1px solid rgba(248, 81, 73, 0.4); color: #ff7b72; padding: 15px; border-radius: 8px; margin-bottom: 25px; font-weight: bold; }
        
        /* HOZZÁADVA: Gomb stílusok */
        .btn { padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; color: white; font-weight: bold; text-transform: uppercase; font-size: 12px; transition: 0.2s; }
        .btn:hover { opacity: 0.8; transform: translateY(-2px); }
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
            <a href="vehicles.php" class="active">Járműveim</a>
            <a href="map.php">Élő Térkép</a>
            <?php if($isAdmin): ?>
                <a href="adminpanel.php" style="color: #e3b341;"><i class="fa-solid fa-shield-halved"></i> Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php" style="color: #ef4444;"><i class="fa-solid fa-power-off"></i> Kilépés</a>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-box">
            <h1><i class="fa-solid fa-car" style="color: #1f6feb; margin-right:10px;"></i> Saját Járműveim</h1>
            <div style="color: #9ca3af; font-size: 16px;">Összesen: <span style="color:#fff; font-weight:bold;"><?= count($vehicles) ?> db</span></div>
        </div>

        <?php if(!empty($dbError)): ?>
            <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= $dbError ?></div>
        <?php endif; ?>
        
        <?php if(!empty($successMsg)): ?>
            <div style="background: rgba(46,160,67,0.15); border: 1px solid #2ea043; color: #3fb950; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight:bold;"><i class="fa-solid fa-check"></i> <?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>

        <?php if(empty($vehicles) && empty($dbError)): ?>
            <div style="text-align: center; padding: 80px 20px; background: rgb(33, 35, 40); border: 1px dashed rgba(255,255,255,0.1); border-radius: 12px; color: #9ca3af;">
                <i class="fa-solid fa-car-side" style="font-size: 64px; opacity: 0.3; margin-bottom: 20px;"></i>
                <h3 style="color:#fff; font-size: 20px;">Nincs járműved</h3>
                <p style="margin-top: 10px;">Jelenleg egyetlen jármű sincs a karaktereid nevén a szerveren.</p>
            </div>
        <?php else: ?>
            <div class="grid-3">
                <?php foreach($vehicles as $veh): 
                    $dbID = $veh['dbID'] ?? '?';
                    $model = $veh['modelId'] ?? 400;
                    $plate = $veh['plateText'] ?? 'NINCS';
                    $ownerName = str_replace('_', ' ', $veh['ownerName'] ?? 'Ismeretlen');
                    
                    // --- ÁLTALÁNOS ÁLLAPOT ---
                    $hp = $veh['health'] ?? 1000;
                    $hpPercent = max(0, min(100, (($hp - 250) / 750) * 100));
                    $hpColor = $hpPercent > 50 ? '#3fb950' : ($hpPercent > 20 ? '#d29922' : '#f85149');
                    
                    $locked = ($veh['lock'] ?? 0) == 1;
                    $impounded = ($veh['impound'] ?? 0) == 1;
                    $handbrake = ($veh['isHandbrake'] ?? '0') === 'true' || ($veh['isHandbrake'] ?? '0') === '1' || ($veh['isHandbrake'] ?? 0) === 1;
                    
                    // --- HAJTÁS (LUA ALAPJÁN) ---
                    $driveType = $veh['driveType'] ?? 'handling';
                    if (strtolower($driveType) === 'awd') $driveTypeText = 'Összkerék (AWD)';
                    elseif (strtolower($driveType) === 'rwd') $driveTypeText = 'Hátsó (RWD)';
                    elseif (strtolower($driveType) === 'fwd') $driveTypeText = 'Első (FWD)';
                    else $driveTypeText = 'Gyári';

                    $hasAuto = ($veh['automaticShifter'] ?? 0) == 1;
                    
                    // ABS
                    $absLevel = (int)($veh['abs'] ?? 0);
                    $absText = '<span style="color:#6e7681;">Nincs</span>';
                    if ($absLevel == 1) $absText = '<span style="color:#1f6feb;">Gyenge</span>';
                    if ($absLevel == 2) $absText = '<span style="color:#3fb950;">Normál</span>';
                    if ($absLevel == 3) $absText = '<span style="color:#e3b341;">Erős</span>';

                    // --- EXTRÁK (LUA ALAPJÁN) ---
                    $airRide = ($veh['airRide'] ?? '0') === 'true' || ($veh['airRide'] ?? '0') === '1' || ($veh['airRide'] ?? 0) === 1;
                    $strobe = ($veh['strobe'] ?? '0') === 'true' || ($veh['strobe'] ?? '0') === '1' || ($veh['strobe'] ?? 0) === 1;
                    $spinner = ($veh['spinner'] ?? '0') === 'true' || ($veh['spinner'] ?? '0') === '1' || ($veh['spinner'] ?? 0) === 1;
                    $lsdDoor = ($veh['lsdDoor'] ?? '0') === 'true' || ($veh['lsdDoor'] ?? '0') === '1' || ($veh['lsdDoor'] ?? 0) === 1;
                    
                    $backfire = (int)($veh['backfire'] ?? 0);
                    if ($backfire == 2) $backfireText = '<span style="color:#bf4b8a;">Egyedi</span>';
                    elseif ($backfire == 1) $backfireText = '<span style="color:#1f6feb;">Normál</span>';
                    else $backfireText = '<span style="color:#6e7681;">Nincs</span>';

                    $traffiRadar = (int)($veh['traffiRadar'] ?? 0);
                    if ($traffiRadar == 2) $traffiRadarText = '<span style="color:#bf4b8a;">Prémium</span>';
                    elseif ($traffiRadar == 1) $traffiRadarText = '<span style="color:#1f6feb;">Normál</span>';
                    else $traffiRadarText = '<span style="color:#6e7681;">Nincs</span>';

                    // Nitro
                    $nitroLevel = (int)($veh['nosLevel'] ?? 0);
                    $nitroType = (int)($veh['nosFillType'] ?? 0);
                    $nitroText = '<span style="color:#6e7681;">Nincs</span>';
                    if ($nitroLevel > 0) {
                        if ($nitroType == 2) $nitroText = '<span style="color:#bf4b8a;">Venom (' . $nitroLevel . '/4)</span>';
                        else $nitroText = '<span style="color:#1f6feb;">Normál (' . $nitroLevel . '/4)</span>';
                    }

                    // Folyadékok és akku
                    $maxBat = (float)($veh['maxBatteryCharge'] ?? 2048);
                    if ($maxBat <= 0) $maxBat = 2048;
                    $batPercent = max(0, min(100, (((float)($veh['batteryCharge'] ?? 2048)) / $maxBat) * 100));
                    $oilPercent = max(0, min(100, (((float)($veh['oil'] ?? 1000)) / 1000) * 100));

                    // Alkatrészek állapota
                    $mechanic = [];
                    if (!empty($veh['mechanicDatas'])) {
                        $parsedMech = json_decode($veh['mechanicDatas'], true);
                        if (is_array($parsedMech) && isset($parsedMech[0]) && is_array($parsedMech[0])) {
                            $mechanic = $parsedMech[0];
                        }
                    }

                    $getPartCond = function($key) use ($mechanic) {
                        if (isset($mechanic[$key]) && isset($mechanic[$key][1])) {
                            return max(0, min(100, 100 - (float)$mechanic[$key][1]));
                        }
                        return 100; 
                    };

                    $batteryCond = $getPartCond('battery');
                    $timingCond = $getPartCond('engineTimingKit');
                    $brakeCond = ($getPartCond('frontBrakes') + $getPartCond('rearBrakes')) / 2;
                    $tireCond = ($getPartCond('frontTires') + $getPartCond('rearTires')) / 2;
                    
                    $color1 = "#888"; $color2 = "#888";
                    if (!empty($veh['colors'])) {
                        $colorsArray = json_decode($veh['colors'], true);
                        if (is_array($colorsArray) && isset($colorsArray[0][0])) {
                            $c1 = $colorsArray[0]; $color1 = "rgb({$c1[0]}, {$c1[1]}, {$c1[2]})";
                            if (isset($colorsArray[1])) { $c2 = $colorsArray[1]; $color2 = "rgb({$c2[0]}, {$c2[1]}, {$c2[2]})"; }
                        }
                    }
                    
                    // --- TUNING (PERFORMANCE JSON FELDOLGOZÁS) ---
                    $perf = [];
                    if (!empty($veh['performance'])) {
                        $parsedTuning = json_decode($veh['performance'], true);
                        if (is_array($parsedTuning)) $perf = $parsedTuning;
                    }
                    $tuneEngine = $perf['engine'] ?? 0;
                    $tuneTurbo = $perf['turbo'] ?? 0;
                    $tuneEcu = $perf['ecu'] ?? 0;
                    $tuneTrans = $perf['transmission'] ?? 0;
                    $tuneSusp = $perf['suspension'] ?? 0;
                    $tuneBrake = $perf['brakes'] ?? 0;
                    $tuneTire = $perf['tire'] ?? 0;
                    $tuneWeight = $perf['weightReduction'] ?? 0;

                    // Színkódoló segédfüggvény
                    $getColor = function($p) {
                        if ($p >= 60) return '#3fb950';
                        if ($p >= 25) return '#d29922';
                        return '#f85149';
                    };
                ?>
                <div class="card">
                    
                    <div class="card-header-box">
                        <div style="display:flex; flex-direction:column; gap:3px;">
                            <span style="color:#fff; font-size:18px; font-weight:700;"><?= getVehicleName($model) ?></span>
                            <span style="font-size: 11px; color: #9ca3af; font-weight: 500;"><i class="fa-solid fa-user"></i> <?= htmlspecialchars($ownerName) ?></span>
                        </div>
                        <span class="plate"><?= htmlspecialchars($plate) ?></span>
                    </div>
                    
                    <div class="card-body-box">
                        
                        <div class="info-module">
                            <div class="module-title"><i class="fa-solid fa-circle-info"></i> Alapadatok & Állapot</div>
                            <div class="data-row" style="border:none; padding-bottom:5px;">
                                <span class="data-label">Karosszéria Állapot:</span>
                                <span class="data-value" style="color: <?= $hpColor ?>;"><?= round($hpPercent) ?>%</span>
                            </div>
                            <div class="hp-bar-bg"><div class="hp-bar-fill" style="width: <?= $hpPercent ?>%; background-color: <?= $hpColor ?>;"></div></div>
                            <div class="data-row mt-2">
                                <span class="data-label">Jármű ID:</span>
                                <span class="data-value">#<?= $dbID ?></span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Futott KM:</span>
                                <span class="data-value"><?= number_format($veh['odometer'] ?? 0, 1, ',', ' ') ?> km</span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Üzemanyag:</span>
                                <span class="data-value"><?= round($veh['fuel'] ?? 0) ?> L <span style="color:#9ca3af; font-size:10px;">(<?= htmlspecialchars($veh['fuelType'] ?? 'petrol') ?>)</span></span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Státusz:</span>
                                <span class="data-value">
                                    <?php if($impounded): ?> <span class="badge badge-red">Lefoglalva</span> <?php endif; ?>
                                    <?php if($handbrake): ?> <span class="badge badge-yellow">Kézifék</span> <?php endif; ?>
                                    <?php if($locked): ?> <span class="badge badge-red"><i class="fa-solid fa-lock"></i> Zárva</span> 
                                    <?php else: ?> <span class="badge badge-green"><i class="fa-solid fa-lock-open"></i> Nyitva</span> <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="info-module">
                            <div class="module-title"><i class="fa-solid fa-car-battery"></i> Műszaki Állapot & Hajtás</div>
                            <div class="data-row">
                                <span class="data-label">Hajtáslánc / Váltó:</span>
                                <span class="data-value"><?= $driveTypeText ?> <span style="color:#9ca3af;font-size:10px;">(<?= $hasAuto ? 'Automata' : 'Manuális' ?>)</span></span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">ABS (Blokkolásgátló):</span>
                                <span class="data-value"><?= $absText ?></span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Akkumulátor Töltöttség:</span>
                                <span class="data-value" style="color: <?= $getColor($batPercent) ?>;"><?= round($batPercent) ?>%</span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Akkumulátor (Kopás):</span>
                                <span class="data-value" style="color: <?= $getColor($batteryCond) ?>;"><?= round($batteryCond) ?>%</span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Motor Olajszint:</span>
                                <span class="data-value" style="color: <?= $getColor($oilPercent) ?>;"><?= round($oilPercent) ?>%</span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Vezérlés (Szíj) Állapota:</span>
                                <span class="data-value" style="color: <?= $getColor($timingCond) ?>;"><?= round($timingCond) ?>%</span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Fékrendszer / Gumik:</span>
                                <span class="data-value">
                                    <span style="color: <?= $getColor($brakeCond) ?>;"><?= round($brakeCond) ?>%</span> / 
                                    <span style="color: <?= $getColor($tireCond) ?>;"><?= round($tireCond) ?>%</span>
                                </span>
                            </div>
                        </div>

                        <div class="info-module">
                            <div class="module-title"><i class="fa-solid fa-wand-magic-sparkles"></i> Megjelenés & Extrák</div>
                            <div class="data-row">
                                <span class="data-label">Fényezés (RGB):</span>
                                <span class="data-value">
                                    <div class="color-box" style="background-color: <?= $color1 ?>;" title="Szín 1"></div>
                                    <div class="color-box" style="background-color: <?= $color2 ?>;" title="Szín 2"></div>
                                </span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Paintjob (Matrica):</span>
                                <span class="data-value"><?= ($veh['paintjob'] ?? 0) > 0 ? '#' . $veh['paintjob'] : 'Gyári' ?></span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">LSD Ajtó / Spinner:</span>
                                <span class="data-value">
                                    <?= $lsdDoor ? '<span style="color:#3fb950; font-weight:bold;">Van</span>' : '<span style="color:#6e7681;">Nincs</span>' ?> / 
                                    <?= $spinner ? '<span style="color:#3fb950; font-weight:bold;">Van</span>' : '<span style="color:#6e7681;">Nincs</span>' ?>
                                </span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">AirRide / Stroboszkóp:</span>
                                <span class="data-value">
                                    <?= $airRide ? '<span style="color:#3fb950; font-weight:bold;">Van</span>' : '<span style="color:#6e7681;">Nincs</span>' ?> / 
                                    <?= $strobe ? '<span style="color:#3fb950; font-weight:bold;">Van</span>' : '<span style="color:#6e7681;">Nincs</span>' ?>
                                </span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Nitro Rendszer:</span>
                                <span class="data-value"><?= $nitroText ?></span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Traffipax Radar:</span>
                                <span class="data-value"><?= $traffiRadarText ?></span>
                            </div>
                            <div class="data-row">
                                <span class="data-label">Backfire (Durrogás):</span>
                                <span class="data-value"><?= $backfireText ?></span>
                            </div>
                        </div>

                        <div class="info-module" style="background: rgba(31, 111, 235, 0.05); border-color: rgba(31, 111, 235, 0.2);">
                            <div class="module-title"><i class="fa-solid fa-wrench"></i> Teljesítmény Tuningok</div>
                            <div class="tuning-grid">
                                <div class="tuning-item"><span>Motor</span> <?= getTuningLevel($tuneEngine, 'engine') ?></div>
                                <div class="tuning-item"><span>Turbó</span> <?= getTuningLevel($tuneTurbo, 'turbo') ?></div>
                                <div class="tuning-item"><span>ECU</span> <?= getTuningLevel($tuneEcu, 'ecu') ?></div>
                                <div class="tuning-item"><span>Váltó</span> <?= getTuningLevel($tuneTrans, 'transmission') ?></div>
                                <div class="tuning-item"><span>Futómű</span> <?= getTuningLevel($tuneSusp, 'suspension') ?></div>
                                <div class="tuning-item"><span>Fékek</span> <?= getTuningLevel($tuneBrake, 'brakes') ?></div>
                                <div class="tuning-item"><span>Gumik</span> <?= getTuningLevel($tuneTire, 'tire') ?></div>
                                <div class="tuning-item"><span>Súlycs.</span> <?= getTuningLevel($tuneWeight, 'weightReduction') ?></div>
                            </div>
                        </div>

                        <div class="info-module" style="border-color: rgba(239, 68, 68, 0.3);">
                            <div class="module-title" style="color:#ef4444;"><i class="fa-solid fa-gear"></i> Jármű Kezelése</div>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <form method="POST" style="margin: 0; flex: 1;" onsubmit="return confirm('Biztosan visszateleportálod a járművet a központba (0,0,0)?');">
                                    <input type="hidden" name="action" value="reset_pos">
                                    <input type="hidden" name="vehicle_id" value="<?= $dbID ?>">
                                    <button type="submit" class="btn" style="background: #0d6efd; width: 100%;"><i class="fa-solid fa-location-dot"></i> Pozíció Visszaállítása</button>
                                </form>

                                <form method="POST" style="margin: 0; flex: 1;" onsubmit="return confirm('VIGYÁZAT! Biztosan eladod a járművet az államnak $50,000-ért? A művelet NEM visszavonható!');">
                                    <input type="hidden" name="action" value="sell_state">
                                    <input type="hidden" name="vehicle_id" value="<?= $dbID ?>">
                                    <button type="submit" class="btn" style="background: #ef4444; width: 100%;"><i class="fa-solid fa-sack-dollar"></i> Eladás Államnak</button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
