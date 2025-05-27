<?php
require_once '../config/Database.php';
require_once '../db/classes/Session.php';

// Start session
Session::start();

// Check if user is logged in
if (!Session::isLoggedIn()) {
    Session::setFlash('error', 'You must be logged in to delete posts');
    header('Location: login.php');
    exit;
}

// Check if post ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    Session::setFlash('error', 'Post ID is required');
    header('Location: forums.php');
    exit;
}

$post_id = intval($_GET['id']);

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Function to get post details
function getPostDetails($db, $post_id) {
    $query = "SELECT p.*, t.title as topic_title, t.topic_id, t.user_id as topic_owner_id,
              f.name as forum_name, f.forum_id, c.name as category_name, c.category_id
              FROM posts p
              JOIN topics t ON p.topic_id = t.topic_id
              JOIN forums f ON t.forum_id = f.forum_id
              JOIN categories c ON f.category_id = c.category_id
              WHERE p.post_id = :post_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':post_id', $post_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to check if post is first post of topic
function isFirstPost($db, $post_id, $topic_id) {
    $query = "SELECT post_id FROM posts WHERE topic_id = :topic_id ORDER BY created_at ASC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':topic_id', $topic_id);
    $stmt->execute();
    $first_post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $first_post && $first_post['post_id'] == $post_id;
}

// Function to delete post
function deletePost($db, $post_id, $topic_id) {
    try {
        $db->beginTransaction();
        
        // Delete the post
        $delete_query = "DELETE FROM posts WHERE post_id = :post_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':post_id', $post_id);
        $delete_stmt->execute();
        
        // Update topic reply count
        $update_topic_query = "UPDATE topics SET reply_count = reply_count - 1 WHERE topic_id = :topic_id";
        $update_topic_stmt = $db->prepare($update_topic_query);
        $update_topic_stmt->bindParam(':topic_id', $topic_id);
        $update_topic_stmt->execute();
        
        // Update last post info for the topic
        $last_post_query = "SELECT post_id, user_id, created_at FROM posts 
                           WHERE topic_id = :topic_id 
                           ORDER BY created_at DESC LIMIT 1";
        $last_post_stmt = $db->prepare($last_post_query);
        $last_post_stmt->bindParam(':topic_id', $topic_id);
        $last_post_stmt->execute();
        $last_post = $last_post_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last_post) {
            $update_last_post_query = "UPDATE topics 
                                     SET last_post_at = :last_post_at, 
                                         last_post_user_id = :last_post_user_id 
                                     WHERE topic_id = :topic_id";
            $update_last_post_stmt = $db->prepare($update_last_post_query);
            $update_last_post_stmt->bindParam(':last_post_at', $last_post['created_at']);
            $update_last_post_stmt->bindParam(':last_post_user_id', $last_post['user_id']);
            $update_last_post_stmt->bindParam(':topic_id', $topic_id);
            $update_last_post_stmt->execute();
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
}

// Get post details
$post = getPostDetails($db, $post_id);

// If post doesn't exist, redirect
if (!$post) {
    Session::setFlash('error', 'Post not found');
    header('Location: forums.php');
    exit;
}

// Check if user owns the post or is moderator/admin
$user_id = Session::get('user_id');
if ($post['user_id'] != $user_id && !Session::isModerator()) {
    Session::setFlash('error', 'You do not have permission to delete this post');
    header('Location: topic.php?id=' . $post['topic_id']);
    exit;
}

// Check if it's the first post (cannot delete first post, must delete entire topic)
if (isFirstPost($db, $post_id, $post['topic_id'])) {
    Session::setFlash('error', 'Cannot delete the first post. To delete this content, you must delete the entire topic.');
    header('Location: topic.php?id=' . $post['topic_id']);
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if (deletePost($db, $post_id, $post['topic_id'])) {
        Session::setFlash('success', 'Post deleted successfully');
        header('Location: topic.php?id=' . $post['topic_id']);
        exit;
    } else {
        $error = 'An error occurred while deleting the post. Please try again.';
    }
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    header('Location: topic.php?id=' . $post['topic_id'] . '#post-' . $post_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("../parts/head.php")?>
    <title>Delete Post - CodeHub</title>
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
                    <li class="breadcrumb-item"><a href="category.php?id=<?php echo $post['category_id']; ?>"><?php echo htmlspecialchars($post['category_name']); ?></a></li>
                    <li class="breadcrumb-item"><a href="forum.php?id=<?php echo $post['forum_id']; ?>"><?php echo htmlspecialchars($post['forum_name']); ?></a></li>
                    <li class="breadcrumb-item"><a href="topic.php?id=<?php echo $post['topic_id']; ?>"><?php echo htmlspecialchars($post['topic_title']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Delete Post</li>
                </ol>
            </nav>
            
            <!-- Alert Messages -->
            <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow border-danger">
                        <div class="card-header bg-danger text-white">
                            <h4 class="mb-0">Confirm Post Deletion</h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <h5>⚠️ Warning: This action cannot be undone!</h5>
                                <p class="mb-0">You are about to permanently delete this post.</p>
                            </div>
                            
                            <!-- Topic Context -->
                            <div class="mb-3">
                                <h6>Topic: <a href="topic.php?id=<?php echo $post['topic_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($post['topic_title']); ?></a></h6>
                                <p class="text-muted">
                                    <span class="badge bg-primary me-2"><?php echo htmlspecialchars($post['category_name']); ?></span>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($post['forum_name']); ?></span>
                                </p>
                            </div>
                            
                            <!-- Post Content Preview -->
                            <div class="card mb-4 border-danger">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Post to be deleted:</h6>
                                </div>
                                <div class="card-body">
                                    <div class="post-content" style="max-height: 200px; overflow-y: auto;">
                                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                    </div>
                                    <hr>
                                    <small class="text-muted">
                                        Posted on <?php echo date('F d, Y g:i a', strtotime($post['created_at'])); ?>
                                        <?php if ($post['updated_at'] !== $post['created_at']): ?>
                                            • Last edited on <?php echo date('F d, Y g:i a', strtotime($post['updated_at'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Confirmation Form -->
                            <form method="POST" action="delete-post.php?id=<?php echo $post_id; ?>">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="confirmCheck" required>
                                        <label class="form-check-label text-danger" for="confirmCheck">
                                            <strong>I understand that this action is permanent and cannot be undone</strong>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="submit" name="cancel" class="btn btn-outline-secondary">
                                        Cancel
                                    </button>
                                    <button type="submit" name="confirm_delete" class="btn btn-danger" id="deleteBtn" disabled>
                                        Delete Post Permanently
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../parts/footer.php" ?>
    
    <script>
        // Enable/disable delete button based on checkbox
        document.getElementById('confirmCheck').addEventListener('change', function() {
            document.getElementById('deleteBtn').disabled = !this.checked;
        });
    </script>
</body>
</html>