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
require_once 'classes/Project.php';
require_once 'classes/User.php';
require_once 'classes/Department.php';

// Initialize objects
$projectObj = new Project();
$userObj = new User();
$departmentObj = new Department();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);

// Process delete action
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['project_id'])) {
    $projectId = (int)$_POST['project_id'];
    
    // Check if user has permission to delete
    $canDelete = false;
    
    // Admins and Project Managers can delete any project
    if ($currentUser['role_id'] <= 2) {
        $canDelete = true;
    } else {
        // Project creators can delete their own projects
        $project = $projectObj->getById($projectId);
        if ($project && $project['created_by'] == $currentUser['id']) {
            $canDelete = true;
        }
    } 
    
    if ($canDelete) {
        if ($projectObj->delete($projectId)) {
            $_SESSION['success_message'] = "Project deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete project: " . implode(', ', $projectObj->getErrors());
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete this project.";
    }
    
    // Redirect to avoid form resubmission
    header("Location: projects.php");
    exit;
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$departmentId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get projects based on user role and filters
$projects = [];
$totalCount = 0;

// Build conditions array for filtering
$conditions = [];

if (!empty($status)) {
    $conditions['status'] = $status;
}

if (!empty($departmentId)) {
    $conditions['department_id'] = $departmentId;
}

if (!empty($search)) {
    // For search, we'll need to use a custom query
    // This is handled differently below
}

// Get projects based on user role
if ($currentUser['role_id'] <= 2) { // Admin or Project Manager - can see all projects
    if (!empty($search)) {
        // Search in title and description
        $projects = $projectObj->searchByKeyword($search, $limit, $offset);
        $totalCount = count($projectObj->searchByKeyword($search));
    } else {
        $projects = $projectObj->search($conditions, 'created_at', 'DESC', $limit, $offset);
        $totalCount = $projectObj->count($conditions);
    }
} elseif ($currentUser['role_id'] == 4) { // Department Head - can see department projects
    $conditions['department_id'] = $currentUser['department_id'];
    
    if (!empty($search)) {
        // Search in title and description within department
        $projects = $projectObj->searchByKeyword($search, $limit, $offset, $currentUser['department_id']);
        $totalCount = count($projectObj->searchByKeyword($search, null, null, $currentUser['department_id']));
    } else {
        $projects = $projectObj->search($conditions, 'created_at', 'DESC', $limit, $offset);
        $totalCount = $projectObj->count($conditions);
    }
} else { // Researcher or Faculty Member - can see projects they're a member of
    if (!empty($search)) {
        // Search in title and description for projects they're a member of
        $projects = $projectObj->searchByKeywordForMember($search, $currentUser['id'], $limit, $offset);
        $totalCount = count($projectObj->searchByKeywordForMember($search, $currentUser['id']));
    } else {
        $projects = $projectObj->getByMember($currentUser['id'], $limit, $offset);
        $totalCount = count($projectObj->getByMember($currentUser['id']));
        
        // Apply additional filters
        if (!empty($status)) {
            $filteredProjects = [];
            foreach ($projects as $project) {
                if ($project['status'] === $status) {
                    $filteredProjects[] = $project;
                }
            }
            $projects = $filteredProjects;
            
            // Recalculate total count
            $totalCount = count($projectObj->getByMember($currentUser['id']));
            $filteredCount = 0;
            foreach ($projectObj->getByMember($currentUser['id']) as $project) {
                if ($project['status'] === $status) {
                    $filteredCount++;
                }
            }
            $totalCount = $filteredCount;
        }
    }
}

// Calculate total pages for pagination
$totalPages = ceil($totalCount / $limit);

// Get departments for filter dropdown
$departments = $departmentObj->getAll();

// Include header
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h1 class="h3 mb-0 text-gray-800">Projects</h1>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="project_form.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New Project
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="planning" <?php echo $status === 'planning' ? 'selected' : ''; ?>>Planning</option>
                    <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="on_hold" <?php echo $status === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <?php if ($currentUser['role_id'] <= 2): // Admin or Project Manager ?>
                <div class="col-md-4">
                    <label for="department_id" class="form-label">Department</label>
                    <select class="form-select" id="department_id" name="department_id">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['id']; ?>" <?php echo $departmentId == $department['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($department['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by title or description">
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Projects List -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">Projects List</h6>
        <div class="text-muted">
            Showing <?php echo min($totalCount, $limit); ?> of <?php echo $totalCount; ?> projects
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($projects)): ?>
            <div class="text-center py-5">
                <i class="fas fa-project-diagram fa-4x text-muted mb-3"></i>
                <p class="lead text-muted">No projects found.</p>
                <?php if (!empty($search) || !empty($status) || !empty($departmentId)): ?>
                    <p>Try adjusting your filters or <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">view all projects</a>.</p>
                <?php else: ?>
                    <p>Click the "Create New Project" button to get started.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Department</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['title']); ?></td>
                                <td>
                                    <span class="project-status status-<?php echo $project['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($project['department_id'])) {
                                        $department = $departmentObj->getById($project['department_id']);
                                        echo htmlspecialchars($department['name'] ?? 'Unknown');
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td><?php echo !empty($project['start_date']) ? date('M d, Y', strtotime($project['start_date'])) : 'Not set'; ?></td>
                                <td><?php echo !empty($project['end_date']) ? date('M d, Y', strtotime($project['end_date'])) : 'Not set'; ?></td>
                                <td>
                                    <a href="project_detail.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    
                                    <?php 
                                    // Check if user can edit
                                    $canEdit = false;
                                    
                                    // Admins and Project Managers can edit any project
                                    if ($currentUser['role_id'] <= 2) {
                                        $canEdit = true;
                                    } else {
                                        // Project creators can edit their own projects
                                        if ($project['created_by'] == $currentUser['id']) {
                                            $canEdit = true;
                                        }
                                    }
                                    
                                    if ($canEdit):
                                    ?>
                                        <a href="project_form.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this project?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Projects pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&department_id=<?php echo $departmentId; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&department_id=<?php echo $departmentId; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&department_id=<?php echo $departmentId; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>