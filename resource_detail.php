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
require_once 'classes/Project.php';

// Initialize objects
$resourceObj = new Resource();
$userObj = new User();
$departmentObj = new Department();
$projectObj = new Project();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);

// Get resource ID from URL
$resourceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if resource exists
$resource = $resourceObj->getById($resourceId);
if (!$resource) {
    $_SESSION['error_message'] = "Resource not found.";
    header("Location: resources.php");
    exit;
}

// Check if user has permission to view this resource
$canView = false;

// Admins and Project Managers can view any resource
if ($currentUser['role_id'] <= 2) {
    $canView = true;
} 
// Department Heads can view resources in their department
elseif ($currentUser['role_id'] == 4 && $resource['department_id'] == $currentUser['department_id']) {
    $canView = true;
}
// Other users can view available resources or resources allocated to their projects
else {
    if ($resource['availability'] === 'available') {
        $canView = true;
    } else {
        // Check if resource is allocated to any of the user's projects
        $userProjects = $projectObj->getByMember($currentUser['id']);
        $projectIds = array_column($userProjects, 'id');
        
        $allocations = $resourceObj->getAllocations($resourceId);
        foreach ($allocations as $allocation) {
            if (in_array($allocation['project_id'], $projectIds)) {
                $canView = true;
                break;
            }
        }
    }
}

if (!$canView) {
    $_SESSION['error_message'] = "You don't have permission to view this resource.";
    header("Location: resources.php");
    exit;
}

// Check if user can edit this resource
$canEdit = false;

// Admins and Project Managers can edit any resource
if ($currentUser['role_id'] <= 2) {
    $canEdit = true;
} 
// Department Heads can edit resources in their department
elseif ($currentUser['role_id'] == 4 && $resource['department_id'] == $currentUser['department_id']) {
    $canEdit = true;
}

// Get department
$department = null;
if (!empty($resource['department_id'])) {
    $department = $departmentObj->getById($resource['department_id']);
}

// Get resource allocations
$allocations = $resourceObj->getAllocations($resourceId);

// Process delete request
if (isset($_POST['delete']) && $_POST['delete'] == 1) {
    // Check if user has permission to delete
    if ($canEdit) {
        $result = $resourceObj->delete($resourceId);
        if ($result) {
            $_SESSION['success_message'] = "Resource deleted successfully.";
            header("Location: resources.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to delete resource. " . $resourceObj->getError();
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete this resource.";
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
                <li class="breadcrumb-item"><a href="resources.php">Resources</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($resource['name']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($resource['name']); ?></h1>
        <p class="mb-0">
            <span class="badge resource-type-<?php echo $resource['type']; ?>">
                <?php echo ucfirst($resource['type']); ?>
            </span>
            
            <?php if ($resource['availability'] === 'available'): ?>
                <span class="badge bg-success ms-2">Available</span>
            <?php else: ?>
                <span class="badge bg-danger ms-2">Not Available</span>
            <?php endif; ?>
            
            <?php if ($department): ?>
                <span class="ms-2">
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($department['name']); ?>
                </span>
            <?php endif; ?>
        </p>
    </div>
    <div class="col-md-4 text-md-end">
        <?php if ($canEdit): ?>
            <a href="resource_form.php?id=<?php echo $resourceId; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit Resource
            </a>
            
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="fas fa-trash"></i> Delete
            </button>
        <?php endif; ?>
        
        <a href="resources.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Resources
        </a>
    </div>
</div>

<div class="row">
    <!-- Resource Details -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Resource Details</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($resource['description'])): ?>
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h5>Description</h5>
                            <p><?php echo nl2br(htmlspecialchars($resource['description'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-3 font-weight-bold">Resource Type:</div>
                    <div class="col-md-9"><?php echo ucfirst($resource['type']); ?></div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3 font-weight-bold">Quantity:</div>
                    <div class="col-md-9">
                        <?php echo $resource['quantity']; ?>
                        <?php if (!empty($resource['unit'])): ?>
                            <?php echo htmlspecialchars($resource['unit']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($resource['location'])): ?>
                    <div class="row mb-3">
                        <div class="col-md-3 font-weight-bold">Location:</div>
                        <div class="col-md-9"><?php echo htmlspecialchars($resource['location']); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($department): ?>
                    <div class="row mb-3">
                        <div class="col-md-3 font-weight-bold">Department:</div>
                        <div class="col-md-9"><?php echo htmlspecialchars($department['name']); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($resource['acquisition_date'])): ?>
                    <div class="row mb-3">
                        <div class="col-md-3 font-weight-bold">Acquisition Date:</div>
                        <div class="col-md-9"><?php echo date('F d, Y', strtotime($resource['acquisition_date'])); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($resource['cost'])): ?>
                    <div class="row mb-3">
                        <div class="col-md-3 font-weight-bold">Cost:</div>
                        <div class="col-md-9">$<?php echo number_format($resource['cost'], 2); ?></div>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-3 font-weight-bold">Availability:</div>
                    <div class="col-md-9">
                        <?php if ($resource['availability'] === 'available'): ?>
                            <span class="badge bg-success">Available</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Not Available</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3 font-weight-bold">Created:</div>
                    <div class="col-md-9"><?php echo date('F d, Y', strtotime($resource['created_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Resource Allocations -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Allocation History</h6>
                <?php if ($canEdit && $resource['availability'] === 'available'): ?>
                    <a href="resource_allocation.php?resource_id=<?php echo $resourceId; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Allocate to Project
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($allocations)): ?>
                    <p class="text-center text-muted">This resource has not been allocated to any projects yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Quantity</th>
                                    <th>Allocation Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allocations as $allocation): ?>
                                    <?php $project = $projectObj->getById($allocation['project_id']); ?>
                                    <tr>
                                        <td>
                                            <?php if ($project): ?>
                                                <a href="project_detail.php?id=<?php echo $project['id']; ?>">
                                                    <?php echo htmlspecialchars($project['title']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Project not found</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $allocation['quantity']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($allocation['allocation_date'])); ?></td>
                                        <td>
                                            <?php echo !empty($allocation['return_date']) ? date('M d, Y', strtotime($allocation['return_date'])) : 'Not returned'; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $allocation['status'] === 'allocated' ? 'bg-success' : ($allocation['status'] === 'returned' ? 'bg-secondary' : 'bg-warning'); ?>">
                                                <?php echo ucfirst($allocation['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($canEdit && $allocation['status'] === 'allocated'): ?>
                                                <a href="resource_allocation.php?id=<?php echo $allocation['id']; ?>&action=return" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-undo"></i> Return
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Resource Status -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Resource Status</h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <?php if ($resource['availability'] === 'available'): ?>
                        <div class="resource-status-icon available">
                            <i class="fas fa-check-circle fa-4x"></i>
                        </div>
                        <h5 class="mt-3">Available for Allocation</h5>
                        <p class="text-muted">This resource can be allocated to projects.</p>
                    <?php else: ?>
                        <div class="resource-status-icon unavailable">
                            <i class="fas fa-times-circle fa-4x"></i>
                        </div>
                        <h5 class="mt-3">Not Available</h5>
                        <p class="text-muted">This resource is currently not available for allocation.</p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($allocations)): ?>
                    <?php
                    // Calculate allocation statistics
                    $totalAllocated = 0;
                    $currentlyAllocated = 0;
                    
                    foreach ($allocations as $allocation) {
                        $totalAllocated += $allocation['quantity'];
                        if ($allocation['status'] === 'allocated') {
                            $currentlyAllocated += $allocation['quantity'];
                        }
                    }
                    
                    $availableQuantity = $resource['quantity'] - $currentlyAllocated;
                    $usagePercentage = ($resource['quantity'] > 0) ? ($currentlyAllocated / $resource['quantity']) * 100 : 0;
                    ?>
                    
                    <div class="mb-3">
                        <h6>Current Allocation</h6>
                        <div class="progress mb-2" style="height: 20px;">
                            <div class="progress-bar <?php echo $usagePercentage >= 90 ? 'bg-danger' : ($usagePercentage >= 70 ? 'bg-warning' : 'bg-success'); ?>" role="progressbar" style="width: <?php echo $usagePercentage; ?>%;" aria-valuenow="<?php echo $usagePercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo round($usagePercentage); ?>%
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Available: <?php echo $availableQuantity; ?> <?php echo !empty($resource['unit']) ? $resource['unit'] : ''; ?></small>
                            <small class="text-muted">Total: <?php echo $resource['quantity']; ?> <?php echo !empty($resource['unit']) ? $resource['unit'] : ''; ?></small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Allocation Statistics</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Currently Allocated
                                <span class="badge bg-primary rounded-pill"><?php echo $currentlyAllocated; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Allocations
                                <span class="badge bg-secondary rounded-pill"><?php echo count($allocations); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Quantity Allocated
                                <span class="badge bg-info rounded-pill"><?php echo $totalAllocated; ?></span>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="d-grid gap-2">
                    <?php if ($canEdit): ?>
                        <a href="resource_form.php?id=<?php echo $resourceId; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Resource
                        </a>
                        
                        <?php if ($resource['availability'] === 'available'): ?>
                            <a href="resource_allocation.php?resource_id=<?php echo $resourceId; ?>" class="btn btn-success">
                                <i class="fas fa-project-diagram"></i> Allocate to Project
                            </a>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="fas fa-trash"></i> Delete Resource
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
                    <p>Are you sure you want to delete this resource? This action cannot be undone.</p>
                    <p><strong>Resource:</strong> <?php echo htmlspecialchars($resource['name']); ?></p>
                    
                    <?php if (!empty($allocations)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Warning: This resource has allocation history. Deleting it will remove all allocation records.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $resourceId); ?>" method="post">
                        <input type="hidden" name="delete" value="1">
                        <button type="submit" class="btn btn-danger">Delete Resource</button>
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