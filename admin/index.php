<?php
require_once '../config/Database.php';
require_once '../db/classes/Session.php';
require_once '../db/classes/User.php';

// Start session
Session::start();

// Check if user is logged in and is admin/moderator
if (!Session::isLoggedIn() || !Session::isModerator()) {
    Session::setFlash('error', 'Access denied. Admin/Moderator privileges required.');
    header('Location: ../pages/login.php');
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Function to get dashboard statistics
function getDashboardStats($db) {
    $stats = [];
    
    // Total users
    $query = "SELECT COUNT(*) as total FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total topics
    $query = "SELECT COUNT(*) as total FROM topics";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_topics'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total posts
    $query = "SELECT COUNT(*) as total FROM posts";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_posts'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total categories
    $query = "SELECT COUNT(*) as total FROM categories";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_categories'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total forums
    $query = "SELECT COUNT(*) as total FROM forums";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_forums'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // New users today
    $query = "SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['new_users_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // New topics today
    $query = "SELECT COUNT(*) as total FROM topics WHERE DATE(created_at) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['new_topics_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // New posts today
    $query = "SELECT COUNT(*) as total FROM posts WHERE DATE(created_at) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['new_posts_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    return $stats;
}

// Function to get recent users
function getRecentUsers($db, $limit = 5) {
    $query = "SELECT user_id, username, email, role, created_at FROM users ORDER BY created_at DESC LIMIT :limit";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get recent topics
function getRecentTopics($db, $limit = 5) {
    $query = "SELECT t.topic_id, t.title, t.created_at, u.username, f.name as forum_name
              FROM topics t
              JOIN users u ON t.user_id = u.user_id
              JOIN forums f ON t.forum_id = f.forum_id
              ORDER BY t.created_at DESC LIMIT :limit";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get forum activity
function getForumActivity($db) {
    $query = "SELECT f.name, f.topic_count, f.post_count, c.name as category_name
              FROM forums f
              JOIN categories c ON f.category_id = c.category_id
              ORDER BY f.post_count DESC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get dashboard data
$stats = getDashboardStats($db);
$recent_users = getRecentUsers($db);
$recent_topics = getRecentTopics($db);
$forum_activity = getForumActivity($db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("../parts/head.php")?>
    <title>Admin Dashboard - CodeHub</title>
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .admin-sidebar {
            background: #2c3e50;
            min-height: 100vh;
        }
        .admin-nav .nav-link {
            color: #ecf0f1;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .admin-nav .nav-link:hover {
            background: #34495e;
            color: white;
        }
        .admin-nav .nav-link.active {
            background: #3498db;
            color: white;
        }
    </style>
</head>
<body>
    <?php require "../parts/header.php" ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block admin-sidebar p-3">
                <h5 class="text-white mb-3">Admin Panel</h5>
                <ul class="nav flex-column admin-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">
                            <i class="fas fa-tags me-2"></i>Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="forums.php">
                            <i class="fas fa-comments me-2"></i>Forums
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="topics.php">
                            <i class="fas fa-list me-2"></i>Topics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="posts.php">
                            <i class="fas fa-comment me-2"></i>Posts
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-arrow-left me-2"></i>Back to Forum
                        </a>
                    </li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-primary">Welcome, <?php echo htmlspecialchars(Session::get('username')); ?></span>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo number_format($stats['total_users']); ?></h4>
                                        <p class="mb-0">Total Users</p>
                                        <small>+<?php echo $stats['new_users_today']; ?> today</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo number_format($stats['total_topics']); ?></h4>
                                        <p class="mb-0">Total Topics</p>
                                        <small>+<?php echo $stats['new_topics_today']; ?> today</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-list fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo number_format($stats['total_posts']); ?></h4>
                                        <p class="mb-0">Total Posts</p>
                                        <small>+<?php echo $stats['new_posts_today']; ?> today</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-comments fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo number_format($stats['total_forums']); ?></h4>
                                        <p class="mb-0">Total Forums</p>
                                        <small><?php echo $stats['total_categories']; ?> categories</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-folder fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row">
                    <!-- Recent Users -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">Recent Users</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_users)): ?>
                                    <p class="text-muted">No users found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Username</th>
                                                    <th>Role</th>
                                                    <th>Joined</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_users as $user): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="../pages/profile.php?id=<?php echo $user['user_id']; ?>">
                                                                <?php echo htmlspecialchars($user['username']); ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'moderator' ? 'warning' : 'primary'); ?>">
                                                                <?php echo ucfirst($user['role']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M d', strtotime($user['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Topics -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">Recent Topics</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_topics)): ?>
                                    <p class="text-muted">No topics found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Title</th>
                                                    <th>Author</th>
                                                    <th>Created</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_topics as $topic): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="../pages/topic.php?id=<?php echo $topic['topic_id']; ?>">
                                                                <?php echo htmlspecialchars(substr($topic['title'], 0, 30)) . (strlen($topic['title']) > 30 ? '...' : ''); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($topic['username']); ?></td>
                                                        <td><?php echo date('M d', strtotime($topic['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Forum Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">Most Active Forums</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($forum_activity)): ?>
                                    <p class="text-muted">No forum activity found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Forum</th>
                                                    <th>Category</th>
                                                    <th class="text-center">Topics</th>
                                                    <th class="text-center">Posts</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($forum_activity as $forum): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($forum['name']); ?></td>
                                                        <td>
                                                            <span class="badge bg-secondary">
                                                                <?php echo htmlspecialchars($forum['category_name']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center"><?php echo number_format($forum['topic_count']); ?></td>
                                                        <td class="text-center"><?php echo number_format($forum['post_count']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php require "../parts/footer.php" ?>
</body>
</html>