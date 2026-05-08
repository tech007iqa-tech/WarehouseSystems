<?php
/**
 * IQA Lightweight Security Helper
 * Simple CSRF protection to keep forms safe.
 */

class Security {
    /**
     * Ensures a CSRF token exists in the session
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Gets the current CSRF token
     */
    public static function getToken() {
        self::init();
        return $_SESSION['csrf_token'];
    }

    /**
     * Validates a submitted CSRF token
     */
    public static function validate($token) {
        self::init();
        return !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
