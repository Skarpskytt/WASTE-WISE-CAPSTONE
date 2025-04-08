<?php
// Detect environment and set base URL
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    
    // Local development
    if ($host === 'localhost' || strpos($host, '127.0.0.1') !== false) {
        return "{$protocol}://{$host}/capstone/WASTE-WISE-CAPSTONE";
    }
    
    // Production (Hostinger)
    return "{$protocol}://{$host}";
}

// App configuration
define('BASE_URL', getBaseUrl());

// Set default timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Other timezone-specific settings
define('TIMEZONE', 'Asia/Manila');
define('TIMEZONE_DISPLAY', 'PHT'); // Philippine Time

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