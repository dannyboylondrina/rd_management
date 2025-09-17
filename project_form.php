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
require_once 'classes/Project.php';
require_once 'classes/User.php';
require_once 'classes/Department.php';
require_once 'classes/Resource.php';
require_once 'classes/Notification.php';

// Initialize objects
$projectObj = new Project();
$userObj = new User();
$departmentObj = new Department();
$resourceObj = new Resource();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);

// Check if user has permission to create/edit projects
$canManageProjects = false;

// Admins, Project Managers, and Department Heads can create/edit projects
if ($currentUser['role_id'] <= 4) {
    $canManageProjects = true;
}

if (!$canManageProjects) {
    $_SESSION['error_message'] = "You don't have permission to create or edit projects.";
    header("Location: projects.php");
    exit;
}

// Check if editing existing project
$isEditing = false;
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project = null;

if ($projectId > 0) {
    $isEditing = true;
    $project = $projectObj->getById($projectId);
    
    // Check if project exists
    if (!$project) {
        $_SESSION['error_message'] = "Project not found.";
        header("Location: projects.php");
        exit;
    }
    
    // Check if user has permission to edit this project
    $canEdit = false;
    
    // Admins and Project Managers can edit any project
    if ($currentUser['role_id'] <= 2) {
        $canEdit = true;
    } 
    // Department Heads can edit projects in their department
    elseif ($currentUser['role_id'] == 4 && $project['department_id'] == $currentUser['department_id']) {
        $canEdit = true;
    }
    // Project creators can edit their own projects
    elseif ($project['created_by'] == $currentUser['id']) {
        $canEdit = true;
    }
    
    if (!$canEdit) {
        $_SESSION['error_message'] = "You don't have permission to edit this project.";
        header("Location: projects.php");
        exit;
    }
}

// Initialize form variables
$title = $isEditing ? $project['title'] : '';
$description = $isEditing ? $project['description'] : '';
$startDate = $isEditing && !empty($project['start_date']) ? $project['start_date'] : '';
$endDate = $isEditing && !empty($project['end_date']) ? $project['end_date'] : '';
$status = $isEditing ? $project['status'] : 'planning';
$budget = $isEditing ? $project['budget'] : '';
$departmentId = $isEditing ? $project['department_id'] : ($currentUser['role_id'] == 4 ? $currentUser['department_id'] : '');

// Get project members if editing
$projectMembers = [];
if ($isEditing) {
    $projectMembers = $projectObj->getMembers($projectId);
}

// Get available resources
$availableResources = $resourceObj->getAvailable();

// Get departments
$departments = $departmentObj->getAll();

// Get users for member selection
$users = $userObj->getAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $status = $_POST['status'] ?? 'planning';
    $budget = $_POST['budget'] ?? '';
    $departmentId = $_POST['department_id'] ?? '';
    
    // Validate form data
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Project title is required.";
    }
    
    if (!empty($startDate) && !empty($endDate) && strtotime($endDate) < strtotime($startDate)) {
        $errors[] = "End date cannot be before start date.";
    }
    
    if (!empty($budget) && !is_numeric($budget)) {
        $errors[] = "Budget must be a number.";
    }
    
    // If no errors, save project
    if (empty($errors)) {
        // Prepare project data
        $projectData = [
            'title' => $title,
            'description' => $description,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
            'budget' => $budget,
            'department_id' => $departmentId
        ];
        
        if (!$isEditing) {
            // Add created_by for new projects
            $projectData['created_by'] = $currentUser['id'];
        }
        
        // Save project
        if ($isEditing) {
            $result = $projectObj->update($projectId, $projectData);
        } else {
            $result = $projectObj->create($projectData);
            $projectId = $result; // Get new project ID
        }
        
        if ($result) {
            // Process project members
            if (isset($_POST['member_user_id']) && is_array($_POST['member_user_id'])) {
                // Remove existing members if editing
                if ($isEditing) {
                    foreach ($projectMembers as $member) {
                        $projectObj->removeMember($projectId, $member['id']);
                    }
                }
                
                // Add members
                foreach ($_POST['member_user_id'] as $index => $userId) {
                    if (!empty($userId)) {
                        $role = $_POST['member_role'][$index] ?? '';
                        $responsibilities = $_POST['member_responsibilities'][$index] ?? '';
                        $projectObj->addMember($projectId, $userId, $role, $responsibilities);
                    }
                }
            }
            
            // Process resources
            if (isset($_POST['resource_id']) && is_array($_POST['resource_id'])) {
                foreach ($_POST['resource_id'] as $index => $resourceId) {
                    if (!empty($resourceId)) {
                        $quantity = $_POST['resource_quantity'][$index] ?? 1;
                        $allocationDate = $_POST['resource_allocation_date'][$index] ?? date('Y-m-d');
                        $returnDate = $_POST['resource_return_date'][$index] ?? '';
                        
                        $projectObj->allocateResource($projectId, $resourceId, $quantity, $allocationDate, $returnDate);
                    }
                }
            }
            
            $_SESSION['success_message'] = $isEditing ? "Project updated successfully." : "Project created successfully.";
            // Notify creator and members on create
            if (!$isEditing) {
                $notification = new Notification();
                // Notify creator
                $notification->createNotification($currentUser['id'], 'Project created', 'Your project "' . $title . '" has been created.', 'project', (int)$projectId, 'project');
                // Notify members if any
                if (isset($_POST['member_user_id']) && is_array($_POST['member_user_id'])) {
                    $memberIds = array_filter(array_map('intval', $_POST['member_user_id']));
                    $notification->createNotificationForMultipleUsers($memberIds, 'Added to project', 'You have been added to the project "' . $title . '".', 'project', (int)$projectId, 'project');
                }
            }
            header("Location: project_detail.php?id=" . $projectId);
            exit;
        } else {
            $errors = $projectObj->getErrors();
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
                <li class="breadcrumb-item"><a href="projects.php">Projects</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $isEditing ? 'Edit Project' : 'Create Project'; ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><?php echo $isEditing ? 'Edit Project' : 'Create New Project'; ?></h6>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($isEditing ? '?id=' . $projectId : '')); ?>">
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h5>Project Details</h5>
                            <hr>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="title" class="form-label required-field">Title</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="planning" <?php echo $status === 'planning' ? 'selected' : ''; ?>>Planning</option>
                                <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="on_hold" <?php echo $status === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="budget" class="form-label">Budget</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="budget" name="budget" value="<?php echo htmlspecialchars($budget); ?>" step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id" <?php echo $currentUser['role_id'] == 4 ? 'disabled' : ''; ?>>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>" <?php echo $departmentId == $department['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($currentUser['role_id'] == 4): ?>
                                <input type="hidden" name="department_id" value="<?php echo $currentUser['department_id']; ?>">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h5>Project Members</h5>
                            <hr>
                        </div>
                        
                        <div class="col-md-12">
                            <div id="members-container">
                                <?php if ($isEditing && !empty($projectMembers)): ?>
                                    <?php foreach ($projectMembers as $index => $member): ?>
                                        <div class="row member-row mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label">User</label>
                                                <select class="form-select" name="member_user_id[]">
                                                    <option value="">Select User</option>
                                                    <?php foreach ($users as $user): ?>
                                                        <option value="<?php echo $user['id']; ?>" <?php echo $member['id'] == $user['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Role</label>
                                                <input type="text" class="form-control" name="member_role[]" value="<?php echo htmlspecialchars($member['role'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Responsibilities</label>
                                                <input type="text" class="form-control" name="member_responsibilities[]" value="<?php echo htmlspecialchars($member['responsibilities'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-1 d-flex align-items-end">
                                                <button type="button" class="btn btn-danger remove-member-btn">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="row member-row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">User</label>
                                            <select class="form-select" name="member_user_id[]">
                                                <option value="">Select User</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['id']; ?>">
                                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Role</label>
                                            <input type="text" class="form-control" name="member_role[]">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Responsibilities</label>
                                            <input type="text" class="form-control" name="member_responsibilities[]">
                                        </div>
                                        <div class="col-md-1 d-flex align-items-end">
                                            <button type="button" class="btn btn-danger remove-member-btn">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <button type="button" id="add-member-btn" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Member
                            </button>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h5>Resources</h5>
                            <hr>
                        </div>
                        
                        <div class="col-md-12">
                            <div id="resources-container">
                                <div class="row resource-row mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Resource</label>
                                        <select class="form-select" name="resource_id[]">
                                            <option value="">Select Resource</option>
                                            <?php foreach ($availableResources as $resource): ?>
                                                <option value="<?php echo $resource['id']; ?>">
                                                    <?php echo htmlspecialchars($resource['name'] . ' (' . ucfirst($resource['type']) . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Quantity</label>
                                        <input type="number" class="form-control" name="resource_quantity[]" min="1" value="1">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Allocation Date</label>
                                        <input type="date" class="form-control" name="resource_allocation_date[]" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Return Date</label>
                                        <input type="date" class="form-control" name="resource_return_date[]">
                                    </div>
                                    <div class="col-md-1 d-flex align-items-end">
                                        <button type="button" class="btn btn-danger remove-resource-btn">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" id="add-resource-btn" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Resource
                            </button>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $isEditing ? 'Update Project' : 'Create Project'; ?>
                            </button>
                            <a href="projects.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Member Template -->
<template id="member-template">
    <div class="row member-row mb-3">
        <div class="col-md-4">
            <label class="form-label">User</label>
            <select class="form-select" name="member_user_id[]">
                <option value="">Select User</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>">
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Role</label>
            <input type="text" class="form-control" name="member_role[]">
        </div>
        <div class="col-md-4">
            <label class="form-label">Responsibilities</label>
            <input type="text" class="form-control" name="member_responsibilities[]">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-danger remove-member-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</template>

<!-- Resource Template -->
<template id="resource-template">
    <div class="row resource-row mb-3">
        <div class="col-md-3">
            <label class="form-label">Resource</label>
            <select class="form-select" name="resource_id[]">
                <option value="">Select Resource</option>
                <?php foreach ($availableResources as $resource): ?>
                    <option value="<?php echo $resource['id']; ?>">
                        <?php echo htmlspecialchars($resource['name'] . ' (' . ucfirst($resource['type']) . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Quantity</label>
            <input type="number" class="form-control" name="resource_quantity[]" min="1" value="1">
        </div>
        <div class="col-md-3">
            <label class="form-label">Allocation Date</label>
            <input type="date" class="form-control" name="resource_allocation_date[]" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Return Date</label>
            <input type="date" class="form-control" name="resource_return_date[]">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-danger remove-resource-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Setup dynamic form elements
    setupDynamicForms();
    
    // Add event listeners for remove buttons
    document.querySelectorAll('.remove-member-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.member-row').remove();
        });
    });
    
    document.querySelectorAll('.remove-resource-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.resource-row').remove();
        });
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>