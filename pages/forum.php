<?php
require_once '../config/Database.php';
require_once '../db/classes/Session.php';
//require_once '../db/classes/Topic.php';

// Start session
Session::start();

// Initialize error variable
$error = '';

// Check if forum ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    Session::setFlash('error', 'Forum ID is required');
    header('Location: forums.php');
    exit;
}

$forum_id = intval($_GET['id']);

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$from_record_num = ($records_per_page * $page) - $records_per_page;

// Function to get forum details
function getForumDetails($db, $forum_id) {
    $query = "SELECT f.*, c.name as category_name, c.category_id
              FROM forums f
              JOIN categories c ON f.category_id = c.category_id
              WHERE f.forum_id = :forum_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':forum_id', $forum_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get topics in a forum with pagination
function getTopicsInForum($db, $forum_id, $from_record_num, $records_per_page) {
    $query = "SELECT t.*, 
              u.username as creator_username,
              (SELECT COUNT(*) FROM posts p WHERE p.topic_id = t.topic_id) - 1 as reply_count,
              (SELECT username FROM users WHERE user_id = t.last_post_user_id) as last_poster,
              (SELECT MAX(created_at) FROM posts WHERE topic_id = t.topic_id) as last_post_time
              FROM topics t
              JOIN users u ON t.user_id = u.user_id
              WHERE t.forum_id = :forum_id
              ORDER BY t.is_sticky DESC, t.last_post_at DESC
              LIMIT :from_record_num, :records_per_page";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':forum_id', $forum_id);
    $stmt->bindParam(':from_record_num', $from_record_num, PDO::PARAM_INT);
    $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to count total topics in forum for pagination
function countTopicsInForum($db, $forum_id) {
    $query = "SELECT COUNT(*) as total FROM topics WHERE forum_id = :forum_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':forum_id', $forum_id);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $row['total'];
}

// Get forum details
$forum = getForumDetails($db, $forum_id);

// If forum doesn't exist, redirect to forums page
if (!$forum) {
    Session::setFlash('error', 'Forum not found');
    header('Location: forums.php');
    exit;
}

// Get topics in forum
$topics = getTopicsInForum($db, $forum_id, $from_record_num, $records_per_page);

// Get total topics count for pagination
$total_topics = countTopicsInForum($db, $forum_id);
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
    <?php require_once("../parts/head.php")?>
    <title><?php echo htmlspecialchars($forum['name']); ?> - CodeHub</title>
</head>
<body>
    <?php require "../parts/header.php" ?>
    
    <main>
        <div class="container py-5">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/codehub/index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="forums.php">Forums</a></li>
                    <li class="breadcrumb-item"><a href="category.php?id=<?php echo $forum['category_id']; ?>"><?php echo htmlspecialchars($forum['category_name']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($forum['name']); ?></li>
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
                    <!-- Forum Header -->
                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <h1 class="mb-3">
                                <?php if (!empty($forum['icon'])): ?>
                                    <i class="<?php echo htmlspecialchars($forum['icon']); ?> me-2 text-primary"></i>
                                <?php else: ?>
                                    <i class="fas fa-comments me-2 text-primary"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($forum['name']); ?>
                            </h1>
                            <p class="lead"><?php echo htmlspecialchars($forum['description']); ?></p>
                            
                            <?php if (Session::isLoggedIn()): ?>
                                <a href="create-topic.php?forum_id=<?php echo $forum_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>Create New Topic
                                </a>
                            <?php else: ?>
                                <div class="alert alert-info" role="alert">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <a href="login.php" class="alert-link">Login</a> or <a href="register.php" class="alert-link">Register</a> to create a new topic.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Topics List -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Topics</h5>
                            <span>Total Topics: <?php echo number_format($total_topics); ?></span>
                        </div>
                        
                        <?php if (empty($topics)): ?>
                            <div class="card-body">
                                <p class="text-center text-muted mb-0">No topics have been created in this forum yet.</p>
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
                                                                <i class="fas fa-thumbtack fa-lg text-warning"></i>
                                                            <?php elseif ($topic['is_locked']): ?>
                                                                <i class="fas fa-lock fa-lg text-secondary"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-comments fa-lg text-primary"></i>
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
                                                                <?php echo date('M d, Y g:i a', strtotime($topic['last_post_time'])); ?>
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
                                    <a class="page-link" href="<?php echo $page <= 1 ? '#' : 'forum.php?id=' . $forum_id . '&page=' . ($page - 1); ?>" tabindex="-1" aria-disabled="<?php echo $page <= 1 ? 'true' : 'false'; ?>">Previous</a>
                                </li>
                                
                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="forum.php?id=<?php echo $forum_id; ?>&page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <!-- Next Page Link -->
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $page >= $total_pages ? '#' : 'forum.php?id=' . $forum_id . '&page=' . ($page + 1); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-3">
                    <!-- Forum Stats -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Forum Statistics</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Topics
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($total_topics); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Posts
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($forum['post_count']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Views
                                    <span class="badge bg-primary rounded-pill">
                                        <?php 
                                            // Calculate total views (sum of all topic views)
                                            $total_views = 0;
                                            foreach ($topics as $topic) {
                                                $total_views += $topic['view_count'];
                                            }
                                            echo number_format($total_views);
                                        ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Forum Actions -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Forum Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <a href="create-topic.php?forum_id=<?php echo $forum_id; ?>" class="list-group-item list-group-item-action <?php echo !Session::isLoggedIn() ? 'disabled' : ''; ?>">
                                    <i class="fas fa-plus-circle me-2"></i>New Topic
                                </a>
                                <?php if (Session::isAdmin()): ?>
                                    <a href="admin/manage-forum.php?id=<?php echo $forum_id; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-edit me-2"></i>Edit Forum
                                    </a>
                                <?php endif; ?>
                                <a href="forums.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Forums
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    
                    
                    
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../parts/footer.php" ?>
</body>
</html>