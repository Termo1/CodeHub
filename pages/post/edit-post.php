<?php
require_once '../../config/Database.php';
require_once '../../db/classes/Session.php';
require_once '../../db/classes/Validator.php';

// Start session
Session::start();

// Check if user is logged in
if (!Session::isLoggedIn()) {
    Session::setFlash('error', 'You must be logged in to edit posts');
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

// Initialize variables
$error = '';
$success = '';

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

// Function to update post
function updatePost($db, $post_id, $content) {
    $query = "UPDATE posts SET content = :content, updated_at = NOW() WHERE post_id = :post_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':content', $content);
    $stmt->bindParam(':post_id', $post_id);
    
    return $stmt->execute();
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
    Session::setFlash('error', 'You do not have permission to edit this post');
    header('Location: topic.php?id=' . $post['topic_id']);
    exit;
}

// Check if it's the first post (should use edit-topic instead)
if (isFirstPost($db, $post_id, $post['topic_id'])) {
    Session::setFlash('error', 'To edit the first post, please use the "Edit Topic" option');
    header('Location: topic.php?id=' . $post['topic_id']);
    exit;
}

// Initialize form variables
$content = $post['content'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $content = $_POST['content'] ?? '';
    
    // Create validator
    $validator = new Validator();
    
    // Validate form data
    $validator->required('content', $content, 'Post content');
    $validator->minLength('content', $content, 10, 'Post content');
    
    // If no validation errors, update the post
    if (!$validator->hasErrors()) {
        if (updatePost($db, $post_id, $content)) {
            Session::setFlash('success', 'Post updated successfully!');
            header('Location: topic.php?id=' . $post['topic_id'] . '#post-' . $post_id);
            exit;
        } else {
            $error = 'An error occurred while updating the post. Please try again.';
        }
    } else {
        $error = $validator->getFirstError();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("../../parts/head.php")?>
    <title>Edit Post - CodeHub</title>
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
                    <li class="breadcrumb-item"><a href="category.php?id=<?php echo $post['category_id']; ?>"><?php echo htmlspecialchars($post['category_name']); ?></a></li>
                    <li class="breadcrumb-item"><a href="forum.php?id=<?php echo $post['forum_id']; ?>"><?php echo htmlspecialchars($post['forum_name']); ?></a></li>
                    <li class="breadcrumb-item"><a href="topic.php?id=<?php echo $post['topic_id']; ?>"><?php echo htmlspecialchars($post['topic_title']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Post</li>
                </ol>
            </nav>
            
            <!-- Alert Messages -->
            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <div class="card shadow">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0">Edit Post</h4>
                </div>
                <div class="card-body">
                    <!-- Topic Context -->
                    <div class="mb-3">
                        <label class="form-label">Topic</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-primary me-2"><?php echo htmlspecialchars($post['category_name']); ?></span>
                            <a href="topic.php?id=<?php echo $post['topic_id']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($post['topic_title']); ?>
                            </a>
                        </p>
                    </div>
                    
                    <form action="edit-post.php?id=<?php echo $post_id; ?>" method="post">
                        <!-- Post Content -->
                        <div class="mb-3">
                            <label for="content" class="form-label">Post Content</label>
                            <textarea class="form-control" id="content" name="content" rows="8" required minlength="10"><?php echo htmlspecialchars($content); ?></textarea>
                            <div class="form-text">
                                Update your post content. Minimum 10 characters required.
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary me-2">
                                Update Post
                            </button>
                            <a href="topic.php?id=<?php echo $post['topic_id']; ?>#post-<?php echo $post_id; ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Post Preview</h5>
                </div>
                <div class="card-body">
                    <h6>Original Post:</h6>
                    <div class="border rounded p-3 bg-light">
                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                    </div>
                    <small class="text-muted">
                        Originally posted on <?php echo date('F d, Y g:i a', strtotime($post['created_at'])); ?>
                        <?php if ($post['updated_at'] !== $post['created_at']): ?>
                            • Last edited on <?php echo date('F d, Y g:i a', strtotime($post['updated_at'])); ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../../parts/footer.php" ?>
</body>
</html>