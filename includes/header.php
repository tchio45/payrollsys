<header class="top-header">
    <div class="header-left">
        <button class="toggle-btn"><i class="fas fa-bars"></i></button>
        <h3><?php echo $pageTitle ?? 'Dashboard'; ?></h3>
    </div>
    <div class="header-right">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                <div class="user-role"><?php echo isAdmin() ? 'Administrator' : 'Employee'; ?></div>
            </div>
        </div>
        <a href="logout.php" class="btn btn-sm btn-danger">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</header>
