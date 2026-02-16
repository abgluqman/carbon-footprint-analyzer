<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                    <i class="bi bi-person"></i>
                    Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calculator.php' ? 'active' : ''; ?>" href="calculator.php">
                    <i class="bi bi-calculator"></i>
                    Calculator
                </a>
            </li>
            <!-- Period Options (Only on Calculator page) -->
            <?php if (basename($_SERVER['PHP_SELF']) == 'calculator.php'): ?>
            <li class="nav-item ps-4">
                <a href="?period=daily" class="nav-link text-decoration-none <?php echo (isset($_GET['period']) && $_GET['period'] == 'daily') || !isset($_GET['period']) ? 'bg-light border-start border-primary border-3 text-primary fw-bold' : ''; ?>">
                    Daily
                </a>
            </li>
            <li class="nav-item ps-4">
                <a href="?period=weekly" class="nav-link text-decoration-none <?php echo (isset($_GET['period']) && $_GET['period'] == 'weekly') ? 'bg-light border-start border-primary border-3 text-primary fw-bold' : ''; ?>">
                    Weekly
                </a>
            </li>
            <li class="nav-item ps-4">
                <a href="?period=monthly" class="nav-link text-decoration-none <?php echo (isset($_GET['period']) && $_GET['period'] == 'monthly') ? 'bg-light border-start border-primary border-3 text-primary fw-bold' : ''; ?>">
                    Monthly
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>" href="history.php">
                    <i class="bi bi-clock-history"></i>
                    History
                </a>
            </li>
            <!-- Period Options (Only on History page) -->
            <?php if (basename($_SERVER['PHP_SELF']) == 'history.php'): ?>
            <li class="nav-item ps-4">
                <a href="?period=daily" class="nav-link text-decoration-none <?php echo (isset($_GET['period']) && $_GET['period'] == 'daily') || !isset($_GET['period']) ? 'bg-light border-start border-primary border-3 text-primary fw-bold' : ''; ?>">
                    Daily
                </a>
            </li>
            <li class="nav-item ps-4">
                <a href="?period=weekly" class="nav-link text-decoration-none <?php echo (isset($_GET['period']) && $_GET['period'] == 'weekly') ? 'bg-light border-start border-primary border-3 text-primary fw-bold' : ''; ?>">
                    Weekly
                </a>
            </li>
            <li class="nav-item ps-4">
                <a href="?period=monthly" class="nav-link text-decoration-none <?php echo (isset($_GET['period']) && $_GET['period'] == 'monthly') ? 'bg-light border-start border-primary border-3 text-primary fw-bold' : ''; ?>">
                    Monthly
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tips.php' ? 'active' : ''; ?>" href="tips.php">
                    <i class="bi bi-lightbulb"></i>
                    Tips & Education
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</nav>