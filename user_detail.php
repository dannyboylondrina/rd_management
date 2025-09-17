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
require_once 'classes/Document.php';

// Initialize objects
$userObj = new User();
$departmentObj = new Department();
$projectObj = new Project();
$documentObj = new Document();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);

// Check if user has permission to access this page
if ($currentUser['role_id'] > 2) { // Only Admin (1) and Project Manager (2) can access
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: index.php");
    exit;
}

// Get user ID from URL
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    $_SESSION['error_message'] = "Invalid user ID.";
    header("Location: users.php");
    exit;
}

// Get user data
$userData = $userObj->getById($userId);
if (!$userData) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: users.php");
    exit;
}

// Get user's department
$departmentData = null;
if (!empty($userData['department_id'])) {
    $departmentData = $departmentObj->getById($userData['department_id']);
}

// Get user's projects
$userProjects = $projectObj->getByMember($userId);

// Get user's documents
$userDocuments = $documentObj->getByUploader($userId);

// Define roles
$roles = [
    1 => 'Administrator',
    2 => 'Project Manager',
    3 => 'Researcher',
    4 => 'Department Head',
    5 => 'Faculty Member'
];

// Set page title
$pageTitle = "User Details: " . $userData['username'];

// Include header
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($userData['username']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="h3 mb-0 text-gray-800">User Details</h1>
    </div>
    <div class="col-md-4 text-md-end">
        <a href="users.php" class="btn btn-secondary me-2">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
        <?php if ($currentUser['role_id'] == 1): // Only admin can edit users ?>
            <a href="user_form.php?id=<?php echo $userId; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit User
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Profile Information</h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="avatar-circle mx-auto mb-3">
                        <span class="avatar-initials"><?php echo strtoupper(substr($userData['first_name'], 0, 1) . substr($userData['last_name'], 0, 1)); ?></span>
                    </div>
                    <h4><?php echo htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']); ?></h4>
                    <p class="text-muted mb-0">
                        <?php echo $roles[$userData['role_id']] ?? 'Unknown Role'; ?>
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
                    <h6 class="font-weight-bold">Department</h6>
                    <p>
                        <?php 
                            if ($departmentData) {
                                echo htmlspecialchars($departmentData['name']);
                            } else {
                                echo '<span class="text-muted">None</span>';
                            }
                        ?>
                    </p>
                </div>
                
                <div class="mb-3">
                    <h6 class="font-weight-bold">Account Status</h6>
                    <p>
                        <?php 
                            if ($userData['is_active'] == '1') {
                                echo '<span class="badge bg-success">Active</span>';
                            } else {
                                echo '<span class="badge bg-danger">Inactive</span>';
                            }
                        ?>
                    </p>
                </div>
                
                <div>
                    <h6 class="font-weight-bold">Member Since</h6>
                    <p><?php echo date('F j, Y', strtotime($userData['created_at'])); ?></p>
                </div>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Role Information</h6>
            </div>
            <div class="card-body">
                <?php 
                    $roleDescriptions = [
                        1 => [
                            'description' => 'Full access to all system features, including user management, department management, and system settings.',
                            'permissions' => [
                                'Manage users and roles',
                                'Manage departments',
                                'Access all projects, documents, and resources',
                                'Generate all reports',
                                'Configure system settings'
                            ]
                        ],
                        2 => [
                            'description' => 'Can create and manage projects, assign team members, allocate resources, and generate reports.',
                            'permissions' => [
                                'Create and manage projects',
                                'Assign team members to projects',
                                'Allocate resources to projects',
                                'Upload and manage documents',
                                'Generate project reports'
                            ]
                        ],
                        3 => [
                            'description' => 'Can participate in projects, upload documents, and use allocated resources.',
                            'permissions' => [
                                'View assigned projects',
                                'Upload and manage documents',
                                'Use allocated resources',
                                'Submit documents to IRJSTEM journal'
                            ]
                        ],
                        4 => [
                            'description' => 'Can manage department resources, view department projects, and generate department reports.',
                            'permissions' => [
                                'View all department projects',
                                'Manage department resources',
                                'Generate department reports',
                                'Approve resource allocations'
                            ]
                        ],
                        5 => [
                            'description' => 'Can view and download research papers and participate in assigned projects.',
                            'permissions' => [
                                'View and download research papers',
                                'Participate in assigned projects',
                                'Submit evaluations and feedback'
                            ]
                        ]
                    ];
                    
                    $roleInfo = $roleDescriptions[$userData['role_id']] ?? null;
                    
                    if ($roleInfo):
                ?>
                    <h5><?php echo $roles[$userData['role_id']] ?? 'Unknown Role'; ?></h5>
                    <p><?php echo $roleInfo['description']; ?></p>
                    
                    <h6 class="font-weight-bold mt-3">Permissions</h6>
                    <ul>
                        <?php foreach ($roleInfo['permissions'] as $permission): ?>
                            <li><?php echo $permission; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No role information available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Projects</h6>
            </div>
            <div class="card-body">
                <?php if (empty($userProjects)): ?>
                    <p class="text-muted">This user is not a member of any projects.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userProjects as $project): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td>
                                            <?php 
                                                // Get user's role in this project
                                                $projectMembers = $projectObj->getMembers($project['id']);
                                                $userRole = 'Member';
                                                foreach ($projectMembers as $member) {
                                                    if ($member['id'] == $userId) {
                                                        $userRole = $member['role'];
                                                        break;
                                                    }
                                                }
                                                echo htmlspecialchars($userRole);
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                if (!empty($project['department_id'])) {
                                                    $dept = $departmentObj->getById($project['department_id']);
                                                    echo $dept ? htmlspecialchars($dept['name']) : 'Unknown';
                                                } else {
                                                    echo 'None';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $statusLabels = [
                                                    'planning' => '<span class="badge bg-info">Planning</span>',
                                                    'in_progress' => '<span class="badge bg-primary">In Progress</span>',
                                                    'on_hold' => '<span class="badge bg-warning">On Hold</span>',
                                                    'completed' => '<span class="badge bg-success">Completed</span>',
                                                    'cancelled' => '<span class="badge bg-danger">Cancelled</span>'
                                                ];
                                                echo $statusLabels[$project['status']] ?? $project['status'];
                                            ?>
                                        </td>
                                        <td>
                                            <a href="project_detail.php?id=<?php echo $project['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Documents</h6>
            </div>
            <div class="card-body">
                <?php if (empty($userDocuments)): ?>
                    <p class="text-muted">This user has not uploaded any documents.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Project</th>
                                    <th>Upload Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userDocuments as $document): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($document['title']); ?></td>
                                        <td>
                                            <?php 
                                                $typeLabels = [
                                                    'research_paper' => '<span class="badge bg-primary">Research Paper</span>',
                                                    'faculty_evaluation' => '<span class="badge bg-info">Faculty Evaluation</span>',
                                                    'patent' => '<span class="badge bg-success">Patent</span>',
                                                    'report' => '<span class="badge bg-warning">Report</span>',
                                                    'other' => '<span class="badge bg-secondary">Other</span>'
                                                ];
                                                echo $typeLabels[$document['type']] ?? $document['type'];
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                if (!empty($document['project_id'])) {
                                                    $project = $projectObj->getById($document['project_id']);
                                                    echo $project ? htmlspecialchars($project['title']) : 'Unknown';
                                                } else {
                                                    echo 'None';
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($document['upload_date'])); ?></td>
                                        <td>
                                            <a href="document_detail.php?id=<?php echo $document['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Activity History</h6>
            </div>
            <div class="card-body">
                <p class="text-muted">Activity history is not available in this version.</p>
                <!-- This would typically show a log of user actions, but we'll leave it as a placeholder for now -->
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: #007bff;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.avatar-initials {
    color: white;
    font-size: 36px;
    font-weight: bold;
}
</style>

<?php include 'includes/footer.php'; ?>