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
?>