<?php
require_once '../config/Database.php';
require_once '../db/classes/Session.php';

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

// Initialize variables
$error = '';
$success = '';

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$from_record_num = ($records_per_page * $page) - $records_per_page;

// Handle post actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = $_POST['post_id'] ?? 0;
    
    if (isset($_POST['toggle_solution'])) {
        $query = "UPDATE posts SET is_solution = NOT is_solution WHERE post_id = :post_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':post_id', $post_id);
        
        if ($stmt->execute()) {
            $success = 'Post solution status updated successfully!';
        } else {
            $error = 'Failed to update post solution status.';
        }
    }
    
    if (isset($_POST['delete_post'])) {
        // Check if it's the first post of a topic (cannot delete)
        $check_query = "SELECT t.topic_id, 
                       (SELECT MIN(post_id) FROM posts WHERE topic_id = t.topic_id) as first_post_id
                       FROM posts p
                       JOIN topics t ON p.topic_id = t.topic_id
                       WHERE p.post_id = :post_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':post_id', $post_id);
        $check_stmt->execute();
        $post_info = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($post_info && $post_info['first_post_id'] == $post_id) {
            $error = 'Cannot delete the first post of a topic. Delete the entire topic instead.';
        } else {
            try {
                $db->beginTransaction();
                
                // Get topic_id for updating counts
                $topic_query = "SELECT topic_id FROM posts WHERE post_id = :post_id";
                $topic_stmt = $db->prepare($topic_query);
                $topic_stmt->bindParam(':post_id', $post_id);
                $topic_stmt->execute();
                $topic_data = $topic_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($topic_data) {
                    $topic_id = $topic_data['topic_id'];
                    
                    // Delete the post
                    $delete_query = "DELETE FROM posts WHERE post_id = :post_id";
                    $delete_stmt = $db->prepare($delete_query);
                    $delete_stmt->bindParam(':post_id', $post_id);
                    $delete_stmt->execute();
                    
                    // Update topic reply count
                    $update_topic = "UPDATE topics SET reply_count = reply_count - 1 WHERE topic_id = :topic_id";
                    $update_stmt = $db->prepare($update_topic);
                    $update_stmt->bindParam(':topic_id', $topic_id);
                    $update_stmt->execute();
                    
                    // Update last post info
                    $last_post_query = "SELECT post_id, user_id, created_at FROM posts 
                                       WHERE topic_id = :topic_id ORDER BY created_at DESC LIMIT 1";
                    $last_stmt = $db->prepare($last_post_query);
                    $last_stmt->bindParam(':topic_id', $topic_id);
                    $last_stmt->execute();
                    $last_post = $last_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($last_post) {
                        $update_last = "UPDATE topics 
                                       SET last_post_at = :last_post_at, last_post_user_id = :last_post_user_id 
                                       WHERE topic_id = :topic_id";
                        $update_last_stmt = $db->prepare($update_last);
                        $update_last_stmt->bindParam(':last_post_at', $last_post['created_at']);
                        $update_last_stmt->bindParam(':last_post_user_id', $last_post['user_id']);
                        $update_last_stmt->bindParam(':topic_id', $topic_id);
                        $update_last_stmt->execute();
                    }
                    
                    // Update forum post count
                    $update_forum = "UPDATE forums f 
                                    JOIN topics t ON f.forum_id = t.forum_id
                                    SET f.post_count = GREATEST(0, f.post_count - 1)
                                    WHERE t.topic_id = :topic_id";
                    $update_forum_stmt = $db->prepare($update_forum);
                    $update_forum_stmt->bindParam(':topic_id', $topic_id);
                    $update_forum_stmt->execute();
                    
                    $db->commit();
                    $success = 'Post deleted successfully!';
                } else {
                    $error = 'Post not found.';
                }
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Failed to delete post.';
            }
        }
    }
}

// Function to get posts with pagination and search
function getPosts($db, $from_record_num, $records_per_page, $search = '', $topic_filter = '', $user_filter = '') {
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "p.content LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }
    
    if (!empty($topic_filter)) {
        $where_conditions[] = "t.topic_id = :topic_filter";
        $params[':topic_filter'] = $topic_filter;
    }
    
    if (!empty($user_filter)) {
        $where_conditions[] = "u.user_id = :user_filter";
        $params[':user_filter'] = $user_filter;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $query = "SELECT p.*, t.title as topic_title, t.topic_id, 
              f.name as forum_name, c.name as category_name,
              u.username as author_username,
              (SELECT MIN(post_id) FROM posts WHERE topic_id = p.topic_id) as first_post_id
              FROM posts p
              JOIN topics t ON p.topic_id = t.topic_id
              JOIN forums f ON t.forum_id = f.forum_id
              JOIN categories c ON f.category_id = c.category_id
              JOIN users u ON p.user_id = u.user_id
              $where_clause
              ORDER BY p.created_at DESC
              LIMIT :from_record_num, :records_per_page";
    
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindParam(':from_record_num', $from_record_num, PDO::PARAM_INT);
    $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to count total posts
function countPosts($db, $search = '', $topic_filter = '', $user_filter = '') {
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "p.content LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }
    
    if (!empty($topic_filter)) {
        $where_conditions[] = "t.topic_id = :topic_filter";
        $params[':topic_filter'] = $topic_filter;
    }
    
    if (!empty($user_filter)) {
        $where_conditions[] = "u.user_id = :user_filter";
        $params[':user_filter'] = $user_filter;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $query = "SELECT COUNT(*) as total 
              FROM posts p
              JOIN topics t ON p.topic_id = t.topic_id
              JOIN users u ON p.user_id = u.user_id
              $where_clause";
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $row['total'];
}

// Function to get recent topics for filter dropdown
function getRecentTopics($db, $limit = 50) {
    $query = "SELECT t.topic_id, t.title, f.name as forum_name
              FROM topics t
              JOIN forums f ON t.forum_id = f.forum_id
              ORDER BY t.created_at DESC
              LIMIT :limit";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get recent users for filter dropdown
function getRecentUsers($db, $limit = 50) {
    $query = "SELECT user_id, username FROM users ORDER BY created_at DESC LIMIT :limit";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$topic_filter = $_GET['topic_filter'] ?? '';
$user_filter = $_GET['user_filter'] ?? '';

// Get data
$posts = getPosts($db, $from_record_num, $records_per_page, $search, $topic_filter, $user_filter);
$total_posts = countPosts($db, $search, $topic_filter, $user_filter);
$total_pages = ceil($total_posts / $records_per_page);
$recent_topics = getRecentTopics($db);
$recent_users = getRecentUsers($db);

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
    <title>Posts Management - Admin Dashboard</title>
    <style>
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
        .post-content {
            max-height: 100px;
            overflow-y: auto;
            font-size: 0.9em;
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
                        <a class="nav-link" href="index.php">
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
                        <a class="nav-link active" href="posts.php">
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
                    <h1 class="h2">Posts Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-info"><?php echo number_format($total_posts); ?> Total Posts</span>
                    </div>
                </div>
                
                <!-- Alert Messages -->
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Search and Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="posts.php" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" placeholder="Search post content..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="topic_filter">
                                    <option value="">All Topics</option>
                                    <?php foreach ($recent_topics as $topic): ?>
                                        <option value="<?php echo $topic['topic_id']; ?>" <?php echo $topic_filter == $topic['topic_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(substr($topic['title'], 0, 40)) . (strlen($topic['title']) > 40 ? '...' : ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="user_filter">
                                    <option value="">All Users</option>
                                    <?php foreach ($recent_users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>" <?php echo $user_filter == $user['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary me-2">Search</button>
                                <a href="posts.php" class="btn btn-outline-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Posts Table -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Posts List</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($posts)): ?>
                            <div class="p-4 text-center">
                                <p class="mb-0 text-muted">No posts found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Content</th>
                                            <th>Topic</th>
                                            <th>Author</th>
                                            <th>Status</th>
                                            <th>Posted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($posts as $post): ?>
                                            <tr>
                                                <td style="width: 35%;">
                                                    <div class="post-content">
                                                        <?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 200))); ?>
                                                        <?php if (strlen($post['content']) > 200): ?>
                                                            <span class="text-muted">...</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($post['post_id'] == $post['first_post_id']): ?>
                                                        <span class="badge bg-info mt-1">First Post</span>
                                                    <?php endif; ?>
                                                    <?php if ($post['is_solution']): ?>
                                                        <span class="badge bg-success mt-1">Solution</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong>
                                                            <a href="../pages/topic.php?id=<?php echo $post['topic_id']; ?>#post-<?php echo $post['post_id']; ?>" class="text-decoration-none">
                                                                <?php echo htmlspecialchars(substr($post['topic_title'], 0, 40)); ?>
                                                                <?php if (strlen($post['topic_title']) > 40): ?>...<?php endif; ?>
                                                            </a>
                                                        </strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <span class="badge bg-primary"><?php echo htmlspecialchars($post['category_name']); ?></span>
                                                            <?php echo htmlspecialchars($post['forum_name']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="../pages/profile.php?id=<?php echo $post['user_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($post['author_username']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if ($post['is_solution']): ?>
                                                        <i class="fas fa-check-circle text-success" title="Solution"></i>
                                                    <?php elseif ($post['post_id'] == $post['first_post_id']): ?>
                                                        <i class="fas fa-star text-warning" title="Original Post"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-comment text-primary" title="Reply"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                                        <br>
                                                        <?php echo date('g:i a', strtotime($post['created_at'])); ?>
                                                        <?php if ($post['updated_at'] !== $post['created_at']): ?>
                                                            <br><em>Edited</em>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group-vertical" role="group">
                                                        <!-- View Post -->
                                                        <a href="../pages/topic.php?id=<?php echo $post['topic_id']; ?>#post-<?php echo $post['post_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Post">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <!-- Toggle Solution (only for non-first posts) -->
                                                        <?php if ($post['post_id'] != $post['first_post_id']): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                                                <button type="submit" name="toggle_solution" class="btn btn-sm btn-outline-<?php echo $post['is_solution'] ? 'success' : 'secondary'; ?>" 
                                                                        title="<?php echo $post['is_solution'] ? 'Remove Solution' : 'Mark as Solution'; ?>">
                                                                    <i class="fas fa-check-circle"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Delete Post (only non-first posts) -->
                                                        <?php if ($post['post_id'] != $post['first_post_id']): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                                                <button type="submit" name="delete_post" class="btn btn-sm btn-outline-danger" 
                                                                        onclick="return confirm('Are you sure you want to delete this post?')" 
                                                                        title="Delete Post">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Cannot delete first post">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $page <= 1 ? '#' : 'posts.php?page=' . ($page - 1) . ($search ? '&search=' . urlencode($search) : '') . ($topic_filter ? '&topic_filter=' . $topic_filter : '') . ($user_filter ? '&user_filter=' . $user_filter : ''); ?>">Previous</a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="posts.php?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $topic_filter ? '&topic_filter=' . $topic_filter : ''; ?><?php echo $user_filter ? '&user_filter=' . $user_filter : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $page >= $total_pages ? '#' : 'posts.php?page=' . ($page + 1) . ($search ? '&search=' . urlencode($search) : '') . ($topic_filter ? '&topic_filter=' . $topic_filter : '') . ($user_filter ? '&user_filter=' . $user_filter : ''); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <?php require "../parts/footer.php" ?>
</body>
</html>