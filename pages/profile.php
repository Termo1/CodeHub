<?php
require_once '../config/Database.php';
require_once '../db/classes/User.php';
require_once '../db/classes/Session.php';
//require_once '../db/classes/Topic.php';
//require_once '../db/classes/Post.php';

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

// Get recent topics
// $topic = new Topic($db);
// $recent_topics = $topic->getRecentTopicsByUser($user_id, 2);

// Get recent posts
// $post = new Post($db);
// $recent_posts = $post->getRecentPostsByUser($user_id, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("../parts/head.php")?>
    <title><?php echo htmlspecialchars($profile['username']); ?>'s Profile - CodeHub</title>
</head>
<body>
    <?php require "../parts/header.php" ?>
    
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
                                    Topics
                                    <span class="badge bg-primary rounded-pill"><?php echo $stats['topic_count']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Posts
                                    <span class="badge bg-primary rounded-pill"><?php echo $stats['post_count']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Solutions
                                    <span class="badge bg-success rounded-pill">7</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Reputation
                                    <span class="badge bg-info rounded-pill"><?php echo $profile['reputation']; ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-award me-2"></i>Badges</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <span class="badge rounded-pill bg-warning text-dark">
                                    <i class="fas fa-star me-1"></i>Top Contributor
                                </span>
                            </div>
                            <div class="mb-2">
                                <span class="badge rounded-pill bg-success">
                                    <i class="fas fa-check-circle me-1"></i>Problem Solver
                                </span>
                            </div>
                            <div class="mb-2">
                                <span class="badge rounded-pill bg-info">
                                    <i class="fas fa-code me-1"></i>JavaScript Expert
                                </span>
                            </div>
                            <div class="mb-2">
                                <span class="badge rounded-pill bg-secondary">
                                    <i class="fas fa-heart me-1"></i>Helpful
                                </span>
                            </div>
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
                                View All
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recent_topics)): ?>
                                <div class="p-4 text-center">
                                    <p class="mb-0 text-muted">No topics created yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_topics as $topic): ?>
                                        <a href="topic.php?id=<?php echo $topic['topic_id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($topic['title']); ?></h6>
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($topic['created_at'])); ?></small>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-folder me-1"></i> 
                                                <?php echo htmlspecialchars($topic['forum_name']); ?>
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
                            <a href="my-posts.php?id=<?php echo $user_id; ?>" class="btn btn-sm btn-outline-light">
                                View All
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recent_posts)): ?>
                                <div class="p-4 text-center">
                                    <p class="mb-0 text-muted">No posts created yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_posts as $post): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">
                                                    <a href="topic.php?id=<?php echo $post['topic_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($post['topic_title']); ?>
                                                    </a>
                                                </h6>
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars(substr($post['content'], 0, 150)) . (strlen($post['content']) > 150 ? '...' : ''); ?></p>
                                            <small class="text-muted">
                                                <a href="topic.php?id=<?php echo $post['topic_id']; ?>#post-<?php echo $post['post_id']; ?>" class="text-decoration-none">
                                                    <i class="fas fa-external-link-alt me-1"></i>View Full Post
                                                </a>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../parts/footer.php" ?>
</body>
</html>