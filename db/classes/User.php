<?php
/**
 * User class for handling user-related operations
 */
class User {
    // Database connection and table name
    private $conn;
    private $table = 'users';
    
    // User properties
    public $user_id;
    public $username;
    public $email;
    public $password;
    public $avatar;
    public $bio;
    public $signature;
    public $role;
    public $reputation;
    public $is_active;
    public $created_at;
    public $last_login;
    
    /**
     * Constructor with DB connection
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Register new user
     */
    public function register() {
        // Sanitize inputs
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        
        // Hash password
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        
        // SQL query
        $query = "INSERT INTO " . $this->table . " 
                  (username, email, password, role, is_active, created_at) 
                  VALUES 
                  (:username, :email, :password, 'user', true, NOW())";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $this->password);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        // Sanitize
        $username = htmlspecialchars(strip_tags($username));
        
        // SQL query
        $query = "SELECT * FROM " . $this->table . " WHERE username = :username AND is_active = true";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind param
        $stmt->bindParam(':username', $username);
        
        // Execute query
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if(password_verify($password, $row['password'])) {
                // Set user properties
                $this->user_id = $row['user_id'];
                $this->username = $row['username'];
                $this->email = $row['email'];
                $this->avatar = $row['avatar'];
                $this->bio = $row['bio'];
                $this->signature = $row['signature'];
                $this->role = $row['role'];
                $this->reputation = $row['reputation'];
                $this->is_active = $row['is_active'];
                $this->created_at = $row['created_at'];
                
                // Update last login
                $this->updateLastLogin();
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Update user's last login time
     */
    private function updateLastLogin() {
        $query = "UPDATE " . $this->table . " SET last_login = NOW() WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->execute();
    }
    
    /**
     * Check if username already exists
     */
    public function isUsernameExists($username) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['count'] > 0;
    }
    
    /**
     * Check if email already exists
     */
    public function isEmailExists($email) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['count'] > 0;
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all users
     */
    public function getAllUsers($limit = 10, $offset = 0) {
        $query = "SELECT * FROM " . $this->table . " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update user profile
     */
    public function updateProfile() {
        // Sanitize inputs
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->bio = htmlspecialchars(strip_tags($this->bio));
        $this->signature = htmlspecialchars(strip_tags($this->signature));
        
        // SQL query
        $query = "UPDATE " . $this->table . " 
                  SET username = :username, email = :email, 
                      bio = :bio, signature = :signature 
                  WHERE user_id = :user_id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':bio', $this->bio);
        $stmt->bindParam(':signature', $this->signature);
        $stmt->bindParam(':user_id', $this->user_id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Update user password
     */
    public function updatePassword($new_password) {
        // Hash password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // SQL query
        $query = "UPDATE " . $this->table . " SET password = :password WHERE user_id = :user_id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':user_id', $this->user_id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Update user avatar
     */
    public function updateAvatar($avatar_path) {
        $query = "UPDATE " . $this->table . " SET avatar = :avatar WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':avatar', $avatar_path);
        $stmt->bindParam(':user_id', $this->user_id);
        
        if($stmt->execute()) {
            $this->avatar = $avatar_path;
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete user account
     */
    public function deleteAccount() {
        $query = "DELETE FROM " . $this->table . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id);
        
        return $stmt->execute();
    }
    
    /**
     * Get user stats (post count, topic count)
     */
    public function getUserStats() {
        $stats = [];
        
        // Get topic count
        $query = "SELECT COUNT(*) as topic_count FROM topics WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['topic_count'] = $row['topic_count'];
        
        // Get post count
        $query = "SELECT COUNT(*) as post_count FROM posts WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['post_count'] = $row['post_count'];
        
        return $stats;
    }
}