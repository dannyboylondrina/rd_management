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
require_once 'classes/Patent.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';
require_once 'classes/Document.php';

// Initialize objects
$patent = new Patent();
$project = new Project();
$user = new User();
$document = new Document();

// Get user role
$currentUser = $user->getById($_SESSION['user_id']);
$userRole = $currentUser['role_id'];
$userDepartment = $currentUser['department_id'];

// Set default values
$patentData = [
    'title' => '',
    'description' => '',
    'patent_number' => '',
    'filing_date' => '',
    'approval_date' => '',
    'status' => 'draft',
    'project_id' => '',
    'document_id' => '',
    'created_by' => $_SESSION['user_id']
];

$isEdit = false;
$pageTitle = "Register New Patent";
$submitButtonText = "Register Patent";
$formAction = "patent_form.php";

// Check if editing existing patent
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $patentId = $_GET['id'];
    $existingPatent = $patent->getById($patentId);
    
    if ($existingPatent) {
        $isEdit = true;
        $patentData = $existingPatent;
        $pageTitle = "Edit Patent";
        $submitButtonText = "Update Patent";
        $formAction = "patent_form.php?id=" . $patentId;
        
        // Check if user has permission to edit
        $canEdit = false;
        
        // Admin can edit any patent
        if ($userRole == 1) {
            $canEdit = true;
        }
        // Patent creator can edit their own patents
        else if ($patentData['created_by'] == $_SESSION['user_id']) {
            $canEdit = true;
        }
        // Project managers can edit patents in their projects
        else if ($userRole == 2 && $patentData['project_id']) {
            $projectData = $project->getById($patentData['project_id']);
            if ($projectData && $projectData['created_by'] == $_SESSION['user_id']) {
                $canEdit = true;
            }
        }
        
        if (!$canEdit) {
            $_SESSION['error_message'] = "You don't have permission to edit this patent.";
            header("Location: patents.php");
            exit;
        }
    } else {
        $_SESSION['error_message'] = "Patent not found.";
        header("Location: patents.php");
        exit;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $patentData = [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'patent_number' => !empty($_POST['patent_number']) ? $_POST['patent_number'] : null,
        'filing_date' => !empty($_POST['filing_date']) ? $_POST['filing_date'] : null,
        'approval_date' => !empty($_POST['approval_date']) ? $_POST['approval_date'] : null,
        'status' => $_POST['status'] ?? 'draft',
        'project_id' => !empty($_POST['project_id']) ? $_POST['project_id'] : null,
        'document_id' => !empty($_POST['document_id']) ? $_POST['document_id'] : null,
        'created_by' => $_SESSION['user_id']
    ];
    
    // Validate form data
    $errors = [];
    
    if (empty($patentData['title'])) {
        $errors[] = "Title is required";
    }
    
    if (empty($patentData['status'])) {
        $errors[] = "Status is required";
    }
    
    // Additional validation based on status
    if ($patentData['status'] == 'filed' && empty($patentData['filing_date'])) {
        $errors[] = "Filing date is required for filed patents";
    }
    
    if ($patentData['status'] == 'approved' && (empty($patentData['filing_date']) || empty($patentData['approval_date']))) {
        $errors[] = "Filing date and approval date are required for approved patents";
    }
    
    // If no errors, save patent
    if (empty($errors)) {
        $success = false;
        
        if ($isEdit) {
            $success = $patent->update($patentId, $patentData);
        } else {
            $patentId = $patent->create($patentData);
            $success = $patentId !== false;
        }
        
        if ($success) {
            $_SESSION['success_message'] = $isEdit ? "Patent updated successfully." : "Patent registered successfully.";
            header("Location: patents.php");
            exit;
        } else {
            $errors = $patent->getErrors();
        }
    }
}

// Get projects for dropdown
$projects = [];

// Admin sees all projects
if ($userRole == 1) {
    $projects = $project->getAll('title', 'ASC');
}
// Department head sees department projects
else if ($userRole == 4) {
    $projects = $project->getByDepartment($userDepartment);
}
// Project manager sees their projects
else if ($userRole == 2) {
    $projects = $project->getByCreator($_SESSION['user_id']);
}
// Researchers and faculty see projects they're members of
else {
    $projects = $project->getByMember($_SESSION['user_id']);
}

// Get documents for dropdown
$documents = [];

// Admin sees all documents
if ($userRole == 1) {
    $documents = $document->getAll('upload_date', 'DESC');
}
// Department head sees department documents
else if ($userRole == 4) {
    // Get department projects
    $departmentProjects = $project->getByDepartment($userDepartment);
    $projectIds = array_column($departmentProjects, 'id');
    
    if (!empty($projectIds)) {
        $conditions = [];
        foreach ($projectIds as $index => $projectId) {
            $conditions["project_id_$index"] = [
                'operator' => '=',
                'value' => $projectId
            ];
        }
        $documents = $document->search($conditions);
    }
    
    // Also get documents uploaded by the user
    $userDocuments = $document->getByUploader($_SESSION['user_id']);
    $documents = array_merge($documents, $userDocuments);
}
// Project manager sees documents in their projects
else if ($userRole == 2) {
    // Get manager's projects
    $managerProjects = $project->getByCreator($_SESSION['user_id']);
    $projectIds = array_column($managerProjects, 'id');
    
    if (!empty($projectIds)) {
        $conditions = [];
        foreach ($projectIds as $index => $projectId) {
            $conditions["project_id_$index"] = [
                'operator' => '=',
                'value' => $projectId
            ];
        }
        $documents = $document->search($conditions);
    }
    
    // Also get documents uploaded by the user
    $userDocuments = $document->getByUploader($_SESSION['user_id']);
    $documents = array_merge($documents, $userDocuments);
}
// Researchers and faculty see documents they've uploaded or in projects they're members of
else {
    // Get user's projects
    $userProjects = $project->getByMember($_SESSION['user_id']);
    $projectIds = array_column($userProjects, 'id');
    
    if (!empty($projectIds)) {
        $conditions = [];
        foreach ($projectIds as $index => $projectId) {
            $conditions["project_id_$index"] = [
                'operator' => '=',
                'value' => $projectId
            ];
        }
        $documents = $document->search($conditions);
    }
    
    // Also get documents uploaded by the user
    $userDocuments = $document->getByUploader($_SESSION['user_id']);
    $documents = array_merge($documents, $userDocuments);
}

// Include header
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $pageTitle; ?></h1>
        <a href="patents.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Patents
        </a>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Patent Form -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Patent Information</h6>
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
                    
                    <form method="POST" action="<?php echo $formAction; ?>">
                        <div class="form-group">
                            <label for="title">Patent Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($patentData['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($patentData['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="patent_number">Patent Number</label>
                            <input type="text" class="form-control" id="patent_number" name="patent_number" value="<?php echo htmlspecialchars($patentData['patent_number'] ?? ''); ?>">
                            <small class="form-text text-muted">
                                Leave blank if not yet assigned.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status <span class="text-danger">*</span></label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="draft" <?php echo ($patentData['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="filed" <?php echo ($patentData['status'] == 'filed') ? 'selected' : ''; ?>>Filed</option>
                                <option value="approved" <?php echo ($patentData['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo ($patentData['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filing_date">Filing Date</label>
                                    <input type="date" class="form-control" id="filing_date" name="filing_date" value="<?php echo $patentData['filing_date'] ?? ''; ?>">
                                    <small class="form-text text-muted">
                                        Required for filed or approved patents.
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="approval_date">Approval Date</label>
                                    <input type="date" class="form-control" id="approval_date" name="approval_date" value="<?php echo $patentData['approval_date'] ?? ''; ?>">
                                    <small class="form-text text-muted">
                                        Required for approved patents.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="project_id">Associated Project</label>
                            <select class="form-control" id="project_id" name="project_id">
                                <option value="">None</option>
                                <?php foreach ($projects as $proj): ?>
                                    <option value="<?php echo $proj['id']; ?>" <?php echo ($patentData['project_id'] == $proj['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($proj['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="document_id">Associated Document</label>
                            <select class="form-control" id="document_id" name="document_id">
                                <option value="">None</option>
                                <?php foreach ($documents as $doc): ?>
                                    <option value="<?php echo $doc['id']; ?>" <?php echo ($patentData['document_id'] == $doc['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($doc['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                Select a document that contains the patent application or related files.
                            </small>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $submitButtonText; ?>
                            </button>
                            <a href="patents.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Patent Guidelines -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Patent Guidelines</h6>
                </div>
                <div class="card-body">
                    <h5>Patent Status</h5>
                    <ul>
                        <li><strong>Draft</strong> - Patent is in preparation stage</li>
                        <li><strong>Filed</strong> - Patent application has been submitted</li>
                        <li><strong>Approved</strong> - Patent has been granted</li>
                        <li><strong>Rejected</strong> - Patent application was rejected</li>
                    </ul>
                    
                    <h5>Required Information</h5>
                    <ul>
                        <li>Title and status are always required</li>
                        <li>Filing date is required for filed or approved patents</li>
                        <li>Approval date is required for approved patents</li>
                        <li>Patent number is typically assigned after filing</li>
                    </ul>
                    
                    <h5>Associated Documents</h5>
                    <p>
                        You can associate a document with this patent, such as the patent application, drawings, or other related files.
                        If the document doesn't exist yet, you can upload it from the Documents section and then edit this patent to associate it.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide date fields based on status
document.getElementById('status').addEventListener('change', function() {
    const status = this.value;
    const filingDateField = document.getElementById('filing_date');
    const approvalDateField = document.getElementById('approval_date');
    
    if (status === 'draft') {
        filingDateField.removeAttribute('required');
        approvalDateField.removeAttribute('required');
    } else if (status === 'filed') {
        filingDateField.setAttribute('required', 'required');
        approvalDateField.removeAttribute('required');
    } else if (status === 'approved') {
        filingDateField.setAttribute('required', 'required');
        approvalDateField.setAttribute('required', 'required');
    } else if (status === 'rejected') {
        filingDateField.setAttribute('required', 'required');
        approvalDateField.removeAttribute('required');
    }
});

// Trigger the change event on page load to set initial state
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('status').dispatchEvent(new Event('change'));
});
</script>

<?php include 'includes/footer.php'; ?>