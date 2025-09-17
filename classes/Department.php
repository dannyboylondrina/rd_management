<?php
/**
 * Department Class
 * 
 * This class handles department-related operations
 */

require_once __DIR__ . '/BaseModel.php';

class Department extends BaseModel {
    // Table name
    protected $table_name = 'departments';
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Validate department data
     * 
     * @param array $data Department data
     * @param int|null $id Department ID for update operations
     * @return bool Validation result
     */
    protected function validate($data, $id = null) {
        $this->errors = [];
        
        // Name validation
        if (isset($data['name'])) {
            if (empty($data['name'])) {
                $this->errors[] = "Department name is required";
            } elseif (strlen($data['name']) < 2) {
                $this->errors[] = "Department name must be at least 2 characters";
            } else {
                // Check if name is unique
                $query = "SELECT id FROM " . $this->table_name . " WHERE name = :name";
                if ($id) {
                    $query .= " AND id != :id";
                }
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':name', $data['name']);
                
                if ($id) {
                    $stmt->bindParam(':id', $id);
                }
                
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $this->errors[] = "Department name already exists";
                }
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Get department members
     * 
     * @param int $departmentId Department ID
     * @return array Array of users
     */
    public function getMembers($departmentId) {
        $query = "SELECT * FROM users WHERE department_id = :department_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get department projects
     * 
     * @param int $departmentId Department ID
     * @return array Array of projects
     */
    public function getProjects($departmentId) {
        $query = "SELECT * FROM projects WHERE department_id = :department_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get department resources
     * 
     * @param int $departmentId Department ID
     * @return array Array of resources
     */
    public function getResources($departmentId) {
        $query = "SELECT * FROM resources WHERE department_id = :department_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get department statistics
     * 
     * @param int $departmentId Department ID
     * @return array Department statistics
     */
    public function getStatistics($departmentId) {
        // Get department
        $department = $this->getById($departmentId);
        if (!$department) {
            $this->errors[] = "Department not found";
            return false;
        }
        
        // Get counts
        $stats = [
            'department' => $department,
            'members_count' => 0,
            'projects_count' => 0,
            'active_projects_count' => 0,
            'completed_projects_count' => 0,
            'documents_count' => 0,
            'resources_count' => 0
        ];
        
        // Members count
        $query = "SELECT COUNT(*) as count FROM users WHERE department_id = :department_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['members_count'] = $result['count'];
        
        // Projects count
        $query = "SELECT COUNT(*) as count FROM projects WHERE department_id = :department_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['projects_count'] = $result['count'];
        
        // Active projects count
        $query = "SELECT COUNT(*) as count FROM projects WHERE department_id = :department_id AND status = 'in_progress'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['active_projects_count'] = $result['count'];
        
        // Completed projects count
        $query = "SELECT COUNT(*) as count FROM projects WHERE department_id = :department_id AND status = 'completed'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['completed_projects_count'] = $result['count'];
        
        // Documents count
        $query = "SELECT COUNT(*) as count FROM documents d 
                  JOIN projects p ON d.project_id = p.id 
                  WHERE p.department_id = :department_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['documents_count'] = $result['count'];
        
        // Resources count
        $query = "SELECT COUNT(*) as count FROM resources WHERE department_id = :department_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['resources_count'] = $result['count'];
        
        return $stats;
    }
    
    /**
     * Get all departments with basic statistics
     * 
     * @return array Array of departments with statistics
     */
    public function getAllWithStats() {
        $departments = $this->getAll();
        $result = [];
        
        foreach ($departments as $department) {
            $stats = $this->getStatistics($department['id']);
            $result[] = $stats;
        }
        
        return $result;
    }
    
    /**
     * Add a user to a department
     * 
     * @param int $departmentId Department ID
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function addMember($departmentId, $userId) {
        // Check if department exists
        $department = $this->getById($departmentId);
        if (!$department) {
            $this->errors[] = "Department not found";
            return false;
        }
        
        // Check if user exists
        $userObj = new User();
        $user = $userObj->getById($userId);
        if (!$user) {
            $this->errors[] = "User not found";
            return false;
        }
        
        // Update user's department
        $query = "UPDATE users SET department_id = :department_id WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->bindParam(':id', $userId);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Remove a user from a department
     * 
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function removeMember($userId) {
        // Check if user exists
        $userObj = new User();
        $user = $userObj->getById($userId);
        if (!$user) {
            $this->errors[] = "User not found";
            return false;
        }
        
        // Update user's department to NULL
        $query = "UPDATE users SET department_id = NULL WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $userId);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Get department dashboard data
     * 
     * @param int $departmentId Department ID
     * @return array Dashboard data
     */
    public function getDashboardData($departmentId) {
        // Get department statistics
        $stats = $this->getStatistics($departmentId);
        if (!$stats) {
            return false;
        }
        
        // Get recent projects
        $query = "SELECT * FROM projects WHERE department_id = :department_id ORDER BY created_at DESC LIMIT 5";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        $recentProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent documents
        $query = "SELECT d.* FROM documents d 
                  JOIN projects p ON d.project_id = p.id 
                  WHERE p.department_id = :department_id 
                  ORDER BY d.upload_date DESC LIMIT 5";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        $recentDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine data
        $dashboardData = [
            'statistics' => $stats,
            'recent_projects' => $recentProjects,
            'recent_documents' => $recentDocuments
        ];
        
        return $dashboardData;
    }
}
?>