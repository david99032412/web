<?php require 'config.php'; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Kapcsolat – NorthSideRP</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
</head>
<body class="bg-dark text-white">

  <div class="container py-5">
    <h1 class="mb-4">Kapcsolatfelvétel</h1>
    <p>Ha kérdésed vagy javaslatod van, az alábbi űrlapon keresztül veheted fel velünk a kapcsolatot.</p>
    <form method="post" action="#">
      <div class="mb-3">
        <label for="name" class="form-label">Neved</label>
        <input type="text" class="form-control" id="name" name="name" required>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Email címed</label>
        <input type="email" class="form-control" id="email" name="email" required>
      </div>
      <div class="mb-3">
        <label for="message" class="form-label">Üzeneted</label>
        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
      </div>
      <button type="submit" class="btn btn-outline-light">Üzenet küldése</button>
    </form>
    <p class="mt-4">E-mailben is elérsz minket: <a href="mailto:info@northsiderp.hu" class="text-info">info@northsiderp.hu</a></p>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
