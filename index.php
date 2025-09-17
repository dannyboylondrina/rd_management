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
require_once 'classes/Project.php';
require_once 'classes/Department.php';
require_once 'classes/Document.php';
require_once 'classes/Resource.php';
require_once 'classes/Notification.php';
require_once 'classes/Patent.php';

// Get current user
$userObj = new User();
$currentUser = $userObj->getById($_SESSION['user_id']);

// Get user's department
$departmentObj = new Department();
$userDepartment = null;
if ($currentUser && !empty($currentUser['department_id'])) {
    $userDepartment = $departmentObj->getById($currentUser['department_id']);
}

// Get dashboard data based on user role
$projectObj = new Project();
$documentObj = new Document();
$resourceObj = new Resource();
$patentObj = new Patent();
$notificationObj = new Notification();

// Get counts
$projectsCount = 0;
$activeProjectsCount = 0;
$documentsCount = 0;
$resourcesCount = 0;
$patentsCount = 0;

// Get recent items
$recentProjects = [];
$recentDocuments = [];
$unreadNotifications = [];

// Admin or Department Head - show all data
if ($currentUser['role_id'] <= 2) { // Admin or Project Manager
    $projectsCount = $projectObj->count();
    $activeProjectsCount = $projectObj->count(['status' => 'in_progress']);
    $documentsCount = $documentObj->count();
    $resourcesCount = $resourceObj->count();
    $patentsCount = $patentObj->count();
    
    $recentProjects = $projectObj->getAll('created_at', 'DESC', 5);
    $recentDocuments = $documentObj->getAll('upload_date', 'DESC', 5);
} elseif ($currentUser['role_id'] == 4) { // Department Head
    // Get department data
    if ($userDepartment) {
        $departmentData = $departmentObj->getDashboardData($currentUser['department_id']);
        $projectsCount = $departmentData['statistics']['projects_count'];
        $activeProjectsCount = $departmentData['statistics']['active_projects_count'];
        $documentsCount = $departmentData['statistics']['documents_count'];
        $resourcesCount = $departmentData['statistics']['resources_count'];
        
        $recentProjects = $departmentData['recent_projects'];
        $recentDocuments = $departmentData['recent_documents'];
    }
    
    $patentsCount = $patentObj->count();
} else { // Researcher or Faculty Member
    // Get projects where user is a member
    $userProjects = $projectObj->getByMember($currentUser['id']);
    $projectsCount = count($userProjects);
    
    // Count active projects
    $activeProjectsCount = 0;
    foreach ($userProjects as $project) {
        if ($project['status'] == 'in_progress') {
            $activeProjectsCount++;
        }
    }
    
    // Get documents from user's projects
    $documentsCount = 0;
    $projectIds = array_column($userProjects, 'id');
    if (!empty($projectIds)) {
        $conditions = [];
        foreach ($projectIds as $index => $projectId) {
            $conditions["project_id_$index"] = [
                'operator' => '=',
                'value' => $projectId
            ];
        }
        $documentsCount = $documentObj->count($conditions);
    }
    
    // Get documents uploaded by user
    $userDocumentsCount = $documentObj->count(['uploaded_by' => $currentUser['id']]);
    $documentsCount += $userDocumentsCount;
    
    // Get patents count
    $patentsCount = $patentObj->count(['created_by' => $currentUser['id']]);
    
    // Get recent projects
    $recentProjects = array_slice($userProjects, 0, 5);
    
    // Get recent documents
    $recentDocuments = $documentObj->getByUploader($currentUser['id']);
    $recentDocuments = array_slice($recentDocuments, 0, 5);
}

// Get unread notifications for all users
$unreadNotifications = $notificationObj->getForUser($currentUser['id'], true, 5);

// Include header
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
        <p class="mb-4">Welcome back, <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>!</p>
    </div>
</div>

<!-- Dashboard Cards -->
<div class="row">
    <!-- Projects Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 dashboard-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Projects</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $projectsCount; ?></div>
                        <div class="text-xs text-muted"><?php echo $activeProjectsCount; ?> active</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="projects.php" class="btn btn-sm btn-primary">View All</a>
            </div>
        </div>
    </div>

    <!-- Documents Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 dashboard-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Documents</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $documentsCount; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="documents.php" class="btn btn-sm btn-success">View All</a>
            </div>
        </div>
    </div>

    <!-- Resources Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2 dashboard-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Resources</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $resourcesCount; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tools fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="resources.php" class="btn btn-sm btn-info">View All</a>
            </div>
        </div>
    </div>

    <!-- Patents Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2 dashboard-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Patents</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $patentsCount; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-certificate fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="patents.php" class="btn btn-sm btn-warning">View All</a>
            </div>
        </div>
    </div>
</div>

<!-- Content Row -->
<div class="row">
    <!-- Recent Projects -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Recent Projects</h6>
                <a href="projects.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentProjects)): ?>
                    <p class="text-center text-muted">No projects found.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($recentProjects as $project): ?>
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

    <!-- Recent Documents -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-success">Recent Documents</h6>
                <a href="documents.php" class="btn btn-sm btn-success">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentDocuments)): ?>
                    <p class="text-center text-muted">No documents found.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($recentDocuments as $document): ?>
                            <a href="document_detail.php?id=<?php echo $document['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($document['title']); ?></h5>
                                    <small class="text-muted">
                                        <span class="document-type-<?php echo $document['type']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $document['type'])); ?>
                                        </span>
                                    </small>
                                </div>
                                <p class="mb-1">
                                    <?php 
                                    $desc = $document['description'] ?? '';
                                    echo htmlspecialchars(substr($desc, 0, 100)) . (strlen($desc) > 100 ? '...' : '');
                                    ?>
                                </p>
                                <small class="text-muted">
                                    Uploaded: <?php echo date('M d, Y', strtotime($document['upload_date'])); ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Notifications -->
<div class="row">
    <div class="col-lg-12 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Recent Notifications</h6>
                <a href="notifications.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($unreadNotifications)): ?>
                    <p class="text-center text-muted">No unread notifications.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($unreadNotifications as $notification): ?>
                            <a href="notification_detail.php?id=<?php echo $notification['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>
                                    </small>
                                </div>
                                <p class="mb-1">
                                    <?php echo htmlspecialchars(substr($notification['message'], 0, 100)) . (strlen($notification['message']) > 100 ? '...' : ''); ?>
                                </p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>