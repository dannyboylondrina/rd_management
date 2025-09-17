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
require_once 'classes/Project.php';

// Initialize objects
$userObj = new User();
$departmentObj = new Department();
$projectObj = new Project();

// Get current user
$userId = $_SESSION['user_id'];
$userData = $userObj->getById($userId);

if (!$userData) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: index.php");
    exit;
}

// Get user's department
$departmentData = null;
if (!empty($userData['department_id'])) {
    $departmentData = $departmentObj->getById($userData['department_id']);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $updateData = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? ''
    ];
    
    // Validate form data
    $errors = [];
    
    if (empty($updateData['first_name'])) {
        $errors[] = "First name is required";
    }
    
    if (empty($updateData['last_name'])) {
        $errors[] = "Last name is required";
    }
    
    if (empty($updateData['email'])) {
        $errors[] = "Email is required";
    } elseif (!filter_var($updateData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // If no errors, update user
    if (empty($errors)) {
        if ($userObj->update($userId, $updateData)) {
            $_SESSION['success_message'] = "Profile updated successfully.";
            // Refresh user data
            $userData = $userObj->getById($userId);
        } else {
            $errors = $userObj->getErrors();
        }
    }
}

// Get user's projects
$userProjects = $projectObj->getByMember($userId);

// Set page title
$pageTitle = "My Profile";

// Include header
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $pageTitle; ?></h1>
    </div>
    
    <div class="row">
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Profile Information</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img class="img-profile rounded-circle" src="assets/img/default-avatar.png" width="150" height="150">
                        <h4 class="mt-3"><?php echo htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']); ?></h4>
                        <p class="text-muted">
                            <?php
                            // Display role
                            $roles = [
                                1 => 'Administrator',
                                2 => 'Project Manager',
                                3 => 'Researcher',
                                4 => 'Department Head',
                                5 => 'Faculty Member'
                            ];
                            echo $roles[$userData['role_id']] ?? 'User';
                            
                            // Display department
                            if ($departmentData) {
                                echo ' - ' . htmlspecialchars($departmentData['name']);
                            }
                            ?>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Username</h6>
                        <p><?php echo htmlspecialchars($userData['username']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Email</h6>
                        <p><?php echo htmlspecialchars($userData['email']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Member Since</h6>
                        <p><?php echo date('F d, Y', strtotime($userData['created_at'])); ?></p>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="change_password.php" class="btn btn-primary btn-block">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Edit Profile Form -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Edit Profile</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="profile.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($userData['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($userData['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($userData['username']); ?>" disabled>
                            <small class="form-text text-muted">Username cannot be changed.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role">Role</label>
                            <input type="text" class="form-control" id="role" value="<?php echo $roles[$userData['role_id']] ?? 'User'; ?>" disabled>
                            <small class="form-text text-muted">Role can only be changed by an administrator.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="department">Department</label>
                            <input type="text" class="form-control" id="department" value="<?php echo $departmentData ? htmlspecialchars($departmentData['name']) : 'None'; ?>" disabled>
                            <small class="form-text text-muted">Department can only be changed by an administrator.</small>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- My Projects -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">My Projects</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($userProjects)): ?>
                        <p class="text-center text-muted">You are not assigned to any projects.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($userProjects as $project): ?>
                                <a href="project_detail.php?id=<?php echo $project['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($project['title']); ?></h5>
                                        <small class="text-muted">
                                            <span class="project-status status-<?php echo $project['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <p class="mb-1">
                                        <?php 
                                        $desc = $project['description'] ?? '';
                                        echo htmlspecialchars(substr($desc, 0, 100)) . (strlen($desc) > 100 ? '...' : '');
                                        ?>
                                    </p>
                                    <small class="text-muted">
                                        <?php if (!empty($project['start_date'])): ?>
                                            Start: <?php echo date('M d, Y', strtotime($project['start_date'])); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($project['end_date'])): ?>
                                            | End: <?php echo date('M d, Y', strtotime($project['end_date'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>