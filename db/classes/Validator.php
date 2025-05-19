<?php
/**
 * Validator class for form validation
 */
class Validator {
    private $errors = [];
    
    /**
     * Check if there are any errors
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get first error
     */
    public function getFirstError() {
        return reset($this->errors);
    }
    
    /**
     * Validate required field
     */
    public function required($field, $value, $fieldName = null) {
        $fieldName = $fieldName ?? ucfirst($field);
        
        if (empty($value)) {
            $this->errors[$field] = "{$fieldName} is required";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate email
     */
    public function email($field, $value, $fieldName = null) {
        $fieldName = $fieldName ?? ucfirst($field);
        
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$fieldName} must be a valid email address";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength($field, $value, $length, $fieldName = null) {
        $fieldName = $fieldName ?? ucfirst($field);
        
        if (strlen($value) < $length) {
            $this->errors[$field] = "{$fieldName} must be at least {$length} characters";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength($field, $value, $length, $fieldName = null) {
        $fieldName = $fieldName ?? ucfirst($field);
        
        if (strlen($value) > $length) {
            $this->errors[$field] = "{$fieldName} must not exceed {$length} characters";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate password strength
     */
    public function passwordStrength($field, $value, $fieldName = null) {
        $fieldName = $fieldName ?? ucfirst($field);
        
        // Password must contain at least one uppercase letter, one lowercase letter, one number, and be at least 8 characters
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $value)) {
            $this->errors[$field] = "{$fieldName} must be at least 8 characters and contain at least one uppercase letter, one lowercase letter, and one number";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate password confirmation
     */
    public function passwordMatch($password, $confirmation) {
        if ($password !== $confirmation) {
            $this->errors['password_confirm'] = "Passwords do not match";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate username format
     */
    public function usernameFormat($field, $value, $fieldName = null) {
        $fieldName = $fieldName ?? ucfirst($field);
        
        // Only allow alphanumeric characters and underscores
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
            $this->errors[$field] = "{$fieldName} can only contain letters, numbers, and underscores";
            return false;
        }
        
        return true;
    }
    
    /**
     * Add custom error
     */
    public function addError($field, $message) {
        $this->errors[$field] = $message;
    }
}