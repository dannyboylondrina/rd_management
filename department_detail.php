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
require_once 'classes/Resource.php';
require_once 'classes/Role.php';

// Initialize objects
$userObj = new User();
$departmentObj = new Department();
$projectObj = new Project();
$resourceObj = new Resource();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);
$userRole = $currentUser['role_id'];

// Check if department ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Department ID is required.";
    header("Location: departments.php");
    exit;
}

$departmentId = (int)$_GET['id'];
$department = $departmentObj->getById($departmentId);

// Check if department exists
if (!$department) {
    $_SESSION['error_message'] = "Department not found.";
    header("Location: departments.php");
    exit;
}

// Get department members
$members = $departmentObj->getMembers($departmentId);

// Get department projects
$projects = $departmentObj->getProjects($departmentId);

// Get department resources
$resources = $departmentObj->getResources($departmentId);

// Set page title
$pageTitle = "Department Details: " . $department['name'];

// Include header
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="departments.php">Departments</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($department['name']); ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($department['name']); ?></h1>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="departments.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left"></i> Back to Departments
            </a>
            <?php if ($userRole == 1): // Only admin can edit departments ?>
                <a href="department_form.php?id=<?php echo $departmentId; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Department
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Department Information -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Department Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="font-weight-bold">Description</h5>
                        <p><?php echo nl2br(htmlspecialchars($department['description'] ?? 'No description available.')); ?></p>
                    </div>
                    
                    <?php if (isset($department['location']) && !empty($department['location'])): ?>
                    <div class="mb-3">
                        <h5 class="font-weight-bold">Location</h5>
                        <p><?php echo htmlspecialchars($department['location']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($department['contact_email']) && !empty($department['contact_email'])): ?>
                    <div class="mb-3">
                        <h5 class="font-weight-bold">Contact Email</h5>
                        <p><a href="mailto:<?php echo htmlspecialchars($department['contact_email']); ?>"><?php echo htmlspecialchars($department['contact_email']); ?></a></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($department['contact_phone']) && !empty($department['contact_phone'])): ?>
                    <div class="mb-3">
                        <h5 class="font-weight-bold">Contact Phone</h5>
                        <p><?php echo htmlspecialchars($department['contact_phone']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <h5 class="font-weight-bold">Created</h5>
                        <p><?php echo date('M d, Y', strtotime($department['created_at'])); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h5 class="font-weight-bold">Last Updated</h5>
                        <p><?php echo date('M d, Y', strtotime($department['updated_at'])); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Department Statistics -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Department Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="h5 mb-0 font-weight-bold text-primary"><?php echo count($members); ?></div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Members</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="h5 mb-0 font-weight-bold text-success"><?php echo count($projects); ?></div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Projects</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="h5 mb-0 font-weight-bold text-info"><?php echo count($resources); ?></div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Resources</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Department Members -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Department Members</h6>
                    <?php if ($userRole <= 2): // Admin or Project Manager ?>
                        <a href="users.php?department_id=<?php echo $departmentId; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-users"></i> Manage Members
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($members)): ?>
                        <div class="alert alert-info">
                            No members in this department yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Email</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                            <td>
                                                <?php 
                                                $roleObj = new Role();
                                                $role = $roleObj->getById($member['role_id']);
                                                echo htmlspecialchars($role['name'] ?? 'Unknown');
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                                            <td>
                                                <a href="user_detail.php?id=<?php echo $member['id']; ?>" class="btn btn-info btn-sm">
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
            
            <!-- Department Projects -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Department Projects</h6>
                    <?php if ($userRole <= 2): // Admin or Project Manager ?>
                        <a href="projects.php?department_id=<?php echo $departmentId; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-project-diagram"></i> View All Projects
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($projects)): ?>
                        <div class="alert alert-info">
                            No projects in this department yet.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach (array_slice($projects, 0, 4) as $project): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header py-3">
                                            <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($project['title']); ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <p>
                                                <?php 
                                                $desc = $project['description'] ?? '';
                                                echo htmlspecialchars(substr($desc, 0, 100)) . (strlen($desc) > 100 ? '...' : '');
                                                ?>
                                            </p>
                                            <div class="mb-2">
                                                <span class="badge bg-<?php 
                                                    switch($project['status']) {
                                                        case 'planning': echo 'secondary'; break;
                                                        case 'in_progress': echo 'primary'; break;
                                                        case 'completed': echo 'success'; break;
                                                        case 'on_hold': echo 'warning'; break;
                                                        case 'cancelled': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $project['status'])); ?>
                                                </span>
                                            </div>
                                            <div class="text-end">
                                                <a href="project_detail.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($projects) > 4): ?>
                            <div class="text-center mt-3">
                                <a href="projects.php?department_id=<?php echo $departmentId; ?>" class="btn btn-outline-primary">
                                    View All <?php echo count($projects); ?> Projects
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Department Resources -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Department Resources</h6>
                    <?php if ($userRole <= 2): // Admin or Project Manager ?>
                        <a href="resources.php?department_id=<?php echo $departmentId; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-cubes"></i> View All Resources
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($resources)): ?>
                        <div class="alert alert-info">
                            No resources in this department yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Availability</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($resources, 0, 5) as $resource): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($resource['name']); ?></td>
                                            <td><?php echo ucfirst($resource['type']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($resource['availability']) {
                                                        case 'available': echo 'success'; break;
                                                        case 'partially_available': echo 'warning'; break;
                                                        case 'unavailable': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $resource['availability'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="resource_detail.php?id=<?php echo $resource['id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($resources) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="resources.php?department_id=<?php echo $departmentId; ?>" class="btn btn-outline-primary">
                                    View All <?php echo count($resources); ?> Resources
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>