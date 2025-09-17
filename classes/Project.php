<?php
/**
 * Project Class
 * 
 * This class handles project-related operations
 */

require_once __DIR__ . '/BaseModel.php';

class Project extends BaseModel {
    // Table name
    protected $table_name = 'projects';
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Validate project data
     * 
     * @param array $data Project data
     * @param int|null $id Project ID for update operations
     * @return bool Validation result
     */
    protected function validate($data, $id = null) {
        $this->errors = [];
        
        // Title validation
        if (isset($data['title'])) {
            if (empty($data['title'])) {
                $this->errors[] = "Project title is required";
            } elseif (strlen($data['title']) < 3) {
                $this->errors[] = "Project title must be at least 3 characters";
            }
        }
        
        // Date validation
        if (isset($data['start_date']) && !empty($data['start_date'])) {
            if (!$this->isValidDate($data['start_date'])) {
                $this->errors[] = "Invalid start date format";
            }
        }
        
        if (isset($data['end_date']) && !empty($data['end_date'])) {
            if (!$this->isValidDate($data['end_date'])) {
                $this->errors[] = "Invalid end date format";
            } elseif (isset($data['start_date']) && !empty($data['start_date']) && $data['end_date'] < $data['start_date']) {
                $this->errors[] = "End date cannot be before start date";
            }
        }
        
        // Budget validation
        if (isset($data['budget']) && !empty($data['budget'])) {
            if (!is_numeric($data['budget']) || $data['budget'] < 0) {
                $this->errors[] = "Budget must be a positive number";
            }
        }
        
        // Status validation
        if (isset($data['status'])) {
            $validStatuses = ['planning', 'in_progress', 'completed', 'on_hold', 'cancelled'];
            if (!in_array($data['status'], $validStatuses)) {
                $this->errors[] = "Invalid project status";
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Check if a date string is valid
     * 
     * @param string $date Date string
     * @return bool True if valid, false otherwise
     */
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Get projects by department
     * 
     * @param int $departmentId Department ID
     * @return array Array of projects
     */
    public function getByDepartment($departmentId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE department_id = :department_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get projects by status
     * 
     * @param string $status Project status
     * @return array Array of projects
     */
    public function getByStatus($status) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE status = :status";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get projects by creator
     * 
     * @param int $userId User ID
     * @return array Array of projects
     */
    public function getByCreator($userId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE created_by = :created_by";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':created_by', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get projects by member
     * 
     * @param int $userId User ID
     * @return array Array of projects
     */
    public function getByMember($userId) {
        $query = "SELECT p.* FROM " . $this->table_name . " p 
                  JOIN project_members pm ON p.id = pm.project_id 
                  WHERE pm.user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get projects by date range
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Array of projects
     */
    public function getByDateRange($startDate, $endDate) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE (start_date BETWEEN :start_date AND :end_date) 
                  OR (end_date BETWEEN :start_date AND :end_date) 
                  OR (start_date <= :start_date AND end_date >= :end_date)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Add a member to a project
     * 
     * @param int $projectId Project ID
     * @param int $userId User ID
     * @param string $role Role in the project
     * @param string $responsibilities Responsibilities in the project
     * @return bool Success or failure
     */
    public function addMember($projectId, $userId, $role = null, $responsibilities = null) {
        // Check if project exists
        $project = $this->getById($projectId);
        if (!$project) {
            $this->errors[] = "Project not found";
            return false;
        }
        
        // Check if user exists
        $userObj = new User();
        $user = $userObj->getById($userId);
        if (!$user) {
            $this->errors[] = "User not found";
            return false;
        }
        
        // Check if user is already a member
        $query = "SELECT id FROM project_members WHERE project_id = :project_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $projectId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $this->errors[] = "User is already a member of this project";
            return false;
        }
        
        // Add member
        $query = "INSERT INTO project_members (project_id, user_id, role, responsibilities, joined_date) 
                  VALUES (:project_id, :user_id, :role, :responsibilities, CURDATE())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $projectId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':responsibilities', $responsibilities);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Remove a member from a project
     * 
     * @param int $projectId Project ID
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function removeMember($projectId, $userId) {
        $query = "DELETE FROM project_members WHERE project_id = :project_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $projectId);
        $stmt->bindParam(':user_id', $userId);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Get project members
     * 
     * @param int $projectId Project ID
     * @return array Array of members
     */
    public function getMembers($projectId) {
        $query = "SELECT u.*, pm.role, pm.responsibilities, pm.joined_date 
                  FROM users u 
                  JOIN project_members pm ON u.id = pm.user_id 
                  WHERE pm.project_id = :project_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $projectId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update project status
     * 
     * @param int $projectId Project ID
     * @param string $status New status
     * @return bool Success or failure
     */
    public function updateStatus($projectId, $status) {
        $validStatuses = ['planning', 'in_progress', 'completed', 'on_hold', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            $this->errors[] = "Invalid project status";
            return false;
        }
        
        $query = "UPDATE " . $this->table_name . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $projectId);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Allocate a resource to a project
     * 
     * @param int $projectId Project ID
     * @param int $resourceId Resource ID
     * @param int $quantity Quantity to allocate
     * @param string $allocationDate Allocation date (Y-m-d)
     * @param string $returnDate Return date (Y-m-d)
     * @return bool Success or failure
     */
    public function allocateResource($projectId, $resourceId, $quantity, $allocationDate = null, $returnDate = null) {
        // Check if project exists
        $project = $this->getById($projectId);
        if (!$project) {
            $this->errors[] = "Project not found";
            return false;
        }
        
        // Check if resource exists and has enough quantity
        $query = "SELECT * FROM resources WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $resourceId);
        $stmt->execute();
        $resource = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$resource) {
            $this->errors[] = "Resource not found";
            return false;
        }
        
        if ($resource['quantity'] < $quantity) {
            $this->errors[] = "Not enough resources available";
            return false;
        }
        
        // Set allocation date to today if not provided
        if (empty($allocationDate)) {
            $allocationDate = date('Y-m-d');
        } elseif (!$this->isValidDate($allocationDate)) {
            $this->errors[] = "Invalid allocation date format";
            return false;
        }
        
        // Validate return date if provided
        if (!empty($returnDate) && !$this->isValidDate($returnDate)) {
            $this->errors[] = "Invalid return date format";
            return false;
        }
        
        // Add resource allocation
        $query = "INSERT INTO project_resources (project_id, resource_id, quantity, allocation_date, return_date, status) 
                  VALUES (:project_id, :resource_id, :quantity, :allocation_date, :return_date, 'allocated')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $projectId);
        $stmt->bindParam(':resource_id', $resourceId);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':allocation_date', $allocationDate);
        $stmt->bindParam(':return_date', $returnDate);
        
        if ($stmt->execute()) {
            // Update resource availability
            $newQuantity = $resource['quantity'] - $quantity;
            $availability = $newQuantity > 0 ? ($newQuantity == $resource['quantity'] ? 'available' : 'partially_available') : 'unavailable';
            
            $query = "UPDATE resources SET quantity = :quantity, availability = :availability WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':quantity', $newQuantity);
            $stmt->bindParam(':availability', $availability);
            $stmt->bindParam(':id', $resourceId);
            $stmt->execute();
            
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Get allocated resources for a project
     * 
     * @param int $projectId Project ID
     * @return array Array of allocated resources
     */
    public function getAllocatedResources($projectId) {
        $query = "SELECT r.*, pr.quantity as allocated_quantity, pr.allocation_date, pr.return_date, pr.status 
                  FROM resources r 
                  JOIN project_resources pr ON r.id = pr.resource_id 
                  WHERE pr.project_id = :project_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $projectId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>