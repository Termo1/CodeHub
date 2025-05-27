<?php
require_once '../../config/Database.php';
require_once '../../db/classes/Session.php';

// Start session
Session::start();

// Initialize error variable
$error = '';

// Check if category ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    Session::setFlash('error', 'Category ID is required');
    header('Location: forums.php');
    exit;
}

$category_id = intval($_GET['id']);

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 15;
$from_record_num = ($records_per_page * $page) - $records_per_page;

// Function to get category details
function getCategoryDetails($db, $category_id) {
    $query = "SELECT * FROM categories WHERE category_id = :category_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':category_id', $category_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get forums in a category
function getForumsInCategory($db, $category_id) {
    $query = "SELECT f.*, 
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
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':category_id', $category_id);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get recent topics in category with pagination
function getRecentTopicsInCategory($db, $category_id, $from_record_num, $records_per_page) {
    $query = "SELECT t.*, f.name as forum_name, f.forum_id,
              u.username as creator_username,
              (SELECT COUNT(*) FROM posts p WHERE p.topic_id = t.topic_id) - 1 as reply_count,
              (SELECT username FROM users WHERE user_id = t.last_post_user_id) as last_poster
              FROM topics t
              JOIN forums f ON t.forum_id = f.forum_id
              JOIN users u ON t.user_id = u.user_id
              WHERE f.category_id = :category_id
              ORDER BY t.is_sticky DESC, t.last_post_at DESC
              LIMIT :from_record_num, :records_per_page";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':category_id', $category_id);
    $stmt->bindParam(':from_record_num', $from_record_num, PDO::PARAM_INT);
    $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to count total topics in category for pagination
function countTopicsInCategory($db, $category_id) {
    $query = "SELECT COUNT(*) as total 
              FROM topics t 
              JOIN forums f ON t.forum_id = f.forum_id 
              WHERE f.category_id = :category_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':category_id', $category_id);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $row['total'];
}

// Get category details
$category = getCategoryDetails($db, $category_id);

// If category doesn't exist, redirect to forums page
if (!$category) {
    Session::setFlash('error', 'Category not found');
    header('Location: forums.php');
    exit;
}

// Get forums in category
$forums = getForumsInCategory($db, $category_id);

// Get recent topics in category
$topics = getRecentTopicsInCategory($db, $category_id, $from_record_num, $records_per_page);

// Get total topics count for pagination
$total_topics = countTopicsInCategory($db, $category_id);
$total_pages = ceil($total_topics / $records_per_page);

// Check for flash messages
if (Session::hasFlash('error')) {
    $error = Session::getFlash('error');
}
if (Session::hasFlash('success')) {
    $success = Session::getFlash('success');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("../../parts/head.php")?>
    <title><?php echo htmlspecialchars($category['name']); ?> - CodeHub</title>
</head>
<body>
    <?php require "../../parts/header.php" ?>
    
    <main>
        <div class="container py-5">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/codehub/index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="forums.php">Forums</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($category['name']); ?></li>
                </ol>
            </nav>
            
            <!-- Alert Messages -->
            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Category Header -->
                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <h1 class="mb-3">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </h1>
                            <?php if (!empty($category['description'])): ?>
                                <p class="lead"><?php echo htmlspecialchars($category['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Forums in Category -->
                    <?php if (!empty($forums)): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">Forums</h5>
                        </div>
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
                                    <?php foreach ($forums as $forum): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="forum-icon me-3">
                                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 20px; font-weight: bold;">
                                                            <?php echo strtoupper(substr($forum['name'], 0, 1)); ?>
                                                        </div>
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
                    </div>
                    <?php endif; ?>
                    
                    <!-- Recent Topics -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Topics</h5>
                            <span>Total Topics: <?php echo number_format($total_topics); ?></span>
                        </div>
                        
                        <?php if (empty($topics)): ?>
                            <div class="card-body">
                                <p class="text-center text-muted mb-0">No topics have been created in this category yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60%">Topic</th>
                                            <th class="text-center d-none d-md-table-cell">Replies</th>
                                            <th class="text-center d-none d-md-table-cell">Views</th>
                                            <th class="d-none d-lg-table-cell">Last Post</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topics as $topic): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="topic-icon me-3">
                                                            <?php if ($topic['is_sticky']): ?>
                                                                <span class="badge bg-warning text-dark">STICKY</span>
                                                            <?php elseif ($topic['is_locked']): ?>
                                                                <span class="badge bg-secondary">LOCKED</span>
                                                            <?php else: ?>
                                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 14px;">
                                                                    T
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <h5 class="mb-1">
                                                                <a href="topic.php?id=<?php echo $topic['topic_id']; ?>" class="text-decoration-none">
                                                                    <?php echo htmlspecialchars($topic['title']); ?>
                                                                </a>
                                                                <?php if ($topic['is_sticky']): ?>
                                                                    <span class="badge bg-warning text-dark ms-2">Sticky</span>
                                                                <?php endif; ?>
                                                                <?php if ($topic['is_locked']): ?>
                                                                    <span class="badge bg-secondary ms-2">Locked</span>
                                                                <?php endif; ?>
                                                            </h5>
                                                            <p class="mb-0 text-muted small">
                                                                In <a href="forum.php?id=<?php echo $topic['forum_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($topic['forum_name']); ?></a> •
                                                                Started by <a href="profile.php?id=<?php echo $topic['user_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($topic['creator_username']); ?></a>
                                                                on <?php echo date('M d, Y g:i a', strtotime($topic['created_at'])); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center align-middle d-none d-md-table-cell">
                                                    <?php echo number_format($topic['reply_count']); ?>
                                                </td>
                                                <td class="text-center align-middle d-none d-md-table-cell">
                                                    <?php echo number_format($topic['view_count']); ?>
                                                </td>
                                                <td class="align-middle d-none d-lg-table-cell">
                                                    <div class="small">
                                                        <a href="profile.php?id=<?php echo $topic['last_post_user_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($topic['last_poster']); ?>
                                                        </a>
                                                        <div class="text-muted">
                                                            <small>
                                                                <?php echo date('M d, Y g:i a', strtotime($topic['last_post_at'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
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
                                <!-- Previous Page Link -->
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $page <= 1 ? '#' : 'category.php?id=' . $category_id . '&page=' . ($page - 1); ?>" tabindex="-1" aria-disabled="<?php echo $page <= 1 ? 'true' : 'false'; ?>">Previous</a>
                                </li>
                                
                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="category.php?id=<?php echo $category_id; ?>&page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <!-- Next Page Link -->
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $page >= $total_pages ? '#' : 'category.php?id=' . $category_id . '&page=' . ($page + 1); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-3">
                    <!-- Category Stats -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">Category Statistics</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Forums
                                    <span class="badge bg-primary rounded-pill"><?php echo count($forums); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Topics
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($total_topics); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Posts
                                    <span class="badge bg-primary rounded-pill">
                                        <?php 
                                            $total_posts = array_sum(array_column($forums, 'post_count'));
                                            echo number_format($total_posts);
                                        ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <a href="forums.php" class="list-group-item list-group-item-action">
                                    Back to All Forums
                                </a>
                                <?php if (Session::isAdmin()): ?>
                                    <a href="admin/manage-categories.php" class="list-group-item list-group-item-action">
                                        Manage Categories
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Browse Other Categories -->
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">Other Categories</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get other categories
                            $other_categories_query = "SELECT * FROM categories WHERE category_id != :category_id ORDER BY name";
                            $other_stmt = $db->prepare($other_categories_query);
                            $other_stmt->bindParam(':category_id', $category_id);
                            $other_stmt->execute();
                            $other_categories = $other_stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            
                            <?php if (!empty($other_categories)): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($other_categories as $other_category): ?>
                                        <a href="category.php?id=<?php echo $other_category['category_id']; ?>" class="list-group-item list-group-item-action">
                                            <?php echo htmlspecialchars($other_category['name']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No other categories available.</p>
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