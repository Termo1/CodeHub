<?php
require_once '../config/Database.php';
require_once '../db/classes/Session.php';
require_once '../db/classes/Validator.php';
require_once '../db/classes/Topic.php';

// Start session
Session::start();

// Check if user is logged in
if (!Session::isLoggedIn()) {
    Session::setFlash('error', 'You must be logged in to create a topic');
    header('Location: login.php');
    exit;
}

// Initialize variables
$title = '';
$content = '';
$forum_id = isset($_GET['forum_id']) ? intval($_GET['forum_id']) : 0;
$error = '';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Function to get forum details
function getForumDetails($db, $forum_id) {
    $query = "SELECT f.*, c.name as category_name, c.category_id
              FROM forums f
              JOIN categories c ON f.category_id = c.category_id
              WHERE f.forum_id = :forum_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':forum_id', $forum_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get all forums for dropdown
function getAllForums($db) {
    $query = "SELECT f.forum_id, f.name, c.name as category_name
              FROM forums f
              JOIN categories c ON f.category_id = c.category_id
              ORDER BY c.name, f.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to create a slug from a string
function createSlug($string) {
    // Replace non letter or digit with -
    $string = preg_replace('~[^\pL\d]+~u', '-', $string);
    // Transliterate
    $string = iconv('utf-8', 'us-ascii//TRANSLIT', $string);
    // Remove unwanted characters
    $string = preg_replace('~[^-\w]+~', '', $string);
    // Trim
    $string = trim($string, '-');
    // Remove duplicate -
    $string = preg_replace('~-+~', '-', $string);
    // Lowercase
    $string = strtolower($string);
    
    if (empty($string)) {
        return 'n-a';
    }
    
    return $string;
}

// Function to create a new topic and first post
function createTopic($db, $forum_id, $user_id, $title, $content) {
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Create slug
        $slug = createSlug($title);
        
        // Check if slug already exists, if so, add a timestamp
        $check_query = "SELECT COUNT(*) FROM topics WHERE slug = :slug";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':slug', $slug);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            $slug = $slug . '-' . time();
        }
        
        // Insert topic
        $topic_query = "INSERT INTO topics 
                      (forum_id, user_id, title, slug, content, created_at, last_post_at, last_post_user_id) 
                      VALUES 
                      (:forum_id, :user_id, :title, :slug, :content, NOW(), NOW(), :last_post_user_id)";
        
        $topic_stmt = $db->prepare($topic_query);
        $topic_stmt->bindParam(':forum_id', $forum_id);
        $topic_stmt->bindParam(':user_id', $user_id);
        $topic_stmt->bindParam(':title', $title);
        $topic_stmt->bindParam(':slug', $slug);
        $topic_stmt->bindParam(':content', $content);
        $topic_stmt->bindParam(':last_post_user_id', $user_id);
        $topic_stmt->execute();
        
        // Get the new topic ID
        $topic_id = $db->lastInsertId();
        
        // Insert first post
        $post_query = "INSERT INTO posts 
                      (topic_id, user_id, content, created_at) 
                      VALUES 
                      (:topic_id, :user_id, :content, NOW())";
        
        $post_stmt = $db->prepare($post_query);
        $post_stmt->bindParam(':topic_id', $topic_id);
        $post_stmt->bindParam(':user_id', $user_id);
        $post_stmt->bindParam(':content', $content);
        $post_stmt->execute();
        
        // Update forum's topic count and last post info
        $update_forum_query = "UPDATE forums 
                             SET topic_count = topic_count + 1, 
                                 post_count = post_count + 1,
                                 last_post_at = NOW() 
                             WHERE forum_id = :forum_id";
        
        $update_forum_stmt = $db->prepare($update_forum_query);
        $update_forum_stmt->bindParam(':forum_id', $forum_id);
        $update_forum_stmt->execute();
        
        // Commit transaction
        $db->commit();
        
        return $topic_id;
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        return false;
    }
}

// Get forum details if forum_id is provided
$forum = null;
if ($forum_id > 0) {
    $forum = getForumDetails($db, $forum_id);
    
    // If forum doesn't exist, redirect to forums page
    if (!$forum) {
        Session::setFlash('error', 'Forum not found');
        header('Location: forums.php');
        exit;
    }
}

// Get all forums for dropdown
$all_forums = getAllForums($db);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $forum_id = $_POST['forum_id'] ?? 0;
    
    // Create validator
    $validator = new Validator();
    
    // Validate form data
    $validator->required('title', $title);
    $validator->minLength('title', $title, 5);
    $validator->maxLength('title', $title, 255);
    
    $validator->required('content', $content);
    $validator->minLength('content', $content, 10);
    
    $validator->required('forum_id', $forum_id, 'Forum');
    
    // If no validation errors, create the topic
    if (!$validator->hasErrors()) {
        $user_id = Session::get('user_id');
        
        $topic_id = createTopic($db, $forum_id, $user_id, $title, $content);
        
        if ($topic_id) {
            // Redirect to the new topic
            header('Location: topic.php?id=' . $topic_id);
            exit;
        } else {
            $error = 'An error occurred while creating your topic. Please try again.';
        }
    } else {
        $error = $validator->getFirstError();
    }
}?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("../parts/head.php")?>
    <title>Create New Topic - CodeHub</title>
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
                    <?php if ($forum): ?>
                        <li class="breadcrumb-item"><a href="category.php?id=<?php echo $forum['category_id']; ?>"><?php echo htmlspecialchars($forum['category_name']); ?></a></li>
                        <li class="breadcrumb-item"><a href="forum.php?id=<?php echo $forum_id; ?>"><?php echo htmlspecialchars($forum['name']); ?></a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page">Create New Topic</li>
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
                    <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Create New Topic</h4>
                </div>
                <div class="card-body">
                    <form action="create-topic.php" method="post">
                        <?php if (!$forum): ?>
                            <!-- Forum Selection Dropdown (only if forum_id not provided) -->
                            <div class="mb-3">
                                <label for="forum_id" class="form-label">Forum</label>
                                <select class="form-select" id="forum_id" name="forum_id" required>
                                    <option value="" selected disabled>-- Select Forum --</option>
                                    <?php
                                    $current_category = null;
                                    foreach ($all_forums as $f):
                                        if ($f['category_name'] !== $current_category):
                                            if ($current_category !== null):
                                                echo '</optgroup>';
                                            endif;
                                            $current_category = $f['category_name'];
                                            echo '<optgroup label="' . htmlspecialchars($current_category) . '">';
                                        endif;
                                    ?>
                                        <option value="<?php echo $f['forum_id']; ?>" <?php echo ($forum_id == $f['forum_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($f['name']); ?>
                                        </option>
                                    <?php
                                        if (end($all_forums) === $f):
                                            echo '</optgroup>';
                                        endif;
                                    endforeach;
                                    ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="forum_id" value="<?php echo $forum_id; ?>">
                            <div class="mb-3">
                                <label class="form-label">Forum</label>
                                <p class="form-control-plaintext">
                                    <i class="fas fa-comments me-2 text-primary"></i>
                                    <?php echo htmlspecialchars($forum['name']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        
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
                                Clearly describe your question or topic. Provide all relevant details, code examples, or error messages to get the best response.
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-paper-plane me-2"></i>Create Topic
                            </button>
                            <a href="<?php echo $forum ? 'forum.php?id=' . $forum_id : 'forums.php'; ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow mt-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Tips for a Great Topic</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li><strong>Be specific</strong> in your title and content - clear questions get better answers</li>
                        <li><strong>Format your code</strong> using code blocks to make it readable</li>
                        <li><strong>Include relevant details</strong> like programming language, frameworks, and version numbers</li>
                        <li><strong>Share your research</strong> - what have you tried already?</li>
                        <li><strong>Check for similar topics</strong> before posting to avoid duplicates</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../parts/footer.php" ?>
</body>
</html>