<?php
/**
 * Document Class
 * 
 * This class handles document-related operations
 */

require_once __DIR__ . '/BaseModel.php';

class Document extends BaseModel {
    // Table name
    protected $table_name = 'documents';
    
    // Upload directory
    protected $upload_dir = '../uploads/';
    
    // Allowed file types
    protected $allowed_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];
    
    // Maximum file size (5MB)
    protected $max_file_size = 5242880;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Ensure upload directory exists with proper permissions
        $this->upload_dir = __DIR__ . '/../uploads/';
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    /**
     * Validate document data
     * 
     * @param array $data Document data
     * @param int|null $id Document ID for update operations
     * @return bool Validation result
     */
    protected function validate($data, $id = null) {
        $this->errors = [];
        
        // Title validation
        if (isset($data['title'])) {
            if (empty($data['title'])) {
                $this->errors[] = "Document title is required";
            } elseif (strlen($data['title']) < 3) {
                $this->errors[] = "Document title must be at least 3 characters";
            }
        }
        
        // Type validation
        if (isset($data['type'])) {
            $validTypes = ['research_paper', 'faculty_evaluation', 'patent', 'report', 'other'];
            if (!in_array($data['type'], $validTypes)) {
                $this->errors[] = "Invalid document type";
            }
        }
        
        // File path validation for existing documents
        if (isset($data['file_path']) && empty($data['file_path'])) {
            $this->errors[] = "File path is required";
        }
        
        return empty($this->errors);
    }
    
    /**
     * Upload a document file
     * 
     * @param array $file File data from $_FILES
     * @param string $customFileName Custom file name (optional)
     * @return array|false File info or false on failure
     */
    public function uploadFile($file, $customFileName = null) {
        // Validate file
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $this->errors[] = "No file uploaded";
            return false;
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            $this->errors[] = "File size exceeds the maximum limit of 5MB";
            return false;
        }
        
        // Get file extension
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension']);
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!array_key_exists($extension, $this->allowed_types) || $this->allowed_types[$extension] !== $mimeType) {
            $this->errors[] = "Invalid file type. Allowed types: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, JPG, JPEG, PNG";
            return false;
        }
        
        // Generate unique file name
        $fileName = $customFileName ? $customFileName . '.' . $extension : uniqid() . '_' . $file['name'];
        $filePath = $this->upload_dir . $fileName;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return [
                'file_name' => $fileName,
                'file_path' => 'uploads/' . $fileName,
                'file_type' => $mimeType,
                'file_size' => $file['size']
            ];
        }
        
        $this->errors[] = "Failed to upload file";
        return false;
    }
    
    /**
     * Create a new document with file upload
     * 
     * @param array $data Document data
     * @param array $file File data from $_FILES
     * @return int|false ID of the new document or false on failure
     */
    public function createWithFile($data, $file) {
        // Upload file
        $fileInfo = $this->uploadFile($file);
        if (!$fileInfo) {
            return false;
        }
        
        // Add file info to document data
        $data['file_path'] = $fileInfo['file_path'];
        $data['file_type'] = $fileInfo['file_type'];
        $data['file_size'] = $fileInfo['file_size'];
        
        // Create document
        return $this->create($data);
    }
    
    /**
     * Update an existing document with file upload
     * 
     * @param int $id Document ID
     * @param array $data Document data
     * @param array|null $file File data from $_FILES (optional)
     * @return bool Success or failure
     */
    public function updateWithFile($id, $data, $file = null) {
        // Get existing document
        $document = $this->getById($id);
        if (!$document) {
            $this->errors[] = "Document not found";
            return false;
        }
        
        // Upload new file if provided
        if ($file && !empty($file['tmp_name'])) {
            $fileInfo = $this->uploadFile($file);
            if (!$fileInfo) {
                return false;
            }
            
            // Add file info to document data
            $data['file_path'] = $fileInfo['file_path'];
            $data['file_type'] = $fileInfo['file_type'];
            $data['file_size'] = $fileInfo['file_size'];
            
            // Delete old file
            $oldFilePath = __DIR__ . '/../' . $document['file_path'];
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }
        
        // Update document
        return $this->update($id, $data);
    }
    
    /**
     * Delete a document and its file
     * 
     * @param int $id Document ID
     * @return bool Success or failure
     */
    public function delete($id) {
        // Get document
        $document = $this->getById($id);
        if (!$document) {
            $this->errors[] = "Document not found";
            return false;
        }
        
        // Delete file
        $filePath = __DIR__ . '/../' . $document['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete document record
        return parent::delete($id);
    }
    
    /**
     * Get documents by project
     * 
     * @param int $projectId Project ID
     * @return array Array of documents
     */
    public function getByProject($projectId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE project_id = :project_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $projectId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get documents by type
     * 
     * @param string $type Document type
     * @return array Array of documents
     */
    public function getByType($type) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE type = :type";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':type', $type);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get documents by uploader
     * 
     * @param int $userId User ID
     * @return array Array of documents
     */
    public function getByUploader($userId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE uploaded_by = :uploaded_by";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uploaded_by', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Search documents by title or description
     * 
     * @param string $keyword Search keyword
     * @return array Array of documents
     */
    public function searchByKeyword($keyword) {
        $keyword = '%' . $keyword . '%';
        $query = "SELECT * FROM " . $this->table_name . " WHERE title LIKE :keyword OR description LIKE :keyword";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':keyword', $keyword);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Submit document to IRJSTEM journal
     * 
     * @param int $documentId Document ID
     * @param string $title Submission title
     * @param string $abstract Submission abstract
     * @param string $authors Submission authors
     * @param int $projectId Project ID
     * @param int $submittedBy User ID of submitter
     * @return int|false ID of the journal submission or false on failure
     */
    public function submitToJournal($documentId, $title, $abstract, $authors, $projectId, $submittedBy) {
        // Check if document exists
        $document = $this->getById($documentId);
        if (!$document) {
            $this->errors[] = "Document not found";
            return false;
        }
        
        // Insert journal submission
        $query = "INSERT INTO journal_submissions (title, abstract, authors, document_id, project_id, submitted_by, status) 
                  VALUES (:title, :abstract, :authors, :document_id, :project_id, :submitted_by, 'submitted')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':abstract', $abstract);
        $stmt->bindParam(':authors', $authors);
        $stmt->bindParam(':document_id', $documentId);
        $stmt->bindParam(':project_id', $projectId);
        $stmt->bindParam(':submitted_by', $submittedBy);
        
        if ($stmt->execute()) {
            $submissionId = $this->conn->lastInsertId();
            
            // TODO: Implement email sending to IRJSTEM
            // This would typically involve using PHPMailer or similar library
            // For now, just update the submission record
            $query = "UPDATE journal_submissions SET irjstem_email_sent = 1, email_sent_date = NOW() WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $submissionId);
            $stmt->execute();
            
            return $submissionId;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
    
    /**
     * Get journal submissions
     * 
     * @param int|null $documentId Document ID (optional)
     * @param int|null $projectId Project ID (optional)
     * @param int|null $submittedBy User ID (optional)
     * @return array Array of journal submissions
     */
    public function getJournalSubmissions($documentId = null, $projectId = null, $submittedBy = null) {
        $query = "SELECT js.*, d.file_path, d.file_type 
                  FROM journal_submissions js 
                  LEFT JOIN documents d ON js.document_id = d.id 
                  WHERE 1=1";
        $params = [];
        
        if ($documentId) {
            $query .= " AND js.document_id = :document_id";
            $params[':document_id'] = $documentId;
        }
        
        if ($projectId) {
            $query .= " AND js.project_id = :project_id";
            $params[':project_id'] = $projectId;
        }
        
        if ($submittedBy) {
            $query .= " AND js.submitted_by = :submitted_by";
            $params[':submitted_by'] = $submittedBy;
        }
        
        $query .= " ORDER BY js.submission_date DESC";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindParam($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>