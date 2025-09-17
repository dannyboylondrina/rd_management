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

// Set page title
$pageTitle = "Patents";

// Get user role
$currentUser = $user->getById($_SESSION['user_id']);
$userRole = $currentUser['role_id'];
$userDepartment = $currentUser['department_id'];

// Handle patent deletion
if (isset($_POST['delete_patent']) && isset($_POST['patent_id'])) {
    $patentId = $_POST['patent_id'];
    
    // Get patent details to check permissions
    $patentData = $patent->getById($patentId);
    
    // Check if user has permission to delete
    $canDelete = false;
    
    if ($patentData) {
        // Admin can delete any patent
        if ($userRole == 1) {
            $canDelete = true;
        }
        // Patent creator can delete their own patents
        else if ($patentData['created_by'] == $_SESSION['user_id']) {
            $canDelete = true;
        }
        // Project managers can delete patents in their projects
        else if ($userRole == 2 && $patentData['project_id']) {
            $projectData = $project->getById($patentData['project_id']);
            if ($projectData && $projectData['created_by'] == $_SESSION['user_id']) {
                $canDelete = true;
            }
        }
    }
    
    if ($canDelete) {
        if ($patent->delete($patentId)) {
            $_SESSION['success_message'] = "Patent deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete patent: " . implode(", ", $patent->getErrors());
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete this patent.";
    }
    
    // Redirect to prevent form resubmission
    header("Location: patents.php");
    exit;
}

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Set up filtering
$filters = [];
$filterParams = [];

// Filter by patent status
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters[] = "status = :status";
    $filterParams[':status'] = $_GET['status'];
}

// Filter by project
if (isset($_GET['project_id']) && !empty($_GET['project_id'])) {
    $filters[] = "project_id = :project_id";
    $filterParams[':project_id'] = $_GET['project_id'];
}

// Filter by search keyword
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters[] = "(title LIKE :search OR description LIKE :search OR patent_number LIKE :search)";
    $filterParams[':search'] = "%" . $_GET['search'] . "%";
}

// Get patents based on user role
$patents = [];
$totalPatents = 0;

// Admin can see all patents
if ($userRole == 1) {
    $patents = $patent->searchWithFilters($filters, $filterParams, "created_at", "DESC", $limit, $offset);
    $totalPatents = $patent->countWithFilters($filters, $filterParams);
}
// Department head can see patents in their department's projects
else if ($userRole == 4) {
    // Get department projects
    $departmentProjects = $project->getByDepartment($userDepartment);
    $projectIds = array_column($departmentProjects, 'id');
    
    if (!empty($projectIds)) {
        $projectIdList = implode(',', $projectIds);
        $filters[] = "(project_id IN ($projectIdList) OR created_by = :user_id)";
        $filterParams[':user_id'] = $_SESSION['user_id'];
    } else {
        $filters[] = "created_by = :user_id";
        $filterParams[':user_id'] = $_SESSION['user_id'];
    }
    
    $patents = $patent->searchWithFilters($filters, $filterParams, "created_at", "DESC", $limit, $offset);
    $totalPatents = $patent->countWithFilters($filters, $filterParams);
}
// Project managers can see patents in their projects
else if ($userRole == 2) {
    // Get manager's projects
    $managerProjects = $project->getByCreator($_SESSION['user_id']);
    $projectIds = array_column($managerProjects, 'id');
    
    if (!empty($projectIds)) {
        $projectIdList = implode(',', $projectIds);
        $filters[] = "(project_id IN ($projectIdList) OR created_by = :user_id)";
        $filterParams[':user_id'] = $_SESSION['user_id'];
    } else {
        $filters[] = "created_by = :user_id";
        $filterParams[':user_id'] = $_SESSION['user_id'];
    }
    
    $patents = $patent->searchWithFilters($filters, $filterParams, "created_at", "DESC", $limit, $offset);
    $totalPatents = $patent->countWithFilters($filters, $filterParams);
}
// Researchers and faculty members can see patents they've created or in projects they're members of
else {
    // Get user's projects
    $userProjects = $project->getByMember($_SESSION['user_id']);
    $projectIds = array_column($userProjects, 'id');
    
    if (!empty($projectIds)) {
        $projectIdList = implode(',', $projectIds);
        $filters[] = "(project_id IN ($projectIdList) OR created_by = :user_id)";
        $filterParams[':user_id'] = $_SESSION['user_id'];
    } else {
        $filters[] = "created_by = :user_id";
        $filterParams[':user_id'] = $_SESSION['user_id'];
    }
    
    $patents = $patent->searchWithFilters($filters, $filterParams, "created_at", "DESC", $limit, $offset);
    $totalPatents = $patent->countWithFilters($filters, $filterParams);
}

// Calculate total pages
$totalPages = ceil($totalPatents / $limit);

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
        <a href="patent_form.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Register New Patent
        </a>
    </div>
    
    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Patents</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="patents.php" class="row">
                <div class="col-md-3 mb-3">
                    <label for="status">Patent Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="draft" <?php echo (isset($_GET['status']) && $_GET['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="filed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'filed') ? 'selected' : ''; ?>>Filed</option>
                        <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
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
                    <input type="text" name="search" id="search" class="form-control" placeholder="Search by title, description, or patent number" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Patents List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Patents</h6>
        </div>
        <div class="card-body">
            <?php if (empty($patents)): ?>
                <div class="alert alert-info">
                    No patents found. <?php echo !isset($_GET['search']) ? 'Register a new patent to get started.' : 'Try adjusting your filters.'; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Patent Number</th>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Filing Date</th>
                                <th>Approval Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patents as $pat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pat['title']); ?></td>
                                    <td><?php echo htmlspecialchars($pat['patent_number'] ?? 'Not Assigned'); ?></td>
                                    <td>
                                        <?php 
                                            if ($pat['project_id']) {
                                                $projectData = $project->getById($pat['project_id']);
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
                                            $statusLabels = [
                                                'draft' => '<span class="badge bg-secondary">Draft</span>',
                                                'filed' => '<span class="badge bg-primary">Filed</span>',
                                                'approved' => '<span class="badge bg-success">Approved</span>',
                                                'rejected' => '<span class="badge bg-danger">Rejected</span>'
                                            ];
                                            echo $statusLabels[$pat['status']] ?? $pat['status'];
                                        ?>
                                    </td>
                                    <td><?php echo !empty($pat['filing_date']) ? date('M d, Y', strtotime($pat['filing_date'])) : 'Not Filed'; ?></td>
                                    <td><?php echo !empty($pat['approval_date']) ? date('M d, Y', strtotime($pat['approval_date'])) : 'Not Approved'; ?></td>
                                    <td>
                                        <a href="patent_detail.php?id=<?php echo $pat['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php
                                        // Check if user can edit this patent
                                        $canEdit = false;
                                        
                                        // Admin can edit any patent
                                        if ($userRole == 1) {
                                            $canEdit = true;
                                        }
                                        // Patent creator can edit their own patents
                                        else if ($pat['created_by'] == $_SESSION['user_id']) {
                                            $canEdit = true;
                                        }
                                        // Project managers can edit patents in their projects
                                        else if ($userRole == 2 && $pat['project_id']) {
                                            $projectData = $project->getById($pat['project_id']);
                                            if ($projectData && $projectData['created_by'] == $_SESSION['user_id']) {
                                                $canEdit = true;
                                            }
                                        }
                                        
                                        if ($canEdit):
                                        ?>
                                        <a href="patent_form.php?id=<?php echo $pat['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#deleteModal<?php echo $pat['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        
                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $pat['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel<?php echo $pat['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $pat['id']; ?>">Confirm Delete</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to delete the patent "<?php echo htmlspecialchars($pat['title']); ?>"? This action cannot be undone.
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                        <form method="POST" action="patents.php">
                                                            <input type="hidden" name="patent_id" value="<?php echo $pat['id']; ?>">
                                                            <button type="submit" name="delete_patent" class="btn btn-danger">Delete</button>
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
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?><?php echo isset($_GET['project_id']) ? '&project_id=' . htmlspecialchars($_GET['project_id']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?><?php echo isset($_GET['project_id']) ? '&project_id=' . htmlspecialchars($_GET['project_id']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?><?php echo isset($_GET['project_id']) ? '&project_id=' . htmlspecialchars($_GET['project_id']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>" aria-label="Next">
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