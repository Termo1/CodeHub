<?php
require_once '../../config/Database.php';
require_once '../../db/classes/Session.php';

// Start session
Session::start();

// Check if user is logged in
if (!Session::isLoggedIn()) {
    Session::setFlash('error', 'You must be logged in to view your topics');
    header('Location: login.php');
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get user ID from session or URL parameter
$user_id = isset($_GET['id']) ? intval($_GET['id']) : Session::get('user_id');
$viewing_own_topics = ($user_id == Session::get('user_id'));

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$from_record_num = ($records_per_page * $page) - $records_per_page;

// Function to get user details
function getUserDetails($db, $user_id) {
    $query = "SELECT username FROM users WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get user's topics with pagination
function getUserTopics($db, $user_id, $from_record_num, $records_per_page) {
    $query = "SELECT t.*, f.name as forum_name, f.forum_id, c.name as category_name, c.category_id,
              (SELECT COUNT(*) FROM posts p WHERE p.topic_id = t.topic_id) - 1 as reply_count,
              (SELECT username FROM users WHERE user_id = t.last_post_user_id) as last_poster
              FROM topics t
              JOIN forums f ON t.forum_id = f.forum_id
              JOIN categories c ON f.category_id = c.category_id
              WHERE t.user_id = :user_id
              ORDER BY t.created_at DESC
              LIMIT :from_record_num, :records_per_page";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':from_record_num', $from_record_num, PDO::PARAM_INT);
    $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to count user's total topics
function countUserTopics($db, $user_id) {
    $query = "SELECT COUNT(*) as total FROM topics WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $row['total'];
}

// Function to get user statistics
function getUserTopicStats($db, $user_id) {
    $query = "SELECT 
              COUNT(*) as total_topics,
              SUM(view_count) as total_views,
              SUM(reply_count) as total_replies,
              SUM(CASE WHEN is_sticky = 1 THEN 1 ELSE 0 END) as sticky_topics,
              MAX(created_at) as latest_topic_date
              FROM topics WHERE user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get user details
$user = getUserDetails($db, $user_id);

// If user doesn't exist, redirect
if (!$user) {
    Session::setFlash('error', 'User not found');
    header('Location: forums.php');
    exit;
}

// Get topics
$topics = getUserTopics($db, $user_id, $from_record_num, $records_per_page);

// Get total topics count for pagination
$total_topics = countUserTopics($db, $user_id);
$total_pages = ceil($total_topics / $records_per_page);

// Get user statistics
$stats = getUserTopicStats($db, $user_id);

// Check for flash messages
$error = Session::getFlash('error');
$success = Session::getFlash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("../../parts/head.php")?>
    <title><?php echo $viewing_own_topics ? 'My Topics' : htmlspecialchars($user['username']) . "'s Topics"; ?> - CodeHub</title>
</head>
<body>
    <?php require "../../parts/header.php" ?>
    
    <main>
        <div class="container py-5">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/codehub/index.php">Home</a></li>
                    <?php if (!$viewing_own_topics): ?>
                        <li class="breadcrumb-item"><a href="profile.php?id=<?php echo $user_id; ?>"><?php echo htmlspecialchars($user['username']); ?>'s Profile</a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page">
                        <?php echo $viewing_own_topics ? 'My Topics' : htmlspecialchars($user['username']) . "'s Topics"; ?>
                    </li>
                </ol>
            </nav>
            
            <!-- Alert Messages -->
            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Header -->
                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <h1 class="mb-3">
                                <i class="fas fa-list me-2 text-primary"></i>
                                <?php echo $viewing_own_topics ? 'My Topics' : htmlspecialchars($user['username']) . "'s Topics"; ?>
                            </h1>
                            <p class="lead">
                                <?php if ($viewing_own_topics): ?>
                                    Manage and view all your forum topics
                                <?php else: ?>
                                    Topics created by <?php echo htmlspecialchars($user['username']); ?>
                                <?php endif; ?>
                            </p>
                            
                            <?php if ($viewing_own_topics): ?>
                                <a href="create-topic.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>Create New Topic
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Topics List -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Topics</h5>
                            <span>Total: <?php echo number_format($total_topics); ?></span>
                        </div>
                        
                        <?php if (empty($topics)): ?>
                            <div class="card-body text-center py-5">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No topics found</h5>
                                <p class="text-muted">
                                    <?php if ($viewing_own_topics): ?>
                                        You haven't created any topics yet. <a href="create-topic.php">Create your first topic</a> to get started!
                                    <?php else: ?>
                                        This user hasn't created any topics yet.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50%">Topic</th>
                                            <th class="text-center d-none d-md-table-cell">Forum</th>
                                            <th class="text-center d-none d-md-table-cell">Replies</th>
                                            <th class="text-center d-none d-md-table-cell">Views</th>
                                            <th class="d-none d-lg-table-cell">Last Post</th>
                                            <?php if ($viewing_own_topics): ?>
                                                <th class="text-center">Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topics as $topic): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="topic-icon me-3">
                                                            <?php if ($topic['is_sticky']): ?>
                                                                <i class="fas fa-thumbtack fa-lg text-warning" title="Sticky"></i>
                                                            <?php elseif ($topic['is_locked']): ?>
                                                                <i class="fas fa-lock fa-lg text-secondary" title="Locked"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-comments fa-lg text-primary"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <a href="topic.php?id=<?php echo $topic['topic_id']; ?>" class="text-decoration-none">
                                                                    <?php echo htmlspecialchars($topic['title']); ?>
                                                                </a>
                                                                <?php if ($topic['is_sticky']): ?>
                                                                    <span class="badge bg-warning text-dark ms-2">Sticky</span>
                                                                <?php endif; ?>
                                                                <?php if ($topic['is_locked']): ?>
                                                                    <span class="badge bg-secondary ms-2">Locked</span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <small class="text-muted">
                                                                Created on <?php echo date('M d, Y g:i a', strtotime($topic['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center align-middle d-none d-md-table-cell">
                                                    <a href="forum.php?id=<?php echo $topic['forum_id']; ?>" class="badge bg-primary text-decoration-none">
                                                        <?php echo htmlspecialchars($topic['forum_name']); ?>
                                                    </a>
                                                </td>
                                                <td class="text-center align-middle d-none d-md-table-cell">
                                                    <?php echo number_format($topic['reply_count']); ?>
                                                </td>
                                                <td class="text-center align-middle d-none d-md-table-cell">
                                                    <?php echo number_format($topic['view_count']); ?>
                                                </td>
                                                <td class="align-middle d-none d-lg-table-cell">
                                                    <?php if ($topic['last_poster']): ?>
                                                        <div class="small">
                                                            <a href="profile.php?id=<?php echo $topic['last_post_user_id']; ?>" class="text-decoration-none">
                                                                <?php echo htmlspecialchars($topic['last_poster']); ?>
                                                            </a>
                                                            <div class="text-muted">
                                                                <small><?php echo date('M d, Y g:i a', strtotime($topic['last_post_at'])); ?></small>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <small class="text-muted">No replies</small>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($viewing_own_topics): ?>
                                                    <td class="text-center align-middle">
                                                        <div class="btn-group">
                                                            <a href="edit-topic.php?id=<?php echo $topic['topic_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="delete-topic.php?id=<?php echo $topic['topic_id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this topic?')">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $page <= 1 ? '#' : 'my-topics.php?id=' . $user_id . '&page=' . ($page - 1); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="my-topics.php?id=<?php echo $user_id; ?>&page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $page >= $total_pages ? '#' : 'my-topics.php?id=' . $user_id . '&page=' . ($page + 1); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-3">
                    <!-- Topic Statistics -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistics</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Total Topics
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($stats['total_topics']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Total Views
                                    <span class="badge bg-success rounded-pill"><?php echo number_format($stats['total_views']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Total Replies
                                    <span class="badge bg-info rounded-pill"><?php echo number_format($stats['total_replies']); ?></span>
                                </li>
                                <?php if ($stats['sticky_topics'] > 0): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Sticky Topics
                                        <span class="badge bg-warning text-dark rounded-pill"><?php echo number_format($stats['sticky_topics']); ?></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            
                            <?php if ($stats['latest_topic_date']): ?>
                                <div class="mt-3 pt-3 border-top">
                                    <small class="text-muted">
                                        <strong>Latest Topic:</strong><br>
                                        <?php echo date('M d, Y g:i a', strtotime($stats['latest_topic_date'])); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <?php if ($viewing_own_topics): ?>
                                    <a href="create-topic.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-plus-circle me-2"></i>Create New Topic
                                    </a>
                                    <a href="profile.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-user me-2"></i>View My Profile
                                    </a>
                                <?php else: ?>
                                    <a href="profile.php?id=<?php echo $user_id; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-user me-2"></i>View Profile
                                    </a>
                                <?php endif; ?>
                                <a href="forums.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-comments me-2"></i>Browse Forums
                                </a>
                                <a href="topics.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-list me-2"></i>Recent Topics
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Topic Categories -->
                    <?php if (!empty($topics)): ?>
                        <div class="card shadow">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="fas fa-folder me-2"></i>Categories</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Group topics by category
                                $categories = [];
                                foreach ($topics as $topic) {
                                    $cat_name = $topic['category_name'];
                                    if (!isset($categories[$cat_name])) {
                                        $categories[$cat_name] = ['count' => 0, 'id' => $topic['category_id']];
                                    }
                                    $categories[$cat_name]['count']++;
                                }
                                ?>
                                
                                <div class="list-group list-group-flush">
                                    <?php foreach ($categories as $cat_name => $cat_data): ?>
                                        <a href="category.php?id=<?php echo $cat_data['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($cat_name); ?>
                                            <span class="badge bg-primary rounded-pill"><?php echo $cat_data['count']; ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../../parts/footer.php" ?>
</body>
</html>