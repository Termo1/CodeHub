<?php
require_once '../config/Database.php';
require_once '../db/classes/Session.php';
require_once '../db/classes/Validator.php';

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

// Handle category creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    $validator = new Validator();
    
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $display_order = $_POST['display_order'] ?? 0;
    
    // Validate form data
    $validator->required('name', $name);
    $validator->minLength('name', $name, 3);
    $validator->maxLength('name', $name, 100);
    
    $validator->required('slug', $slug);
    $validator->minLength('slug', $slug, 3);
    $validator->maxLength('slug', $slug, 100);
    
    // Check if slug already exists
    $check_query = "SELECT COUNT(*) FROM categories WHERE slug = :slug";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':slug', $slug);
    $check_stmt->execute();
    if ($check_stmt->fetchColumn() > 0) {
        $validator->addError('slug', 'Slug already exists');
    }
    
    if (!$validator->hasErrors()) {
        $query = "INSERT INTO categories (name, description, slug, display_order, created_at) 
                  VALUES (:name, :description, :slug, :display_order, NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':slug', $slug);
        $stmt->bindParam(':display_order', $display_order);
        
        if ($stmt->execute()) {
            $success = 'Category created successfully!';
        } else {
            $error = 'Failed to create category.';
        }
    } else {
        $error = $validator->getFirstError();
    }
}

// Handle category update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $validator = new Validator();
    
    $category_id = $_POST['category_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $display_order = $_POST['display_order'] ?? 0;
    
    // Validate form data
    $validator->required('name', $name);
    $validator->minLength('name', $name, 3);
    $validator->maxLength('name', $name, 100);
    
    $validator->required('slug', $slug);
    $validator->minLength('slug', $slug, 3);
    $validator->maxLength('slug', $slug, 100);
    
    // Check if slug already exists (but not for current category)
    $check_query = "SELECT COUNT(*) FROM categories WHERE slug = :slug AND category_id != :category_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':slug', $slug);
    $check_stmt->bindParam(':category_id', $category_id);
    $check_stmt->execute();
    if ($check_stmt->fetchColumn() > 0) {
        $validator->addError('slug', 'Slug already exists');
    }
    
    if (!$validator->hasErrors()) {
        $query = "UPDATE categories SET name = :name, description = :description, 
                  slug = :slug, display_order = :display_order 
                  WHERE category_id = :category_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':slug', $slug);
        $stmt->bindParam(':display_order', $display_order);
        $stmt->bindParam(':category_id', $category_id);
        
        if ($stmt->execute()) {
            $success = 'Category updated successfully!';
        } else {
            $error = 'Failed to update category.';
        }
    } else {
        $error = $validator->getFirstError();
    }
}

// Handle category deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $category_id = $_POST['category_id'] ?? 0;
    
    // Check if category has forums
    $check_query = "SELECT COUNT(*) FROM forums WHERE category_id = :category_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':category_id', $category_id);
    $check_stmt->execute();
    
    if ($check_stmt->fetchColumn() > 0) {
        $error = 'Cannot delete category that contains forums. Please move or delete forums first.';
    } else {
        $query = "DELETE FROM categories WHERE category_id = :category_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':category_id', $category_id);
        
        if ($stmt->execute()) {
            $success = 'Category deleted successfully!';
        } else {
            $error = 'Failed to delete category.';
        }
    }
}

// Function to get all categories with forum counts
function getCategories($db) {
    $query = "SELECT c.*, 
              (SELECT COUNT(*) FROM forums WHERE category_id = c.category_id) as forum_count,
              (SELECT COUNT(*) FROM topics t JOIN forums f ON t.forum_id = f.forum_id WHERE f.category_id = c.category_id) as topic_count,
              (SELECT COUNT(*) FROM posts p JOIN topics t ON p.topic_id = t.topic_id JOIN forums f ON t.forum_id = f.forum_id WHERE f.category_id = c.category_id) as post_count
              FROM categories c 
              ORDER BY c.display_order ASC, c.name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$categories = getCategories($db);

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
    <title>Categories Management - Admin Dashboard</title>
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
                        <a class="nav-link active" href="categories.php">
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
                    <h1 class="h2">Categories Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                            <i class="fas fa-plus me-2"></i>Create Category
                        </button>
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
                
                <!-- Categories List -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Categories List</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($categories)): ?>
                            <div class="p-4 text-center">
                                <p class="mb-0 text-muted">No categories found. Create your first category!</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Order</th>
                                            <th>Name</th>
                                            <th>Slug</th>
                                            <th>Description</th>
                                            <th>Forums</th>
                                            <th>Topics</th>
                                            <th>Posts</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $category['display_order']; ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                </td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($category['slug']); ?></code>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(substr($category['description'] ?? '', 0, 50)); ?>
                                                    <?php if (strlen($category['description'] ?? '') > 50): ?>...<?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo number_format($category['forum_count']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo number_format($category['topic_count']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo number_format($category['post_count']); ?></span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($category['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <!-- View Category -->
                                                        <a href="../pages/category.php?id=<?php echo $category['category_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Category">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <!-- Edit Category -->
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit Category" 
                                                                onclick="editCategory(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo htmlspecialchars($category['description'] ?? ''); ?>', '<?php echo htmlspecialchars($category['slug']); ?>', <?php echo $category['display_order']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <!-- Delete Category -->
                                                        <?php if ($category['forum_count'] == 0): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                                                                <button type="submit" name="delete_category" class="btn btn-sm btn-outline-danger" 
                                                                        onclick="return confirm('Are you sure you want to delete this category?')" title="Delete Category">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Cannot delete - contains forums">
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
            </main>
        </div>
    </div>
    
    <!-- Create Category Modal -->
    <div class="modal fade" id="createCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug *</label>
                            <input type="text" class="form-control" id="slug" name="slug" required>
                            <div class="form-text">URL-friendly version of the name</div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="display_order" name="display_order" value="0" min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_category" class="btn btn-primary">Create Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="edit_category_id" name="category_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_slug" class="form-label">Slug *</label>
                            <input type="text" class="form-control" id="edit_slug" name="slug" required>
                            <div class="form-text">URL-friendly version of the name</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="edit_display_order" name="display_order" min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php require "../parts/footer.php" ?>
    
    <script>
        // Auto-generate slug from name
        document.getElementById('name').addEventListener('input', function() {
            const name = this.value;
            const slug = name.toLowerCase()
                .replace(/[^a-z0-9 -]/g, '') // Remove invalid chars
                .replace(/\s+/g, '-') // Replace spaces with -
                .replace(/-+/g, '-'); // Replace multiple - with single -
            document.getElementById('slug').value = slug;
        });
        
        // Edit category function
        function editCategory(id, name, description, slug, displayOrder) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_slug').value = slug;
            document.getElementById('edit_display_order').value = displayOrder;
            
            const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            modal.show();
        }
        
        // Auto-generate slug from name in edit form
        document.getElementById('edit_name').addEventListener('input', function() {
            const name = this.value;
            const slug = name.toLowerCase()
                .replace(/[^a-z0-9 -]/g, '') // Remove invalid chars
                .replace(/\s+/g, '-') // Replace spaces with -
                .replace(/-+/g, '-'); // Replace multiple - with single -
            document.getElementById('edit_slug').value = slug;
        });
    </script>
</body>
</html>