<?php
session_start();
require_once __DIR__ . '/config/db_connection.php';

// Determine if visitor is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// Fetch latest educational content (limit 4)
$contents = [];
$res = $conn->query("SELECT content_id, title, description, content_type, emissions_level, content_image FROM educational_content ORDER BY content_id DESC LIMIT 4");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $contents[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌎 Carbon Footprint Analyzer - Track Your Environmental Impact</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body>
    
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        
        <div class="container">

            <a class="navbar-brand text-success fw-bold" href="index.php">
                Carbon Analyzer
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#resources">EduLearn</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About Us</a>
                    </li>
                </ul>
                

                <div class="d-flex">
                    <a href="pages/login.php" class="btn btn-outline-success me-2">
                        Login
                    </a>
                    <a href="pages/register.php" class="btn btn-success">
                        Sign Up
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold text-success mb-4">
                        Know Your Impact, Lower Your Footprint
                    </h1>
                    <p class="lead mb-4">
                        It takes 2 minutes to see how your lifestyle affects the planet. Get a personalized roadmap to go green without the guesswork.
                    </p>
                    <h2 class="lead mb-3">Register for free to get full access.</h2>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="pages/register.php" class="btn btn-success btn-lg px-4 me-md-2">
                             Calculate Now!
                        </a>
                        
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="assets/images/landing-page.png" 
                         alt="Globe Image Landing Page" 
                         style="max-width: 100%; height: auto; opacity: 1;">
                    <!-- CHANGED: opacity 1.4 → 1 (was invalid, fixed to normal) -->
                    <!-- CHANGED: ../assets → assets (fixed path) -->
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Key Features</h2>
                <p class="text-muted">Everything you need to manage your carbon footprint</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm text-center p-4">
                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3"
                             style="width: 80px; height: 80px;">
                            <i class="bi bi-calculator text-success fs-2"></i>
                        </div>
                        <h5>Easy Tracking</h5>
                        <p class="text-muted small mb-0">
                            Simple data entry for electricity, fuel, water, waste, and more
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm text-center p-4">
                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3"
                             style="width: 80px; height: 80px;">
                            <i class="bi bi-graph-up text-success fs-2"></i>
                        </div>
                        <h5>Visual Analytics</h5>
                        <p class="text-muted small mb-0">
                            Interactive charts and graphs to understand your trends
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm text-center p-4">
                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3"
                             style="width: 80px; height: 80px;">
                            <i class="bi bi-lightbulb text-success fs-2"></i>
                        </div>
                        <h5>Personalized Tips</h5>
                        <p class="text-muted small mb-0">
                            Get customized recommendations to reduce your emissions
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm text-center p-4">
                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3"
                             style="width: 80px; height: 80px;">
                            <i class="bi bi-file-pdf text-success fs-2"></i>
                        </div>
                        <h5>PDF Reports</h5>
                        <p class="text-muted small mb-0">
                            Generate detailed reports to share and track progress
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How Can You Reduce? -->
    <section id="resources" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">How Can You Reduce?</h2>
                <p class="text-muted">Get the latest tips, articles and guides! </p>
            </div>

            <div class="row g-4">
                <?php if (!empty($contents)): ?>
                    <?php foreach ($contents as $item): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <?php if (!empty($item['content_image'])): ?>
                                    <img src="data:image/jpeg;base64,<?php echo base64_encode($item['content_image']); ?>"
                                         class="card-img-top" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h6>
                                    <p class="card-text small text-muted">
                                        <?php echo htmlspecialchars(substr($item['description'], 0, 80)) . '...'; ?>
                                    </p>
                                    <a href="pages/tips.php" 
                                       class="content-card-link btn btn-sm btn-outline-success"
                                       data-content-id="<?php echo htmlspecialchars($item['content_id']); ?>">
                                        Read More
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">No educational content published yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Content Preview Modal (for non-registered visitors) -->
    <div class="modal fade" id="contentPreviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="contentPreviewTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="contentPreviewImage" class="mb-3 text-center"></div>
                    <div id="contentPreviewBody"></div>
                </div>
                <div class="modal-footer">
                    <small class="text-muted me-auto" id="contentPreviewMeta"></small>
                    <a href="#" id="contentPreviewFullLink" class="btn btn-success">View full content</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    

    <!-- About Section -->
    <section id="about" class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h2 class="fw-bold mb-4">About Us</h2>
                    <p>
                        Our platform helps individuals track, analyze, and reduce 
                        their carbon emissions. We provide 
                        comprehensive tools to monitor daily activities and their environmental impact.
                    </p>
                    <p>
                        By understanding your carbon footprint, you can make informed decisions and 
                        contribute to a more sustainable future.
                    </p>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Accurate emission calculations
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Real-time progress tracking
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Educational content
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            PDF reporting
                        </li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <img src="assets/images/about-image.png" 
                         alt="About" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-light">
        <div class="container text-center">
            <h2 class="fw-bold mb-4">Ready to Start Tracking?</h2>
            <p class="lead mb-4">Join us in making a positive impact on the environment</p>
            <a href="pages/register.php" class="btn btn-success btn-lg">
                Create Account Now!
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-leaf"></i> Carbon Footprint Analyzer</h5>
                    <p class="small mb-0">
                        Helping individuals track and reduce their environmental impact.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="small mb-0">
                        &copy; <?php echo date('Y'); ?> Carbon Footprint Analyzer. All rights reserved.
                    </p>
                    
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Whether visitor is logged in (injected from PHP)
        const isLoggedIn = <?php echo json_encode($isLoggedIn); ?>;

        // Attach click handlers to content links; non-logged-in users see modal preview
        document.addEventListener('DOMContentLoaded', function() {
            const links = document.querySelectorAll('.content-card-link');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    const contentId = this.getAttribute('data-content-id');
                    if (!isLoggedIn) {
                        e.preventDefault();
                        // Fetch content details and show modal
                        fetch('pages/content_view.php?content_id=' + encodeURIComponent(contentId))
                            .then(r => r.json())
                            .then(data => {
                                if (!data.success) {
                                    alert(data.error || 'Failed to load content');
                                    return;
                                }
                                const c = data.content;
                                document.getElementById('contentPreviewTitle').innerText = c.title;
                                const imgWrap = document.getElementById('contentPreviewImage');
                                imgWrap.innerHTML = '';
                                if (c.image_base64) {
                                    const img = document.createElement('img');
                                    img.src = 'data:image/jpeg;base64,' + c.image_base64;
                                    img.className = 'img-fluid rounded mb-3';
                                    imgWrap.appendChild(img);
                                }
                                document.getElementById('contentPreviewBody').innerText = c.description;
                                document.getElementById('contentPreviewMeta').innerText = (c.content_type ? c.content_type : '') + (c.emissions_level ? (' · ' + c.emissions_level) : '');
                                const fullLink = document.getElementById('contentPreviewFullLink');
                                fullLink.href = 'pages/report.php?content_id=' + encodeURIComponent(c.content_id);

                                const modalEl = document.getElementById('contentPreviewModal');
                                const modal = new bootstrap.Modal(modalEl);
                                modal.show();
                            }).catch(err => {
                                console.error(err);
                                alert('Failed to load content');
                            });
                    }
                    // If logged in, allow default navigation to full content page
                });
            });
        });
    </script>
</body>
</html>