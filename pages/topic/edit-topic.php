<?php
require_once '../../db/classes/Session.php';
require_once '../../config/Database.php';

require_once '../../db/classes/Validator.php';

// Start session
Session::start();

// Check if user is logged in
if (!Session::isLoggedIn()) {
    Session::setFlash('error', 'You must be logged in to edit topics');
    header('Location: login.php');
    exit;
}

// Check if topic ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    Session::setFlash('error', 'Topic ID is required');
    header('Location: forums.php');
    exit;
}

$topic_id = intval($_GET['id']);

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize variables
$error = '';
$success = '';

// Function to get topic details
function getTopicDetails($db, $topic_id) {
    $query = "SELECT t.*, f.name as forum_name, f.forum_id, c.name as category_name, c.category_id
              FROM topics t
              JOIN forums f ON t.forum_id = f.forum_id
              JOIN categories c ON f.category_id = c.category_id
              WHERE t.topic_id = :topic_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':topic_id', $topic_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get first post content
function getFirstPostContent($db, $topic_id) {
    $query = "SELECT content FROM posts WHERE topic_id = :topic_id ORDER BY created_at ASC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':topic_id', $topic_id);
    $stmt->execute();
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $post ? $post['content'] : '';
}

// Function to update topic
function updateTopic($db, $topic_id, $title, $content) {
    try {
        $db->beginTransaction();
        
        // Update topic
        $topic_query = "UPDATE topics SET title = :title, updated_at = NOW() WHERE topic_id = :topic_id";
        $topic_stmt = $db->prepare($topic_query);
        $topic_stmt->bindParam(':title', $title);
        $topic_stmt->bindParam(':topic_id', $topic_id);
        $topic_stmt->execute();
        
        // Update first post
        $post_query = "UPDATE posts SET content = :content, updated_at = NOW() 
                      WHERE topic_id = :topic_id 
                      ORDER BY created_at ASC LIMIT 1";
        $post_stmt = $db->prepare($post_query);
        $post_stmt->bindParam(':content', $content);
        $post_stmt->bindParam(':topic_id', $topic_id);
        $post_stmt->execute();
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
}

// Get topic details
$topic = getTopicDetails($db, $topic_id);

// If topic doesn't exist, redirect
if (!$topic) {
    Session::setFlash('error', 'Topic not found');
    header('Location: forums.php');
    exit;
}

// Check if user owns the topic or is moderator/admin
$user_id = Session::get('user_id');
if ($topic['user_id'] != $user_id && !Session::isModerator()) {
    Session::setFlash('error', 'You do not have permission to edit this topic');
    header('Location: topic.php?id=' . $topic_id);
    exit;
}

// Get first post content
$first_post_content = getFirstPostContent($db, $topic_id);

// Initialize form variables
$title = $topic['title'];
$content = $first_post_content;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    
    // Create validator
    $validator = new Validator();
    
    // Validate form data
    $validator->required('title', $title);
    $validator->minLength('title', $title, 5);
    $validator->maxLength('title', $title, 255);
    
    $validator->required('content', $content);
    $validator->minLength('content', $content, 10);
    
    // If no validation errors, update the topic
    if (!$validator->hasErrors()) {
        if (updateTopic($db, $topic_id, $title, $content)) {
            Session::setFlash('success', 'Topic updated successfully!');
            header('Location: topic.php?id=' . $topic_id);
            exit;
        } else {
            $error = 'An error occurred while updating the topic. Please try again.';
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
    <title>Edit Topic - CodeHub</title>
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
                    <li class="breadcrumb-item"><a href="category.php?id=<?php echo $topic['category_id']; ?>"><?php echo htmlspecialchars($topic['category_name']); ?></a></li>
                    <li class="breadcrumb-item"><a href="forum.php?id=<?php echo $topic['forum_id']; ?>"><?php echo htmlspecialchars($topic['forum_name']); ?></a></li>
                    <li class="breadcrumb-item"><a href="topic.php?id=<?php echo $topic_id; ?>"><?php echo htmlspecialchars($topic['title']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Topic</li>
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
                    <h4 class="mb-0">Edit Topic</h4>
                </div>
                <div class="card-body">
                    <form action="edit-topic.php?id=<?php echo $topic_id; ?>" method="post">
                        <!-- Forum Display -->
                        <div class="mb-3">
                            <label class="form-label">Forum</label>
                            <p class="form-control-plaintext">
                                <span class="badge bg-primary me-2"><?php echo htmlspecialchars($topic['category_name']); ?></span>
                                <?php echo htmlspecialchars($topic['forum_name']); ?>
                            </p>
                        </div>
                        
                        <!-- Topic Title -->
                        <div class="mb-3">
                            <label for="title" class="form-label">Topic Title</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required minlength="5" maxlength="255">
                            <div class="form-text">
                                Choose a title that briefly and clearly describes your topic. Min 5, max 255 characters.
                            </div>
                        </div>
                        
                        <!-- Topic Content -->
                        <div class="mb-3">
                            <label for="content" class="form-label">Topic Content</label>
                            <textarea class="form-control" id="content" name="content" rows="10" required minlength="10"><?php echo htmlspecialchars($content); ?></textarea>
                            <div class="form-text">
                                Update your topic content. This will modify the first post of the topic.
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary me-2">
                                Update Topic
                            </button>
                            <a href="topic.php?id=<?php echo $topic_id; ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow mt-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Important Note</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0">
                        <strong>Editing this topic will:</strong>
                    </p>
                    <ul class="mb-0 mt-2">
                        <li>Update the topic title</li>
                        <li>Modify the content of the first post</li>
                        <li>Show "edited" timestamp on the topic</li>
                        <li>Preserve all replies and discussions</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../../parts/footer.php" ?>
</body>
</html>