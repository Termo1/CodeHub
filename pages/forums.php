<?php
require_once '../config/Database.php';
require_once '../db/classes/Session.php';

// Start session
Session::start();

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Function to get all categories with their forums
function getCategoriesWithForums($db) {
    $query = "SELECT c.*, 
              (SELECT COUNT(*) FROM forums f WHERE f.category_id = c.category_id) as forum_count 
              FROM categories c 
              ORDER BY c.display_order ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $categories = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $category_id = $row['category_id'];
        
        // Get forums for this category
        $forum_query = "SELECT f.*, 
                        (SELECT COUNT(*) FROM topics t WHERE t.forum_id = f.forum_id) as topic_count,
                        (SELECT COUNT(*) FROM topics t JOIN posts p ON t.topic_id = p.topic_id WHERE t.forum_id = f.forum_id) as post_count,
                        (SELECT u.username FROM topics t 
                         JOIN users u ON t.last_post_user_id = u.user_id 
                         WHERE t.forum_id = f.forum_id 
                         ORDER BY t.last_post_at DESC LIMIT 1) as last_poster,
                        (SELECT t.title FROM topics t 
                         WHERE t.forum_id = f.forum_id 
                         ORDER BY t.last_post_at DESC LIMIT 1) as last_topic_title,
                        (SELECT t.topic_id FROM topics t 
                         WHERE t.forum_id = f.forum_id 
                         ORDER BY t.last_post_at DESC LIMIT 1) as last_topic_id,
                        (SELECT t.last_post_at FROM topics t 
                         WHERE t.forum_id = f.forum_id 
                         ORDER BY t.last_post_at DESC LIMIT 1) as last_activity
                        FROM forums f 
                        WHERE f.category_id = :category_id 
                        ORDER BY f.display_order ASC";
        
        $forum_stmt = $db->prepare($forum_query);
        $forum_stmt->bindParam(':category_id', $category_id);
        $forum_stmt->execute();
        
        $forums = [];
        while ($forum_row = $forum_stmt->fetch(PDO::FETCH_ASSOC)) {
            $forums[] = $forum_row;
        }
        
        $row['forums'] = $forums;
        $categories[] = $row;
    }
    
    return $categories;
}

// Get categories with forums
$categories = getCategoriesWithForums($db);

// Get total stats
$stats_query = "SELECT 
               (SELECT COUNT(*) FROM users) as user_count,
               (SELECT COUNT(*) FROM topics) as topic_count,
               (SELECT COUNT(*) FROM posts) as post_count,
               (SELECT COUNT(*) FROM categories) as category_count,
               (SELECT COUNT(*) FROM forums) as forum_count";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get latest registered user
$latest_user_query = "SELECT username FROM users ORDER BY created_at DESC LIMIT 1";
$latest_user_stmt = $db->prepare($latest_user_query);
$latest_user_stmt->execute();
$latest_user = $latest_user_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("../parts/head.php")?>
    <title>Forums - CodeHub</title>
</head>
<body>
    <?php require "../parts/header.php" ?>
    
    <main>
        <div class="container py-5">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/codehub/index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Forums</li>
                </ol>
            </nav>
            
            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-9">
                    <div class="mb-4">
                        <h1 class="mb-3"><i class="fas fa-comments me-2"></i>Forums</h1>
                        <p class="lead">Browse our development forums and join the conversation.</p>
                    </div>
                    
                    <?php if (empty($categories)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No categories or forums have been created yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <div class="card shadow mb-4">
                                <div class="card-header bg-dark text-white">
                                    <h5 class="mb-0">
                                        <?php if (!empty($category['icon'])): ?>
                                            <i class="<?php echo htmlspecialchars($category['icon']); ?> me-2"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </h5>
                                </div>
                                
                                <?php if (empty($category['forums'])): ?>
                                    <div class="card-body">
                                        <p class="text-muted mb-0">No forums in this category yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 55%">Forum</th>
                                                    <th class="text-center d-none d-md-table-cell">Topics</th>
                                                    <th class="text-center d-none d-md-table-cell">Posts</th>
                                                    <th class="d-none d-lg-table-cell">Last Post</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($category['forums'] as $forum): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="forum-icon me-3">
                                                                    <?php if (!empty($forum['icon'])): ?>
                                                                        <i class="<?php echo htmlspecialchars($forum['icon']); ?> fa-2x text-primary"></i>
                                                                    <?php else: ?>
                                                                        <i class="fas fa-comments fa-2x text-primary"></i>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div>
                                                                    <h5 class="mb-1">
                                                                        <a href="forum.php?id=<?php echo $forum['forum_id']; ?>" class="text-decoration-none">
                                                                            <?php echo htmlspecialchars($forum['name']); ?>
                                                                        </a>
                                                                    </h5>
                                                                    <p class="mb-0 text-muted small">
                                                                        <?php echo htmlspecialchars($forum['description']); ?>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="text-center align-middle d-none d-md-table-cell">
                                                            <?php echo number_format($forum['topic_count']); ?>
                                                        </td>
                                                        <td class="text-center align-middle d-none d-md-table-cell">
                                                            <?php echo number_format($forum['post_count']); ?>
                                                        </td>
                                                        <td class="align-middle d-none d-lg-table-cell">
                                                            <?php if (!empty($forum['last_topic_id'])): ?>
                                                                <div class="small">
                                                                    <a href="topic.php?id=<?php echo $forum['last_topic_id']; ?>" class="text-decoration-none">
                                                                        <?php echo htmlspecialchars(substr($forum['last_topic_title'], 0, 30)) . (strlen($forum['last_topic_title']) > 30 ? '...' : ''); ?>
                                                                    </a>
                                                                    <div class="text-muted">
                                                                        <small>
                                                                            by <a href="profile.php?id=<?php echo $forum['last_poster']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($forum['last_poster']); ?></a>
                                                                            <br>
                                                                            <?php echo !empty($forum['last_activity']) ? date('M d, Y g:i a', strtotime($forum['last_activity'])) : 'No activity'; ?>
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <small class="text-muted">No posts yet</small>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Add New Forum button for Admins -->
                    <?php if (Session::isAdmin()): ?>
                        <div class="text-end mt-3">
                            <a href="admin/manage-forums.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>Manage Forums
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-3">
                    <!-- Forum Statistics -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistics</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Members
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($stats['user_count']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Topics
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($stats['topic_count']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Posts
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($stats['post_count']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Categories
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($stats['category_count']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Forums
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($stats['forum_count']); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Online Users -->
                    
                    
                    <!-- Forum Info -->
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Forum Info</h5>
                        </div>
                        <div class="card-body">
                            <p>
                                Welcome to our developer forums! Join our community to ask questions, share knowledge, and connect with fellow developers.
                            </p>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../parts/footer.php" ?>
</body>
</html>