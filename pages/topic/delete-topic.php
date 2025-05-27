<?php
require_once '../../config/Database.php';
require_once '../../db/classes/Session.php';

// Start session
Session::start();

// Check if user is logged in
if (!Session::isLoggedIn()) {
    Session::setFlash('error', 'You must be logged in to delete topics');
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

// Function to get topic details
function getTopicDetails($db, $topic_id) {
    $query = "SELECT t.*, f.name as forum_name, f.forum_id, c.name as category_name, c.category_id,
              (SELECT COUNT(*) FROM posts WHERE topic_id = t.topic_id) as post_count
              FROM topics t
              JOIN forums f ON t.forum_id = f.forum_id
              JOIN categories c ON f.category_id = c.category_id
              WHERE t.topic_id = :topic_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':topic_id', $topic_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to delete topic and all related data
function deleteTopic($db, $topic_id) {
    try {
        $db->beginTransaction();
        
        // Get topic info for forum update
        $topic_query = "SELECT forum_id, (SELECT COUNT(*) FROM posts WHERE topic_id = :topic_id) as post_count 
                       FROM topics WHERE topic_id = :topic_id";
        $topic_stmt = $db->prepare($topic_query);
        $topic_stmt->bindParam(':topic_id', $topic_id);
        $topic_stmt->execute();
        $topic_data = $topic_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$topic_data) {
            throw new Exception("Topic not found");
        }
        
        $forum_id = $topic_data['forum_id'];
        $post_count = $topic_data['post_count'];
        
        // Delete all posts in the topic
        $delete_posts_query = "DELETE FROM posts WHERE topic_id = :topic_id";
        $delete_posts_stmt = $db->prepare($delete_posts_query);
        $delete_posts_stmt->bindParam(':topic_id', $topic_id);
        $delete_posts_stmt->execute();
        
        // Delete topic tags if any
        $delete_tags_query = "DELETE FROM topic_tags WHERE topic_id = :topic_id";
        $delete_tags_stmt = $db->prepare($delete_tags_query);
        $delete_tags_stmt->bindParam(':topic_id', $topic_id);
        $delete_tags_stmt->execute();
        
        // Delete the topic
        $delete_topic_query = "DELETE FROM topics WHERE topic_id = :topic_id";
        $delete_topic_stmt = $db->prepare($delete_topic_query);
        $delete_topic_stmt->bindParam(':topic_id', $topic_id);
        $delete_topic_stmt->execute();
        
        // Update forum statistics
        $update_forum_query = "UPDATE forums 
                             SET topic_count = GREATEST(0, topic_count - 1), 
                                 post_count = GREATEST(0, post_count - :post_count)
                             WHERE forum_id = :forum_id";
        
        $update_forum_stmt = $db->prepare($update_forum_query);
        $update_forum_stmt->bindParam(':post_count', $post_count, PDO::PARAM_INT);
        $update_forum_stmt->bindParam(':forum_id', $forum_id);
        $update_forum_stmt->execute();
        
        $db->commit();
        return $forum_id;
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

// Check permissions - only topic owner or moderator/admin can delete
$user_id = Session::get('user_id');
if ($topic['user_id'] != $user_id && !Session::isModerator()) {
    Session::setFlash('error', 'You do not have permission to delete this topic');
    header('Location: topic.php?id=' . $topic_id);
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $forum_id = deleteTopic($db, $topic_id);
    
    if ($forum_id) {
        Session::setFlash('success', 'Topic deleted successfully');
        header('Location: forum.php?id=' . $forum_id);
        exit;
    } else {
        $error = 'An error occurred while deleting the topic. Please try again.';
    }
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    header('Location: topic.php?id=' . $topic_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("../../parts/head.php")?>
    <title>Delete Topic - CodeHub</title>
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
                    <li class="breadcrumb-item active" aria-current="page">Delete Topic</li>
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
                            <h4 class="mb-0">Confirm Topic Deletion</h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <h5>⚠️ Warning: This action cannot be undone!</h5>
                                <p class="mb-0">You are about to permanently delete this topic and all its contents.</p>
                            </div>
                            
                            <!-- Topic Information -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($topic['title']); ?></h5>
                                    <p class="card-text">
                                        <span class="badge bg-primary me-2"><?php echo htmlspecialchars($topic['category_name']); ?></span>
                                        <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($topic['forum_name']); ?></span>
                                    </p>
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <div class="border rounded p-3">
                                                <h6 class="text-muted mb-1">Total Posts</h6>
                                                <h4 class="text-danger"><?php echo number_format($topic['post_count']); ?></h4>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="border rounded p-3">
                                                <h6 class="text-muted mb-1">Views</h6>
                                                <h4 class="text-danger"><?php echo number_format($topic['view_count']); ?></h4>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="border rounded p-3">
                                                <h6 class="text-muted mb-1">Created</h6>
                                                <h6 class="text-danger"><?php echo date('M d, Y', strtotime($topic['created_at'])); ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- What will be deleted -->
                            <div class="card mb-4 border-danger">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">What will be permanently deleted:</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0">
                                        <li>The topic "<strong><?php echo htmlspecialchars($topic['title']); ?></strong>"</li>
                                        <li>All <strong><?php echo $topic['post_count']; ?> posts</strong> in this topic</li>
                                        <li>All replies and discussions</li>
                                        <li>Any attachments or files (if applicable)</li>
                                        <li>Topic view history and statistics</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Confirmation Form -->
                            <form method="POST" action="delete-topic.php?id=<?php echo $topic_id; ?>">
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
                                        Delete Topic Permanently
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../../parts/footer.php" ?>
    
    <script>
        // Enable/disable delete button based on checkbox
        document.getElementById('confirmCheck').addEventListener('change', function() {
            document.getElementById('deleteBtn').disabled = !this.checked;
        });
    </script>
</body>
</html>