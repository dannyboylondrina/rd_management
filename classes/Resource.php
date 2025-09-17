<?php
/**
 * Resource Class
 * 
 * This class handles resource-related operations
 */

require_once __DIR__ . '/BaseModel.php';

class Resource extends BaseModel {
    // Table name
    protected $table_name = 'resources';
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Validate resource data
     * 
     * @param array $data Resource data
     * @param int|null $id Resource ID for update operations
     * @return bool Validation result
     */
    protected function validate($data, $id = null) {
        $this->errors = [];
        
        // Name validation
        if (isset($data['name'])) {
            if (empty($data['name'])) {
                $this->errors[] = "Resource name is required";
            } elseif (strlen($data['name']) < 2) {
                $this->errors[] = "Resource name must be at least 2 characters";
            }
        }
        
        // Type validation
        if (isset($data['type'])) {
            $validTypes = ['personnel', 'equipment', 'facility', 'financial'];
            if (!in_array($data['type'], $validTypes)) {
                $this->errors[] = "Invalid resource type";
            }
        }
        
        // Quantity validation
        if (isset($data['quantity']) && $data['quantity'] < 0) {
            $this->errors[] = "Quantity cannot be negative";
        }
        
        // Availability validation
        if (isset($data['availability'])) {
            $validAvailability = ['available', 'partially_available', 'unavailable'];
            if (!in_array($data['availability'], $validAvailability)) {
                $this->errors[] = "Invalid availability status";
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Get resources by type
     * 
     * @param string $type Resource type
     * @return array Array of resources
     */
    public function getByType($type) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE type = :type";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':type', $type);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get resources by availability
     * 
     * @param string $availability Availability status
     * @return array Array of resources
     */
    public function getByAvailability($availability) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE availability = :availability";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':availability', $availability);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get resources by department
     * 
     * @param int $departmentId Department ID
     * @return array Array of resources
     */
    public function getByDepartment($departmentId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE department_id = :department_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get available resources
     * 
     * @param string|null $type Resource type (optional)
     * @param int|null $departmentId Department ID (optional)
     * @return array Array of available resources
     */
    public function getAvailable($type = null, $departmentId = null) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE availability != 'unavailable'";
        $params = [];
        
        if ($type) {
            $query .= " AND type = :type";
            $params[':type'] = $type;
        }
        
        if ($departmentId) {
            $query .= " AND department_id = :department_id";
            $params[':department_id'] = $departmentId;
        }
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindParam($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update resource availability
     * 
     * @param int $id Resource ID
     * @param string $availability New availability status
     * @return bool Success or failure
     */
    public function updateAvailability($id, $availability) {
        $validAvailability = ['available', 'partially_available', 'unavailable'];
        if (!in_array($availability, $validAvailability)) {
            $this->errors[] = "Invalid availability status";
            return false;
        }
        
        $query = "UPDATE " . $this->table_name . " SET availability = :availability WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':availability', $availability);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Update resource quantity
     * 
     * @param int $id Resource ID
     * @param int $quantity New quantity
     * @return bool Success or failure
     */
    public function updateQuantity($id, $quantity) {
        if ($quantity < 0) {
            $this->errors[] = "Quantity cannot be negative";
            return false;
        }
        
        $query = "UPDATE " . $this->table_name . " SET quantity = :quantity WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            // Update availability based on quantity
            $availability = $quantity > 0 ? 'available' : 'unavailable';
            $this->updateAvailability($id, $availability);
            
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Get projects using a resource
     * 
     * @param int $resourceId Resource ID
     * @return array Array of projects
     */
    public function getProjects($resourceId) {
        $query = "SELECT p.* FROM projects p 
                  JOIN project_resources pr ON p.id = pr.project_id 
                  WHERE pr.resource_id = :resource_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':resource_id', $resourceId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get resource allocation history
     * 
     * @param int $resourceId Resource ID
     * @return array Array of allocation records
     */
    public function getAllocationHistory($resourceId) {
        $query = "SELECT pr.*, p.title as project_title, p.status as project_status 
                  FROM project_resources pr 
                  JOIN projects p ON pr.project_id = p.id 
                  WHERE pr.resource_id = :resource_id 
                  ORDER BY pr.allocation_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':resource_id', $resourceId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Transfer resource between departments
     * 
     * @param int $resourceId Resource ID
     * @param int $newDepartmentId New department ID
     * @return bool Success or failure
     */
    public function transferToDepartment($resourceId, $newDepartmentId) {
        // Check if resource exists
        $resource = $this->getById($resourceId);
        if (!$resource) {
            $this->errors[] = "Resource not found";
            return false;
        }
        
        // Check if department exists
        $departmentObj = new Department();
        $department = $departmentObj->getById($newDepartmentId);
        if (!$department) {
            $this->errors[] = "Department not found";
            return false;
        }
        
        // Update resource's department
        $query = "UPDATE " . $this->table_name . " SET department_id = :department_id WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $newDepartmentId);
        $stmt->bindParam(':id', $resourceId);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Get resource usage statistics
     * 
     * @param int $resourceId Resource ID
     * @return array Resource usage statistics
     */
    public function getUsageStatistics($resourceId) {
        // Check if resource exists
        $resource = $this->getById($resourceId);
        if (!$resource) {
            $this->errors[] = "Resource not found";
            return false;
        }
        
        // Get allocation count
        $query = "SELECT COUNT(*) as allocation_count FROM project_resources WHERE resource_id = :resource_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':resource_id', $resourceId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $allocationCount = $result['allocation_count'];
        
        // Get current allocations
        $query = "SELECT COUNT(*) as current_allocations FROM project_resources 
                  WHERE resource_id = :resource_id AND status = 'allocated'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':resource_id', $resourceId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentAllocations = $result['current_allocations'];
        
        // Get total allocated quantity
        $query = "SELECT SUM(quantity) as total_allocated FROM project_resources 
                  WHERE resource_id = :resource_id AND status = 'allocated'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':resource_id', $resourceId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalAllocated = $result['total_allocated'] ?: 0;
        
        // Calculate availability percentage
        $availabilityPercentage = 0;
        if ($resource['quantity'] > 0) {
            $availabilityPercentage = (($resource['quantity'] - $totalAllocated) / $resource['quantity']) * 100;
            $availabilityPercentage = max(0, min(100, $availabilityPercentage)); // Ensure between 0-100
        }
        
        // Combine statistics
        $statistics = [
            'resource' => $resource,
            'allocation_count' => $allocationCount,
            'current_allocations' => $currentAllocations,
            'total_allocated' => $totalAllocated,
            'available_quantity' => max(0, $resource['quantity'] - $totalAllocated),
            'availability_percentage' => $availabilityPercentage
        ];
        
        return $statistics;
    }
    
    /**
     * Get currently allocated quantity for a resource
     * 
     * @param int $resourceId Resource ID
     * @return int Currently allocated quantity
     */ 
    public function getCurrentlyAllocatedQuantity($resourceId) {
        // Query the database to get the currently allocated quantity for the given resource ID
        $query = "SELECT SUM(quantity) as total_allocated FROM project_resources 
                  WHERE resource_id = :resource_id AND status = 'allocated'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':resource_id', $resourceId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalAllocated = $result['total_allocated'] ?: 0;
        
        return $totalAllocated;
    }
    
    /**
     * Get resource allocations (alias for getAllocationHistory)
     * 
     * @param int $resourceId Resource ID
     * @return array Array of allocation records
     */
    public function getAllocations($resourceId) {
        return $this->getAllocationHistory($resourceId);
    }
    
    /**
     * Get allocation by ID
     * 
     * @param int $allocationId Allocation ID
     * @return array|false Allocation record or false if not found
     */
    public function getAllocationById($allocationId) {
        $query = "SELECT pr.*, p.title as project_title, p.status as project_status 
                  FROM project_resources pr 
                  JOIN projects p ON pr.project_id = p.id 
                  WHERE pr.id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $allocationId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Allocate resource to project
     * 
     * @param array $allocation Allocation data
     * @return bool Success or failure
     */
    public function allocateToProject($allocation) {
        // Check if resource exists
        $resource = $this->getById($allocation['resource_id']);
        if (!$resource) {
            $this->errors[] = "Resource not found";
            return false;
        }
        
        // Check if project exists
        $projectObj = new Project();
        $project = $projectObj->getById($allocation['project_id']);
        if (!$project) {
            $this->errors[] = "Project not found";
            return false;
        }
        
        // Check if quantity is valid
        if ($allocation['quantity'] <= 0) {
            $this->errors[] = "Quantity must be greater than zero";
            return false;
        }
        
        // Check if enough quantity is available
        $currentlyAllocated = $this->getCurrentlyAllocatedQuantity($allocation['resource_id']);
        $availableQuantity = $resource['quantity'] - $currentlyAllocated;
        
        if ($allocation['quantity'] > $availableQuantity) {
            $this->errors[] = "Requested quantity exceeds available quantity. Maximum available: " . $availableQuantity;
            return false;
        }
        
        // Insert allocation record
        $query = "INSERT INTO project_resources (project_id, resource_id, quantity, allocation_date, status, notes) 
                  VALUES (:project_id, :resource_id, :quantity, :allocation_date, 'allocated', :notes)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $allocation['project_id']);
        $stmt->bindParam(':resource_id', $allocation['resource_id']);
        $stmt->bindParam(':quantity', $allocation['quantity']);
        $stmt->bindParam(':allocation_date', $allocation['allocation_date']);
        $stmt->bindParam(':notes', $allocation['notes']);
        
        if ($stmt->execute()) {
            // Update resource availability if necessary
            $remainingQuantity = $availableQuantity - $allocation['quantity'];
            if ($remainingQuantity <= 0) {
                $this->updateAvailability($allocation['resource_id'], 'unavailable');
            } elseif ($remainingQuantity < $resource['quantity']) {
                $this->updateAvailability($allocation['resource_id'], 'partially_available');
            }
            
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Update allocation
     * 
     * @param int $allocationId Allocation ID
     * @param array $allocation Allocation data
     * @return bool Success or failure
     */
    public function updateAllocation($allocationId, $allocation) {
        // Check if allocation exists
        $existingAllocation = $this->getAllocationById($allocationId);
        if (!$existingAllocation) {
            $this->errors[] = "Allocation not found";
            return false;
        }
        
        // Update allocation record
        $query = "UPDATE project_resources 
                  SET status = :status, return_date = :return_date, notes = :notes 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $allocation['status']);
        $stmt->bindParam(':return_date', $allocation['return_date']);
        $stmt->bindParam(':notes', $allocation['notes']);
        $stmt->bindParam(':id', $allocationId);
        
        if ($stmt->execute()) {
            // Update resource availability if necessary
            if ($allocation['status'] === 'returned') {
                $resource = $this->getById($existingAllocation['resource_id']);
                $currentlyAllocated = $this->getCurrentlyAllocatedQuantity($existingAllocation['resource_id']);
                
                if ($currentlyAllocated <= 0) {
                    $this->updateAvailability($existingAllocation['resource_id'], 'available');
                } elseif ($currentlyAllocated < $resource['quantity']) {
                    $this->updateAvailability($existingAllocation['resource_id'], 'partially_available');
                }
            }
            
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Get available resources by department
     * 
     * @param int $departmentId Department ID
     * @param string|null $type Resource type (optional)
     * @return array Array of available resources
     */
    public function getAvailableByDepartment($departmentId, $type = null) {
        return $this->getAvailable($type, $departmentId);
    }
    
    /**
     * Get all resources with filters
     * 
     * @param array $filters Filter conditions
     * @param string $orderBy Column to order by
     * @param string $orderDirection Order direction (ASC or DESC)
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Array of resources
     */
    public function getAllWithFilters($filters = [], $orderBy = 'name', $orderDirection = 'ASC', $limit = null, $offset = null) {
        $query = "SELECT r.* FROM " . $this->table_name . " r";
        $whereClause = [];
        $values = [];
        
        // Apply filters
        if (!empty($filters)) {
            if (isset($filters['type']) && !empty($filters['type'])) {
                $whereClause[] = "r.type = :type";
                $values[':type'] = $filters['type'];
            }
            
            if (isset($filters['availability']) && $filters['availability'] !== '') {
                $whereClause[] = "r.availability = :availability";
                $values[':availability'] = $filters['availability'] == 1 ? 'available' : 'unavailable';
            }
            
            if (isset($filters['department_id']) && !empty($filters['department_id'])) {
                $whereClause[] = "r.department_id = :department_id";
                $values[':department_id'] = $filters['department_id'];
            }
            
            if (isset($filters['search']) && !empty($filters['search'])) {
                $whereClause[] = "(r.name LIKE :search OR r.description LIKE :search)";
                $values[':search'] = '%' . $filters['search'] . '%';
            }
        }
        
        if (!empty($whereClause)) {
            $query .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        if ($orderBy) {
            $query .= " ORDER BY r." . $orderBy . " " . $orderDirection;
        }
        
        if ($limit) {
            $query .= " LIMIT " . $limit;
            
            if ($offset) {
                $query .= " OFFSET " . $offset;
            }
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($values);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count resources with filters
     * 
     * @param array $filters Filter conditions
     * @return int Number of resources
     */
    public function countWithFilters($filters = []) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " r";
        $whereClause = [];
        $values = [];
        
        // Apply filters
        if (!empty($filters)) {
            if (isset($filters['type']) && !empty($filters['type'])) {
                $whereClause[] = "r.type = :type";
                $values[':type'] = $filters['type'];
            }
            
            if (isset($filters['availability']) && $filters['availability'] !== '') {
                $whereClause[] = "r.availability = :availability";
                $values[':availability'] = $filters['availability'] == 1 ? 'available' : 'unavailable';
            }
            
            if (isset($filters['department_id']) && !empty($filters['department_id'])) {
                $whereClause[] = "r.department_id = :department_id";
                $values[':department_id'] = $filters['department_id'];
            }
            
            if (isset($filters['search']) && !empty($filters['search'])) {
                $whereClause[] = "(r.name LIKE :search OR r.description LIKE :search)";
                $values[':search'] = '%' . $filters['search'] . '%';
            }
        }
        
        if (!empty($whereClause)) {
            $query .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($values);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }
    
    /**
     * Get available resources and resources allocated to user's projects
     * 
     * @param int $userId User ID
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @param array $filters Filter conditions
     * @return array Array of resources
     */
    public function getAvailableAndAllocatedToUser($userId, $limit = null, $offset = null, $filters = []) {
        $query = "SELECT DISTINCT r.* FROM " . $this->table_name . " r
                  LEFT JOIN project_resources pr ON r.id = pr.resource_id
                  LEFT JOIN project_members pm ON pr.project_id = pm.project_id
                  WHERE (r.availability != 'unavailable' OR (pm.user_id = :user_id AND pr.status = 'allocated'))";
        
        $values = [':user_id' => $userId];
        $whereClause = [];
        
        // Apply additional filters
        if (!empty($filters)) {
            if (isset($filters['type']) && !empty($filters['type'])) {
                $whereClause[] = "r.type = :type";
                $values[':type'] = $filters['type'];
            }
            
            if (isset($filters['availability']) && $filters['availability'] !== '') {
                $whereClause[] = "r.availability = :availability";
                $values[':availability'] = $filters['availability'] == 1 ? 'available' : 'unavailable';
            }
            
            if (isset($filters['department_id']) && !empty($filters['department_id'])) {
                $whereClause[] = "r.department_id = :department_id";
                $values[':department_id'] = $filters['department_id'];
            }
            
            if (isset($filters['search']) && !empty($filters['search'])) {
                $whereClause[] = "(r.name LIKE :search OR r.description LIKE :search)";
                $values[':search'] = '%' . $filters['search'] . '%';
            }
        }
        
        if (!empty($whereClause)) {
            $query .= " AND " . implode(' AND ', $whereClause);
        }
        
        $query .= " ORDER BY r.name ASC";
        
        if ($limit) {
            $query .= " LIMIT " . $limit;
            
            if ($offset) {
                $query .= " OFFSET " . $offset;
            }
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($values);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count available resources and resources allocated to user's projects
     * 
     * @param int $userId User ID
     * @param array $filters Filter conditions
     * @return int Number of resources
     */
    public function countAvailableAndAllocatedToUser($userId, $filters = []) {
        $query = "SELECT COUNT(DISTINCT r.id) as count FROM " . $this->table_name . " r
                  LEFT JOIN project_resources pr ON r.id = pr.resource_id
                  LEFT JOIN project_members pm ON pr.project_id = pm.project_id
                  WHERE (r.availability != 'unavailable' OR (pm.user_id = :user_id AND pr.status = 'allocated'))";
        
        $values = [':user_id' => $userId];
        $whereClause = [];
        
        // Apply additional filters
        if (!empty($filters)) {
            if (isset($filters['type']) && !empty($filters['type'])) {
                $whereClause[] = "r.type = :type";
                $values[':type'] = $filters['type'];
            }
            
            if (isset($filters['availability']) && $filters['availability'] !== '') {
                $whereClause[] = "r.availability = :availability";
                $values[':availability'] = $filters['availability'] == 1 ? 'available' : 'unavailable';
            }
            
            if (isset($filters['department_id']) && !empty($filters['department_id'])) {
                $whereClause[] = "r.department_id = :department_id";
                $values[':department_id'] = $filters['department_id'];
            }
            
            if (isset($filters['search']) && !empty($filters['search'])) {
                $whereClause[] = "(r.name LIKE :search OR r.description LIKE :search)";
                $values[':search'] = '%' . $filters['search'] . '%';
            }
        }
        
        if (!empty($whereClause)) {
            $query .= " AND " . implode(' AND ', $whereClause);
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($values);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }
}
?>