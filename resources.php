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
require_once 'classes/Resource.php';
require_once 'classes/User.php';
require_once 'classes/Department.php';

// Initialize objects
$resourceObj = new Resource();
$userObj = new User();
$departmentObj = new Department();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);

// Process delete request
if (isset($_POST['delete']) && isset($_POST['resource_id'])) {
    $resourceId = (int)$_POST['resource_id'];
    
    // Check if user has permission to delete
    $canDelete = false;
    
    // Admins and Resource Managers can delete any resource
    if ($currentUser['role_id'] <= 2) {
        $canDelete = true;
    }
    // Department Heads can delete resources in their department
    elseif ($currentUser['role_id'] == 4) {
        $resource = $resourceObj->getById($resourceId);
        if ($resource && $resource['department_id'] == $currentUser['department_id']) {
            $canDelete = true;
        }
    }
    
    if ($canDelete) {
        $result = $resourceObj->delete($resourceId);
        if ($result) {
            $_SESSION['success_message'] = "Resource deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete resource. " . $resourceObj->getError();
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete this resource.";
    }
    
    // Redirect to prevent form resubmission
    header("Location: resources.php");
    exit;
}

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Set up filtering
$filters = [];

// Filter by type
if (isset($_GET['type']) && !empty($_GET['type'])) {
    $filters['type'] = $_GET['type'];
}

// Filter by availability
if (isset($_GET['availability']) && $_GET['availability'] !== '') {
    $filters['availability'] = (int)$_GET['availability'];
}

// Filter by department
if (isset($_GET['department_id']) && !empty($_GET['department_id'])) {
    $filters['department_id'] = (int)$_GET['department_id'];
}

// Filter by search term
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Get resources based on user role and filters
$resources = [];
$totalResources = 0;

// Admins and Project Managers can see all resources
if ($currentUser['role_id'] <= 2) {
    $resources = $resourceObj->getAllWithFilters($filters, 'name', 'ASC', $limit, $offset);
    $totalResources = $resourceObj->countWithFilters($filters);
} 
// Department Heads can see resources in their department
elseif ($currentUser['role_id'] == 4) {
    $filters['department_id'] = $currentUser['department_id'];
    $resources = $resourceObj->getAllWithFilters($filters, 'name', 'ASC', $limit, $offset);
    $totalResources = $resourceObj->countWithFilters($filters);
}
// Other users can see available resources and resources allocated to their projects
else {
    $resources = $resourceObj->getAvailableAndAllocatedToUser($currentUser['id'], $limit, $offset, $filters);
    $totalResources = $resourceObj->countAvailableAndAllocatedToUser($currentUser['id'], $filters);
}

// Calculate total pages
$totalPages = ceil($totalResources / $limit);

// Get departments for filter dropdown
$departments = $departmentObj->getAll();

// Include header
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h1 class="h3 mb-0 text-gray-800">Resources</h1>
        <p class="mb-0">Manage equipment, personnel, and other resources</p>
    </div>
    <div class="col-md-6 text-md-end">
        <?php if ($currentUser['role_id'] <= 2 || $currentUser['role_id'] == 4): ?>
            <a href="resource_form.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Resource
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Resources</h6>
    </div>
    <div class="card-body">
        <form action="resources.php" method="get" class="row g-3">
            <div class="col-md-3">
                <label for="type" class="form-label">Resource Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All Types</option>
                    <option value="equipment" <?php echo (isset($_GET['type']) && $_GET['type'] === 'equipment') ? 'selected' : ''; ?>>Equipment</option>
                    <option value="personnel" <?php echo (isset($_GET['type']) && $_GET['type'] === 'personnel') ? 'selected' : ''; ?>>Personnel</option>
                    <option value="facility" <?php echo (isset($_GET['type']) && $_GET['type'] === 'facility') ? 'selected' : ''; ?>>Facility</option>
                    <option value="software" <?php echo (isset($_GET['type']) && $_GET['type'] === 'software') ? 'selected' : ''; ?>>Software</option>
                    <option value="other" <?php echo (isset($_GET['type']) && $_GET['type'] === 'other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="availability" class="form-label">Availability</label>
                <select class="form-select" id="availability" name="availability">
                    <option value="">All</option>
                    <option value="1" <?php echo (isset($_GET['availability']) && $_GET['availability'] === '1') ? 'selected' : ''; ?>>Available</option>
                    <option value="0" <?php echo (isset($_GET['availability']) && $_GET['availability'] === '0') ? 'selected' : ''; ?>>Not Available</option>
                </select>
            </div>
            
            <?php if ($currentUser['role_id'] <= 2): ?>
                <div class="col-md-3">
                    <label for="department_id" class="form-label">Department</label>
                    <select class="form-select" id="department_id" name="department_id">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['id']; ?>" <?php echo (isset($_GET['department_id']) && $_GET['department_id'] == $department['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($department['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Search resources..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="resources.php" class="btn btn-secondary">
                    <i class="fas fa-sync"></i> Reset Filters
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Resources List -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Resources List</h6>
    </div>
    <div class="card-body">
        <?php if (empty($resources)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No resources found matching your criteria.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Department</th>
                            <th>Quantity</th>
                            <th>Availability</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources as $resource): ?>
                            <tr>
                                <td>
                                    <a href="resource_detail.php?id=<?php echo $resource['id']; ?>">
                                        <?php echo htmlspecialchars($resource['name']); ?>
                                    </a>
                                    <?php if (!empty($resource['description'])): ?>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars(substr($resource['description'], 0, 50)) . (strlen($resource['description']) > 50 ? '...' : ''); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge resource-type-<?php echo $resource['type']; ?>">
                                        <?php echo ucfirst($resource['type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($resource['department_id'])) {
                                        $department = $departmentObj->getById($resource['department_id']);
                                        echo $department ? htmlspecialchars($department['name']) : 'N/A';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo $resource['quantity']; ?>
                                    <?php if (!empty($resource['unit'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($resource['unit']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($resource['availability'] === 'available'): ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Not Available</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="resource_detail.php?id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($currentUser['role_id'] <= 2 || ($currentUser['role_id'] == 4 && $resource['department_id'] == $currentUser['department_id'])): ?>
                                        <a href="resource_form.php?id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $resource['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        
                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $resource['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $resource['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $resource['id']; ?>">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete this resource?</p>
                                                        <p><strong>Resource:</strong> <?php echo htmlspecialchars($resource['name']); ?></p>
                                                        <p class="text-danger">This action cannot be undone.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form action="resources.php" method="post">
                                                            <input type="hidden" name="resource_id" value="<?php echo $resource['id']; ?>">
                                                            <input type="hidden" name="delete" value="1">
                                                            <button type="submit" class="btn btn-danger">Delete</button>
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
                    <ul class="pagination justify-content-center mt-4">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['type']) ? '&type=' . urlencode($_GET['type']) : ''; ?><?php echo isset($_GET['availability']) ? '&availability=' . urlencode($_GET['availability']) : ''; ?><?php echo isset($_GET['department_id']) ? '&department_id=' . urlencode($_GET['department_id']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['type']) ? '&type=' . urlencode($_GET['type']) : ''; ?><?php echo isset($_GET['availability']) ? '&availability=' . urlencode($_GET['availability']) : ''; ?><?php echo isset($_GET['department_id']) ? '&department_id=' . urlencode($_GET['department_id']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['type']) ? '&type=' . urlencode($_GET['type']) : ''; ?><?php echo isset($_GET['availability']) ? '&availability=' . urlencode($_GET['availability']) : ''; ?><?php echo isset($_GET['department_id']) ? '&department_id=' . urlencode($_GET['department_id']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" aria-label="Next">
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