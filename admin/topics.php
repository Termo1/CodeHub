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
$records_per_page = 15;
$from_record_num = ($records_per_page * $page) - $records_per_page;

// Handle topic actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic_id = $_POST['topic_id'] ?? 0;
    
    if (isset($_POST['toggle_sticky'])) {
        $query = "UPDATE topics SET is_sticky = NOT is_sticky WHERE topic_id = :topic_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':topic_id', $topic_id);
        
        if ($stmt->execute()) {
            $success = 'Topic sticky status updated successfully!';
        } else {
            $error = 'Failed to update topic sticky status.';
        }
    }
    
    if (isset($_POST['toggle_lock'])) {
        $query = "UPDATE topics SET is_locked = NOT is_locked WHERE topic_id = :topic_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':topic_id', $topic_id);
        
        if ($stmt->execute()) {
            $success = 'Topic lock status updated successfully!';
        } else {
            $error = 'Failed to update topic lock status.';
        }
    }
    
    if (isset($_POST['delete_topic'])) {
        try {
            $db->beginTransaction();
            
            // Get forum_id and post count before deletion
            $info_query = "SELECT forum_id, (SELECT COUNT(*) FROM posts WHERE topic_id = :topic_id) as post_count 
                          FROM topics WHERE topic_id = :topic_id";
            $info_stmt = $db->prepare($info_query);
            $info_stmt->bindParam(':topic_id', $topic_id);
            $info_stmt->execute();
            $topic_info = $info_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($topic_info) {
                // Delete all posts
                $delete_posts = "DELETE FROM posts WHERE topic_id = :topic_id";
                $stmt = $db->prepare($delete_posts);
                $stmt->bindParam(':topic_id', $topic_id);
                $stmt->execute();
                
                // Delete topic tags
                $delete_tags = "DELETE FROM topic_tags WHERE topic_id = :topic_id";
                $stmt = $db->prepare($delete_tags);
                $stmt->bindParam(':topic_id', $topic_id);
                $stmt->execute();
                
                // Delete topic
                $delete_topic = "DELETE FROM topics WHERE topic_id = :topic_id";
                $stmt = $db->prepare($delete_topic);
                $stmt->bindParam(':topic_id', $topic_id);
                $stmt->execute();
                
                // Update forum counts
                $update_forum = "UPDATE forums 
                               SET topic_count = GREATEST(0, topic_count - 1), 
                                   post_count = GREATEST(0, post_count - :post_count)
                               WHERE forum_id = :forum_id";
                $stmt = $db->prepare($update_forum);
                $stmt->bindParam(':post_count', $topic_info['post_count']);
                $stmt->bindParam(':forum_id', $topic_info['forum_id']);
                $stmt->execute();
                
                $db->commit();
                $success = 'Topic deleted successfully!';
            } else {
                $error = 'Topic not found.';
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Failed to delete topic.';
        }
    }
}

// Function to get topics with pagination and search
function getTopics($db, $from_record_num, $records_per_page, $search = '', $forum_filter = '') {
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(t.title LIKE :search OR t.content LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    if (!empty($forum_filter)) {
        $where_conditions[] = "f.forum_id = :forum_filter";
        $params[':forum_filter'] = $forum_filter;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $query = "SELECT t.*, f.name as forum_name, c.name as category_name, u.username as creator_username,
              (SELECT COUNT(*) FROM posts WHERE topic_id = t.topic_id) as post_count
              FROM topics t
              JOIN forums f ON t.forum_id = f.forum_id
              JOIN categories c ON f.category_id = c.category_id
              JOIN users u ON t.user_id = u.user_id
              $where_clause
              ORDER BY t.created_at DESC
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

// Function to count total topics
function countTopics($db, $search = '', $forum_filter = '') {
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(t.title LIKE :search OR t.content LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    if (!empty($forum_filter)) {
        $where_conditions[] = "f.forum_id = :forum_filter";
        $params[':forum_filter'] = $forum_filter;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $query = "SELECT COUNT(*) as total 
              FROM topics t
              JOIN forums f ON t.forum_id = f.forum_id
              $where_clause";
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $row['total'];
}

// Function to get forums for filter dropdown
function getForumsForFilter($db) {
    $query = "SELECT f.forum_id, f.name, c.name as category_name
              FROM forums f
              JOIN categories c ON f.category_id = c.category_id
              ORDER BY c.name, f.name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$forum_filter = $_GET['forum_filter'] ?? '';

// Get data
$topics = getTopics($db, $from_record_num, $records_per_page, $search, $forum_filter);
$total_topics = countTopics($db, $search, $forum_filter);
$total_pages = ceil($total_topics / $records_per_page);
$forums = getForumsForFilter($db);

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
    <title>Topics Management - Admin Dashboard</title>
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
                        <a class="nav-link active" href="topics.php">
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
                    <h1 class="h2">Topics Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-info"><?php echo number_format($total_topics); ?> Total Topics</span>
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
                        <form method="GET" action="topics.php" class="row g-3">
                            <div class="col-md-5">
                                <input type="text" class="form-control" name="search" placeholder="Search topics..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" name="forum_filter">
                                    <option value="">All Forums</option>
                                    <?php 
                                    $current_category = '';
                                    foreach ($forums as $forum): 
                                        if ($forum['category_name'] !== $current_category):
                                            if ($current_category !== '') echo '</optgroup>';
                                            $current_category = $forum['category_name'];
                                            echo '<optgroup label="' . htmlspecialchars($current_category) . '">';
                                        endif;
                                    ?>
                                        <option value="<?php echo $forum['forum_id']; ?>" <?php echo $forum_filter == $forum['forum_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($forum['name']); ?>
                                        </option>
                                    <?php 
                                        if (end($forums) === $forum) echo '</optgroup>';
                                    endforeach; 
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary me-2">Search</button>
                                <a href="topics.php" class="btn btn-outline-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Topics Table -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Topics List</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($topics)): ?>
                            <div class="p-4 text-center">
                                <p class="mb-0 text-muted">No topics found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Topic</th>
                                            <th>Forum</th>
                                            <th>Author</th>
                                            <th>Status</th>
                                            <th>Stats</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topics as $topic): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong>
                                                            <a href="../pages/topic.php?id=<?php echo $topic['topic_id']; ?>" class="text-decoration-none">
                                                                <?php echo htmlspecialchars(substr($topic['title'], 0, 60)); ?>
                                                                <?php if (strlen($topic['title']) > 60): ?>...<?php endif; ?>
                                                            </a>
                                                        </strong>
                                                        <?php if ($topic['is_sticky']): ?>
                                                            <span class="badge bg-warning text-dark ms-2">Sticky</span>
                                                        <?php endif; ?>
                                                        <?php if ($topic['is_locked']): ?>
                                                            <span class="badge bg-secondary ms-2">Locked</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small>
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($topic['category_name']); ?></span><br>
                                                        <?php echo htmlspecialchars($topic['forum_name']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <a href="../pages/profile.php?id=<?php echo $topic['user_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($topic['creator_username']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if ($topic['is_sticky']): ?>
                                                        <i class="fas fa-thumbtack text-warning" title="Sticky"></i>
                                                    <?php endif; ?>
                                                    <?php if ($topic['is_locked']): ?>
                                                        <i class="fas fa-lock text-secondary" title="Locked"></i>
                                                    <?php endif; ?>
                                                    <?php if (!$topic['is_sticky'] && !$topic['is_locked']): ?>
                                                        <i class="fas fa-comments text-primary" title="Normal"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <strong><?php echo number_format($topic['post_count']); ?></strong> posts<br>
                                                        <strong><?php echo number_format($topic['view_count']); ?></strong> views
                                                    </small>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($topic['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <!-- View Topic -->
                                                        <a href="../pages/topic.php?id=<?php echo $topic['topic_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Topic">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <!-- Toggle Sticky -->
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="topic_id" value="<?php echo $topic['topic_id']; ?>">
                                                            <button type="submit" name="toggle_sticky" class="btn btn-sm btn-outline-<?php echo $topic['is_sticky'] ? 'warning' : 'secondary'; ?>" 
                                                                    title="<?php echo $topic['is_sticky'] ? 'Remove Sticky' : 'Make Sticky'; ?>">
                                                                <i class="fas fa-thumbtack"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- Toggle Lock -->
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="topic_id" value="<?php echo $topic['topic_id']; ?>">
                                                            <button type="submit" name="toggle_lock" class="btn btn-sm btn-outline-<?php echo $topic['is_locked'] ? 'secondary' : 'info'; ?>" 
                                                                    title="<?php echo $topic['is_locked'] ? 'Unlock' : 'Lock'; ?>">
                                                                <i class="fas fa-lock"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- Delete Topic -->
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="topic_id" value="<?php echo $topic['topic_id']; ?>">
                                                            <button type="submit" name="delete_topic" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="return confirm('Are you sure you want to delete this topic? This will also delete all posts in this topic.')" 
                                                                    title="Delete Topic">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
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
                                <a class="page-link" href="<?php echo $page <= 1 ? '#' : 'topics.php?page=' . ($page - 1) . ($search ? '&search=' . urlencode($search) : '') . ($forum_filter ? '&forum_filter=' . $forum_filter : ''); ?>">Previous</a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="topics.php?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $forum_filter ? '&forum_filter=' . $forum_filter : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $page >= $total_pages ? '#' : 'topics.php?page=' . ($page + 1) . ($search ? '&search=' . urlencode($search) : '') . ($forum_filter ? '&forum_filter=' . $forum_filter : ''); ?>">Next</a>
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