<?php
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    
    if ($host === 'localhost' || strpos($host, '127.0.0.1') !== false) {
        return "{$protocol}://{$host}/capstone/WASTE-WISE-CAPSTONE";
    }
    return "{$protocol}://{$host}";
}

define('BASE_URL', getBaseUrl());
date_default_timezone_set('Asia/Manila');

// Only set these if session hasn't started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session security
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
}

/**
 * Generate relative URL paths that work across environments
 * 
 * @param string $path The path relative to the app root
 * @param bool $includeBase Whether to include BASE_URL prefix
 * @return string The formatted path
 */
function appPath($path, $includeBase = true) {
    // Remove leading slashes
    $path = ltrim($path, '/');
    
    return $includeBase ? BASE_URL . '/' . $path : $path;
}
?>