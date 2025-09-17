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
require_once 'classes/User.php';
require_once 'classes/Project.php';

// Initialize objects
$documentObj = new Document();
$userObj = new User();
$projectObj = new Project();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);

// Get document ID from URL if provided
$documentId = isset($_GET['document_id']) ? (int)$_GET['document_id'] : 0;
$document = null;

if ($documentId > 0) {
    $document = $documentObj->getById($documentId);
    if (!$document) {
        $_SESSION['error_message'] = "Document not found.";
        header("Location: documents.php");
        exit;
    }
}

// Get project ID from URL if provided
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$project = null;

if ($projectId > 0) {
    $project = $projectObj->getById($projectId);
    if (!$project) {
        $_SESSION['error_message'] = "Project not found.";
        header("Location: projects.php");
        exit;
    }
}

// Check if user has permission to submit to journal
$canSubmit = false;

// Admins can submit any document
if ($currentUser['role_id'] == 1) {
    $canSubmit = true;
} 
// Document uploader can submit their own document
elseif ($document && $document['uploaded_by'] == $currentUser['id']) {
    $canSubmit = true;
}
// Project managers can submit documents from their projects
elseif ($currentUser['role_id'] == 2) {
    if ($document && !empty($document['project_id'])) {
        $docProject = $projectObj->getById($document['project_id']);
        if ($docProject && $docProject['created_by'] == $currentUser['id']) {
            $canSubmit = true;
        }
    } elseif ($project && $project['created_by'] == $currentUser['id']) {
        $canSubmit = true;
    }
}
// Department heads can submit documents from their department
elseif ($currentUser['role_id'] == 4) {
    if ($document && !empty($document['project_id'])) {
        $docProject = $projectObj->getById($document['project_id']);
        if ($docProject && $docProject['department_id'] == $currentUser['department_id']) {
            $canSubmit = true;
        }
    } elseif ($project && $project['department_id'] == $currentUser['department_id']) {
        $canSubmit = true;
    }
}

if (!$canSubmit) {
    $_SESSION['error_message'] = "You don't have permission to submit documents to the journal.";
    header("Location: " . ($document ? "document_detail.php?id=" . $documentId : ($project ? "project_detail.php?id=" . $projectId : "documents.php")));
    exit;
}

// Get documents for dropdown if no document is selected
$documents = [];
if (!$document) {
    if ($project) {
        // Get documents from project
        $documents = $documentObj->getByProject($projectId);
    } else {
        // Get documents uploaded by user or from their projects
        if ($currentUser['role_id'] <= 2) {
            // Admins and Project Managers can see all documents
            $documents = $documentObj->getAll();
        } elseif ($currentUser['role_id'] == 4) {
            // Department Heads can see documents from their department
            $departmentProjects = $projectObj->getByDepartment($currentUser['department_id']);
            $projectIds = array_column($departmentProjects, 'id');
            
            if (!empty($projectIds)) {
                $documents = $documentObj->getByProjectIds($projectIds);
            } else {
                $documents = [];
            }
        } else {
            // Other users can see documents they uploaded
            $documents = $documentObj->getByUploader($currentUser['id']);
        }
    }
    
    // Filter out documents that are already submitted to journal
    $documents = array_filter($documents, function($doc) {
        return !$doc['submit_to_journal'];
    });
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $submissionData = [
        'document_id' => isset($_POST['document_id']) ? (int)$_POST['document_id'] : $documentId,
        'journal_name' => $_POST['journal_name'] ?? 'IRJSTEM',
        'authors' => $_POST['authors'] ?? '',
        'corresponding_author' => $_POST['corresponding_author'] ?? '',
        'email' => $_POST['email'] ?? '',
        'abstract' => $_POST['abstract'] ?? '',
        'keywords' => $_POST['keywords'] ?? '',
        'comments' => $_POST['comments'] ?? ''
    ];
    
    // Validate form data
    $errors = [];
    
    if (empty($submissionData['document_id'])) {
        $errors[] = "Please select a document to submit.";
    } else {
        $submissionDocument = $documentObj->getById($submissionData['document_id']);
        if (!$submissionDocument) {
            $errors[] = "Selected document not found.";
        } elseif ($submissionDocument['submit_to_journal']) {
            $errors[] = "This document has already been submitted to a journal.";
        }
    }
    
    if (empty($submissionData['authors'])) {
        $errors[] = "Authors are required.";
    }
    
    if (empty($submissionData['corresponding_author'])) {
        $errors[] = "Corresponding author is required.";
    }
    
    if (empty($submissionData['email'])) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($submissionData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($submissionData['abstract'])) {
        $errors[] = "Abstract is required.";
    }
    
    if (empty($submissionData['keywords'])) {
        $errors[] = "Keywords are required.";
    }
    
    // If no errors, submit to journal
    if (empty($errors)) {
        // In a real implementation, this would send an email to the journal
        // For this demo, we'll just mark the document as submitted
        
        $submissionDocument = $documentObj->getById($submissionData['document_id']);
        $submissionDocument['submit_to_journal'] = 1;
        
        $result = $documentObj->update($submissionDocument['id'], $submissionDocument);
        
        if ($result) {
            // Log the submission details
            $submissionLog = [
                'document_id' => $submissionDocument['id'],
                'journal_name' => $submissionData['journal_name'],
                'authors' => $submissionData['authors'],
                'corresponding_author' => $submissionData['corresponding_author'],
                'email' => $submissionData['email'],
                'abstract' => $submissionData['abstract'],
                'keywords' => $submissionData['keywords'],
                'comments' => $submissionData['comments'],
                'submitted_by' => $currentUser['id'],
                'submission_date' => date('Y-m-d H:i:s')
            ];
            
            // In a real implementation, this would be saved to a database table
            // For this demo, we'll just store it in the session
            $_SESSION['journal_submission'] = $submissionLog;
            
            $_SESSION['success_message'] = "Document successfully submitted to " . $submissionData['journal_name'] . " journal.";
            
            // Redirect to document detail page
            header("Location: document_detail.php?id=" . $submissionDocument['id']);
            exit;
        } else {
            $errors[] = "Failed to submit document to journal. " . $documentObj->getError();
        }
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
                <?php if ($document): ?>
                    <li class="breadcrumb-item"><a href="documents.php">Documents</a></li>
                    <li class="breadcrumb-item"><a href="document_detail.php?id=<?php echo $document['id']; ?>"><?php echo htmlspecialchars($document['title']); ?></a></li>
                <?php elseif ($project): ?>
                    <li class="breadcrumb-item"><a href="projects.php">Projects</a></li>
                    <li class="breadcrumb-item"><a href="project_detail.php?id=<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['title']); ?></a></li>
                <?php else: ?>
                    <li class="breadcrumb-item"><a href="documents.php">Documents</a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active" aria-current="page">Journal Submission</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="h3 mb-0 text-gray-800">Submit to IRJSTEM Journal</h1>
        <p class="mb-0">Submit your research document directly to the IRJSTEM journal</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Submission Form</h6>
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
                
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($document ? '?document_id=' . $documentId : ($project ? '?project_id=' . $projectId : ''))); ?>" method="post" id="journalForm">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="document_id" class="form-label">Document <span class="text-danger">*</span></label>
                            <?php if ($document): ?>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($document['title']); ?>" readonly>
                                <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                <div class="form-text">
                                    Type: <?php echo ucfirst(str_replace('_', ' ', $document['type'])); ?>
                                    <?php if (!empty($document['project_id'])): ?>
                                        <?php $docProject = $projectObj->getById($document['project_id']); ?>
                                        <?php if ($docProject): ?>
                                            | Project: <?php echo htmlspecialchars($docProject['title']); ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <select class="form-select" id="document_id" name="document_id" required>
                                    <option value="">-- Select Document --</option>
                                    <?php foreach ($documents as $doc): ?>
                                        <option value="<?php echo $doc['id']; ?>">
                                            <?php echo htmlspecialchars($doc['title']); ?> (<?php echo ucfirst(str_replace('_', ' ', $doc['type'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($documents)): ?>
                                    <div class="form-text text-danger">
                                        No eligible documents found. Please upload a document first.
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="journal_name" class="form-label">Journal Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="journal_name" name="journal_name" value="IRJSTEM" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Corresponding Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($currentUser['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="authors" class="form-label">Authors <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="authors" name="authors" value="<?php echo isset($_POST['authors']) ? htmlspecialchars($_POST['authors']) : htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>" placeholder="Comma-separated list of authors" required>
                            <div class="form-text">
                                Enter all authors' names separated by commas (e.g., John Doe, Jane Smith, Robert Johnson)
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="corresponding_author" class="form-label">Corresponding Author <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="corresponding_author" name="corresponding_author" value="<?php echo isset($_POST['corresponding_author']) ? htmlspecialchars($_POST['corresponding_author']) : htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="abstract" class="form-label">Abstract <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="abstract" name="abstract" rows="5" required><?php echo isset($_POST['abstract']) ? htmlspecialchars($_POST['abstract']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="keywords" class="form-label">Keywords <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="keywords" name="keywords" value="<?php echo isset($_POST['keywords']) ? htmlspecialchars($_POST['keywords']) : ''; ?>" placeholder="Comma-separated keywords" required>
                            <div class="form-text">
                                Enter keywords separated by commas (e.g., research, development, innovation)
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="comments" class="form-label">Additional Comments</label>
                            <textarea class="form-control" id="comments" name="comments" rows="3"><?php echo isset($_POST['comments']) ? htmlspecialchars($_POST['comments']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit to Journal
                            </button>
                            <a href="<?php echo $document ? 'document_detail.php?id=' . $documentId : ($project ? 'project_detail.php?id=' . $projectId : 'documents.php'); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">About IRJSTEM Journal</h6>
            </div>
            <div class="card-body">
                <p>The International Research Journal of Science, Technology, Engineering, and Mathematics (IRJSTEM) is a peer-reviewed journal that publishes original research articles, review articles, and case studies in the fields of science, technology, engineering, and mathematics.</p>
                
                <h6 class="font-weight-bold mt-4">Submission Guidelines</h6>
                <ul>
                    <li>Research papers should be original and not previously published</li>
                    <li>Manuscripts should be in English and follow academic writing standards</li>
                    <li>References should follow the APA citation style</li>
                    <li>Figures and tables should be clearly labeled and referenced in the text</li>
                    <li>Abstracts should be 150-250 words</li>
                </ul>
                
                <h6 class="font-weight-bold mt-4">Review Process</h6>
                <p>All submissions undergo a double-blind peer review process. The typical review process takes 4-6 weeks. Authors will be notified of the decision via email.</p>
                
                <h6 class="font-weight-bold mt-4">Publication Frequency</h6>
                <p>IRJSTEM publishes quarterly issues (March, June, September, December).</p>
                
                <div class="text-center mt-4">
                    <img src="assets/img/irjstem-logo.png" alt="IRJSTEM Logo" class="img-fluid" style="max-width: 200px;">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation
        const form = document.getElementById('journalForm');
        if (form) {
            form.addEventListener('submit', function(event) {
                let isValid = true;
                
                // Validate document selection
                const documentSelect = document.getElementById('document_id');
                if (documentSelect && !documentSelect.value) {
                    isValid = false;
                    documentSelect.classList.add('is-invalid');
                } else if (documentSelect) {
                    documentSelect.classList.remove('is-invalid');
                }
                
                // Validate email
                const emailInput = document.getElementById('email');
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(emailInput.value)) {
                    isValid = false;
                    emailInput.classList.add('is-invalid');
                } else {
                    emailInput.classList.remove('is-invalid');
                }
                
                // Validate required fields
                const requiredFields = ['authors', 'corresponding_author', 'abstract', 'keywords'];
                requiredFields.forEach(function(fieldId) {
                    const field = document.getElementById(fieldId);
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    event.preventDefault();
                }
            });
        }
    });
</script>

<?php
// Include footer
include 'includes/footer.php';
?>