<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: login.php");
    exit;
}

// Include necessary files
require_once 'classes/Document.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';
require_once 'classes/Notification.php';

// Initialize objects
$document = new Document();
$project = new Project();
$user = new User();

// Get user role
$currentUser = $user->getById($_SESSION['user_id']);
$userRole = $currentUser['role_id'];
$userDepartment = $currentUser['department_id'];

// Set default values
$documentData = [
    'title' => '',
    'description' => '',
    'type' => 'research_paper',
    'project_id' => '',
    'submit_to_journal' => false
];

$isEdit = false;
$pageTitle = "Upload New Document";
$submitButtonText = "Upload Document";
$formAction = "document_form.php";

// Check if editing existing document
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $documentId = $_GET['id'];
    $existingDocument = $document->getById($documentId);
    
    if ($existingDocument) {
        $isEdit = true;
        $documentData = $existingDocument;
        $pageTitle = "Edit Document";
        $submitButtonText = "Update Document";
        $formAction = "document_form.php?id=" . $documentId;
        
        // Check if user has permission to edit
        $canEdit = false;
        
        // Admin can edit any document
        if ($userRole == 1) {
            $canEdit = true;
        }
        // Document uploader can edit their own documents
        else if ($documentData['uploaded_by'] == $_SESSION['user_id']) {
            $canEdit = true;
        }
        // Managers cannot edit others' documents due to privacy boundary
        
        if (!$canEdit) {
            $_SESSION['error_message'] = "You don't have permission to edit this document.";
            header("Location: documents.php");
            exit;
        }
    } else {
        $_SESSION['error_message'] = "Document not found.";
        header("Location: documents.php");
        exit;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $documentData = [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'type' => $_POST['type'] ?? 'research_paper',
        'project_id' => !empty($_POST['project_id']) ? $_POST['project_id'] : null,
        'uploaded_by' => $_SESSION['user_id']
    ];
    
    // Check if submitting to journal
    $submitToJournal = isset($_POST['submit_to_journal']) && $_POST['submit_to_journal'] == '1';
    
    // Validate form data
    $errors = [];
    
    if (empty($documentData['title'])) {
        $errors[] = "Title is required";
    }
    
    if (empty($documentData['type'])) {
        $errors[] = "Document type is required";
    }
    
    // Handle file upload for new documents or if a new file is provided
    $fileUploaded = false;
    
    if (!$isEdit || (isset($_FILES['document_file']) && $_FILES['document_file']['size'] > 0)) {
        // Check if file was uploaded
        if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] != UPLOAD_ERR_OK) {
            $errors[] = "Document file is required";
        } else {
            // Get file info
            $fileName = $_FILES['document_file']['name'];
            $fileTmpName = $_FILES['document_file']['tmp_name'];
            $fileSize = $_FILES['document_file']['size'];
            $fileType = $_FILES['document_file']['type'];
            
            // Get file extension
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Check file size (limit to 20MB)
            if ($fileSize > 20000000) {
                $errors[] = "File size must be less than 20MB";
            }
            
            // Allow certain file formats
            $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($fileExt, $allowedExtensions)) {
                $errors[] = "Only PDF, Office documents, text files, and images are allowed";
            }
            
            if (empty($errors)) {
                // Create uploads directory if it doesn't exist
                $uploadDir = 'uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate unique filename
                $newFileName = uniqid() . '_' . $fileName;
                $uploadPath = $uploadDir . $newFileName;
                
                // Upload file
                if (move_uploaded_file($fileTmpName, $uploadPath)) {
                    $documentData['file_path'] = $uploadPath;
                    $documentData['file_type'] = $fileType;
                    $documentData['file_size'] = $fileSize;
                    $fileUploaded = true;
                } else {
                    $errors[] = "Failed to upload file";
                }
            }
        }
    }
    
    // If no errors, save document
    if (empty($errors)) {
        $success = false;
        
        if ($isEdit) {
            // Only update file info if a new file was uploaded
            if (!$fileUploaded) {
                unset($documentData['file_path']);
                unset($documentData['file_type']);
                unset($documentData['file_size']);
            }
            
            $success = $document->update($documentId, $documentData);
        } else {
            $documentId = $document->create($documentData);
            $success = $documentId !== false;
        }
        
        if ($success) {
            $_SESSION['success_message'] = $isEdit ? "Document updated successfully." : "Document uploaded successfully.";
            // Notifications: notify project members if linked to a project
            if (!empty($documentData['project_id'])) {
                $notification = new Notification();
                $title = $isEdit ? 'Document updated' : 'New document uploaded';
                $msg = ($isEdit ? 'A document was updated' : 'A new document was uploaded') . ' in a project you are part of: ' . ($documentData['title'] ?? 'Untitled');
                $notification->createNotificationForProject((int)$documentData['project_id'], $title, $msg, 'document', (int)$documentId, 'document');
            }
            
            // If submitting to journal, redirect to journal submission page
            if ($submitToJournal) {
                header("Location: journal_submission.php?document_id=" . $documentId);
                exit;
            } else {
                header("Location: documents.php");
                exit;
            }
        } else {
            $errors = $document->getErrors();
        }
    }
}

// Get projects for dropdown
$projects = [];

// Admin sees all projects
if ($userRole == 1) {
    $projects = $project->getAll("title");
}
// Others: only show no project association to prevent leakage
else {
    $projects = [];
}

// Include header
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $pageTitle; ?></h1>
        <a href="documents.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Documents
        </a>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Document Form -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Document Information</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?php echo $formAction; ?>" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="title">Document Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($documentData['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($documentData['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="type">Document Type <span class="text-danger">*</span></label>
                            <select class="form-control" id="type" name="type" required>
                                <option value="research_paper" <?php echo ($documentData['type'] == 'research_paper') ? 'selected' : ''; ?>>Research Paper</option>
                                <option value="faculty_evaluation" <?php echo ($documentData['type'] == 'faculty_evaluation') ? 'selected' : ''; ?>>Faculty Evaluation</option>
                                <option value="patent" <?php echo ($documentData['type'] == 'patent') ? 'selected' : ''; ?>>Patent</option>
                                <option value="report" <?php echo ($documentData['type'] == 'report') ? 'selected' : ''; ?>>Report</option>
                                <option value="other" <?php echo ($documentData['type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="project_id">Associated Project</label>
                            <select class="form-control" id="project_id" name="project_id">
                                <option value="">None</option>
                                <?php foreach ($projects as $proj): ?>
                                    <option value="<?php echo $proj['id']; ?>" <?php echo ($documentData['project_id'] == $proj['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($proj['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="document_file">
                                <?php echo $isEdit ? 'Document File (leave empty to keep current file)' : 'Document File <span class="text-danger">*</span>'; ?>
                            </label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="document_file" name="document_file" <?php echo $isEdit ? '' : 'required'; ?>>
                                <label class="custom-file-label" for="document_file">Choose file</label>
                            </div>
                            <small class="form-text text-muted">
                                Allowed file types: PDF, Office documents, text files, and images. Maximum size: 20MB.
                            </small>
                            
                            <?php if ($isEdit && !empty($documentData['file_path'])): ?>
                                <div class="mt-2">
                                    <p class="mb-1">Current file: 
                                        <a href="<?php echo $documentData['file_path']; ?>" target="_blank">
                                            <?php echo basename($documentData['file_path']); ?>
                                        </a>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="submit_to_journal" name="submit_to_journal" value="1">
                                <label class="custom-control-label" for="submit_to_journal">Submit to IRJSTEM Journal</label>
                            </div>
                            <small class="form-text text-muted">
                                Check this option to submit this document to the IRJSTEM Journal after uploading.
                            </small>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $submitButtonText; ?>
                            </button>
                            <a href="documents.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Document Guidelines -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Document Guidelines</h6>
                </div>
                <div class="card-body">
                    <h5>Document Types</h5>
                    <ul>
                        <li><strong>Research Paper</strong> - Academic research papers, articles, or manuscripts</li>
                        <li><strong>Faculty Evaluation</strong> - Faculty performance evaluations or assessments</li>
                        <li><strong>Patent</strong> - Patent applications, drafts, or related documentation</li>
                        <li><strong>Report</strong> - Project reports, progress reports, or technical reports</li>
                        <li><strong>Other</strong> - Any other document type not listed above</li>
                    </ul>
                    
                    <h5>File Requirements</h5>
                    <ul>
                        <li>Maximum file size: 20MB</li>
                        <li>Allowed file types: PDF, Office documents (Word, Excel, PowerPoint), text files, and images</li>
                    </ul>
                    
                    <h5>IRJSTEM Journal Submission</h5>
                    <p>
                        If you wish to submit your document to the IRJSTEM Journal, check the "Submit to IRJSTEM Journal" option.
                        You will be redirected to a form to provide additional information required for journal submission.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update file input label with selected filename
document.querySelector('.custom-file-input').addEventListener('change', function(e) {
    var fileName = e.target.files[0].name;
    var nextSibling = e.target.nextElementSibling;
    nextSibling.innerText = fileName;
});
</script>

<?php include 'includes/footer.php'; ?>