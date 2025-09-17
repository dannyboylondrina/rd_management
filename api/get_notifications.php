<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated',
        'notifications' => [],
        'unread_count' => 0
    ]);
    exit;
}

// Include necessary files
require_once '../classes/Notification.php';

// Get user ID from session
$userId = $_SESSION['user_id'];

// Initialize Notification object
$notificationObj = new Notification();

// Get unread notifications count
$unreadCount = $notificationObj->countUnread($userId);

// Get recent notifications (limit to 5)
$notifications = $notificationObj->getForUser($userId, false, 5);

// Process notifications for display
$processedNotifications = [];
foreach ($notifications as $notification) {
    // Calculate time ago
    $timeAgo = getTimeAgo($notification['created_at']);
    
    // Add to processed notifications
    $processedNotifications[] = [
        'id' => $notification['id'],
        'title' => $notification['title'],
        'message' => $notification['message'],
        'is_read' => (bool)$notification['is_read'],
        'time_ago' => $timeAgo,
        'created_at' => $notification['created_at'],
        'type' => $notification['type'],
        'related_id' => $notification['related_id'],
        'related_type' => $notification['related_type']
    ];
}

// Return JSON response
echo json_encode([
    'success' => true,
    'notifications' => $processedNotifications,
    'unread_count' => $unreadCount
]);

/**
 * Get time ago string from timestamp
 * 
 * @param string $timestamp Timestamp
 * @return string Time ago string
 */
function getTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}
?>