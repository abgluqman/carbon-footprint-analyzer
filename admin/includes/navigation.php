<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-shield-check"></i> Admin Panel
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                       href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" 
                       href="users.php">
                        <i class="bi bi-people"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'content.php' ? 'active' : ''; ?>" 
                       href="content.php">
                        <i class="bi bi-file-earmark-text"></i> Educational Content
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" 
                       href="reports.php">
                        <i class="bi bi-graph-up"></i> Analytics
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" 
                       data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="../pages/dashboard.php" target="_blank">
                                <i class="bi bi-box-arrow-up-right"></i> View User Site
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>