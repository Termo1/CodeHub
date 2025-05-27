<?php
// Start session if not already started
require_once __DIR__ . '/../db/classes/Session.php';
Session::start();
?>
<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/codehub/index.php">
                <i class="fas fa-code me-2"></i>CodeHub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/codehub/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/codehub/pages/forums.php">Forums</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/codehub/pages/topics.php">Recent Topics</a>
                    </li>
                </ul>
                
                <div class="d-flex">
                    <?php if (Session::isLoggedIn()): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars(Session::get('username')); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="/codehub/pages/profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="/codehub/pages/my-topics.php"><i class="fas fa-list me-2"></i>My Topics</a></li>
                                <li><a class="dropdown-item" href="/codehub/pages/settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <?php if (Session::get('role') === 'admin' || Session::get('role') === 'moderator'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/codehub/admin/index.php"><i class="fas fa-lock me-2"></i>Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/codehub/pages/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="/codehub/pages/login.php" class="btn btn-outline-light me-2">Login</a>
                        <a href="/codehub/pages/register.php" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>