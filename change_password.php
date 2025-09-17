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

// Initialize user object
$userObj = new User();

// Get current user
$userId = $_SESSION['user_id'];
$userData = $userObj->getById($userId);

if (!$userData) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: index.php");
    exit;
}

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate form data
    if (empty($currentPassword)) {
        $errors[] = "Current password is required";
    }
    
    if (empty($newPassword)) {
        $errors[] = "New password is required";
    } elseif (strlen($newPassword) < 6) {
        $errors[] = "New password must be at least 6 characters";
    }
    
    if (empty($confirmPassword)) {
        $errors[] = "Confirm password is required";
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = "New password and confirm password do not match";
    }
    
    // If no errors, change password
    if (empty($errors)) {
        if ($userObj->changePassword($userId, $currentPassword, $newPassword)) {
            $success = true;
            $_SESSION['success_message'] = "Password changed successfully.";
        } else {
            $errors = $userObj->getErrors();
        }
    }
}

// Set page title
$pageTitle = "Change Password";

// Include header
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $pageTitle; ?></h1>
        <a href="profile.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Change Your Password</h6>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Password changed successfully. You can now use your new password to log in.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="change_password.php">
                        <div class="mb-3">
                            <label for="current_password">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="form-text text-muted">
                                Password must be at least 6 characters long.
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                            <a href="profile.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Password Guidelines</h6>
                </div>
                <div class="card-body">
                    <p>For a strong password, consider the following guidelines:</p>
                    <ul>
                        <li>Use at least 8 characters</li>
                        <li>Include a mix of uppercase and lowercase letters</li>
                        <li>Include at least one number</li>
                        <li>Include at least one special character (e.g., !@#$%^&*)</li>
                        <li>Avoid using easily guessable information (e.g., your name, birthdate)</li>
                        <li>Don't reuse passwords from other websites</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>