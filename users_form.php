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
require_once 'classes/Role.php';
require_once 'classes/Department.php';

// Initialize objects
$userObj = new User();
$roleObj = new Role();
$departmentObj = new Department();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);

// Check if user has permission to access this page
if ($currentUser['role_id'] != 1) { // Only administrators can manage users
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: index.php");
    exit;
}

// Initialize variables
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $userId > 0;
$userData = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'role_id' => '',
    'department_id' => '',
    'is_active' => 1
];
$errors = [];

// If editing, get user data
if ($isEdit) {
    $userData = $userObj->getById($userId);
    if (!$userData) {
        $_SESSION['error_message'] = "User not found.";
        header("Location: users.php");
        exit;
    }
    
    // Remove password from data (will be set only if provided in the form)
    unset($userData['password']);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $userData = [
        'username' => $_POST['username'] ?? '',
        'email' => $_POST['email'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'role_id' => $_POST['role_id'] ?? '',
        'department_id' => $_POST['department_id'] ?? null,
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    // Handle password
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate password
    if (!$isEdit || !empty($password)) {
        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        } elseif ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match.";
        } else {
            // Hash password
            $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
    }
    
    // If no errors, save user
    if (empty($errors)) {
        if ($isEdit) {
            $result = $userObj->update($userId, $userData);
            $successMessage = "User updated successfully.";
        } else {
            $result = $userObj->create($userData);
            $successMessage = "User created successfully.";
        }
        
        if ($result) {
            $_SESSION['success_message'] = $successMessage;
            header("Location: users.php");
            exit;
        } else {
            $errors = $userObj->getErrors();
        }
    }
}

// Get roles and departments for dropdowns
$roles = $roleObj->getAll();
$departments = $departmentObj->getAll();

// Include header
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $isEdit ? 'Edit User' : 'Add User'; ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $isEdit ? 'Edit User' : 'Add User'; ?></h1>
    </div>
    <div class="col-md-4 text-md-end">
        <a href="users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Users
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
                <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
            </div>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($isEdit ? '?id=' . $userId : '')); ?>" method="post" id="userForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($userData['username']); ?>" required>
                            <div class="form-text">Username must be unique and at least 3 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($userData['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($userData['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label"><?php echo $isEdit ? 'New Password' : 'Password'; ?> <?php echo $isEdit ? '' : '<span class="text-danger">*</span>'; ?></label>
                            <input type="password" class="form-control" id="password" name="password" <?php echo $isEdit ? '' : 'required'; ?>>
                            <div class="form-text">Password must be at least 6 characters. <?php echo $isEdit ? 'Leave blank to keep current password.' : ''; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password <?php echo $isEdit ? '' : '<span class="text-danger">*</span>'; ?></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" <?php echo $isEdit ? '' : 'required'; ?>>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role_id" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php echo $userData['role_id'] == $role['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">None</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>" <?php echo $userData['department_id'] == $department['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?php echo $userData['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Active Account</label>
                        <div class="form-text">Inactive accounts cannot log in to the system.</div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="users.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update User' : 'Create User'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Role Information</h6>
            </div>
            <div class="card-body">
                <div class="role-info" id="role-info-1" style="display: none;">
                    <h5>Administrator</h5>
                    <p>Full access to all system features, including user management, department management, and system settings.</p>
                    <ul>
                        <li>Manage users and roles</li>
                        <li>Manage departments</li>
                        <li>Access all projects, documents, and resources</li>
                        <li>Generate all reports</li>
                        <li>Configure system settings</li>
                    </ul>
                </div>
                
                <div class="role-info" id="role-info-2" style="display: none;">
                    <h5>Project Manager</h5>
                    <p>Can create and manage projects, assign team members, allocate resources, and generate reports.</p>
                    <ul>
                        <li>Create and manage projects</li>
                        <li>Assign team members to projects</li>
                        <li>Allocate resources to projects</li>
                        <li>Upload and manage documents</li>
                        <li>Generate project reports</li>
                    </ul>
                </div>
                
                <div class="role-info" id="role-info-3" style="display: none;">
                    <h5>Researcher</h5>
                    <p>Can participate in projects, upload documents, and use allocated resources.</p>
                    <ul>
                        <li>View assigned projects</li>
                        <li>Upload and manage documents</li>
                        <li>Use allocated resources</li>
                        <li>Submit documents to IRJSTEM journal</li>
                    </ul>
                </div>
                
                <div class="role-info" id="role-info-4" style="display: none;">
                    <h5>Department Head</h5>
                    <p>Can manage department resources, view department projects, and generate department reports.</p>
                    <ul>
                        <li>View all department projects</li>
                        <li>Manage department resources</li>
                        <li>Generate department reports</li>
                        <li>Approve resource allocations</li>
                    </ul>
                </div>
                
                <div class="role-info" id="role-info-5" style="display: none;">
                    <h5>Faculty Member</h5>
                    <p>Can view and download research papers and participate in assigned projects.</p>
                    <ul>
                        <li>View and download research papers</li>
                        <li>Participate in assigned projects</li>
                        <li>Submit evaluations and feedback</li>
                    </ul>
                </div>
                
                <div id="no-role-selected" class="text-center text-muted">
                    <p>Select a role to view its permissions and responsibilities.</p>
                </div>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Password Guidelines</h6>
            </div>
            <div class="card-body">
                <p>For security reasons, passwords should:</p>
                <ul>
                    <li>Be at least 6 characters long</li>
                    <li>Include a mix of letters, numbers, and special characters</li>
                    <li>Not be based on personal information</li>
                    <li>Not be reused from other systems</li>
                </ul>
                <p class="mb-0 text-muted"><small>Note: Passwords are stored securely using one-way encryption.</small></p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show role information when role is selected
    const roleSelect = document.getElementById('role_id');
    const noRoleSelected = document.getElementById('no-role-selected');
    
    function updateRoleInfo() {
        // Hide all role info divs
        document.querySelectorAll('.role-info').forEach(div => {
            div.style.display = 'none';
        });
        
        const selectedRole = roleSelect.value;
        if (selectedRole) {
            const roleInfoDiv = document.getElementById('role-info-' + selectedRole);
            if (roleInfoDiv) {
                roleInfoDiv.style.display = 'block';
                noRoleSelected.style.display = 'none';
            } else {
                noRoleSelected.style.display = 'block';
            }
        } else {
            noRoleSelected.style.display = 'block';
        }
    }
    
    roleSelect.addEventListener('change', updateRoleInfo);
    
    // Initialize role info display
    updateRoleInfo();
    
    // Form validation
    const userForm = document.getElementById('userForm');
    userForm.addEventListener('submit', function(event) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        // Check if passwords match when password is provided
        if (password && password !== confirmPassword) {
            event.preventDefault();
            alert('Passwords do not match.');
        }
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>