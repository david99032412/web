<?php
// templates/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        NorthSide <span>UCP</span>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i> Kezdőlap
        </a>
        <a href="characters.php" class="menu-item <?= $current_page == 'characters.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> Karakterek
        </a>
        <a href="vehicles.php" class="menu-item <?= $current_page == 'vehicles.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-car"></i> Járművek
        </a>
        <a href="profile.php" class="menu-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-user-gear"></i> Profilom
        </a>
        
        <?php if (isset($_SESSION['user_admin']) && $_SESSION['user_admin'] >= 7): ?>
        <div style="padding: 15px 20px; color: #4b5563; font-size: 11px; font-weight: 800; text-transform: uppercase;">Adminisztráció</div>
        <a href="adminpanel.php" class="menu-item <?= $current_page == 'adminpanel.php' ? 'active' : '' ?>" style="color: #ff7b72;">
            <i class="fa-solid fa-shield-halved"></i> Admin Panel
        </a>
        <?php endif; ?>
    </div>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="menu-item" style="color: #ef4444;">
            <i class="fa-solid fa-right-from-bracket"></i> Kijelentkezés
            <nav class="navbar" style="background: rgba(17, 18, 20, 0.95); border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; backdrop-filter: blur(10px);">
        <div class="navbar-brand" style="font-size: 20px; font-weight: 900; color: #fff; text-transform: uppercase;">NorthSide <span style="color: #e67e22;">UCP</span></div>
        <div class="nav-links">
            <a href="dashboard.php" style="color: #9ca3af; text-decoration: none; margin-left: 20px; font-size: 14px; font-weight: 500;">Kezdőlap</a>
            <a href="profile.php" style="color: #9ca3af; text-decoration: none; margin-left: 20px; font-size: 14px; font-weight: 500;">Profilom</a>
            <a href="characters.php" style="color: #9ca3af; text-decoration: none; margin-left: 20px; font-size: 14px; font-weight: 500;">Karakterek</a>
            <a href="vehicles.php" style="color: #9ca3af; text-decoration: none; margin-left: 20px; font-size: 14px; font-weight: 500;">Járművek</a>
            <a href="adminpanel.php" style="color: #e3b341; text-decoration: none; margin-left: 20px; font-size: 14px; font-weight: bold;"><i class="fa-solid fa-shield-halved"></i> Admin Panel</a>
            <a href="../logout.php" style="color: #ef4444; text-decoration: none; margin-left: 20px; font-size: 14px; font-weight: 500;"><i class="fa-solid fa-power-off"></i> Kilépés</a>
        </div>
    </nav>
        </a>
    </div>
</div>