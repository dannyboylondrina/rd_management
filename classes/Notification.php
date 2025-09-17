<?php
/**
 * Notification Class
 * 
 * This class handles notification-related operations
 */

require_once __DIR__ . '/BaseModel.php';

class Notification extends BaseModel {
    // Table name
    protected $table_name = 'notifications';
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Validate notification data
     * 
     * @param array $data Notification data
     * @param int|null $id Notification ID for update operations
     * @return bool Validation result
     */
    protected function validate($data, $id = null) {
        $this->errors = [];
        
        // User ID validation
        if (isset($data['user_id'])) {
            if (empty($data['user_id'])) {
                $this->errors[] = "User ID is required";
            } else {
                // Check if user exists
                $userObj = new User();
                $user = $userObj->getById($data['user_id']);
                if (!$user) {
                    $this->errors[] = "User not found";
                }
            }
        }
        
        // Title validation
        if (isset($data['title']) && empty($data['title'])) {
            $this->errors[] = "Notification title is required";
        }
        
        // Message validation
        if (isset($data['message']) && empty($data['message'])) {
            $this->errors[] = "Notification message is required";
        }
        
        return empty($this->errors);
    }
    
    /**
     * Get notifications for a user
     * 
     * @param int $userId User ID
     * @param bool $unreadOnly Get only unread notifications
     * @param int $limit Number of notifications to return
     * @param int $offset Offset for pagination
     * @return array Array of notifications
     */
    public function getForUser($userId, $unreadOnly = false, $limit = null, $offset = null) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id";
        
        if ($unreadOnly) {
            $query .= " AND is_read = 0";
        }
        
        $query .= " ORDER BY created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT " . $limit;
            
            if ($offset) {
                $query .= " OFFSET " . $offset;
            }
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mark a notification as read
     * 
     * @param int $id Notification ID
     * @return bool Success or failure
     */
    public function markAsRead($id) {
        $query = "UPDATE " . $this->table_name . " SET is_read = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Mark all notifications as read for a user
     * 
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function markAllAsRead($userId) {
        $query = "UPDATE " . $this->table_name . " SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Count unread notifications for a user
     * 
     * @param int $userId User ID
     * @return int Number of unread notifications
     */
    public function countUnread($userId) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }
    
    /**
     * Create a notification for a user
     * 
     * @param int $userId User ID
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param int $relatedId Related entity ID
     * @param string $relatedType Related entity type
     * @return int|false ID of the new notification or false on failure
     */
    public function createNotification($userId, $title, $message, $type = null, $relatedId = null, $relatedType = null) {
        $data = [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
            'is_read' => 0
        ];
        
        return $this->create($data);
    }
    
    /**
     * Create notifications for multiple users
     * 
     * @param array $userIds Array of user IDs
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param int $relatedId Related entity ID
     * @param string $relatedType Related entity type
     * @return bool Success or failure
     */
    public function createNotificationForMultipleUsers($userIds, $title, $message, $type = null, $relatedId = null, $relatedType = null) {
        if (empty($userIds)) {
            $this->errors[] = "No users specified";
            return false;
        }
        
        $success = true;
        
        foreach ($userIds as $userId) {
            $result = $this->createNotification($userId, $title, $message, $type, $relatedId, $relatedType);
            if (!$result) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Create notifications for all users in a department
     * 
     * @param int $departmentId Department ID
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param int $relatedId Related entity ID
     * @param string $relatedType Related entity type
     * @return bool Success or failure
     */
    public function createNotificationForDepartment($departmentId, $title, $message, $type = null, $relatedId = null, $relatedType = null) {
        // Get department members
        $departmentObj = new Department();
        $members = $departmentObj->getMembers($departmentId);
        
        if (empty($members)) {
            $this->errors[] = "No members found in the department";
            return false;
        }
        
        $userIds = array_column($members, 'id');
        
        return $this->createNotificationForMultipleUsers($userIds, $title, $message, $type, $relatedId, $relatedType);
    }
    
    /**
     * Create notifications for all users in a project
     * 
     * @param int $projectId Project ID
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param int $relatedId Related entity ID
     * @param string $relatedType Related entity type
     * @return bool Success or failure
     */
    public function createNotificationForProject($projectId, $title, $message, $type = null, $relatedId = null, $relatedType = null) {
        // Get project members
        $projectObj = new Project();
        $members = $projectObj->getMembers($projectId);
        
        if (empty($members)) {
            $this->errors[] = "No members found in the project";
            return false;
        }
        
        $userIds = array_column($members, 'id');
        
        return $this->createNotificationForMultipleUsers($userIds, $title, $message, $type, $relatedId, $relatedType);
    }
    
    /**
     * Delete all notifications for a user
     * 
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function deleteAllForUser($userId) {
        $query = "DELETE FROM " . $this->table_name . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
}
?>