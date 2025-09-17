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
require_once 'classes/Department.php';
require_once 'classes/User.php';

// Initialize objects
$departmentObj = new Department();
$userObj = new User();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);

// Check if user has permission to access this page
if ($currentUser['role_id'] != 1) { // Only administrators can manage departments
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: index.php");
    exit;
}

// Initialize variables
$departmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $departmentId > 0;
$departmentData = [
    'name' => '',
    'description' => '',
    'location' => '',
    'contact_email' => '',
    'contact_phone' => ''
];
$errors = [];

// If editing, get department data
if ($isEdit) {
    $departmentData = $departmentObj->getById($departmentId);
    if (!$departmentData) {
        $_SESSION['error_message'] = "Department not found.";
        header("Location: departments.php");
        exit;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $departmentData = [
        'name' => $_POST['name'] ?? '',
        'description' => $_POST['description'] ?? '',
        'location' => $_POST['location'] ?? '',
        'contact_email' => $_POST['contact_email'] ?? '',
        'contact_phone' => $_POST['contact_phone'] ?? ''
    ];
    
    // If no errors, save department
    if (empty($errors)) {
        if ($isEdit) {
            $result = $departmentObj->update($departmentId, $departmentData);
            $successMessage = "Department updated successfully.";
        } else {
            $result = $departmentObj->create($departmentData);
            $successMessage = "Department created successfully.";
        }
        
        if ($result) {
            $_SESSION['success_message'] = $successMessage;
            header("Location: departments.php");
            exit;
        } else {
            $errors = $departmentObj->getErrors();
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
                <li class="breadcrumb-item"><a href="departments.php">Departments</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $isEdit ? 'Edit Department' : 'Add Department'; ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $isEdit ? 'Edit Department' : 'Add Department'; ?></h1>
    </div>
    <div class="col-md-4 text-md-end">
        <a href="departments.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Departments
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Department Information</h6>
            </div>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($isEdit ? '?id=' . $departmentId : '')); ?>" method="post" id="departmentForm">
                    <div class="mb-3">
                        <label for="name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($departmentData['name']); ?>" required>
                        <div class="form-text">Department name must be unique.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($departmentData['description']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($departmentData['location']); ?>">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="contact_email" class="form-label">Contact Email</label>
                            <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($departmentData['contact_email']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="contact_phone" class="form-label">Contact Phone</label>
                            <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($departmentData['contact_phone']); ?>">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="departments.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update Department' : 'Create Department'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Department Guidelines</h6>
            </div>
            <div class="card-body">
                <p>Departments are organizational units that group users, projects, and resources together.</p>
                
                <h6 class="font-weight-bold">Department Features:</h6>
                <ul>
                    <li>Group users by department</li>
                    <li>Organize projects by department</li>
                    <li>Manage department-specific resources</li>
                    <li>Generate department-level reports</li>
                </ul>
                
                <h6 class="font-weight-bold">Department Roles:</h6>
                <ul>
                    <li><strong>Department Head:</strong> Manages department resources and oversees department projects</li>
                    <li><strong>Faculty Members:</strong> Participate in department projects and access department resources</li>
                    <li><strong>Researchers:</strong> Conduct research within department projects</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> Each user can be assigned to one department. Department Heads have special permissions for their assigned department.
                </div>
            </div>
        </div>
        
        <?php if ($isEdit): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Department Statistics</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get department statistics
                    $memberCount = count($departmentObj->getMembers($departmentId));
                    $projectCount = count($departmentObj->getProjects($departmentId));
                    $resourceCount = count($departmentObj->getResources($departmentId));
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="h5 mb-0 font-weight-bold text-primary"><?php echo $memberCount; ?></div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Members</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="h5 mb-0 font-weight-bold text-success"><?php echo $projectCount; ?></div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Projects</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="h5 mb-0 font-weight-bold text-info"><?php echo $resourceCount; ?></div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Resources</div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="users.php?department_id=<?php echo $departmentId; ?>" class="btn btn-sm btn-outline-primary w-100 mb-2">
                            <i class="fas fa-users"></i> View Department Members
                        </a>
                        <a href="projects.php?department_id=<?php echo $departmentId; ?>" class="btn btn-sm btn-outline-success w-100 mb-2">
                            <i class="fas fa-project-diagram"></i> View Department Projects
                        </a>
                        <a href="resources.php?department_id=<?php echo $departmentId; ?>" class="btn btn-sm btn-outline-info w-100">
                            <i class="fas fa-cubes"></i> View Department Resources
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const departmentForm = document.getElementById('departmentForm');
    departmentForm.addEventListener('submit', function(event) {
        const name = document.getElementById('name').value.trim();
        
        if (!name) {
            event.preventDefault();
            alert('Department name is required.');
        }
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>