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

// Initialize objects
$documentObj = new Document();
$projectObj = new Project();
$userObj = new User();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);

// Get document ID from URL
$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if document exists
$document = $documentObj->getById($documentId);
if (!$document) {
    $_SESSION['error_message'] = "Document not found.";
    header("Location: documents.php");
    exit;
}

// Privacy boundary: only owner or Admin can view
$canView = ($currentUser['role_id'] == 1) || ($document['uploaded_by'] == $currentUser['id']);

if (!$canView) {
    $_SESSION['error_message'] = "You don't have permission to view this document.";
    header("Location: documents.php");
    exit;
}

// Check if user can edit this document
$canEdit = false;

// Admins can edit any document
if ($currentUser['role_id'] == 1) {
    $canEdit = true;
} 
// Document uploader can edit their own document
elseif ($document['uploaded_by'] == $currentUser['id']) {
    $canEdit = true;
}
// Project managers can edit documents in their projects
elseif ($currentUser['role_id'] == 2 && !empty($document['project_id'])) {
    $project = $projectObj->getById($document['project_id']);
    if ($project && $project['created_by'] == $currentUser['id']) {
        $canEdit = true;
    }
}

// Get document uploader
$uploader = null;
if (!empty($document['uploaded_by'])) {
    $uploader = $userObj->getById($document['uploaded_by']);
}

// Get associated project
$project = null;
if (!empty($document['project_id'])) {
    $project = $projectObj->getById($document['project_id']);
}

// Process delete request
if (isset($_POST['delete']) && $_POST['delete'] == 1) {
    // Check if user has permission to delete
    if ($canEdit) {
        $result = $documentObj->delete($documentId);
        if ($result) {
            $_SESSION['success_message'] = "Document deleted successfully.";
            header("Location: documents.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to delete document. " . $documentObj->getError();
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete this document.";
    }
}

// Include header
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="documents.php">Documents</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($document['title']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($document['title']); ?></h1>
        <p class="mb-0">
            <span class="document-type-<?php echo $document['type']; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $document['type'])); ?>
            </span>
            
            <?php if ($uploader): ?>
                <span class="ms-2">
                    <i class="fas fa-user"></i> Uploaded by <?php echo htmlspecialchars($uploader['first_name'] . ' ' . $uploader['last_name']); ?>
                </span>
            <?php endif; ?>
            
            <span class="ms-2">
                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($document['upload_date'])); ?>
            </span>
        </p>
    </div>
    <div class="col-md-4 text-md-end">
        <a href="<?php echo htmlspecialchars($document['file_path']); ?>" class="btn btn-success" download>
            <i class="fas fa-download"></i> Download
        </a>
        
        <?php if ($canEdit): ?>
            <a href="document_form.php?id=<?php echo $documentId; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
            
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="fas fa-trash"></i> Delete
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Document Details -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Document Details</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($document['description'])): ?>
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h5>Description</h5>
                            <p><?php echo nl2br(htmlspecialchars($document['description'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-3 font-weight-bold">Document Type:</div>
                    <div class="col-md-9"><?php echo ucfirst(str_replace('_', ' ', $document['type'])); ?></div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3 font-weight-bold">File Name:</div>
                    <div class="col-md-9"><?php echo basename($document['file_path']); ?></div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3 font-weight-bold">File Size:</div>
                    <div class="col-md-9">
                        <?php 
                        $filePath = 'uploads/' . $document['file_path'];
                        if (file_exists($filePath)) {
                            $fileSize = filesize($filePath);
                            if ($fileSize < 1024) {
                                echo $fileSize . ' bytes';
                            } elseif ($fileSize < 1024 * 1024) {
                                echo round($fileSize / 1024, 2) . ' KB';
                            } else {
                                echo round($fileSize / (1024 * 1024), 2) . ' MB';
                            }
                        } else {
                            echo 'File not found';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3 font-weight-bold">Upload Date:</div>
                    <div class="col-md-9"><?php echo date('F d, Y', strtotime($document['upload_date'])); ?></div>
                </div>
                
                <?php if ($uploader): ?>
                    <div class="row mb-3">
                        <div class="col-md-3 font-weight-bold">Uploaded By:</div>
                        <div class="col-md-9">
                            <?php echo htmlspecialchars($uploader['first_name'] . ' ' . $uploader['last_name']); ?>
                            <small class="text-muted">(<?php echo htmlspecialchars($uploader['email']); ?>)</small>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($project): ?>
                    <div class="row mb-3">
                        <div class="col-md-3 font-weight-bold">Associated Project:</div>
                        <div class="col-md-9">
                            <a href="project_detail.php?id=<?php echo $project['id']; ?>">
                                <?php echo htmlspecialchars($project['title']); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-3 font-weight-bold">Journal Submission:</div>
                    <div class="col-md-9">
                        <?php if ($document['submit_to_journal']): ?>
                            <span class="badge bg-info">Submitted to IRJSTEM Journal</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not submitted to journal</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Document Preview -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Document Preview</h6>
            </div>
            <div class="card-body">
                <?php
                $filePath = $document['file_path'];
                $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
                
                // Check file type for preview
                if (in_array(strtolower($fileExtension), ['pdf'])) {
                    echo '<div class="embed-responsive" style="height: 600px;">';
                    echo '<iframe class="embed-responsive-item" src="' . $filePath . '" style="width: 100%; height: 100%;"></iframe>';
                    echo '</div>';
                } elseif (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif'])) {
                    echo '<div class="text-center">';
                    echo '<img src="' . $filePath . '" class="img-fluid" alt="Document Preview">';
                    echo '</div>';
                } elseif (in_array(strtolower($fileExtension), ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) {
                    echo '<div class="alert alert-info">';
                    echo '<i class="fas fa-info-circle"></i> This document type cannot be previewed directly. Please download the file to view it.';
                    echo '</div>';
                    echo '<div class="text-center mt-3">';
                    echo '<a href="' . $filePath . '" class="btn btn-lg btn-success" download>';
                    echo '<i class="fas fa-download"></i> Download ' . ucfirst($fileExtension) . ' File';
                    echo '</a>';
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-info">';
                    echo '<i class="fas fa-info-circle"></i> Preview not available for this file type. Please download the file to view it.';
                    echo '</div>';
                    echo '<div class="text-center mt-3">';
                    echo '<a href="' . $filePath . '" class="btn btn-lg btn-success" download>';
                    echo '<i class="fas fa-download"></i> Download File';
                    echo '</a>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Actions -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?php echo htmlspecialchars($document['file_path']); ?>" class="btn btn-success" download>
                        <i class="fas fa-download"></i> Download Document
                    </a>
                    
                    <?php if (!$document['submit_to_journal']): ?>
                        <a href="journal_submission.php?document_id=<?php echo $documentId; ?>" class="btn btn-info">
                            <i class="fas fa-paper-plane"></i> Submit to IRJSTEM Journal
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($project): ?>
                        <a href="project_detail.php?id=<?php echo $project['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-project-diagram"></i> View Associated Project
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($canEdit): ?>
                        <a href="document_form.php?id=<?php echo $documentId; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit Document
                        </a>
                        
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="fas fa-trash"></i> Delete Document
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Related Documents -->
        <?php if ($project): ?>
            <?php
            $relatedDocuments = $documentObj->getByProject($project['id'], 5, 0, $documentId);
            if (!empty($relatedDocuments)):
            ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Related Documents</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($relatedDocuments as $relatedDoc): ?>
                                <a href="document_detail.php?id=<?php echo $relatedDoc['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($relatedDoc['title']); ?></h6>
                                        <small class="text-muted document-type-<?php echo $relatedDoc['type']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $relatedDoc['type'])); ?>
                                        </small>
                                    </div>
                                    <small class="text-muted">
                                        Uploaded: <?php echo date('M d, Y', strtotime($relatedDoc['upload_date'])); ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<?php if ($canEdit): ?>
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this document? This action cannot be undone.</p>
                    <p><strong>Document:</strong> <?php echo htmlspecialchars($document['title']); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $documentId); ?>" method="post">
                        <input type="hidden" name="delete" value="1">
                        <button type="submit" class="btn btn-danger">Delete Document</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Include footer
include 'includes/footer.php';
?>