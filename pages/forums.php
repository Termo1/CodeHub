<?php
require_once '../config/Database.php';
require_once '../db/classes/Session.php';

// Start session
Session::start();

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$filter_categories = isset($_GET['categories']) ? explode(',', $_GET['categories']) : [];
$filter_categories = array_map('intval', $filter_categories); // Convert to integers
$filter_categories = array_filter($filter_categories); // Remove zeros

// Function to get all categories
function getAllCategories($db) {
    $query = "SELECT * FROM categories ORDER BY display_order ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get categories with their forums (with optional filtering)
function getCategoriesWithForums($db, $filter_categories = []) {
    // Base query for categories
    $category_where = '';
    if (!empty($filter_categories)) {
        $placeholders = str_repeat('?,', count($filter_categories) - 1) . '?';
        $category_where = "WHERE c.category_id IN ($placeholders)";
    }
    
    $query = "SELECT c.*, 
              (SELECT COUNT(*) FROM forums f WHERE f.category_id = c.category_id) as forum_count 
              FROM categories c 
              $category_where
              ORDER BY c.display_order ASC";
    
    $stmt = $db->prepare($query);
    if (!empty($filter_categories)) {
        $stmt->execute($filter_categories);
    } else {
        $stmt->execute();
    }
    
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

// Get all categories for filter dropdown
$all_categories = getAllCategories($db);

// Get categories with forums (filtered if necessary)
$categories = getCategoriesWithForums($db, $filter_categories);

// Get total stats (considering filters)
if (!empty($filter_categories)) {
    $placeholders = str_repeat('?,', count($filter_categories) - 1) . '?';
    $stats_query = "SELECT 
                   (SELECT COUNT(*) FROM users) as user_count,
                   (SELECT COUNT(*) FROM topics t JOIN forums f ON t.forum_id = f.forum_id WHERE f.category_id IN ($placeholders)) as topic_count,
                   (SELECT COUNT(*) FROM posts p JOIN topics t ON p.topic_id = t.topic_id JOIN forums f ON t.forum_id = f.forum_id WHERE f.category_id IN ($placeholders)) as post_count,
                   (SELECT COUNT(*) FROM categories WHERE category_id IN ($placeholders)) as category_count,
                   (SELECT COUNT(*) FROM forums WHERE category_id IN ($placeholders)) as forum_count";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute(array_merge($filter_categories, $filter_categories, $filter_categories, $filter_categories));
} else {
    $stats_query = "SELECT 
                   (SELECT COUNT(*) FROM users) as user_count,
                   (SELECT COUNT(*) FROM topics) as topic_count,
                   (SELECT COUNT(*) FROM posts) as post_count,
                   (SELECT COUNT(*) FROM categories) as category_count,
                   (SELECT COUNT(*) FROM forums) as forum_count";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
}

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
    <style>
        .category-filter {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .filter-checkbox {
            margin-right: 0.5rem;
        }
        .filter-actions {
            border-top: 1px solid #dee2e6;
            padding-top: 0.75rem;
            margin-top: 0.75rem;
        }
    </style>
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
                        <h1 class="mb-3">Forums</h1>
                        <p class="lead">Browse our development forums and join the conversation.</p>
                    </div>
                    
                    <!-- Category Filter -->
                    <div class="category-filter">
                        <h5 class="mb-3">Filter by Categories</h5>
                        <form method="GET" action="forums.php" id="filterForm">
                            <div class="row">
                                <?php foreach ($all_categories as $category): ?>
                                    <div class="col-md-6 col-lg-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input filter-checkbox" type="checkbox" 
                                                   name="category_<?php echo $category['category_id']; ?>" 
                                                   id="category_<?php echo $category['category_id']; ?>"
                                                   value="<?php echo $category['category_id']; ?>"
                                                   <?php echo in_array($category['category_id'], $filter_categories) ? 'checked' : ''; ?>
                                                   onchange="updateFilter()">
                                            <label class="form-check-label" for="category_<?php echo $category['category_id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="filter-actions">
                                <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="selectAll()">
                                    Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="selectNone()">
                                    Select None
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="resetFilter()">
                                    Reset
                                </button>
                                
                                <?php if (!empty($filter_categories)): ?>
                                    <span class="ms-3 text-muted">
                                        Showing <?php echo count($filter_categories); ?> of <?php echo count($all_categories); ?> categories
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <input type="hidden" name="categories" id="categoriesInput" value="<?php echo implode(',', $filter_categories); ?>">
                        </form>
                    </div>
                    
                    <?php if (empty($categories)): ?>
                        <div class="alert alert-info">
                            <?php if (!empty($filter_categories)): ?>
                                No forums found in the selected categories. Try adjusting your filter.
                            <?php else: ?>
                                No categories or forums have been created yet.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <div class="card shadow mb-4">
                                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <a href="category.php?id=<?php echo $category['category_id']; ?>" class="text-white text-decoration-none">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </a>
                                    </h5>
                                    <small>
                                        <a href="category.php?id=<?php echo $category['category_id']; ?>" class="text-white-50 text-decoration-none">
                                            View All →
                                        </a>
                                    </small>
                                </div>
                                
                                <?php if (!empty($category['description'])): ?>
                                    <div class="card-body border-bottom">
                                        <p class="mb-0 text-muted"><?php echo htmlspecialchars($category['description']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
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
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Add New Forum button for Admins -->
                    <?php if (Session::isAdmin()): ?>
                        <div class="text-end mt-3">
                            <a href="admin/manage-forums.php" class="btn btn-primary">
                                Manage Forums
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-3">
                    <!-- Forum Statistics -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">Statistics</h5>
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
                    
                    <!-- Quick Links -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">Quick Links</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($all_categories as $category): ?>
                                    <a href="category.php?id=<?php echo $category['category_id']; ?>" class="list-group-item list-group-item-action">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Forum Info -->
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">Forum Info</h5>
                        </div>
                        <div class="card-body">
                            <p>
                                Welcome to our developer forums! Join our community to ask questions, share knowledge, and connect with fellow developers.
                            </p>
                            <?php if ($latest_user): ?>
                                <div class="border-top pt-2">
                                    <p class="mb-0">
                                        <strong>Newest Member:</strong> 
                                        <a href="profile.php?username=<?php echo urlencode($latest_user['username']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($latest_user['username']); ?>
                                        </a>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../parts/footer.php" ?>
    
    <script>
        function updateFilter() {
            const checkboxes = document.querySelectorAll('.filter-checkbox:checked');
            const selectedCategories = Array.from(checkboxes).map(cb => cb.value);
            document.getElementById('categoriesInput').value = selectedCategories.join(',');
            
            // Auto-submit form when filter changes
            setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 100);
        }
        
        function selectAll() {
            const checkboxes = document.querySelectorAll('.filter-checkbox');
            checkboxes.forEach(cb => cb.checked = true);
            updateFilter();
        }
        
        function selectNone() {
            const checkboxes = document.querySelectorAll('.filter-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            updateFilter();
        }
        
        function resetFilter() {
            window.location.href = 'forums.php';
        }
    </script>
</body>
</html>