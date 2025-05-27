<?php
require_once '../../config/Database.php';
require_once '../../db/classes/User.php';
require_once '../../db/classes/Session.php';

// Start session
Session::start();

// Check if user is logged in
if (!Session::isLoggedIn()) {
    Session::setFlash('error', 'You must be logged in to view profiles');
    header('Location: login.php');
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize variables
$user_id = isset($_GET['id']) ? intval($_GET['id']) : Session::get('user_id');
$user = new User($db);
$profile = $user->getUserById($user_id);

// If user doesn't exist, redirect to members page
if (!$profile) {
    Session::setFlash('error', 'User not found');
    header('Location: members.php');
    exit;
}

// Get user statistics
$user->user_id = $user_id;
$stats = $user->getUserStats();

// Function to get recent topics by user
function getRecentTopicsByUser($db, $user_id, $limit = 3) {
    $query = "SELECT t.*, f.name as forum_name, f.forum_id,
              (SELECT COUNT(*) FROM posts p WHERE p.topic_id = t.topic_id) - 1 as reply_count
              FROM topics t
              JOIN forums f ON t.forum_id = f.forum_id
              WHERE t.user_id = :user_id
              ORDER BY t.created_at DESC
              LIMIT :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get recent posts by user
function getRecentPostsByUser($db, $user_id, $limit = 3) {
    $query = "SELECT p.*, t.title as topic_title, t.topic_id, f.name as forum_name
              FROM posts p
              JOIN topics t ON p.topic_id = t.topic_id
              JOIN forums f ON t.forum_id = f.forum_id
              WHERE p.user_id = :user_id
              ORDER BY p.created_at DESC
              LIMIT :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get user badges/achievements
function getUserBadges($db, $user_id) {
    $badges = [];
    
    // Get user stats for badge calculation
    $stats_query = "SELECT 
                   (SELECT COUNT(*) FROM topics WHERE user_id = :user_id) as topic_count,
                   (SELECT COUNT(*) FROM posts WHERE user_id = :user_id) as post_count,
                   (SELECT COUNT(*) FROM posts WHERE user_id = :user_id AND is_solution = 1) as solution_count,
                   reputation
                   FROM users WHERE user_id = :user_id";
    
    $stmt = $db->prepare($stats_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        // Topic creator badges
        if ($stats['topic_count'] >= 50) {
            $badges[] = ['name' => 'Topic Master', 'class' => 'bg-warning text-dark', 'icon' => 'fas fa-crown'];
        } elseif ($stats['topic_count'] >= 10) {
            $badges[] = ['name' => 'Topic Creator', 'class' => 'bg-info', 'icon' => 'fas fa-plus-circle'];
        }
        
        // Post count badges
        if ($stats['post_count'] >= 1000) {
            $badges[] = ['name' => 'Forum Legend', 'class' => 'bg-danger', 'icon' => 'fas fa-trophy'];
        } elseif ($stats['post_count'] >= 500) {
            $badges[] = ['name' => 'Forum Expert', 'class' => 'bg-warning text-dark', 'icon' => 'fas fa-star'];
        } elseif ($stats['post_count'] >= 100) {
            $badges[] = ['name' => 'Active Member', 'class' => 'bg-success', 'icon' => 'fas fa-check-circle'];
        } elseif ($stats['post_count'] >= 10) {
            $badges[] = ['name' => 'Contributor', 'class' => 'bg-primary', 'icon' => 'fas fa-comments'];
        }
        
        // Solution provider badge
        if ($stats['solution_count'] >= 5) {
            $badges[] = ['name' => 'Problem Solver', 'class' => 'bg-success', 'icon' => 'fas fa-lightbulb'];
        }
        
        // Reputation badges
        if ($stats['reputation'] >= 1000) {
            $badges[] = ['name' => 'Highly Reputed', 'class' => 'bg-warning text-dark', 'icon' => 'fas fa-medal'];
        } elseif ($stats['reputation'] >= 500) {
            $badges[] = ['name' => 'Well Known', 'class' => 'bg-info', 'icon' => 'fas fa-thumbs-up'];
        }
        
        // Helpful badge (posts with positive engagement)
        if ($stats['post_count'] >= 20) {
            $badges[] = ['name' => 'Helpful', 'class' => 'bg-secondary', 'icon' => 'fas fa-heart'];
        }
    }
    
    return $badges;
}

// Function to get additional user statistics
function getExtendedUserStats($db, $user_id) {
    $query = "SELECT 
              (SELECT COUNT(*) FROM topics WHERE user_id = :user_id) as total_topics,
              (SELECT SUM(view_count) FROM topics WHERE user_id = :user_id) as total_topic_views,
              (SELECT COUNT(*) FROM posts WHERE user_id = :user_id AND is_solution = 1) as solutions_provided,
              (SELECT COUNT(DISTINCT topic_id) FROM posts WHERE user_id = :user_id) as topics_participated,
              (SELECT COUNT(*) FROM topics WHERE user_id = :user_id AND is_sticky = 1) as sticky_topics,
              u.last_login,
              DATEDIFF(NOW(), u.created_at) as days_registered
              FROM users u WHERE u.user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get recent topics and posts
$recent_topics = getRecentTopicsByUser($db, $user_id, 3);
$recent_posts = getRecentPostsByUser($db, $user_id, 3);

// Get user badges
$badges = getUserBadges($db, $user_id);

// Get extended statistics
$extended_stats = getExtendedUserStats($db, $user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("../../parts/head.php")?>
    <title><?php echo htmlspecialchars($profile['username']); ?>'s Profile - CodeHub</title>
</head>
<body>
    <?php require "../../parts/header.php" ?>
    
    <main>
        <div class="container py-5">
            <!-- Profile Header -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 150px; height: 150px; font-size: 64px;">
                                        <?php echo strtoupper(substr($profile['username'], 0, 1)); ?>
                                    </div>
                                    
                                    <?php if (Session::get('user_id') == $user_id): ?>
                                        <a href="settings.php" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i>Edit Profile
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($extended_stats['last_login']): ?>
                                        <div class="mt-3">
                                            <small class="text-muted">
                                                Last seen: <?php echo date('M d, Y g:i a', strtotime($extended_stats['last_login'])); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <h2><?php echo htmlspecialchars($profile['username']); ?></h2>
                                    <p class="text-muted">
                                        <i class="fas fa-user-shield me-1"></i> 
                                        <?php echo ucfirst(htmlspecialchars($profile['role'])); ?>
                                        <span class="mx-2">|</span>
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        Joined <?php echo date('F Y', strtotime($profile['created_at'])); ?>
                                        <span class="mx-2">|</span>
                                        <i class="fas fa-star me-1"></i>
                                        Reputation: <?php echo number_format($profile['reputation']); ?>
                                        <?php if ($extended_stats['days_registered']): ?>
                                            <span class="mx-2">|</span>
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo $extended_stats['days_registered']; ?> days as member
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div class="mt-3">
                                        <h5>Bio</h5>
                                        <p><?php echo nl2br(htmlspecialchars($profile['bio'] ?? 'No bio provided.')); ?></p>
                                    </div>
                                    
                                    <?php if (!empty($profile['signature'])): ?>
                                        <div class="mt-3">
                                            <h5>Signature</h5>
                                            <div class="border-top pt-2">
                                                <?php echo nl2br(htmlspecialchars($profile['signature'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics and Activities -->
            <div class="row">
                <!-- Statistics -->
                <div class="col-md-3">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistics</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Topics Created
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($extended_stats['total_topics']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Posts Made
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($stats['post_count']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Solutions Provided
                                    <span class="badge bg-success rounded-pill"><?php echo number_format($extended_stats['solutions_provided']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Topics Participated
                                    <span class="badge bg-info rounded-pill"><?php echo number_format($extended_stats['topics_participated']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Total Topic Views
                                    <span class="badge bg-warning text-dark rounded-pill"><?php echo number_format($extended_stats['total_topic_views'] ?? 0); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Reputation
                                    <span class="badge bg-info rounded-pill"><?php echo number_format($profile['reputation']); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Badges Section -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-award me-2"></i>Badges</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($badges)): ?>
                                <?php foreach ($badges as $badge): ?>
                                    <div class="mb-2">
                                        <span class="badge rounded-pill <?php echo $badge['class']; ?>">
                                            <i class="<?php echo $badge['icon']; ?> me-1"></i><?php echo $badge['name']; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">No badges earned yet. Keep participating to earn badges!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities -->
                <div class="col-md-9">
                    <!-- Recent Topics -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Topics</h5>
                            <a href="my-topics.php?id=<?php echo $user_id; ?>" class="btn btn-sm btn-outline-light">
                                View All (<?php echo number_format($extended_stats['total_topics']); ?>)
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recent_topics)): ?>
                                <div class="p-4 text-center">
                                    <i class="fas fa-comments fa-2x text-muted mb-2"></i>
                                    <p class="mb-0 text-muted">No topics created yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_topics as $topic): ?>
                                        <a href="topic.php?id=<?php echo $topic['topic_id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">
                                                    <?php echo htmlspecialchars($topic['title']); ?>
                                                    <?php if ($topic['is_sticky']): ?>
                                                        <span class="badge bg-warning text-dark ms-2">Sticky</span>
                                                    <?php endif; ?>
                                                    <?php if ($topic['is_locked']): ?>
                                                        <span class="badge bg-secondary ms-2">Locked</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($topic['created_at'])); ?></small>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-folder me-1"></i> 
                                                <?php echo htmlspecialchars($topic['forum_name']); ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-comments me-1"></i>
                                                <?php echo $topic['reply_count']; ?> replies
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-eye me-1"></i>
                                                <?php echo number_format($topic['view_count']); ?> views
                                            </small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Posts -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Recent Posts</h5>
                            <span class="badge bg-secondary"><?php echo number_format($stats['post_count']); ?> total</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recent_posts)): ?>
                                <div class="p-4 text-center">
                                    <i class="fas fa-comment fa-2x text-muted mb-2"></i>
                                    <p class="mb-0 text-muted">No posts created yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_posts as $post): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">
                                                    <a href="topic.php?id=<?php echo $post['topic_id']; ?>#post-<?php echo $post['post_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($post['topic_title']); ?>
                                                    </a>
                                                    <?php if ($post['is_solution']): ?>
                                                        <span class="badge bg-success ms-2">Solution</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted"><?php echo date('M d, Y g:i a', strtotime($post['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars(substr($post['content'], 0, 150)) . (strlen($post['content']) > 150 ? '...' : ''); ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-folder me-1"></i>
                                                <?php echo htmlspecialchars($post['forum_name']); ?>
                                                <span class="mx-2">•</span>
                                                <a href="topic.php?id=<?php echo $post['topic_id']; ?>#post-<?php echo $post['post_id']; ?>" class="text-decoration-none">
                                                    <i class="fas fa-external-link-alt me-1"></i>View Post
                                                </a>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Activity Summary -->
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Activity Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3 mb-3">
                                    <div class="border rounded p-3">
                                        <h4 class="text-primary mb-1"><?php echo number_format($extended_stats['total_topics']); ?></h4>
                                        <small class="text-muted">Topics Started</small>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="border rounded p-3">
                                        <h4 class="text-success mb-1"><?php echo number_format($stats['post_count']); ?></h4>
                                        <small class="text-muted">Posts Made</small>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="border rounded p-3">
                                        <h4 class="text-warning mb-1"><?php echo number_format($extended_stats['total_topic_views'] ?? 0); ?></h4>
                                        <small class="text-muted">Topic Views</small>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="border rounded p-3">
                                        <h4 class="text-info mb-1"><?php echo number_format($profile['reputation']); ?></h4>
                                        <small class="text-muted">Reputation</small>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($extended_stats['solutions_provided'] > 0): ?>
                                <div class="alert alert-success mt-3">
                                    <i class="fas fa-lightbulb me-2"></i>
                                    <strong>Helpful Member!</strong> This user has provided <?php echo number_format($extended_stats['solutions_provided']); ?> solution<?php echo $extended_stats['solutions_provided'] > 1 ? 's' : ''; ?> to community problems.
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($extended_stats['sticky_topics'] > 0): ?>
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-thumbtack me-2"></i>
                                    <strong>Quality Contributor!</strong> This user has <?php echo number_format($extended_stats['sticky_topics']); ?> sticky topic<?php echo $extended_stats['sticky_topics'] > 1 ? 's' : ''; ?>.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../../parts/footer.php" ?>
</body>
</html>