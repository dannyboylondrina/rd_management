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
require_once 'classes/Project.php';
require_once 'classes/User.php';
require_once 'classes/Department.php';

// Initialize objects
$resourceObj = new Resource();
$projectObj = new Project();
$userObj = new User();
$departmentObj = new Department();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);

// Check if returning a resource
$isReturn = isset($_GET['action']) && $_GET['action'] === 'return' && isset($_GET['id']);

// Check if editing an allocation
$allocationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Initialize allocation data
$allocation = [
    'id' => 0,
    'resource_id' => isset($_GET['resource_id']) ? (int)$_GET['resource_id'] : 0,
    'project_id' => isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0,
    'quantity' => 1,
    'allocation_date' => date('Y-m-d'),
    'return_date' => '',
    'status' => 'allocated',
    'notes' => ''
];

// Get resource and project data
$resource = null;
$project = null;

if ($isReturn && $allocationId > 0) {
    // Get allocation data for return
    $allocation = $resourceObj->getAllocationById($allocationId);
    if (!$allocation) {
        $_SESSION['error_message'] = "Allocation not found.";
        header("Location: resources.php");
        exit;
    }
    
    $resource = $resourceObj->getById($allocation['resource_id']);
    $project = $projectObj->getById($allocation['project_id']);
} else {
    // Get resource data
    if ($allocation['resource_id'] > 0) {
        $resource = $resourceObj->getById($allocation['resource_id']);
        if (!$resource) {
            $_SESSION['error_message'] = "Resource not found.";
            header("Location: resources.php");
            exit;
        }
    }
    
    // Get project data
    if ($allocation['project_id'] > 0) {
        $project = $projectObj->getById($allocation['project_id']);
        if (!$project) {
            $_SESSION['error_message'] = "Project not found.";
            header("Location: projects.php");
            exit;
        }
    }
}

// Check if user has permission to allocate resources
$canAllocate = false;

// Admins and Project Managers can allocate any resource
if ($currentUser['role_id'] <= 2) {
    $canAllocate = true;
} 
// Department Heads can allocate resources in their department
elseif ($currentUser['role_id'] == 4) {
    if ($resource && $resource['department_id'] == $currentUser['department_id']) {
        $canAllocate = true;
    } elseif ($project && $project['department_id'] == $currentUser['department_id']) {
        $canAllocate = true;
    }
}
// Project creators can allocate resources to their projects
elseif ($project && $project['created_by'] == $currentUser['id']) {
    $canAllocate = true;
}

if (!$canAllocate) {
    $_SESSION['error_message'] = "You don't have permission to allocate resources.";
    header("Location: resources.php");
    exit;
}

// Get available resources for dropdown
$availableResources = [];
if (!$resource && !$isReturn) {
    if ($currentUser['role_id'] <= 2) {
        // Admins and Project Managers can see all available resources
        $availableResources = $resourceObj->getAvailable();
    } elseif ($currentUser['role_id'] == 4) {
        // Department Heads can see available resources in their department
        $availableResources = $resourceObj->getAvailableByDepartment($currentUser['department_id']);
    } else {
        // Other users can see all available resources
        $availableResources = $resourceObj->getAvailable();
    }
}

// Get projects for dropdown
$projects = [];
if (!$project && !$isReturn) {
    if ($currentUser['role_id'] <= 2) {
        // Admins and Project Managers can see all active projects
        $projects = $projectObj->getByStatus('in_progress');
    } elseif ($currentUser['role_id'] == 4) {
        // Department Heads can see active projects in their department
        $projects = $projectObj->getByDepartmentAndStatus($currentUser['department_id'], 'in_progress');
    } else {
        // Other users can see active projects they're members of
        $projects = $projectObj->getByMemberAndStatus($currentUser['id'], 'in_progress');
    }
}

// Calculate available quantity for the resource
$availableQuantity = 0;
if ($resource) {
    $currentlyAllocated = $resourceObj->getCurrentlyAllocatedQuantity($resource['id']);
    $availableQuantity = $resource['quantity'] - $currentlyAllocated;
    
    // If returning, add the current allocation quantity to available
    if ($isReturn) {
        $availableQuantity += $allocation['quantity'];
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    if ($isReturn) {
        // Process return
        $allocation['return_date'] = $_POST['return_date'] ?? date('Y-m-d');
        $allocation['status'] = 'returned';
        $allocation['notes'] = $_POST['notes'] ?? '';
        
        $result = $resourceObj->updateAllocation($allocation['id'], $allocation);
        
        if ($result) {
            $_SESSION['success_message'] = "Resource returned successfully.";
            
            // Redirect based on context
            if (!empty($allocation['resource_id'])) {
                header("Location: resource_detail.php?id=" . $allocation['resource_id']);
            } elseif (!empty($allocation['project_id'])) {
                header("Location: project_detail.php?id=" . $allocation['project_id']);
            } else {
                header("Location: resources.php");
            }
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to return resource. " . $resourceObj->getError();
        }
    } else {
        // Process allocation
        $allocation['resource_id'] = isset($_POST['resource_id']) ? (int)$_POST['resource_id'] : $allocation['resource_id'];
        $allocation['project_id'] = isset($_POST['project_id']) ? (int)$_POST['project_id'] : $allocation['project_id'];
        $allocation['quantity'] = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        $allocation['allocation_date'] = $_POST['allocation_date'] ?? date('Y-m-d');
        $allocation['notes'] = $_POST['notes'] ?? '';
        
        // Validate form data
        $errors = [];
        
        if (empty($allocation['resource_id'])) {
            $errors[] = "Resource is required.";
        }
        
        if (empty($allocation['project_id'])) {
            $errors[] = "Project is required.";
        }
        
        if ($allocation['quantity'] <= 0) {
            $errors[] = "Quantity must be greater than zero.";
        }
        
        if ($allocation['quantity'] > $availableQuantity) {
            $errors[] = "Requested quantity exceeds available quantity. Maximum available: " . $availableQuantity;
        }
        
        // If no errors, save allocation
        if (empty($errors)) {
            $result = $resourceObj->allocateToProject($allocation);
            
            if ($result) {
                $_SESSION['success_message'] = "Resource allocated successfully.";
                
                // Redirect based on context
                if (!empty($allocation['resource_id'])) {
                    header("Location: resource_detail.php?id=" . $allocation['resource_id']);
                } elseif (!empty($allocation['project_id'])) {
                    header("Location: project_detail.php?id=" . $allocation['project_id']);
                } else {
                    header("Location: resources.php");
                }
                exit;
            } else {
                $errors[] = "Failed to allocate resource. " . $resourceObj->getError();
            }
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
                <li class="breadcrumb-item"><a href="resources.php">Resources</a></li>
                <?php if ($resource): ?>
                    <li class="breadcrumb-item"><a href="resource_detail.php?id=<?php echo $resource['id']; ?>"><?php echo htmlspecialchars($resource['name']); ?></a></li>
                <?php endif; ?>
                <?php if ($project): ?>
                    <li class="breadcrumb-item"><a href="project_detail.php?id=<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['title']); ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $isReturn ? 'Return Resource' : 'Allocate Resource'; ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $isReturn ? 'Return Resource' : 'Allocate Resource'; ?></h1>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><?php echo $isReturn ? 'Return Details' : 'Allocation Details'; ?></h6>
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
                
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($isReturn ? '?action=return&id=' . $allocationId : '')); ?>" method="post" id="allocationForm">
                    <?php if ($isReturn): ?>
                        <!-- Return Form -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Resource</h5>
                                <p><?php echo htmlspecialchars($resource['name']); ?></p>
                                <p class="badge resource-type-<?php echo $resource['type']; ?>"><?php echo ucfirst($resource['type']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Project</h5>
                                <p><?php echo htmlspecialchars($project['title']); ?></p>
                                <p class="badge project-status-<?php echo $project['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="quantity" class="form-label">Allocated Quantity</label>
                                <input type="number" class="form-control" id="quantity" value="<?php echo $allocation['quantity']; ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="allocation_date" class="form-label">Allocation Date</label>
                                <input type="date" class="form-control" id="allocation_date" value="<?php echo date('Y-m-d', strtotime($allocation['allocation_date'])); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="return_date" class="form-label">Return Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="return_date" name="return_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($allocation['notes']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-undo"></i> Return Resource
                                </button>
                                <a href="<?php echo !empty($allocation['resource_id']) ? 'resource_detail.php?id=' . $allocation['resource_id'] : 'resources.php'; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Allocation Form -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="resource_id" class="form-label">Resource <span class="text-danger">*</span></label>
                                <?php if ($resource): ?>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($resource['name']); ?>" readonly>
                                    <input type="hidden" name="resource_id" value="<?php echo $resource['id']; ?>">
                                <?php else: ?>
                                    <select class="form-select" id="resource_id" name="resource_id" required>
                                        <option value="">-- Select Resource --</option>
                                        <?php foreach ($availableResources as $res): ?>
                                            <option value="<?php echo $res['id']; ?>" data-quantity="<?php echo $res['quantity'] - $resourceObj->getCurrentlyAllocatedQuantity($res['id']); ?>" data-unit="<?php echo htmlspecialchars($res['unit']); ?>">
                                                <?php echo htmlspecialchars($res['name']); ?> (<?php echo $res['quantity'] - $resourceObj->getCurrentlyAllocatedQuantity($res['id']); ?> available)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="project_id" class="form-label">Project <span class="text-danger">*</span></label>
                                <?php if ($project): ?>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($project['title']); ?>" readonly>
                                    <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                <?php else: ?>
                                    <select class="form-select" id="project_id" name="project_id" required>
                                        <option value="">-- Select Project --</option>
                                        <?php foreach ($projects as $proj): ?>
                                            <option value="<?php echo $proj['id']; ?>">
                                                <?php echo htmlspecialchars($proj['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="quantity" name="quantity" value="<?php echo $allocation['quantity']; ?>" min="1" max="<?php echo $availableQuantity; ?>" required>
                                    <span class="input-group-text" id="unit-addon"><?php echo !empty($resource['unit']) ? htmlspecialchars($resource['unit']) : ''; ?></span>
                                </div>
                                <?php if ($resource): ?>
                                    <div class="form-text">
                                        Maximum available: <?php echo $availableQuantity; ?> <?php echo !empty($resource['unit']) ? htmlspecialchars($resource['unit']) : ''; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="allocation_date" class="form-label">Allocation Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="allocation_date" name="allocation_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($allocation['notes']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Allocate Resource
                                </button>
                                <a href="<?php echo !empty($allocation['resource_id']) ? 'resource_detail.php?id=' . $allocation['resource_id'] : 'resources.php'; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <?php if ($resource): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Resource Details</h6>
                </div>
                <div class="card-body">
                    <h5><?php echo htmlspecialchars($resource['name']); ?></h5>
                    <p class="badge resource-type-<?php echo $resource['type']; ?>"><?php echo ucfirst($resource['type']); ?></p>
                    
                    <?php if (!empty($resource['description'])): ?>
                        <p><?php echo nl2br(htmlspecialchars($resource['description'])); ?></p>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <strong>Total Quantity:</strong> <?php echo $resource['quantity']; ?> <?php echo !empty($resource['unit']) ? htmlspecialchars($resource['unit']) : ''; ?>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Available Quantity:</strong> <?php echo $availableQuantity; ?> <?php echo !empty($resource['unit']) ? htmlspecialchars($resource['unit']) : ''; ?>
                    </div>
                    
                    <?php if (!empty($resource['location'])): ?>
                        <div class="mb-3">
                            <strong>Location:</strong> <?php echo htmlspecialchars($resource['location']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php
                    // Calculate usage percentage
                    $usagePercentage = ($resource['quantity'] > 0) ? (($resource['quantity'] - $availableQuantity) / $resource['quantity']) * 100 : 0;
                    ?>
                    
                    <div class="mb-3">
                        <strong>Current Usage:</strong>
                        <div class="progress mt-2" style="height: 20px;">
                            <div class="progress-bar <?php echo $usagePercentage >= 90 ? 'bg-danger' : ($usagePercentage >= 70 ? 'bg-warning' : 'bg-success'); ?>" role="progressbar" style="width: <?php echo $usagePercentage; ?>%;" aria-valuenow="<?php echo $usagePercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo round($usagePercentage); ?>%
                            </div>
                        </div>
                    </div>
                    
                    <a href="resource_detail.php?id=<?php echo $resource['id']; ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-info-circle"></i> View Full Details
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($project): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Project Details</h6>
                </div>
                <div class="card-body">
                    <h5><?php echo htmlspecialchars($project['title']); ?></h5>
                    <p class="badge project-status-<?php echo $project['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?></p>
                    
                    <?php if (!empty($project['description'])): ?>
                        <p><?php echo nl2br(htmlspecialchars(substr($project['description'], 0, 150))) . (strlen($project['description']) > 150 ? '...' : ''); ?></p>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <strong>Timeline:</strong>
                        <?php if (!empty($project['start_date']) && !empty($project['end_date'])): ?>
                            <?php echo date('M d, Y', strtotime($project['start_date'])); ?> to <?php echo date('M d, Y', strtotime($project['end_date'])); ?>
                        <?php elseif (!empty($project['start_date'])): ?>
                            Starts on <?php echo date('M d, Y', strtotime($project['start_date'])); ?>
                        <?php elseif (!empty($project['end_date'])): ?>
                            Ends on <?php echo date('M d, Y', strtotime($project['end_date'])); ?>
                        <?php else: ?>
                            Not specified
                        <?php endif; ?>
                    </div>
                    
                    <?php
                    // Get current resource allocations for this project
                    $projectResources = $projectObj->getAllocatedResources($project['id']);
                    ?>
                    
                    <?php if (!empty($projectResources)): ?>
                        <div class="mb-3">
                            <strong>Current Resource Allocations:</strong>
                            <ul class="list-group mt-2">
                                <?php foreach ($projectResources as $pr): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($pr['name']); ?>
                                        <span class="badge bg-primary rounded-pill"><?php echo $pr['allocated_quantity']; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <a href="project_detail.php?id=<?php echo $project['id']; ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-info-circle"></i> View Full Details
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Resource selection change handler
        const resourceSelect = document.getElementById('resource_id');
        const quantityInput = document.getElementById('quantity');
        const unitAddon = document.getElementById('unit-addon');
        
        if (resourceSelect && quantityInput) {
            resourceSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const maxQuantity = selectedOption.getAttribute('data-quantity');
                const unit = selectedOption.getAttribute('data-unit');
                
                if (maxQuantity) {
                    quantityInput.max = maxQuantity;
                    quantityInput.nextElementSibling.textContent = `Maximum available: ${maxQuantity} ${unit || ''}`;
                }
                
                if (unitAddon) {
                    unitAddon.textContent = unit || '';
                }
            });
        }
        
        // Form validation
        const form = document.getElementById('allocationForm');
        if (form) {
            form.addEventListener('submit', function(event) {
                let isValid = true;
                
                // Validate quantity
                if (quantityInput) {
                    const max = parseInt(quantityInput.max);
                    const value = parseInt(quantityInput.value);
                    
                    if (isNaN(value) || value <= 0) {
                        isValid = false;
                        quantityInput.classList.add('is-invalid');
                    } else if (max && value > max) {
                        isValid = false;
                        quantityInput.classList.add('is-invalid');
                    } else {
                        quantityInput.classList.remove('is-invalid');
                    }
                }
                
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