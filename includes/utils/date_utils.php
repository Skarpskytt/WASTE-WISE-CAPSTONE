<?php
// Function to format dates in Philippine format
function formatPhilippineDate($datetime, $includeTime = true) {
    if (empty($datetime)) {
        return '';
    }
    
    $timestamp = strtotime($datetime);
    
    if ($includeTime) {
        // Format with time (e.g., "April 8, 2025 3:45 PM")
        return date('F j, Y g:i A', $timestamp);
    } else {
        // Format date only (e.g., "April 8, 2025")
        return date('F j, Y', $timestamp);
    }
}

// Function to format time slots in a human-readable way
function formatTimeSlot($time) {
    $hour = (int)date('G', strtotime($time));
    
    switch($hour) {
        case 9:
            return '9:00 AM - 10:00 AM';
        case 10:
            return '10:00 AM - 11:00 AM';
        case 11:
            return '11:00 AM - 12:00 PM';
        case 13:
            return '1:00 PM - 2:00 PM';
        case 14:
            return '2:00 PM - 3:00 PM';
        case 15:
            return '3:00 PM - 4:00 PM';
        case 16:
            return '4:00 PM - 5:00 PM';
        default:
            return date('g:i A', strtotime($time));
    }
}