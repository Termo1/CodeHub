<?php
/**
 * Post class for handling post-related operations
 */
class Post {
    private $conn;
    private $table = 'posts';
    
    public $post_id;
    public $topic_id;
    public $user_id;
    public $content;
    public $is_solution;
    public $created_at;
    public $updated_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Create a new post
     */
    public function create() {
        try {
            $this->conn->beginTransaction();
            
            $query = "INSERT INTO " . $this->table . " 
                      (topic_id, user_id, content, is_solution, created_at) 
                      VALUES (:topic_id, :user_id, :content, :is_solution, NOW())";
            
            $stmt = $this->conn->prepare($query);
            $this->content = htmlspecialchars(strip_tags($this->content));
            $this->is_solution = $this->is_solution ? 1 : 0;
            
            $stmt->bindParam(':topic_id', $this->topic_id);
            $stmt->bindParam(':user_id', $this->user_id);
            $stmt->bindParam(':content', $this->content);
            $stmt->bindParam(':is_solution', $this->is_solution);
            $stmt->execute();
            
            $this->post_id = $this->conn->lastInsertId();
            
            // Update topic
            $update_topic = "UPDATE topics 
                           SET last_post_at = NOW(), last_post_user_id = :user_id, reply_count = reply_count + 1
                           WHERE topic_id = :topic_id";
            $stmt = $this->conn->prepare($update_topic);
            $stmt->bindParam(':user_id', $this->user_id);
            $stmt->bindParam(':topic_id', $this->topic_id);
            $stmt->execute();
            
            // Update forum
            $update_forum = "UPDATE forums f JOIN topics t ON f.forum_id = t.forum_id
                           SET f.post_count = f.post_count + 1, f.last_post_at = NOW()
                           WHERE t.topic_id = :topic_id";
            $stmt = $this->conn->prepare($update_forum);
            $stmt->bindParam(':topic_id', $this->topic_id);
            $stmt->execute();
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }
    
    /**
     * Update post
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET content = :content, updated_at = NOW() 
                  WHERE post_id = :post_id";
        
        $stmt = $this->conn->prepare($query);
        $this->content = htmlspecialchars(strip_tags($this->content));
        
        $stmt->bindParam(':content', $this->content);
        $stmt->bindParam(':post_id', $this->post_id);
        
        return $stmt->execute();
    }
    
    /**
     * Delete post
     */
    public function delete() {
        try {
            $this->conn->beginTransaction();
            
            $topic_query = "SELECT topic_id FROM " . $this->table . " WHERE post_id = :post_id";
            $stmt = $this->conn->prepare($topic_query);
            $stmt->bindParam(':post_id', $this->post_id);
            $stmt->execute();
            $topic_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$topic_data) throw new Exception("Post not found");
            
            $topic_id = $topic_data['topic_id'];
            
            // Delete post
            $delete_query = "DELETE FROM " . $this->table . " WHERE post_id = :post_id";
            $stmt = $this->conn->prepare($delete_query);
            $stmt->bindParam(':post_id', $this->post_id);
            $stmt->execute();
            
            // Update topic reply count
            $update_topic = "UPDATE topics SET reply_count = reply_count - 1 WHERE topic_id = :topic_id";
            $stmt = $this->conn->prepare($update_topic);
            $stmt->bindParam(':topic_id', $topic_id);
            $stmt->execute();
            
            // Update last post info
            $last_post_query = "SELECT post_id, user_id, created_at FROM posts 
                               WHERE topic_id = :topic_id ORDER BY created_at DESC LIMIT 1";
            $stmt = $this->conn->prepare($last_post_query);
            $stmt->bindParam(':topic_id', $topic_id);
            $stmt->execute();
            $last_post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($last_post) {
                $update_last = "UPDATE topics 
                               SET last_post_at = :last_post_at, last_post_user_id = :last_post_user_id 
                               WHERE topic_id = :topic_id";
                $stmt = $this->conn->prepare($update_last);
                $stmt->bindParam(':last_post_at', $last_post['created_at']);
                $stmt->bindParam(':last_post_user_id', $last_post['user_id']);
                $stmt->bindParam(':topic_id', $topic_id);
                $stmt->execute();
            }
            
            // Update forum post count
            $update_forum = "UPDATE forums f JOIN topics t ON f.forum_id = t.forum_id
                           SET f.post_count = GREATEST(0, f.post_count - 1)
                           WHERE t.topic_id = :topic_id";
            $stmt = $this->conn->prepare($update_forum);
            $stmt->bindParam(':topic_id', $topic_id);
            $stmt->execute();
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }
    
    /**
     * Get post by ID
     */
    public function getById($post_id) {
        $query = "SELECT p.*, t.title as topic_title, t.topic_id, 
                  f.name as forum_name, f.forum_id, c.name as category_name, c.category_id
                  FROM " . $this->table . " p
                  JOIN topics t ON p.topic_id = t.topic_id
                  JOIN forums f ON t.forum_id = f.forum_id
                  JOIN categories c ON f.category_id = c.category_id
                  WHERE p.post_id = :post_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if post is first in topic
     */
    public function isFirstPost($post_id, $topic_id) {
        $query = "SELECT post_id FROM " . $this->table . " 
                  WHERE topic_id = :topic_id ORDER BY created_at ASC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':topic_id', $topic_id);
        $stmt->execute();
        $first_post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $first_post && $first_post['post_id'] == $post_id;
    }
    
    /**
     * Get posts in topic with pagination
     */
    public function getPostsInTopic($topic_id, $limit = 10, $offset = 0) {
        $query = "SELECT p.*, u.username, u.signature, u.reputation, u.created_at as member_since,
                  (SELECT COUNT(*) FROM posts WHERE user_id = p.user_id) as user_post_count
                  FROM " . $this->table . " p
                  JOIN users u ON p.user_id = u.user_id
                  WHERE p.topic_id = :topic_id
                  ORDER BY p.created_at ASC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':topic_id', $topic_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count posts in topic
     */
    public function countPostsInTopic($topic_id) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE topic_id = :topic_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':topic_id', $topic_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'];
    }

    
    
    
}