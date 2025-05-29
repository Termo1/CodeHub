<?php
require_once '../../config/Database.php';
require_once '../../db/classes/Session.php';
require_once '../../db/classes/Validator.php';

// Start session
Session::start();

// Initialize variables
$error = '';
$success = '';

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

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$from_record_num = ($records_per_page * $page) - $records_per_page;

// Function to get topic details
function getTopicDetails($db, $topic_id) {
    $query = "SELECT t.*, f.name as forum_name, f.forum_id, c.name as category_name, c.category_id, 
              u.username as creator_username, u.signature as creator_signature, u.reputation as creator_reputation
              FROM topics t
              JOIN forums f ON t.forum_id = f.forum_id
              JOIN categories c ON f.category_id = c.category_id
              JOIN users u ON t.user_id = u.user_id
              WHERE t.topic_id = :topic_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':topic_id', $topic_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get posts in a topic with pagination
function getPostsInTopic($db, $topic_id, $from_record_num, $records_per_page) {
    $query = "SELECT p.*, u.username, u.signature, u.reputation, u.created_at as member_since, 
              (SELECT COUNT(*) FROM posts WHERE user_id = p.user_id) as user_post_count
              FROM posts p
              JOIN users u ON p.user_id = u.user_id
              WHERE p.topic_id = :topic_id
              ORDER BY p.created_at ASC
              LIMIT :from_record_num, :records_per_page";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':topic_id', $topic_id);
    $stmt->bindParam(':from_record_num', $from_record_num, PDO::PARAM_INT);
    $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to count total posts in topic for pagination
function countPostsInTopic($db, $topic_id) {
    $query = "SELECT COUNT(*) as total FROM posts WHERE topic_id = :topic_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':topic_id', $topic_id);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $row['total'];
}

// Function to update topic view count
function updateTopicViewCount($db, $topic_id) {
    $query = "UPDATE topics SET view_count = view_count + 1 WHERE topic_id = :topic_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':topic_id', $topic_id);
    $stmt->execute();
}

// Function to create a new post/reply
function createPost($db, $topic_id, $user_id, $content) {
    $query = "INSERT INTO posts (topic_id, user_id, content, created_at) 
              VALUES (:topic_id, :user_id, :content, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':topic_id', $topic_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':content', $content);
    
    if($stmt->execute()) {
        // Update topic's last_post_at and last_post_user_id
        $update_query = "UPDATE topics SET 
                        last_post_at = NOW(), 
                        last_post_user_id = :user_id,
                        reply_count = reply_count + 1
                        WHERE topic_id = :topic_id";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':user_id', $user_id);
        $update_stmt->bindParam(':topic_id', $topic_id);
        $update_stmt->execute();
        
        return true;
    }
    
    return false;
}

// Get topic details
$topic = getTopicDetails($db, $topic_id);

// If topic doesn't exist, redirect to forums page
if (!$topic) {
    Session::setFlash('error', 'Topic not found');
    header('Location: forums.php');
    exit;
}

// Update view count (only once per session)
if (!isset($_SESSION['viewed_topics'][$topic_id])) {
    updateTopicViewCount($db, $topic_id);
    $_SESSION['viewed_topics'][$topic_id] = true;
}

// Process reply form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    // Check if user is logged in
    if (!Session::isLoggedIn()) {
        $error = 'You must be logged in to reply';
    } else {
        // Get form data
        $content = $_POST['content'] ?? '';
        
        // Create validator
        $validator = new Validator();
        
        // Validate form data
        $validator->required('content', $content, 'Reply content');
        $validator->minLength('content', $content, 10, 'Reply content');
        
        // If no validation errors, create the post
        if (!$validator->hasErrors()) {
            $user_id = Session::get('user_id');
            
            if (createPost($db, $topic_id, $user_id, $content)) {
                // Redirect to the last page to see the new post
                $total_posts = countPostsInTopic($db, $topic_id);
                $total_pages = ceil($total_posts / $records_per_page);
                header('Location: topic.php?id=' . $topic_id . '&page=' . $total_pages . '#post-latest');
                exit;
            } else {
                $error = 'An error occurred while posting your reply. Please try again.';
            }
        } else {
            $error = $validator->getFirstError();
        }
    }
}

// Get posts in topic
$posts = getPostsInTopic($db, $topic_id, $from_record_num, $records_per_page);

// Get total posts count for pagination
$total_posts = countPostsInTopic($db, $topic_id);
$total_pages = ceil($total_posts / $records_per_page);

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
    <?php require_once("../../parts/head.php")?>
    <title><?php echo htmlspecialchars($topic['title']); ?> - CodeHub</title>
</head>
<body>
    <?php require "../../parts/header.php" ?>
    
    <main>
        <div class="container py-5">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="http://localhost/codehub/pages/forum/forums.php">Forums</a></li>
                    <li class="breadcrumb-item"><a href="http://localhost/codehub/pages/forum/category.php?id=<?php echo $topic['category_id']; ?>"><?php echo htmlspecialchars($topic['category_name']); ?></a></li>
                    <li class="breadcrumb-item"><a href="http://localhost/codehub/pages/forum/forum.php?id=<?php echo $topic['forum_id']; ?>"><?php echo htmlspecialchars($topic['forum_name']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($topic['title']); ?></li>
                </ol>
            </nav>
            
            <!-- Alert Messages -->
            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <!-- Topic Info Box -->
            <div class="card shadow mb-5 info-topic">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <?php echo htmlspecialchars($topic['title']); ?>
                    </h4>
                    <div>
                        <span class="badge bg-info"><?php echo number_format($topic['view_count']); ?> views</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-0">
                                <i class="fas fa-user me-1"></i> Started by <a href="profile.php?id=<?php echo $topic['user_id']; ?>"><?php echo htmlspecialchars($topic['creator_username']); ?></a>
                                <span class="mx-2">|</span>
                                <i class="far fa-calendar-alt me-1"></i> <?php echo date('F d, Y g:i a', strtotime($topic['created_at'])); ?>
                            </p>
                        </div>
                        <div>
                            <?php if (Session::isLoggedIn() && !$topic['is_locked']): ?>
                                <a href="#reply-form" class="btn btn-primary">
                                    Reply
                                </a>
                            <?php endif; ?>
                            
                            <?php if (Session::isLoggedIn() && (Session::get('user_id') == $topic['user_id'] || Session::isModerator())): ?>
                                <div class="btn-group ms-2">
                                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (Session::get('user_id') == $topic['user_id'] || Session::isModerator()): ?>
                                            <li><a class="dropdown-item" href="edit-topic.php?id=<?php echo $topic_id; ?>">Edit Topic</a></li>
                                            <li><a class="dropdown-item text-danger" href="delete-topic.php?id=<?php echo $topic_id; ?>">Delete Topic</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pagination (Top) -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mb-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $page <= 1 ? '#' : 'topic.php?id=' . $topic_id . '&page=' . ($page - 1); ?>" tabindex="-1" aria-disabled="<?php echo $page <= 1 ? 'true' : 'false'; ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="topic.php?id=<?php echo $topic_id; ?>&page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $page >= $total_pages ? '#' : 'topic.php?id=' . $topic_id . '&page=' . ($page + 1); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
            
            <!-- Posts List -->
            <?php 
            $counter = 0;
            foreach ($posts as $post): 
                $counter++;
                $is_first_post = ($page === 1 && $counter === 1);
                $post_number = ($page - 1) * $records_per_page + $counter;
            ?>
                <div id="post-<?php echo $post['post_id']; ?>" class="card shadow mb-4 <?php echo $is_first_post ? 'border-primary' : ''; ?>">
                    <div class="card-header <?php echo $is_first_post ? 'bg-primary text-white' : 'bg-light'; ?> d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-comment me-2"></i> 
                            <a href="#post-<?php echo $post['post_id']; ?>" class="text-<?php echo $is_first_post ? 'white' : 'dark'; ?>">#<?php echo $post_number; ?></a>
                            <span class="mx-2">|</span>
                            <i class="far fa-clock me-1"></i> <?php echo date('F d, Y g:i a', strtotime($post['created_at'])); ?>
                            <?php if ($post['updated_at'] !== $post['created_at']): ?>
                                <small class="text-<?php echo $is_first_post ? 'white-50' : 'muted'; ?> ms-2">(Edited: <?php echo date('F d, Y g:i a', strtotime($post['updated_at'])); ?>)</small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($post['is_solution']): ?>
                                <span class="badge bg-success me-2">Solution</span>
                            <?php endif; ?>
                            
                            <?php if (Session::isLoggedIn()): ?>
                                <button class="btn btn-sm btn-outline-<?php echo $is_first_post ? 'light' : 'secondary'; ?>" onclick="quote(<?php echo $post['post_id']; ?>)" title="Quote">
                                    <i class="fas fa-quote-right"></i>
                                </button>
                            <?php endif; ?>
                            
                            <?php if (Session::isLoggedIn() && (Session::isModerator() || Session::get('user_id') == $post['user_id'])): ?>
                                <div class="btn-group ms-1">
                                    <button type="button" class="btn btn-sm btn-outline-<?php echo $is_first_post ? 'light' : 'secondary'; ?> dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if ($is_first_post): ?>
                                            <li><a class="dropdown-item" href="edit-topic.php?id=<?php echo $topic_id; ?>"><i class="fas fa-edit me-2"></i>Edit Topic</a></li>
                                        <?php else: ?>
                                            <li><a class="dropdown-item" href="edit-post.php?id=<?php echo $post['post_id']; ?>"><i class="fas fa-edit me-2"></i>Edit Post</a></li>
                                        <?php endif; ?>
                                        
                                        <?php if (Session::isModerator() && !$is_first_post): ?>
                                            <li><a class="dropdown-item" href="../admin/toggle-solution.php?id=<?php echo $post['post_id']; ?>&topic_id=<?php echo $topic_id; ?>"><i class="fas fa-check-circle me-2"></i><?php echo $post['is_solution'] ? 'Unmark as Solution' : 'Mark as Solution'; ?></a></li>
                                        <?php endif; ?>
                                        
                                        <?php if (!$is_first_post): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="delete-post.php?id=<?php echo $post['post_id']; ?>" onclick="return confirm('Are you sure you want to delete this post?');"><i class="fas fa-trash-alt me-2"></i>Delete Post</a></li>
                                        <?php elseif (Session::get('user_id') == $post['user_id'] || Session::isModerator()): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="delete-topic.php?id=<?php echo $topic_id; ?>" onclick="return confirm('Are you sure you want to delete this entire topic?');"><i class="fas fa-trash-alt me-2"></i>Delete Topic</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- User Info Sidebar -->
                            <div class="col-md-2 text-center border-end">
                                <div class="avatar mb-3">
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px; font-size: 36px;">
                                        <?php echo strtoupper(substr($post['username'], 0, 1)); ?>
                                    </div>
                                </div>
                                <h5><a href="profile.php?id=<?php echo $post['user_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($post['username']); ?></a></h5>
                                <p class="badge bg-<?php 
                                    if ($post['reputation'] >= 1000) {
                                        echo 'danger';
                                    } elseif ($post['reputation'] >= 500) {
                                        echo 'warning text-dark';
                                    } elseif ($post['reputation'] >= 100) {
                                        echo 'success';
                                    } else {
                                        echo 'primary';
                                    }
                                ?>">
                                    Reputation: <?php echo number_format($post['reputation']); ?>
                                </p>
                                <div class="small text-muted">
                                    <p class="mb-1">Posts: <?php echo number_format($post['user_post_count']); ?></p>
                                    <p class="mb-0">Joined: <?php echo date('M Y', strtotime($post['member_since'])); ?></p>
                                </div>
                            </div>
                            
                            <!-- Post Content -->
                            <div class="col-md-10">
                                <div class="post-content mb-3">
                                    <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                </div>
                                
                                <?php if (!empty($post['signature'])): ?>
                                    <div class="post-signature pt-3 mt-3 border-top">
                                        <small class="text-muted">
                                            <?php echo nl2br(htmlspecialchars($post['signature'])); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                </div>
            <?php endforeach; ?>
            
            <!-- Pagination (Bottom) -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mb-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $page <= 1 ? '#' : 'topic.php?id=' . $topic_id . '&page=' . ($page - 1); ?>" tabindex="-1" aria-disabled="<?php echo $page <= 1 ? 'true' : 'false'; ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="topic.php?id=<?php echo $topic_id; ?>&page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $page >= $total_pages ? '#' : 'topic.php?id=' . $topic_id . '&page=' . ($page + 1); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
            
            <!-- Reply Form -->
            <?php if (Session::isLoggedIn() && !$topic['is_locked']): ?>
                <div id="reply-form" class="card shadow mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-reply me-2"></i>Post a Reply</h5>
                    </div>
                    <div class="card-body">
                        <form action="topic.php?id=<?php echo $topic_id; ?>" method="post">
                            <div class="mb-3">
                                <textarea class="form-control" id="content" name="content" rows="5" required placeholder="Write your reply here..."></textarea>
                            </div>
                            <button type="submit" name="submit_reply" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Post Reply
                            </button>
                        </form>
                    </div>
                </div>
            <?php elseif ($topic['is_locked']): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-lock me-2"></i>This topic is locked. No new replies can be posted.
                </div>
            <?php elseif (!Session::isLoggedIn()): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Please <a href="login.php" class="alert-link">login</a> to reply to this topic.
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php require "../../parts/footer.php" ?>
    
    <script>
        function quote(postId) {
            // Get the post content
            const postContent = document.querySelector(`#post-${postId} .post-content`).innerText;
            const username = document.querySelector(`#post-${postId} .col-md-2 h5 a`).innerText;
            
            // Get the textarea
            const textarea = document.querySelector('#content');
            
            // Add the quote to the textarea
            const quote = `[quote="${username}"]${postContent}[/quote]\n\n`;
            textarea.value += quote;
            
            // Scroll to the reply form
            document.getElementById('reply-form').scrollIntoView({ behavior: 'smooth' });
            
            // Focus the textarea
            textarea.focus();
        }
    </script>
</body>
</html>