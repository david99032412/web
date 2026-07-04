<?php require 'config.php'; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Letöltések – NorthSideRP</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="icon" type="image/png" href="assets/logo.png">
  <style>
      :root { --bg-color: #0a0a0c; --panel-bg: #121318; --primary: #0d6efd; }
      body { background-color: var(--bg-color); color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
      .navbar { background: rgba(18, 19, 24, 0.95); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 0;}
      .navbar-brand { font-weight: 900; font-size: 24px; color: #fff; text-decoration: none;}
      .navbar-brand span { color: var(--primary); }
      .nav-link { color: #9ca3af !important; font-weight: 500; }
      .nav-link:hover { color: #fff !important; }
      .content-box { background: var(--panel-bg); border: 1px solid rgba(255,255,255,0.05); padding: 40px; border-radius: 12px; margin-bottom: 30px; }
  </style>
</head>
<body>

  <nav class="navbar navbar-expand-lg sticky-top mb-5">
      <div class="container">
          <a class="navbar-brand" href="index.php">NorthSide<span>RP</span></a>
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
              <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
          </button>
          <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
              <ul class="navbar-nav align-items-center gap-3">
                  <li class="nav-item"><a class="nav-link" href="index.php">Főoldal</a></li>
                  <li class="nav-item"><a class="nav-link text-white" href="downloads.php">Letöltések</a></li>
                  <li class="nav-item"><a class="nav-link" href="forum.php">Fórum</a></li>
                  <li class="nav-item"><a class="nav-link" href="contact.php">Kapcsolat</a></li>
                  <li class="nav-item"><a class="btn btn-primary ms-lg-3" href="ucp/login.php">UCP Belépés</a></li>
              </ul>
          </div>
      </div>
  </nav>

  <div class="container py-4">
    <h1 class="mb-4" style="font-weight: 900;">Letöltések</h1>

    <div class="content-box">
      <h2 style="color: #0d6efd;">FiveM Kliens</h2>
      <p class="text-muted">A szerverünk a <strong>FiveM</strong> (GTA V) módosításon fut. A játékhoz rendelkezned kell egy eredeti, megvásárolt Grand Theft Auto V példánnyal (Steam, Epic Games vagy Rockstar Launcher). Töltsd le a FiveM klienst a hivatalos oldalról:</p>
      <a href="https://fivem.net/" target="_blank" class="btn btn-primary mt-2 px-4 py-2" style="font-weight: bold; text-transform: uppercase;">FiveM Kliens Letöltése</a>
    </div>

    <div class="content-box">
      <h2 style="color: #0d6efd;">Szerver Csatlakozás</h2>
      <p class="text-muted">Ha már telepítetted a FiveM-et, a legegyszerűbben az alábbi gombra kattintva, vagy az F8-as konzolba az `connect 94.156.37.91` parancs beírásával tudsz csatlakozni.</p>
      <a href="fivem://connect/94.156.37.91:30120" class="btn btn-success mt-2 px-4 py-2" style="font-weight: bold; text-transform: uppercase;">Azonnali Csatlakozás</a>
    </div>

    <div class="content-box">
      <h2 style="color: #0d6efd;">Egyéb Erőforrások</h2>
      <p class="text-muted">Szabályzatok, billentyűkiosztások és a legfrissebb bejelentések a Discord szerverünkön találhatók.</p>
      <a href="https://discord.gg/northsiderp" target="_blank" class="btn btn-outline-light mt-2 px-4 py-2" style="font-weight: bold;">Csatlakozás a Discordhoz</a>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
