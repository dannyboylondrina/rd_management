<?php
/**
 * Role Class
 * Handles role-related operations
 */
class Role extends BaseModel {
    protected $table_name = 'roles';
    
    /**
     * Validate role data
     * 
     * @param array $data Role data to validate
     * @param int|null $id Role ID for update operations
     * @return bool True if valid, false otherwise
     */
    public function validate($data, $id = null) {
        $this->errors = [];
        
        // Validate name
        if (empty($data['name'])) {
            $this->errors[] = "Role name is required.";
        } elseif (strlen($data['name']) > 50) {
            $this->errors[] = "Role name cannot exceed 50 characters.";
        }
        
        // Check if name is unique
        if (!empty($data['name'])) {
            $existingId = $this->isNameUnique($data['name'], $id ?? $data['id'] ?? null);
            if ($existingId !== false) {
                $this->errors[] = "Role name already exists.";
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Check if role name is unique
     * 
     * @param string $name Role name to check
     * @param int|null $id Role ID to exclude from check (for updates)
     * @return mixed False if unique, role ID if not unique
     */
    public function isNameUnique($name, $id = null) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE name = :name";
        $params = [':name' => $name];
        
        if ($id) {
            $query .= " AND id != :id";
            $params[':id'] = $id;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : false;
    }
    
    /**
     * Get roles by permission
     * 
     * @param string $permission Permission to search for
     * @return array Roles with the specified permission
     */
    public function getByPermission($permission) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE permissions LIKE :permission";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':permission' => '%' . $permission . '%']);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get role name by ID
     * 
     * @param int $id Role ID
     * @return string|null Role name or null if not found
     */
    public function getNameById($id) {
        $role = $this->getById($id);
        return $role ? $role['name'] : null;
    }
    
    /**
     * Get all roles with user counts
     * 
     * @return array Roles with user counts
     */
    public function getAllWithUserCounts() {
        $query = "SELECT r.*, COUNT(u.id) as user_count 
                 FROM " . $this->table_name . " r 
                 LEFT JOIN users u ON r.id = u.role_id 
                 GROUP BY r.id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>