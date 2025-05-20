<?php
/**
 * Topic class for handling topic-related operations
 */
class Topic {
    // Database connection and table name
    private $conn;
    private $table = 'topics';
    
    // Topic properties
    public $topic_id;
    public $forum_id;
    public $user_id;
    public $title;
    public $slug;
    public $content;
    public $is_sticky;
    public $is_locked;
    public $view_count;
    public $reply_count;
    public $created_at;
    public $updated_at;
    public $last_post_at;
    public $last_post_user_id;
    
    /**
     * Constructor with DB connection
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Create a new topic
     */
    public function create() {
        // Create slug
        $this->slug = $this->createSlug($this->title);
        
        // Check if slug already exists, if so, add a timestamp
        $check_query = "SELECT COUNT(*) FROM " . $this->table . " WHERE slug = ?";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->execute([$this->slug]);
        
        if ($check_stmt->fetchColumn() > 0) {
            $this->slug = $this->slug . '-' . time();
        }
        
        try {
            // Begin transaction
            $this->conn->beginTransaction();
            
            // Insert topic
            $query = "INSERT INTO " . $this->table . " 
                      (forum_id, user_id, title, slug, content, is_sticky, is_locked, 
                       view_count, reply_count, created_at, last_post_at, last_post_user_id) 
                      VALUES 
                      (:forum_id, :user_id, :title, :slug, :content, :is_sticky, :is_locked, 
                       :view_count, :reply_count, NOW(), NOW(), :last_post_user_id)";
            
            $stmt = $this->conn->prepare($query);
            
            // Clean and bind data
            $this->title = htmlspecialchars(strip_tags($this->title));
            $this->content = htmlspecialchars(strip_tags($this->content));
            $this->is_sticky = $this->is_sticky ? 1 : 0;
            $this->is_locked = $this->is_locked ? 1 : 0;
            $this->view_count = 0;
            $this->reply_count = 0;
            
            $stmt->bindParam(':forum_id', $this->forum_id);
            $stmt->bindParam(':user_id', $this->user_id);
            $stmt->bindParam(':title', $this->title);
            $stmt->bindParam(':slug', $this->slug);
            $stmt->bindParam(':content', $this->content);
            $stmt->bindParam(':is_sticky', $this->is_sticky);
            $stmt->bindParam(':is_locked', $this->is_locked);
            $stmt->bindParam(':view_count', $this->view_count);
            $stmt->bindParam(':reply_count', $this->reply_count);
            $stmt->bindParam(':last_post_user_id', $this->user_id);
            
            $stmt->execute();
            
            // Get the new topic ID
            $this->topic_id = $this->conn->lastInsertId();
            
            // Create the first post/message
            $post_query = "INSERT INTO posts 
                          (topic_id, user_id, content, created_at) 
                          VALUES 
                          (:topic_id, :user_id, :content, NOW())";
            
            $post_stmt = $this->conn->prepare($post_query);
            $post_stmt->bindParam(':topic_id', $this->topic_id);
            $post_stmt->bindParam(':user_id', $this->user_id);
            $post_stmt->bindParam(':content', $this->content);
            $post_stmt->execute();
            
            // Update forum's topic count and last post info
            $update_forum_query = "UPDATE forums 
                                 SET topic_count = topic_count + 1, 
                                     post_count = post_count + 1,
                                     last_post_at = NOW() 
                                 WHERE forum_id = :forum_id";
            
            $update_forum_stmt = $this->conn->prepare($update_forum_query);
            $update_forum_stmt->bindParam(':forum_id', $this->forum_id);
            $update_forum_stmt->execute();
            
            // Commit transaction
            $this->conn->commit();
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            return false;
        }
    }
    
    /**
     * Update an existing topic
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET title = :title, content = :content, is_sticky = :is_sticky, 
                      is_locked = :is_locked, updated_at = NOW() 
                  WHERE topic_id = :topic_id";
        
        $stmt = $this->conn->prepare($query);
        
        // Clean and bind data
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->content = htmlspecialchars(strip_tags($this->content));
        $this->is_sticky = $this->is_sticky ? 1 : 0;
        $this->is_locked = $this->is_locked ? 1 : 0;
        
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':content', $this->content);
        $stmt->bindParam(':is_sticky', $this->is_sticky);
        $stmt->bindParam(':is_locked', $this->is_locked);
        $stmt->bindParam(':topic_id', $this->topic_id);
        
        if ($stmt->execute()) {
            // Also update the first post
            $post_query = "UPDATE posts 
                          SET content = :content, updated_at = NOW() 
                          WHERE topic_id = :topic_id 
                          ORDER BY created_at ASC 
                          LIMIT 1";
            
            $post_stmt = $this->conn->prepare($post_query);
            $post_stmt->bindParam(':content', $this->content);
            $post_stmt->bindParam(':topic_id', $this->topic_id);
            $post_stmt->execute();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete a topic
     */
    public function delete() {
        try {
            // Begin transaction
            $this->conn->beginTransaction();
            
            // First, get the forum ID
            $forum_query = "SELECT forum_id, (SELECT COUNT(*) FROM posts WHERE topic_id = :topic_id) as post_count 
                           FROM " . $this->table . " 
                           WHERE topic_id = :topic_id";
            
            $forum_stmt = $this->conn->prepare($forum_query);
            $forum_stmt->bindParam(':topic_id', $this->topic_id);
            $forum_stmt->execute();
            $topic_data = $forum_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$topic_data) {
                throw new Exception("Topic not found");
            }
            
            $forum_id = $topic_data['forum_id'];
            $post_count = $topic_data['post_count'];
            
            // Delete all posts in the topic
            $delete_posts_query = "DELETE FROM posts WHERE topic_id = :topic_id";
            $delete_posts_stmt = $this->conn->prepare($delete_posts_query);
            $delete_posts_stmt->bindParam(':topic_id', $this->topic_id);
            $delete_posts_stmt->execute();
            
            // Delete the topic
            $delete_query = "DELETE FROM " . $this->table . " WHERE topic_id = :topic_id";
            $delete_stmt = $this->conn->prepare($delete_query);
            $delete_stmt->bindParam(':topic_id', $this->topic_id);
            $delete_stmt->execute();
            
            // Update forum's topic count and post count
            $update_forum_query = "UPDATE forums 
                                 SET topic_count = GREATEST(0, topic_count - 1), 
                                     post_count = GREATEST(0, post_count - :post_count)
                                 WHERE forum_id = :forum_id";
            
            $update_forum_stmt = $this->conn->prepare($update_forum_query);
            $update_forum_stmt->bindParam(':post_count', $post_count, PDO::PARAM_INT);
            $update_forum_stmt->bindParam(':forum_id', $forum_id);
            $update_forum_stmt->execute();
            
            // Commit transaction
            $this->conn->commit();
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            return false;
        }
    }
    
    /**
     * Get a single topic by ID
     */
    public function read() {
        $query = "SELECT t.*, 
                  f.name as forum_name, f.forum_id, 
                  c.name as category_name, c.category_id,
                  u.username as creator_username, u.signature as creator_signature
                  FROM " . $this->table . " t
                  JOIN forums f ON t.forum_id = f.forum_id
                  JOIN categories c ON f.category_id = c.category_id
                  JOIN users u ON t.user_id = u.user_id
                  WHERE t.topic_id = :topic_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':topic_id', $this->topic_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Set properties
            $this->forum_id = $row['forum_id'];
            $this->user_id = $row['user_id'];
            $this->title = $row['title'];
            $this->slug = $row['slug'];
            $this->content = $row['content'];
            $this->is_sticky = $row['is_sticky'];
            $this->is_locked = $row['is_locked'];
            $this->view_count = $row['view_count'];
            $this->reply_count = $row['reply_count'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->last_post_at = $row['last_post_at'];
            $this->last_post_user_id = $row['last_post_user_id'];
            
            // Additional data
            $this->forum_name = $row['forum_name'];
            $this->category_name = $row['category_name'];
            $this->category_id = $row['category_id'];
            $this->creator_username = $row['creator_username'];
            $this->creator_signature = $row['creator_signature'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Increment topic view count
     */
    public function incrementViewCount() {
        $query = "UPDATE " . $this->table . " 
                  SET view_count = view_count + 1 
                  WHERE topic_id = :topic_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':topic_id', $this->topic_id);
        
        return $stmt->execute();
    }
    
    /**
     * Get recent topics
     */
    public function getRecentTopics($limit = 5) {
        $query = "SELECT t.*, 
                  f.name as forum_name, 
                  u.username as creator_username,
                  (SELECT COUNT(*) FROM posts WHERE topic_id = t.topic_id) - 1 as reply_count,
                  (SELECT username FROM users WHERE user_id = t.last_post_user_id) as last_poster
                  FROM " . $this->table . " t
                  JOIN forums f ON t.forum_id = f.forum_id
                  JOIN users u ON t.user_id = u.user_id
                  ORDER BY t.last_post_at DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent topics by user
     */
    public function getRecentTopicsByUser($user_id, $limit = 5) {
        $query = "SELECT t.*, 
                  f.name as forum_name, 
                  u.username as creator_username,
                  (SELECT COUNT(*) FROM posts WHERE topic_id = t.topic_id) - 1 as reply_count,
                  (SELECT username FROM users WHERE user_id = t.last_post_user_id) as last_poster
                  FROM " . $this->table . " t
                  JOIN forums f ON t.forum_id = f.forum_id
                  JOIN users u ON t.user_id = u.user_id
                  WHERE t.user_id = :user_id
                  ORDER BY t.created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Search topics
     */
    public function search($keywords, $limit = 10) {
        $query = "SELECT t.*, 
                  f.name as forum_name, 
                  u.username as creator_username,
                  (SELECT COUNT(*) FROM posts WHERE topic_id = t.topic_id) - 1 as reply_count,
                  (SELECT username FROM users WHERE user_id = t.last_post_user_id) as last_poster
                  FROM " . $this->table . " t
                  JOIN forums f ON t.forum_id = f.forum_id
                  JOIN users u ON t.user_id = u.user_id
                  WHERE MATCH(t.title, t.content) AGAINST(:keywords IN BOOLEAN MODE)
                  ORDER BY t.last_post_at DESC
                  LIMIT :limit";
        
        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = $keywords . '*'; // Add wildcard for partial matches
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':keywords', $keywords);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a slug from title
     */
    private function createSlug($string) {
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
}