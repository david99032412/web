<?php
require_once __DIR__ . '/config.php';
?>

<!--
  A fejléc sablonja. Ez határozza meg az oldal tetején lévő logót és navigációt.
  A stílus a fő CSS fájlból érkezik (assets/style.css), így itt csak a szerkezetet
  definiáljuk. A navigációs linkekhez relatív útvonalakat használunk, hogy az
  aloldalak is megfelelően működjenek.
-->
<header class="main-header">
  <div class="logo-area">
    <a href="/index.php"><img src="/assets/logo.png" alt="NorthSideRP logó"></a>
    <span class="site-title">NorthSideRP</span>
  </div>
  <nav class="main-nav">
    <ul>
      <li><a href="/index.php">Főoldal</a></li>
      <li><a href="/downloads.php">Letöltések</a></li>
      <li><a href="/forum.php">Fórum</a></li>
      <li><a href="/contact.php">Kapcsolatok</a></li>
      <li><a href="/ucp/login.php">UCP</a></li>
    </ul>
  </nav>
</header>
