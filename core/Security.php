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

    /**
     * Sanitizes currency/decimal strings (e.g., "$1,200.50" -> 1200.50)
     */
    public static function sanitize_float($val) {
        if (is_numeric($val)) return (float)$val;
        $clean = preg_replace('/[^-0-9.]/', '', $val);
        return (float)$clean;
    }

    /**
     * Sanitizes integer strings (e.g., "1,000" -> 1000)
     */
    public static function sanitize_int($val) {
        if (is_numeric($val)) return (int)$val;
        $clean = preg_replace('/[^0-9]/', '', $val);
        return (int)$clean;
    }
}
