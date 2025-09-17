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
require_once 'classes/User.php';
require_once 'classes/Department.php';

// Initialize objects
$userObj = new User();
$departmentObj = new Department();

// Get user role
$currentUser = $userObj->getById($_SESSION['user_id']);
$userRole = $currentUser['role_id'];

// Check if user has permission to access departments
if ($userRole > 2) { // Only Admin (1) and Project Manager (2) can access
    $_SESSION['error_message'] = "You don't have permission to access departments.";
    header("Location: index.php");
    exit;
}

// Handle department deletion
if (isset($_POST['delete_department']) && isset($_POST['department_id'])) {
    $departmentId = $_POST['department_id'];
    
    // Only admin can delete departments
    if ($userRole == 1) {
        if ($departmentObj->delete($departmentId)) {
            $_SESSION['success_message'] = "Department deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete department: " . implode(", ", $departmentObj->getErrors());
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete departments.";
    }
    
    // Redirect to prevent form resubmission
    header("Location: departments.php");
    exit;
}

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Set up filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get departments
$departments = [];
$totalDepartments = 0;

if (!empty($search)) {
    $filters = ["name LIKE :search OR description LIKE :search"];
    $filterParams = [':search' => "%$search%"];
    $departments = $departmentObj->search($filters, $filterParams, "name ASC", $limit, $offset);
    $totalDepartments = $departmentObj->count($filters, $filterParams);
} else {
    $departments = $departmentObj->getAll("name", "ASC", $limit, $offset);
    $totalDepartments = $departmentObj->count();
}

// Calculate total pages
$totalPages = ceil($totalDepartments / $limit);

// Set page title
$pageTitle = "Departments";

// Include header
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $pageTitle; ?></h1>
        <a href="department_form.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Department
        </a>
    </div>
    
    <!-- Search Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Search Departments</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="departments.php" class="row">
                <div class="col-md-10 mb-3">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or description" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Departments List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Departments</h6>
        </div>
        <div class="card-body">
            <?php if (empty($departments)): ?>
                <div class="alert alert-info">
                    No departments found. <?php echo empty($search) ? 'Add a new department to get started.' : 'Try adjusting your search.'; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Members</th>
                                <th>Projects</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                    <td>
                                        <?php 
                                        $desc = $dept['description'] ?? '';
                                        echo htmlspecialchars(substr($desc, 0, 100)) . (strlen($desc) > 100 ? '...' : '');
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $members = $departmentObj->getMembers($dept['id']);
                                        echo count($members);
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $projects = $departmentObj->getProjects($dept['id']);
                                        echo count($projects);
                                        ?>
                                    </td>
                                    <td>
                                        <a href="department_detail.php?id=<?php echo $dept['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($userRole == 1): // Only admin can edit/delete departments ?>
                                            <a href="department_form.php?id=<?php echo $dept['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#deleteModal<?php echo $dept['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            
                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $dept['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel<?php echo $dept['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteModalLabel<?php echo $dept['id']; ?>">Confirm Delete</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete the department "<?php echo htmlspecialchars($dept['name']); ?>"?</p>
                                                            <p class="text-danger">
                                                                <strong>Warning:</strong> This will also remove all users and projects associated with this department.
                                                                This action cannot be undone.
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                            <form method="POST" action="departments.php">
                                                                <input type="hidden" name="department_id" value="<?php echo $dept['id']; ?>">
                                                                <button type="submit" name="delete_department" class="btn btn-danger">Delete</button>
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
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . htmlspecialchars($search) : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . htmlspecialchars($search) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . htmlspecialchars($search) : ''; ?>" aria-label="Next">
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