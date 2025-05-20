<?php
/**
 * Session handling class
 */
class Session {
    /**
     * Start a session
     */
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Set a session variable
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get a session variable
     */
    public static function get($key) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }
    
    /**
     * Check if a session variable exists
     */
    public static function exists($key) {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove a session variable
     */
    public static function remove($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Destroy the session
     */
    public static function destroy() {
        session_unset();
        session_destroy();
    }
    
    /**
     * Set user as logged in
     */
    public static function setUserLoggedIn($user) {
        self::set('is_logged_in', true);
        self::set('user_id', $user->user_id);
        self::set('username', $user->username);
        self::set('email', $user->email);
        self::set('role', $user->role);
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return self::exists('is_logged_in') && self::get('is_logged_in') === true;
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::isLoggedIn() && self::get('role') === 'admin';
    }
    
    /**
     * Check if user is moderator
     */
    public static function isModerator() {
        return self::isLoggedIn() && (self::get('role') === 'moderator' || self::get('role') === 'admin');
    }
    
    /**
     * Set flash message
     */
    public static function setFlash($key, $message) {
        $_SESSION['flash'][$key] = $message;
    }
    
    /**
     * Get flash message and remove it
     */
    public static function getFlash($key) {
        $message = null;
        
        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
        }
        
        return $message;
    }
    
    /**
     * Check if flash message exists
     */
    public static function hasFlash($key) {
        return isset($_SESSION['flash'][$key]);
    }
    
    /**
     * Get all flash messages
     */
    public static function getAllFlash() {
        $flash = $_SESSION['flash'] ?? [];
        $_SESSION['flash'] = [];
        return $flash;
    }
    
    /**
     * Get CSRF token
     */
    public static function getCsrfToken() {
        if (!self::exists('csrf_token')) {
            self::set('csrf_token', bin2hex(random_bytes(32)));
        }
        
        return self::get('csrf_token');
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCsrfToken($token) {
        return self::exists('csrf_token') && self::get('csrf_token') === $token;
    }
}