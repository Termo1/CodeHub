<?php
require_once '../config/Database.php';
require_once '../db/classes/Session.php';
require_once '../db/classes/User.php';

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

// Handle role updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'] ?? 0;
    $new_role = $_POST['new_role'] ?? '';
    
    if ($user_id && in_array($new_role, ['user', 'moderator', 'admin'])) {
        $query = "UPDATE users SET role = :role WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':role', $new_role);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $success = 'User role updated successfully!';
        } else {
            $error = 'Failed to update user role.';
        }
    } else {
        $error = 'Invalid user or role data.';
    }
}

// Handle user status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $user_id = $_POST['user_id'] ?? 0;
    $current_status = $_POST['current_status'] ?? 0;
    $new_status = $current_status ? 0 : 1;
    
    if ($user_id) {
        $query = "UPDATE users SET is_active = :status WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $success = 'User status updated successfully!';
        } else {
            $error = 'Failed to update user status.';
        }
    }
}

// Function to get users with pagination and search
function getUsers($db, $from_record_num, $records_per_page, $search = '') {
    $where_clause = '';
    $params = [];
    
    if (!empty($search)) {
        $where_clause = "WHERE username LIKE :search OR email LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }
    
    $query = "SELECT u.*, 
              (SELECT COUNT(*) FROM topics WHERE user_id = u.user_id) as topic_count,
              (SELECT COUNT(*) FROM posts WHERE user_id = u.user_id) as post_count
              FROM users u 
              $where_clause
              ORDER BY u.created_at DESC 
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

// Function to count total users
function countUsers($db, $search = '') {
    $where_clause = '';
    $params = [];
    
    if (!empty($search)) {
        $where_clause = "WHERE username LIKE :search OR email LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }
    
    $query = "SELECT COUNT(*) as total FROM users $where_clause";
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $row['total'];
}

// Get search parameter
$search = $_GET['search'] ?? '';

// Get users data
$users = getUsers($db, $from_record_num, $records_per_page, $search);
$total_users = countUsers($db, $search);
$total_pages = ceil($total_users / $records_per_page);

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
    <title>Users Management - Admin Dashboard</title>
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
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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
                        <a class="nav-link active" href="users.php">
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
                    <h1 class="h2">Users Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-info"><?php echo number_format($total_users); ?> Total Users</span>
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
                        <form method="GET" action="users.php" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" placeholder="Search by username or email..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary me-2">Search</button>
                                <a href="users.php" class="btn btn-outline-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Users List</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($users)): ?>
                            <div class="p-4 text-center">
                                <p class="mb-0 text-muted">No users found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>User</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Activity</th>
                                            <th>Joined</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-3">
                                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">Reputation: <?php echo number_format($user['reputation']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?>                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $user['role'] === 'admin' ? 'danger' : 
                                                            ($user['role'] === 'moderator' ? 'warning' : 'primary'); 
                                                    ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small>
                                                        <strong><?php echo number_format($user['topic_count']); ?></strong> topics<br>
                                                        <strong><?php echo number_format($user['post_count']); ?></strong> posts
                                                    </small>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                                        <?php if ($user['last_login']): ?>
                                                            <br>Last: <?php echo date('M d, Y', strtotime($user['last_login'])); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <!-- View Profile -->
                                                        <a href="../pages/profile.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Profile">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <!-- Role Change Dropdown -->
                                                        <?php if (Session::isAdmin() && $user['user_id'] != Session::get('user_id')): ?>
                                                            <div class="btn-group" role="group">
                                                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="Change Role">
                                                                    <i class="fas fa-user-cog"></i>
                                                                </button>
                                                                <ul class="dropdown-menu">
                                                                    <?php foreach (['user', 'moderator', 'admin'] as $role): ?>
                                                                        <?php if ($role !== $user['role']): ?>
                                                                            <li>
                                                                                <form method="POST" style="margin: 0;">
                                                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                                    <input type="hidden" name="new_role" value="<?php echo $role; ?>">
                                                                                    <button type="submit" name="update_role" class="dropdown-item" onclick="return confirm('Change role to <?php echo $role; ?>?')">
                                                                                        Make <?php echo ucfirst($role); ?>
                                                                                    </button>
                                                                                </form>
                                                                            </li>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Toggle Status -->
                                                        <?php if ($user['user_id'] != Session::get('user_id')): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>" 
                                                                        onclick="return confirm('<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> this user?')" 
                                                                        title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> User">
                                                                    <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                                </button>
                                                            </form>
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
                                <a class="page-link" href="<?php echo $page <= 1 ? '#' : 'users.php?page=' . ($page - 1) . ($search ? '&search=' . urlencode($search) : ''); ?>">Previous</a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="users.php?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $page >= $total_pages ? '#' : 'users.php?page=' . ($page + 1) . ($search ? '&search=' . urlencode($search) : ''); ?>">Next</a>
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