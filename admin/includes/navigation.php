<!-- Admin Sidebar -->
<nav id="adminSidebar" class="col-md-3 col-lg-2 d-md-block bg-success sidebar">
    <div class="d-flex flex-column h-100 pt-3">
        <!-- Brand -->
        <div class="px-3 mb-4 text-white">
            <h5 class="mb-0">
                <i class="bi bi-shield-check"></i> Admin Panel
            </h5>
            <small class="text-white-50">Carbon Footprint Analyzer</small>
        </div>

        <!-- Navigation Links -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active bg-white bg-opacity-25' : ''; ?>" 
                   href="dashboard.php">
                    <i class="bi bi-speedometer2"></i>
                    <span class="sidebar-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active bg-white bg-opacity-25' : ''; ?>" 
                   href="users.php">
                    <i class="bi bi-people"></i>
                    <span class="sidebar-text">Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'content.php' ? 'active bg-white bg-opacity-25' : ''; ?>" 
                   href="content.php">
                    <i class="bi bi-file-earmark-text"></i>
                    <span class="sidebar-text">Educational Content</span>
                </a>
            </li>
        </ul>

        <!-- Divider -->
        <hr class="border-white opacity-25 my-3">

        <!-- User Actions -->
        <ul class="nav flex-column flex-grow-1">
            <li class="nav-item">
                <a class="nav-link text-white" href="../pages/dashboard.php" target="_blank">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span class="sidebar-text">View User Site</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white-50" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="sidebar-text">Logout</span>
                </a>
            </li>
        </ul>

        <!-- Admin Info (bottom) -->
        <div class="mt-auto p-3 text-white-50 border-top border-white border-opacity-25">
            <small>
                <i class="bi bi-person-circle"></i>
                <span class="sidebar-text"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
            </small>
        </div>
    </div>
</nav>

<!-- Toggle Button for Mobile -->
<button class="btn btn-success d-md-none position-fixed top-0 start-0 m-2" 
        id="sidebarToggleBtn" 
        type="button"
        style="z-index: 1050;">
    <i class="bi bi-list"></i>
</button>

<style>
/* Admin Sidebar Styles */
#adminSidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 1040;
    padding: 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
    overflow-y: auto;
    transition: transform 0.3s ease-in-out;
    width: 16.6667%; /* col-md-2 equivalent */
}

/* Main content offset for sidebar */
@media (min-width: 768px) {
    body {
        padding-left: 16.6667%; /* Match sidebar width */
    }
}

@media (min-width: 992px) {
    #adminSidebar {
        width: 16.6667%; /* col-lg-2 */
    }
    
    body {
        padding-left: 16.6667%;
    }
}

#adminSidebar .nav-link {
    padding: 0.75rem 1rem;
    color: rgba(255, 255, 255, 0.8);
    transition: all 0.2s;
}

#adminSidebar .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
}

#adminSidebar .nav-link.active {
    background-color: rgba(255, 255, 255, 0.25);
    color: white;
    font-weight: 500;
}

#adminSidebar .nav-link i {
    margin-right: 0.5rem;
    width: 20px;
    text-align: center;
}

/* Mobile styles */
@media (max-width: 767.98px) {
    body {
        padding-left: 0 !important;
    }
    
    #adminSidebar {
        transform: translateX(-100%);
        width: 280px;
    }
    
    #adminSidebar.show {
        transform: translateX(0);
    }
}
</style>

<script>
// Sidebar toggle for mobile
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 768) {
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });
});
</script>