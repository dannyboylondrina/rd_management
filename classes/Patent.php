<?php
/**
 * Patent Class
 * 
 * This class handles patent-related operations
 */

require_once __DIR__ . '/BaseModel.php';

class Patent extends BaseModel {
    // Table name
    protected $table_name = 'patents';
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Validate patent data
     * 
     * @param array $data Patent data
     * @param int|null $id Patent ID for update operations
     * @return bool Validation result
     */
    protected function validate($data, $id = null) {
        $this->errors = [];
        
        // Title validation
        if (isset($data['title'])) {
            if (empty($data['title'])) {
                $this->errors[] = "Patent title is required";
            } elseif (strlen($data['title']) < 3) {
                $this->errors[] = "Patent title must be at least 3 characters";
            }
        }
        
        // Patent number validation (if provided)
        if (isset($data['patent_number']) && !empty($data['patent_number'])) {
            // Check if patent number is unique
            $query = "SELECT id FROM " . $this->table_name . " WHERE patent_number = :patent_number";
            if ($id) {
                $query .= " AND id != :id";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':patent_number', $data['patent_number']);
            
            if ($id) {
                $stmt->bindParam(':id', $id);
            }
            
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $this->errors[] = "Patent number already exists";
            }
        }
        
        // Date validation
        if (isset($data['filing_date']) && !empty($data['filing_date'])) {
            if (!$this->isValidDate($data['filing_date'])) {
                $this->errors[] = "Invalid filing date format";
            }
        }
        
        if (isset($data['approval_date']) && !empty($data['approval_date'])) {
            if (!$this->isValidDate($data['approval_date'])) {
                $this->errors[] = "Invalid approval date format";
            } elseif (isset($data['filing_date']) && !empty($data['filing_date']) && $data['approval_date'] < $data['filing_date']) {
                $this->errors[] = "Approval date cannot be before filing date";
            }
        }
        
        // Status validation
        if (isset($data['status'])) {
            $validStatuses = ['draft', 'filed', 'approved', 'rejected'];
            if (!in_array($data['status'], $validStatuses)) {
                $this->errors[] = "Invalid patent status";
            }
        }
        
        // Project validation (if provided)
        if (isset($data['project_id']) && !empty($data['project_id'])) {
            $projectObj = new Project();
            $project = $projectObj->getById($data['project_id']);
            if (!$project) {
                $this->errors[] = "Project not found";
            }
        }
        
        // Document validation (if provided)
        if (isset($data['document_id']) && !empty($data['document_id'])) {
            $documentObj = new Document();
            $document = $documentObj->getById($data['document_id']);
            if (!$document) {
                $this->errors[] = "Document not found";
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
     * Create a new patent with document
     * 
     * @param array $data Patent data
     * @param array $file File data from $_FILES
     * @param int $projectId Project ID
     * @param int $createdBy User ID
     * @return int|false ID of the new patent or false on failure
     */
    public function createWithDocument($data, $file, $projectId, $createdBy) {
        // Upload document
        $documentObj = new Document();
        $documentData = [
            'title' => $data['title'] . ' - Patent Document',
            'description' => isset($data['description']) ? $data['description'] : null,
            'type' => 'patent',
            'project_id' => $projectId,
            'uploaded_by' => $createdBy
        ];
        
        $documentId = $documentObj->createWithFile($documentData, $file);
        if (!$documentId) {
            $this->errors = array_merge($this->errors, $documentObj->getErrors());
            return false;
        }
        
        // Add document ID to patent data
        $data['document_id'] = $documentId;
        $data['project_id'] = $projectId;
        $data['created_by'] = $createdBy;
        
        // Create patent
        return $this->create($data);
    }
    
    /**
     * Update patent status
     * 
     * @param int $id Patent ID
     * @param string $status New status
     * @return bool Success or failure
     */
    public function updateStatus($id, $status) {
        $validStatuses = ['draft', 'filed', 'approved', 'rejected'];
        if (!in_array($status, $validStatuses)) {
            $this->errors[] = "Invalid patent status";
            return false;
        }
        
        $query = "UPDATE " . $this->table_name . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Get patents by project
     * 
     * @param int $projectId Project ID
     * @return array Array of patents
     */
    public function getByProject($projectId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE project_id = :project_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $projectId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get patents by status
     * 
     * @param string $status Patent status
     * @return array Array of patents
     */
    public function getByStatus($status) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE status = :status";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get patents by creator
     * 
     * @param int $userId User ID
     * @return array Array of patents
     */
    public function getByCreator($userId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE created_by = :created_by";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':created_by', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get patents by date range
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param string $dateField Date field to filter by (filing_date or approval_date)
     * @return array Array of patents
     */
    public function getByDateRange($startDate, $endDate, $dateField = 'filing_date') {
        if ($dateField != 'filing_date' && $dateField != 'approval_date') {
            $dateField = 'filing_date';
        }
        
        $query = "SELECT * FROM " . $this->table_name . " WHERE " . $dateField . " BETWEEN :start_date AND :end_date";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Search patents by keyword
     * 
     * @param string $keyword Search keyword
     * @return array Array of patents
     */
    public function searchByKeyword($keyword) {
        $keyword = '%' . $keyword . '%';
        $query = "SELECT * FROM " . $this->table_name . " WHERE title LIKE :keyword OR description LIKE :keyword OR patent_number LIKE :keyword";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':keyword', $keyword);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get patent document
     * 
     * @param int $patentId Patent ID
     * @return array|false Document data or false if not found
     */
    public function getDocument($patentId) {
        $patent = $this->getById($patentId);
        if (!$patent || empty($patent['document_id'])) {
            return false;
        }
        
        $documentObj = new Document();
        return $documentObj->getById($patent['document_id']);
    }
    
    /**
     * Search patents with custom filters
     * 
     * @param array $filters Array of WHERE clause conditions
     * @param array $filterParams Array of parameter values
     * @param string $orderBy Column to order by
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Array of patents
     */
    public function searchWithFilters($filters = [], $filterParams = [], $orderBy = 'created_at', $orderDirection = 'DESC', $limit = null, $offset = null) {
        $query = "SELECT * FROM " . $this->table_name;
        
        if (!empty($filters)) {
            $query .= " WHERE " . implode(' AND ', $filters);
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
        $stmt->execute($filterParams);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count patents with custom filters
     * 
     * @param array $filters Array of WHERE clause conditions
     * @param array $filterParams Array of parameter values
     * @return int Number of patents
     */
    public function countWithFilters($filters = [], $filterParams = []) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name;
        
        if (!empty($filters)) {
            $query .= " WHERE " . implode(' AND ', $filters);
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($filterParams);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }
    
    /**
     * Get patent statistics
     * 
     * @return array Patent statistics
     */
    public function getStatistics() {
        $stats = [
            'total' => 0,
            'draft' => 0,
            'filed' => 0,
            'approved' => 0,
            'rejected' => 0,
            'by_year' => []
        ];
        
        // Get total count
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total'] = $result['count'];
        
        // Get counts by status
        $query = "SELECT status, COUNT(*) as count FROM " . $this->table_name . " GROUP BY status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $result) {
            $stats[$result['status']] = $result['count'];
        }
        
        // Get counts by year
        $query = "SELECT YEAR(filing_date) as year, COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE filing_date IS NOT NULL 
                  GROUP BY YEAR(filing_date) 
                  ORDER BY YEAR(filing_date) DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $result) {
            $stats['by_year'][$result['year']] = $result['count'];
        }
        
        return $stats;
    }
}
?>