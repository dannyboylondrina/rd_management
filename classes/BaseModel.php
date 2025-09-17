<?php
/**
 * BaseModel Class
 * 
 * This class serves as the foundation for all model classes in the system.
 * It provides common functionality for database operations.
 */

require_once __DIR__ . '/../config/database.php';

abstract class BaseModel {
    // Database connection
    protected $conn;
    
    // Table name - to be defined by child classes
    protected $table_name;
    
    // Primary key column name
    protected $id_column = 'id';
    
    // Error messages
    protected $errors = [];

    /**
     * Constructor
     * 
     * Initializes database connection
     */
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * Get all records from the table
     * 
     * @param string $orderBy Column to order by
     * @param string $orderDirection Order direction (ASC or DESC)
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Array of records
     */
    public function getAll($orderBy = null, $orderDirection = 'ASC', $limit = null, $offset = null) {
        $query = "SELECT * FROM " . $this->table_name;
        
        if ($orderBy) {
            $query .= " ORDER BY " . $orderBy . " " . $orderDirection;
        }
        
        if ($limit) {
            $query .= " LIMIT " . $limit;
            
            if ($offset) {
                $query .= " OFFSET " . $offset;
            }
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get a single record by ID
     * 
     * @param int $id Record ID
     * @return array|false Record data or false if not found
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE " . $this->id_column . " = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a new record
     * 
     * @param array $data Record data
     * @return int|false ID of the new record or false on failure
     */
    public function create($data) {
        if (!$this->validate($data)) {
            return false;
        }
        
        $columns = [];
        $placeholders = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            if ($column != $this->id_column) {
                $columns[] = $column;
                $placeholders[] = ':' . $column;
                $values[':' . $column] = $value;
            }
        }
        
        $query = "INSERT INTO " . $this->table_name . " (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute($values)) {
            return $this->conn->lastInsertId();
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Update an existing record
     * 
     * @param int $id Record ID
     * @param array $data Record data
     * @return bool Success or failure
     */
    public function update($id, $data) {
        if (!$this->validate($data, $id)) {
            return false;
        }
        
        $setClause = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            if ($column != $this->id_column) {
                $setClause[] = $column . ' = :' . $column;
                $values[':' . $column] = $value;
            }
        }
        
        $values[':id'] = $id;
        
        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $setClause) . " WHERE " . $this->id_column . " = :id";
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute($values)) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Delete a record
     * 
     * @param int $id Record ID
     * @return bool Success or failure
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE " . $this->id_column . " = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Search records
     * 
     * @param array $conditions Search conditions
     * @param string $orderBy Column to order by
     * @param string $orderDirection Order direction (ASC or DESC)
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Array of records
     */
    public function search($conditions, $orderBy = null, $orderDirection = 'ASC', $limit = null, $offset = null) {
        $query = "SELECT * FROM " . $this->table_name;
        $whereClause = [];
        $values = [];
        
        // Only add WHERE clause if there are conditions
        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if (is_array($value) && count($value) == 2 && isset($value['operator'])) {
                    $whereClause[] = $column . ' ' . $value['operator'] . ' :' . $column;
                    $values[':' . $column] = $value['value'];
                } else {
                    $whereClause[] = $column . ' = :' . $column;
                    $values[':' . $column] = $value;
                }
            }
            
            if (!empty($whereClause)) {
                $query .= " WHERE " . implode(' AND ', $whereClause);
            }
        }
        
        if ($orderBy) {
            $query .= " ORDER BY " . $orderBy . " " . $orderDirection;
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
     * Count records
     * 
     * @param array $conditions Optional conditions
     * @return int Number of records
     */
    public function count($conditions = []) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name;
        
        if (!empty($conditions)) {
            $query .= " WHERE ";
            $whereClause = [];
            $values = [];
            
            foreach ($conditions as $column => $value) {
                if (is_array($value) && count($value) == 2 && isset($value['operator'])) {
                    $whereClause[] = $column . ' ' . $value['operator'] . ' :' . $column;
                    $values[':' . $column] = $value['value'];
                } else {
                    $whereClause[] = $column . ' = :' . $column;
                    $values[':' . $column] = $value;
                }
            }
            
            $query .= implode(' AND ', $whereClause);
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($values);
        } else {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        }
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }
    
    /**
     * Get error messages
     * 
     * @return array Error messages
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Validate data before saving
     * 
     * @param array $data Data to validate
     * @param int|null $id Record ID for update operations
     * @return bool Validation result
     */
    abstract protected function validate($data, $id = null);
}
?>