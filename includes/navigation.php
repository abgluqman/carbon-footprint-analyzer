<?php
// Get user's profile photo if logged in
$userPhoto = null;
$userInitial = '';

if (isset($_SESSION['user_id'])) {
    $photoQuery = $conn->query("
        SELECT profile_photo, name 
        FROM user 
        WHERE user_id = " . intval($_SESSION['user_id'])
    );

    if ($photoQuery && $photoRow = $photoQuery->fetch_assoc()) {
        $userPhoto = $photoRow['profile_photo'];
        $userInitial = strtoupper(substr($photoRow['name'], 0, 1));
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm sticky-top">
    <div class="container-fluid">

        <!-- Sidebar Toggle Button -->
        <button id="sidebarToggleBtn"
                class="btn btn-sm btn-outline-light me-2"
                style="border: none; padding: 0.5rem 0.75rem;">
            <i class="bi bi-list"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-leaf"></i> Carbon Footprint Analyzer
        </a>

        <!-- Mobile Toggle -->
        <button class="navbar-toggler" 
                type="button" 
                data-bs-toggle="collapse" 
                data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Content -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">

                <?php if (isset($_SESSION['user_id'])): ?>

                    <li class="nav-item dropdown">

                        <a class="nav-link dropdown-toggle d-flex align-items-center"
                           href="#"
                           id="userDropdown"
                           role="button"
                           data-bs-toggle="dropdown"
                           aria-expanded="false">

                            <!-- Profile Photo -->
                            <?php if ($userPhoto): ?>

                                <img src="data:image/jpeg;base64,<?php echo base64_encode($userPhoto); ?>"
                                     alt="Profile"
                                     style="
                                        width: 32px;
                                        height: 32px;
                                        border-radius: 50%;
                                        object-fit: cover;
                                        margin-right: 8px;
                                        border: 2px solid white;
                                     ">

                            <?php else: ?>

                                <!-- User Initial -->
                                <span style="
                                    width: 32px;
                                    height: 32px;
                                    border-radius: 50%;
                                    background: white;
                                    color: #198754;
                                    display: inline-flex;
                                    align-items: center;
                                    justify-content: center;
                                    margin-right: 8px;
                                    font-weight: bold;
                                    border: 2px solid white;
                                ">
                                    <?php echo $userInitial; ?>
                                </span>

                            <?php endif; ?>

                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>

                        </a>

                        <!-- Dropdown Menu -->
                        <ul class="dropdown-menu dropdown-menu-end"
                            aria-labelledby="userDropdown">

                            <li>
                                <a class="dropdown-item" href="profile.php">
                                    <i class="bi bi-person"></i> Profile
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item" href="dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a>
                            </li>

                            <li>
                                <hr class="dropdown-divider">
                            </li>

                            <li>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </li>

                        </ul>

                    </li>

                <?php else: ?>

                    <li class="nav-item">
                        <a class="nav-link" href="pages/login.php">
                            Login
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="pages/register.php">
                            Register
                        </a>
                    </li>

                <?php endif; ?>

            </ul>
        </div>
    </div>
</nav>