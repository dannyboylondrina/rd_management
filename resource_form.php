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

// Check if user has permission to add/edit resources
$canManageResources = false;

// Admins and Project Managers can manage any resource
if ($currentUser['role_id'] <= 2) {
    $canManageResources = true;
} 
// Department Heads can manage resources in their department
elseif ($currentUser['role_id'] == 4) {
    $canManageResources = true;
}

if (!$canManageResources) {
    $_SESSION['error_message'] = "You don't have permission to manage resources.";
    header("Location: resources.php");
    exit;
}

// Check if editing existing resource or creating new one
$resourceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($resourceId > 0);

// Initialize resource data
$resource = [ 
    'id' => 0,
    'name' => '',
    'description' => '',
    'type' => 'equipment',
    'quantity' => 1,
    'unit' => '',
    'availability' => 'available',
    'department_id' => $currentUser['role_id'] == 4 ? $currentUser['department_id'] : null,
    'location' => '',
    'acquisition_date' => '',
    'cost' => ''
];

// If editing, get resource data
if ($isEdit) {
    $resource = $resourceObj->getById($resourceId);
    if (!$resource) {
        $_SESSION['error_message'] = "Resource not found.";
        header("Location: resources.php");
        exit;
    }
    
    // Check if user has permission to edit this resource
    if ($currentUser['role_id'] == 4 && $resource['department_id'] != $currentUser['department_id']) {
        $_SESSION['error_message'] = "You don't have permission to edit this resource.";
        header("Location: resources.php");
        exit;
    }
}

// Get departments for dropdown
$departments = [];
if ($currentUser['role_id'] <= 2) {
    // Admins and Project Managers can see all departments
    $departments = $departmentObj->getAll();
} elseif ($currentUser['role_id'] == 4) {
    // Department Heads can only see their department
    $departments = [$departmentObj->getById($currentUser['department_id'])];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $resource['name'] = $_POST['name'] ?? '';
    $resource['description'] = $_POST['description'] ?? '';
    $resource['type'] = $_POST['type'] ?? 'equipment';
    $resource['quantity'] = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    $resource['unit'] = $_POST['unit'] ?? '';
    $resource['availability'] = isset($_POST['is_available']) ? 'available' : 'unavailable';
    $resource['department_id'] = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $resource['location'] = $_POST['location'] ?? '';
    $resource['acquisition_date'] = !empty($_POST['acquisition_date']) ? $_POST['acquisition_date'] : null;
    $resource['cost'] = !empty($_POST['cost']) ? (float)$_POST['cost'] : null;
    
    // Set created_by to current user ID for new resources
    if (!$isEdit) {
        $resource['created_by'] = $_SESSION['user_id'];
    }
    
    // Validate form data
    $errors = [];
    
    if (empty($resource['name'])) {
        $errors[] = "Name is required.";
    }
    
    if (empty($resource['type'])) {
        $errors[] = "Resource type is required.";
    }
    
    if ($resource['quantity'] <= 0) {
        $errors[] = "Quantity must be greater than zero.";
    }
    
    // If Department Head, force their department
    if ($currentUser['role_id'] == 4) {
        $resource['department_id'] = $currentUser['department_id'];
    }
    
    // If no errors, save resource
    if (empty($errors)) {
        $result = false;
        
        if ($isEdit) {
            // Update existing resource
            $result = $resourceObj->update($resource['id'], $resource);
            
            if ($result) {
                $_SESSION['success_message'] = "Resource updated successfully.";
                header("Location: resource_detail.php?id=" . $resource['id']);
                exit;
            } else {
                $errors[] = "Failed to update resource. " . $resourceObj->getError();
            }
        } else {
            // Create new resource
            $result = $resourceObj->create($resource);
            
            if ($result) {
                $_SESSION['success_message'] = "Resource created successfully.";
                header("Location: resource_detail.php?id=" . $result);
                exit;
            } else {
                $errors[] = "Failed to create resource. " . $resourceObj->getError();
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
                <li class="breadcrumb-item active" aria-current="page"><?php echo $isEdit ? 'Edit Resource' : 'Add Resource'; ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $isEdit ? 'Edit Resource' : 'Add Resource'; ?></h1>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><?php echo $isEdit ? 'Edit Resource Details' : 'Resource Details'; ?></h6>
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
                
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($isEdit ? '?id=' . $resourceId : '')); ?>" method="post" id="resourceForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($resource['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="type" class="form-label">Resource Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="equipment" <?php echo $resource['type'] === 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                                <option value="personnel" <?php echo $resource['type'] === 'personnel' ? 'selected' : ''; ?>>Personnel</option>
                                <option value="facility" <?php echo $resource['type'] === 'facility' ? 'selected' : ''; ?>>Facility</option>
                                <option value="software" <?php echo $resource['type'] === 'software' ? 'selected' : ''; ?>>Software</option>
                                <option value="other" <?php echo $resource['type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($resource['description']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="<?php echo $resource['quantity']; ?>" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label for="unit" class="form-label">Unit</label>
                            <input type="text" class="form-control" id="unit" name="unit" value="<?php echo htmlspecialchars($resource['unit']); ?>" placeholder="e.g., pieces, hours, licenses">
                        </div>
                        <div class="col-md-4">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($resource['location']); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id" <?php echo $currentUser['role_id'] == 4 ? 'disabled' : ''; ?>>
                                <option value="">-- None --</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>" <?php echo $resource['department_id'] == $department['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($currentUser['role_id'] == 4): ?>
                                <input type="hidden" name="department_id" value="<?php echo $currentUser['department_id']; ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label for="acquisition_date" class="form-label">Acquisition Date</label>
                            <input type="date" class="form-control" id="acquisition_date" name="acquisition_date" value="<?php echo $resource['acquisition_date'] ? date('Y-m-d', strtotime($resource['acquisition_date'])) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="cost" class="form-label">Cost</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="cost" name="cost" value="<?php echo $resource['cost']; ?>" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_available" name="is_available" <?php echo $resource['availability'] === 'available' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_available">
                                    Available for allocation
                                </label>
                                <div class="form-text">
                                    Check this option if this resource is currently available to be allocated to projects.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update Resource' : 'Save Resource'; ?>
                            </button>
                            <a href="resources.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation
        const form = document.getElementById('resourceForm');
        if (form) {
            form.addEventListener('submit', function(event) {
                let isValid = true;
                
                // Validate name
                const nameInput = document.getElementById('name');
                if (!nameInput.value.trim()) {
                    isValid = false;
                    nameInput.classList.add('is-invalid');
                } else {
                    nameInput.classList.remove('is-invalid');
                }
                
                // Validate quantity
                const quantityInput = document.getElementById('quantity');
                if (!quantityInput.value || parseInt(quantityInput.value) <= 0) {
                    isValid = false;
                    quantityInput.classList.add('is-invalid');
                } else {
                    quantityInput.classList.remove('is-invalid');
                }
                
                if (!isValid) {
                    event.preventDefault();
                }
            });
        }
        
        // Type-specific fields
        const typeSelect = document.getElementById('type');
        if (typeSelect) {
            typeSelect.addEventListener('change', function() {
                const unitInput = document.getElementById('unit');
                const quantityInput = document.getElementById('quantity');
                
                // Set default unit based on type
                switch (this.value) {
                    case 'equipment':
                        unitInput.placeholder = 'e.g., pieces, sets';
                        break;
                    case 'personnel':
                        unitInput.placeholder = 'e.g., hours, days';
                        break;
                    case 'facility':
                        unitInput.placeholder = 'e.g., rooms, spaces';
                        break;
                    case 'software':
                        unitInput.placeholder = 'e.g., licenses, seats';
                        break;
                    default:
                        unitInput.placeholder = 'e.g., units';
                }
                
                // For personnel, default quantity to 1
                if (this.value === 'personnel' && !quantityInput.value) {
                    quantityInput.value = 1;
                }
            });
        }
    });
</script>

<?php
// Include footer
include 'includes/footer.php';
?>