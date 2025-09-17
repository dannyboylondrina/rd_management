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

// Check if user has permission to access users
if ($userRole > 2) { // Only Admin (1) and Project Manager (2) can access
    $_SESSION['error_message'] = "You don't have permission to access users.";
    header("Location: index.php");
    exit;
}

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $userId = $_POST['user_id'];
    
    // Only admin can delete users
    if ($userRole == 1) {
        // Prevent deleting yourself
        if ($userId == $_SESSION['user_id']) {
            $_SESSION['error_message'] = "You cannot delete your own account.";
        } else {
            if ($userObj->delete($userId)) {
                $_SESSION['success_message'] = "User deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete user: " . implode(", ", $userObj->getErrors());
            }
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete users.";
    }
    
    // Redirect to prevent form resubmission
    header("Location: users.php");
    exit;
}

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Set up filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$roleFilter = isset($_GET['role']) ? $_GET['role'] : '';
$departmentFilter = isset($_GET['department']) ? $_GET['department'] : '';

// Get users
$users = [];
$totalUsers = 0;

// Build filters (use BaseModel conditions API)
$conditions = [];

if (!empty($search)) {
    $conditions['username'] = ['operator' => 'LIKE', 'value' => "%$search%"];
}

if (!empty($roleFilter)) {
    $conditions['role_id'] = $roleFilter;
}

if (!empty($departmentFilter)) {
    $conditions['department_id'] = $departmentFilter;
}

// Get users with filters
$users = $userObj->search($conditions, 'username', 'ASC', $limit, $offset);
$totalUsers = $userObj->count($conditions);

// Calculate total pages
$totalPages = ceil($totalUsers / $limit);

// Get all departments for filter dropdown
$departments = $departmentObj->getAll('name', 'ASC');

// Define roles for filter dropdown
$roles = [
    1 => 'Administrator',
    2 => 'Project Manager',
    3 => 'Researcher',
    4 => 'Department Head',
    5 => 'Faculty Member'
];

// Set page title
$pageTitle = "Users";

// Include header
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $pageTitle; ?></h1>
        <a href="user_form.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New User
        </a>
    </div>
    
    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Users</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="users.php" class="row">
                <div class="col-md-4 mb-3">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Search by username, email, or name" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="role">Role</label>
                    <select name="role" id="role" class="form-control">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo ($roleFilter == $id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="department">Department</label>
                    <select name="department" id="department" class="form-control">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo ($departmentFilter == $dept['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Users List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Users</h6>
        </div>
        <div class="card-body">
            <?php if (empty($users)): ?>
                <div class="alert alert-info">
                    No users found. <?php echo empty($search) && empty($roleFilter) && empty($departmentFilter) ? 'Add a new user to get started.' : 'Try adjusting your filters.'; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php 
                                            echo $roles[$user['role_id']] ?? 'Unknown';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            if (!empty($user['department_id'])) {
                                                $dept = $departmentObj->getById($user['department_id']);
                                                echo $dept ? htmlspecialchars($dept['name']) : 'Unknown';
                                            } else {
                                                echo 'None';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo ((int)($user['is_active'] ?? 1) === 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?>
                                    </td>
                                    <td>
                                        <a href="user_detail.php?id=<?php echo $user['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($userRole == 1): // Only admin can edit/delete users ?>
                                            <a href="user_form.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <a href="admin_reset_password.php?user=<?php echo urlencode($user['username']); ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-key"></i>
                                            </a>
                                            
                                            <?php if ($user['id'] != $_SESSION['user_id']): // Can't delete yourself ?>
                                                <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#deleteModal<?php echo $user['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                
                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteModal<?php echo $user['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $user['id']; ?>">Confirm Delete</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete the user "<?php echo htmlspecialchars($user['username']); ?>"?</p>
                                                                <p class="text-danger">
                                                                    <strong>Warning:</strong> This will remove the user from all projects and delete all their documents.
                                                                    This action cannot be undone.
                                                                </p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                                <form method="POST" action="users.php">
                                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                    <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
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
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . htmlspecialchars($search) : ''; ?><?php echo !empty($roleFilter) ? '&role=' . htmlspecialchars($roleFilter) : ''; ?><?php echo !empty($departmentFilter) ? '&department=' . htmlspecialchars($departmentFilter) : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . htmlspecialchars($search) : ''; ?><?php echo !empty($roleFilter) ? '&role=' . htmlspecialchars($roleFilter) : ''; ?><?php echo !empty($departmentFilter) ? '&department=' . htmlspecialchars($departmentFilter) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . htmlspecialchars($search) : ''; ?><?php echo !empty($roleFilter) ? '&role=' . htmlspecialchars($roleFilter) : ''; ?><?php echo !empty($departmentFilter) ? '&department=' . htmlspecialchars($departmentFilter) : ''; ?>" aria-label="Next">
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