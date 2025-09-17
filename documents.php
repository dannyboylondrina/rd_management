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
require_once 'classes/Department.php';

// Initialize document object
$document = new Document();
$project = new Project();
$user = new User();

// Set page title
$pageTitle = "Documents";

// Get user role
$currentUser = $user->getById($_SESSION['user_id']);
$userRole = $currentUser['role_id'];
$userDepartment = $currentUser['department_id'];

// Handle document deletion
if (isset($_POST['delete_document']) && isset($_POST['document_id'])) {
    $documentId = $_POST['document_id'];
    
    // Get document details to check permissions
    $documentData = $document->getById($documentId);
    
    // Check if user has permission to delete
    $canDelete = false;
    
    if ($documentData) {
        // Admin can delete any document
        if ($userRole == 1) {
            $canDelete = true;
        }
        // Document uploader can delete their own documents
        else if ($documentData['uploaded_by'] == $_SESSION['user_id']) {
            $canDelete = true;
        }
        // Project managers can delete documents in their projects
        else if ($userRole == 2 && $documentData['project_id']) {
            $projectData = $project->getById($documentData['project_id']);
            if ($projectData && $projectData['created_by'] == $_SESSION['user_id']) {
                $canDelete = true;
            }
        }
        // Department heads can delete documents in their department's projects
        else if ($userRole == 4 && $documentData['project_id']) {
            $projectData = $project->getById($documentData['project_id']);
            if ($projectData && $projectData['department_id'] == $userDepartment) {
                $canDelete = true;
            }
        }
    }
    
    if ($canDelete) {
        if ($document->delete($documentId)) {
            $_SESSION['success_message'] = "Document deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete document: " . implode(", ", $document->getErrors());
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete this document.";
    }
    
    // Redirect to prevent form resubmission
    header("Location: documents.php");
    exit;
}

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Set up filtering using BaseModel::search conditions format
$conditions = [];

// Filter by document type
if (isset($_GET['type']) && !empty($_GET['type'])) {
    $conditions['type'] = $_GET['type'];
}

// Filter by project
if (isset($_GET['project_id']) && !empty($_GET['project_id'])) {
    $conditions['project_id'] = $_GET['project_id'];
}

// Filter by search keyword
if (isset($_GET['search']) && !empty($_GET['search'])) {
    // Limit keyword search to title to stay within BaseModel capability
    $conditions['title'] = [
        'operator' => 'LIKE',
        'value' => '%' . $_GET['search'] . '%'
    ];
}

// Get documents based on user role
$documents = [];
$totalDocuments = 0;

// Fetch documents with role rules
if ($userRole == 1) {
    // Admin: all matching filters
    $documents = $document->search($conditions, 'upload_date', 'DESC', $limit, $offset);
    $totalDocuments = $document->count($conditions);
} elseif ($userRole == 2) {
    // Project Manager: own uploads + docs from projects they created
    $allDocs = [];
    // Own uploads
    $ownDocs = $document->search(['uploaded_by' => $_SESSION['user_id']], 'upload_date', 'DESC');
    if (is_array($ownDocs)) { $allDocs = array_merge($allDocs, $ownDocs); }
    // Manager projects
    $managerProjects = $project->getByCreator($_SESSION['user_id']);
    $projectIds = array_column($managerProjects, 'id');
    foreach ($projectIds as $pid) {
        $projDocs = $document->getByProject($pid);
        if (is_array($projDocs)) { $allDocs = array_merge($allDocs, $projDocs); }
    }
    // Deduplicate by id
    $seen = [];
    $allDocs = array_values(array_filter($allDocs, function($d) use (&$seen) {
        if (isset($seen[$d['id']])) return false;
        $seen[$d['id']] = true;
        return true;
    }));
    // Apply filters (type, project_id, search)
    $allDocs = array_values(array_filter($allDocs, function($d) {
        if (isset($_GET['type']) && $_GET['type'] !== '' && $d['type'] !== $_GET['type']) return false;
        if (isset($_GET['project_id']) && $_GET['project_id'] !== '' && (string)$d['project_id'] !== (string)$_GET['project_id']) return false;
        if (isset($_GET['search']) && $_GET['search'] !== '') {
            $kw = strtolower($_GET['search']);
            $title = strtolower($d['title'] ?? '');
            $desc = strtolower($d['description'] ?? '');
            if (strpos($title, $kw) === false && strpos($desc, $kw) === false) return false;
        }
        return true;
    }));
    // Sort by upload_date DESC
    usort($allDocs, function($a, $b) {
        return strtotime($b['upload_date']) <=> strtotime($a['upload_date']);
    });
    // Pagination
    $totalDocuments = count($allDocs);
    $documents = array_slice($allDocs, $offset, $limit);
} else {
    // Regular users: only own uploads
    $conditions['uploaded_by'] = $_SESSION['user_id'];
    $documents = $document->search($conditions, 'upload_date', 'DESC', $limit, $offset);
    $totalDocuments = $document->count($conditions);
}

// Calculate total pages
$totalPages = ceil($totalDocuments / $limit);

// Get all projects for filter dropdown
$allProjects = [];
if ($userRole == 1) {
    // Admin sees all projects
    $allProjects = $project->getAll("title","ASC");
} else if ($userRole == 4) {
    // Department head sees department projects
    $allProjects = $project->getByDepartment($userDepartment);
} else if ($userRole == 2) {
    // Project manager sees their projects
    $allProjects = $project->getByCreator($_SESSION['user_id']);
} else {
    // Researchers and faculty see projects they're members of
    $allProjects = $project->getByMember($_SESSION['user_id']);
}

// Include header
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $pageTitle; ?></h1>
        <a href="document_form.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Upload New Document
        </a>
    </div>
    
    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Documents</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="documents.php" class="row">
                <div class="col-md-3 mb-3">
                    <label for="type">Document Type</label>
                    <select name="type" id="type" class="form-control">
                        <option value="">All Types</option>
                        <option value="research_paper" <?php echo (isset($_GET['type']) && $_GET['type'] == 'research_paper') ? 'selected' : ''; ?>>Research Paper</option>
                        <option value="faculty_evaluation" <?php echo (isset($_GET['type']) && $_GET['type'] == 'faculty_evaluation') ? 'selected' : ''; ?>>Faculty Evaluation</option>
                        <option value="patent" <?php echo (isset($_GET['type']) && $_GET['type'] == 'patent') ? 'selected' : ''; ?>>Patent</option>
                        <option value="report" <?php echo (isset($_GET['type']) && $_GET['type'] == 'report') ? 'selected' : ''; ?>>Report</option>
                        <option value="other" <?php echo (isset($_GET['type']) && $_GET['type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="project_id">Project</label>
                    <select name="project_id" id="project_id" class="form-control">
                        <option value="">All Projects</option>
                        <?php foreach ($allProjects as $proj): ?>
                            <option value="<?php echo $proj['id']; ?>" <?php echo (isset($_GET['project_id']) && $_GET['project_id'] == $proj['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($proj['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Search by title or description" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Documents List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Documents</h6>
        </div>
        <div class="card-body">
            <?php if (empty($documents)): ?>
                <div class="alert alert-info">
                    No documents found. <?php echo !isset($_GET['search']) ? 'Upload a new document to get started.' : 'Try adjusting your filters.'; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Project</th>
                                <th>Uploaded By</th>
                                <th>Upload Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                    <td>
                                        <?php 
                                            $typeLabels = [
                                                'research_paper' => '<span class="badge badge-primary">Research Paper</span>',
                                                'faculty_evaluation' => '<span class="badge badge-info">Faculty Evaluation</span>',
                                                'patent' => '<span class="badge badge-success">Patent</span>',
                                                'report' => '<span class="badge badge-warning">Report</span>',
                                                'other' => '<span class="badge badge-secondary">Other</span>'
                                            ];
                                            echo $typeLabels[$doc['type']] ?? $doc['type'];
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            if ($doc['project_id']) {
                                                $projectData = $project->getById($doc['project_id']);
                                                if ($projectData) {
                                                    echo '<a href="project_detail.php?id=' . $projectData['id'] . '">' . htmlspecialchars($projectData['title']) . '</a>';
                                                } else {
                                                    echo '<span class="text-muted">Unknown Project</span>';
                                                }
                                            } else {
                                                echo '<span class="text-muted">None</span>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $uploaderData = $user->getById($doc['uploaded_by']);
                                            if ($uploaderData) {
                                                echo htmlspecialchars($uploaderData['first_name'] . ' ' . $uploaderData['last_name']);
                                            } else {
                                                echo '<span class="text-muted">Unknown User</span>';
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($doc['upload_date'])); ?></td>
                                    <td>
                                        <a href="document_detail.php?id=<?php echo $doc['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php
                                        // Check if user can edit this document
                                        $canEdit = false;
                                        
                                        // Admin can edit any document
                                        if ($userRole == 1) {
                                            $canEdit = true;
                                        }
                                        // Document uploader can edit their own documents
                                        else if ($doc['uploaded_by'] == $_SESSION['user_id']) {
                                            $canEdit = true;
                                        }
                                        // Project managers can edit documents in their projects
                                        else if ($userRole == 2 && $doc['project_id']) {
                                            $projectData = $project->getById($doc['project_id']);
                                            if ($projectData && $projectData['created_by'] == $_SESSION['user_id']) {
                                                $canEdit = true;
                                            }
                                        }
                                        
                                        if ($canEdit):
                                        ?>
                                        <a href="document_form.php?id=<?php echo $doc['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#deleteModal<?php echo $doc['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        
                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $doc['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel<?php echo $doc['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $doc['id']; ?>">Confirm Delete</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to delete the document "<?php echo htmlspecialchars($doc['title']); ?>"? This action cannot be undone.
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                        <form method="POST" action="documents.php">
                                                            <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                                            <button type="submit" name="delete_document" class="btn btn-danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['type']) ? '&type=' . htmlspecialchars($_GET['type']) : ''; ?><?php echo isset($_GET['project_id']) ? '&project_id=' . htmlspecialchars($_GET['project_id']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['type']) ? '&type=' . htmlspecialchars($_GET['type']) : ''; ?><?php echo isset($_GET['project_id']) ? '&project_id=' . htmlspecialchars($_GET['project_id']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['type']) ? '&type=' . htmlspecialchars($_GET['type']) : ''; ?><?php echo isset($_GET['project_id']) ? '&project_id=' . htmlspecialchars($_GET['project_id']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>