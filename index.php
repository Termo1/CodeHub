<?php
require_once "config/Database.php";
require_once "db/classes/Session.php";

// Start session
Session::start();

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Function to get popular categories with real data
function getPopularCategories($db, $limit = 3) {
    $query = "SELECT c.*, 
              COUNT(t.topic_id) as topic_count,
              (SELECT name FROM forums WHERE category_id = c.category_id ORDER BY topic_count DESC LIMIT 1) as popular_forum
              FROM categories c
              LEFT JOIN forums f ON c.category_id = f.category_id
              LEFT JOIN topics t ON f.forum_id = t.forum_id
              GROUP BY c.category_id
              ORDER BY topic_count DESC
              LIMIT :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get recent discussions
function getRecentDiscussions($db, $limit = 3) {
    $query = "SELECT t.*, u.username, f.name as forum_name,
              (SELECT COUNT(*) FROM posts p WHERE p.topic_id = t.topic_id) - 1 as reply_count,
              DATEDIFF(NOW(), t.created_at) as days_ago
              FROM topics t
              JOIN users u ON t.user_id = u.user_id
              JOIN forums f ON t.forum_id = f.forum_id
              WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              ORDER BY t.last_post_at DESC
              LIMIT :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get community statistics
function getCommunityStats($db) {
    $query = "SELECT 
              (SELECT COUNT(*) FROM users WHERE is_active = 1) as member_count,
              (SELECT COUNT(*) FROM topics) as topic_count,
              (SELECT COUNT(*) FROM posts) as post_count,
              (SELECT COUNT(*) FROM categories) as category_count,
              (SELECT COUNT(*) FROM forums) as forum_count,
              (SELECT username FROM users ORDER BY created_at DESC LIMIT 1) as newest_member,
              (SELECT SUM(view_count) FROM topics) as total_views";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get top contributors this month
function getTopContributors($db, $limit = 5) {
    $query = "SELECT u.username, u.user_id, u.reputation,
              COUNT(p.post_id) as posts_this_month,
              COUNT(DISTINCT t.topic_id) as topics_this_month,
              (COUNT(p.post_id) + COUNT(DISTINCT t.topic_id) * 2) as activity_score
              FROM users u
              LEFT JOIN posts p ON u.user_id = p.user_id AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              LEFT JOIN topics t ON u.user_id = t.user_id AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              WHERE u.is_active = 1
              GROUP BY u.user_id, u.username, u.reputation
              HAVING activity_score > 0
              ORDER BY activity_score DESC
              LIMIT :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Get data
$popular_categories = getPopularCategories($db, 3);
$recent_discussions = getRecentDiscussions($db, 3);
$stats = getCommunityStats($db);
$top_contributors = getTopContributors($db, 5);

// Fallback data for categories if none exist
if (empty($popular_categories)) {
    $popular_categories = [
        ['name' => 'JavaScript', 'description' => 'Discuss JavaScript frameworks, libraries, and coding techniques.', 'topic_count' => 0],
        ['name' => 'PHP', 'description' => 'Get help with PHP programming, frameworks, and backend development.', 'topic_count' => 0],
        ['name' => 'Python', 'description' => 'Everything Python - from web development to data science.', 'topic_count' => 0]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("parts/head.php")?>
    <title>CodeHub - Forum for Developers</title>
</head>
<body>
    <?php require "parts/header.php" ?>
    
    <main>
        <!-- Hero Section -->
        <section class="bg-dark text-white py-5 text-center">
            <div class="container">
                <h1 class="display-4">CodeHub</h1>
                <p class="lead">A community forum for developers to ask questions, share knowledge, and connect with peers</p>
                <div class="mt-4">
                    <a href="pages/forum/forums.php" class="btn btn-primary btn-lg me-2">Browse Forums</a>
                    <?php if (!Session::isLoggedIn()): ?>
                        <a href="pages/auth/register.php" class="btn btn-outline-light btn-lg">Join Now</a>
                    <?php else: ?>
                        <a href="pages/topic/create-topic.php" class="btn btn-outline-light btn-lg">Create Topic</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Featured Categories -->
        <section class="py-5">
            <div class="container">
                <h2 class="mb-4">Popular Categories</h2>
                <div class="row">
                    <?php 
                    $category_icons = ['fas fa-js-square text-warning', 'fas fa-php text-info', 'fab fa-python text-success'];
                    $category_colors = ['warning', 'info', 'success'];
                    foreach ($popular_categories as $index => $category): 
                        $icon = isset($category_icons[$index]) ? $category_icons[$index] : 'fas fa-code text-primary';
                        $color = isset($category_colors[$index]) ? $category_colors[$index] : 'primary';
                    ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="<?php echo $icon; ?> me-2"></i>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </h5>
                                    <p class="card-text"><?php echo htmlspecialchars($category['description'] ?? 'Explore topics in this category.'); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted"><?php echo number_format($category['topic_count']); ?> topics</small>
                                        <?php if (isset($category['category_id'])): ?>
                                            <a href="pages/forum/category.php?id=<?php echo $category['category_id']; ?>" class="btn btn-sm btn-outline-<?php echo $color; ?>">View Topics</a>
                                        <?php else: ?>
                                            <a href="pages/forum/forums.php" class="btn btn-sm btn-outline-<?php echo $color; ?>">Browse Forums</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Recent Discussions -->
        <section class="py-5 bg-light">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Recent Discussions</h2>
                    <a href="pages/forum/forums.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                
                <?php if (empty($recent_discussions)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No recent discussions</h5>
                        <p class="text-muted">Be the first to start a conversation!</p>
                        <?php if (Session::isLoggedIn()): ?>
                            <a href="pages/topic/create-topic.php" class="btn btn-primary">Create First Topic</a>
                        <?php else: ?>
                            <a href="pages/auth/register.php" class="btn btn-primary">Join to Start Discussion</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($recent_discussions as $topic): ?>
                            <a href="pages/topic/topic.php?id=<?php echo $topic['topic_id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1">
                                        <?php echo htmlspecialchars($topic['title']); ?>
                                        <?php if ($topic['is_sticky']): ?>
                                            <span class="badge bg-warning text-dark ms-2">Sticky</span>
                                        <?php endif; ?>
                                    </h5>
                                    <small class="text-muted">
                                        <?php 
                                        if ($topic['days_ago'] == 0) {
                                            echo 'Today';
                                        } elseif ($topic['days_ago'] == 1) {
                                            echo '1 day ago';
                                        } else {
                                            echo $topic['days_ago'] . ' days ago';
                                        }
                                        ?>
                                    </small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars(substr($topic['content'], 0, 120)) . (strlen($topic['content']) > 120 ? '...' : ''); ?></p>
                                <small class="text-muted">
                                    Posted by: <?php echo htmlspecialchars($topic['username']); ?> • 
                                    <?php echo number_format($topic['reply_count']); ?> replies • 
                                    in <?php echo htmlspecialchars($topic['forum_name']); ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Community Stats and Top Contributors -->
        <section class="py-5">
            <div class="container">
                <div class="row">
                    <!-- Community Statistics -->
                    <div class="col-md-8">
                        <h2 class="text-center mb-5">Community Statistics</h2>
                        <div class="row text-center">
                            <div class="col-md-3 mb-4">
                                <div class="border rounded p-4">
                                    <h3 class="display-4 fw-bold text-primary"><?php echo number_format($stats['member_count']); ?></h3>
                                    <p class="text-muted mb-0">Members</p>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="border rounded p-4">
                                    <h3 class="display-4 fw-bold text-success"><?php echo number_format($stats['topic_count']); ?></h3>
                                    <p class="text-muted mb-0">Topics</p>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="border rounded p-4">
                                    <h3 class="display-4 fw-bold text-info"><?php echo number_format($stats['post_count']); ?></h3>
                                    <p class="text-muted mb-0">Posts</p>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="border rounded p-4">
                                    <h3 class="display-4 fw-bold text-warning"><?php echo number_format($stats['total_views'] ?? 0); ?></h3>
                                    <p class="text-muted mb-0">Total Views</p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($stats['newest_member']): ?>
                            <div class="text-center mt-4">
                                <p class="text-muted">
                                    <strong>Newest Member:</strong> 
                                    <a href="pages/user/profile.php?username=<?php echo urlencode($stats['newest_member']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($stats['newest_member']); ?>
                                    </a>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Top Contributors -->
                    <div class="col-md-4">
                        <div class="card shadow">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Contributors This Month</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($top_contributors)): ?>
                                    <p class="text-muted text-center">No activity this month yet.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($top_contributors as $index => $contributor): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                <div>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-<?php echo $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : ($index == 2 ? 'dark' : 'primary')); ?> me-2">
                                                            <?php echo $index + 1; ?>
                                                        </span>
                                                        <a href="pages/user/profile.php?id=<?php echo $contributor['user_id']; ?>" class="text-decoration-none fw-bold">
                                                            <?php echo htmlspecialchars($contributor['username']); ?>
                                                        </a>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo $contributor['posts_this_month']; ?> posts, 
                                                        <?php echo $contributor['topics_this_month']; ?> topics
                                                    </small>
                                                </div>
                                                <span class="badge bg-info rounded-pill"><?php echo number_format($contributor['reputation']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Access Section -->
        <?php if (Session::isLoggedIn()): ?>
            <section class="py-5 bg-light">
                <div class="container">
                    <h2 class="text-center mb-4">Quick Access</h2>
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="pages/topic/create-topic.php" class="btn btn-primary w-100">
                                        <i class="fas fa-plus-circle mb-2 d-block"></i>
                                        Create Topic
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="pages/user/my-topics.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-list mb-2 d-block"></i>
                                        My Topics
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="pages/user/profile.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-user mb-2 d-block"></i>
                                        My Profile
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="pages/forum/forums.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-comments mb-2 d-block"></i>
                                        Browse Forums
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php require "parts/footer.php" ?>
</body>
</html>