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
require_once 'classes/Document.php';
require_once 'classes/Resource.php';
require_once 'classes/Patent.php';

// Initialize objects
$projectObj = new Project();
$userObj = new User();
$departmentObj = new Department();
$documentObj = new Document();
$resourceObj = new Resource();
$patentObj = new Patent();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);

// Get project ID from URL
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if project exists
$project = $projectObj->getById($projectId);
if (!$project) {
    $_SESSION['error_message'] = "Project not found.";
    header("Location: projects.php");
    exit;
}

// Check if user has permission to view this project
$canView = false;

// Admins and Project Managers can view any project
if ($currentUser['role_id'] <= 2) {
    $canView = true;
} 
// Department Heads can view projects in their department
elseif ($currentUser['role_id'] == 4 && $project['department_id'] == $currentUser['department_id']) {
    $canView = true;
}
// Project members can view their projects
else {
    $projectMembers = $projectObj->getMembers($projectId);
    foreach ($projectMembers as $member) {
        if ($member['id'] == $currentUser['id']) {
            $canView = true;
            break;
        }
    }
}

if (!$canView) {
    $_SESSION['error_message'] = "You don't have permission to view this project.";
    header("Location: projects.php");
    exit;
}

// Check if user can edit this project
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

// Get project department
$department = null;
if (!empty($project['department_id'])) {
    $department = $departmentObj->getById($project['department_id']);
}

// Get project creator
$creator = null;
if (!empty($project['created_by'])) {
    $creator = $userObj->getById($project['created_by']);
}

// Get project members
$projectMembers = $projectObj->getMembers($projectId);

// Get project resources
$projectResources = $projectObj->getAllocatedResources($projectId);

// Get project documents
$projectDocuments = $documentObj->getByProject($projectId);

// Get project patents
$projectPatents = $patentObj->getByProject($projectId);

// Include header
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="projects.php">Projects</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($project['title']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($project['title']); ?></h1>
        <p class="mb-0">
            <span class="project-status status-<?php echo $project['status']; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
            </span>
            
            <?php if ($department): ?>
                <span class="ms-2">
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($department['name']); ?>
                </span>
            <?php endif; ?>
            
            <?php if ($creator): ?>
                <span class="ms-2">
                    <i class="fas fa-user"></i> Created by <?php echo htmlspecialchars($creator['first_name'] . ' ' . $creator['last_name']); ?>
                </span>
            <?php endif; ?>
        </p>
    </div>
    <div class="col-md-4 text-md-end">
        <?php if ($canEdit): ?>
            <a href="project_form.php?id=<?php echo $projectId; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit Project
            </a>
        <?php endif; ?>
        <a href="projects.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Projects
        </a>
    </div>
</div>

<div class="row">
    <!-- Project Details -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Project Details</h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3 font-weight-bold">Description:</div>
                    <div class="col-md-9"><?php echo nl2br(htmlspecialchars($project['description'] ?? 'No description provided.')); ?></div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3 font-weight-bold">Timeline:</div>
                    <div class="col-md-9">
                        <?php if (!empty($project['start_date']) && !empty($project['end_date'])): ?>
                            <?php echo date('M d, Y', strtotime($project['start_date'])); ?> to <?php echo date('M d, Y', strtotime($project['end_date'])); ?>
                            
                            <?php
                            // Calculate progress
                            $startDate = strtotime($project['start_date']);
                            $endDate = strtotime($project['end_date']);
                            $currentDate = time();
                            $totalDuration = $endDate - $startDate;
                            $elapsedDuration = $currentDate - $startDate;
                            
                            if ($totalDuration > 0 && $elapsedDuration > 0) {
                                $progress = min(100, max(0, ($elapsedDuration / $totalDuration) * 100));
                                
                                // Determine progress color
                                $progressColor = 'bg-info';
                                if ($progress >= 100) {
                                    $progressColor = 'bg-success';
                                } elseif ($project['status'] === 'on_hold') {
                                    $progressColor = 'bg-warning';
                                } elseif ($project['status'] === 'cancelled') {
                                    $progressColor = 'bg-danger';
                                }
                            
                                echo '<div class="progress mt-2" style="height: 10px;">';
                                echo '<div class="progress-bar ' . $progressColor . '" role="progressbar" style="width: ' . $progress . '%;" aria-valuenow="' . $progress . '" aria-valuemin="0" aria-valuemax="100"></div>';
                                echo '</div>';
                                echo '<small class="text-muted">' . round($progress) . '% complete</small>';
                            }
                            ?>
                        <?php else: ?>
                            <?php if (!empty($project['start_date'])): ?>
                                Starts on <?php echo date('M d, Y', strtotime($project['start_date'])); ?>
                            <?php elseif (!empty($project['end_date'])): ?>
                                Ends on <?php echo date('M d, Y', strtotime($project['end_date'])); ?>
                            <?php else: ?>
                                No timeline specified
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3 font-weight-bold">Budget:</div>
                    <div class="col-md-9">
                        <?php if (!empty($project['budget'])): ?>
                            $<?php echo number_format($project['budget'], 2); ?>
                        <?php else: ?>
                            Not specified
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3 font-weight-bold">Created:</div>
                    <div class="col-md-9"><?php echo date('M d, Y', strtotime($project['created_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Project Members -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Project Members</h6>
                <?php if ($canEdit): ?>
                    <a href="project_form.php?id=<?php echo $projectId; ?>#membersContainer" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit"></i> Manage Members
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($projectMembers)): ?>
                    <p class="text-center text-muted">No members assigned to this project.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Responsibilities</th>
                                    <th>Joined Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projectMembers as $member): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                            <small class="d-block text-muted"><?php echo htmlspecialchars($member['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['role'] ?? 'Not specified'); ?></td>
                                        <td><?php echo htmlspecialchars($member['responsibilities'] ?? 'Not specified'); ?></td>
                                        <td><?php echo !empty($member['joined_date']) ? date('M d, Y', strtotime($member['joined_date'])) : 'Not specified'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Project Resources -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Allocated Resources</h6>
                <?php if ($canEdit): ?>
                    <a href="project_form.php?id=<?php echo $projectId; ?>#resourcesContainer" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit"></i> Manage Resources
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($projectResources)): ?>
                    <p class="text-center text-muted">No resources allocated to this project.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Resource</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Allocation Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projectResources as $resource): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($resource['name']); ?></td>
                                        <td><?php echo ucfirst($resource['type']); ?></td>
                                        <td><?php echo $resource['allocated_quantity']; ?></td>
                                        <td><?php echo !empty($resource['allocation_date']) ? date('M d, Y', strtotime($resource['allocation_date'])) : 'Not specified'; ?></td>
                                        <td><?php echo !empty($resource['return_date']) ? date('M d, Y', strtotime($resource['return_date'])) : 'Not specified'; ?></td>
                                        <td>
                                            <span class="badge <?php echo $resource['status'] === 'allocated' ? 'bg-success' : ($resource['status'] === 'returned' ? 'bg-secondary' : 'bg-warning'); ?>">
                                                <?php echo ucfirst($resource['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Project Documents -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Documents</h6>
                <a href="document_form.php?project_id=<?php echo $projectId; ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Add Document
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($projectDocuments)): ?>
                    <p class="text-center text-muted">No documents uploaded for this project.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($projectDocuments as $document): ?>
                            <a href="document_detail.php?id=<?php echo $document['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($document['title']); ?></h6>
                                    <small class="text-muted document-type-<?php echo $document['type']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $document['type'])); ?>
                                    </small>
                                </div>
                                <small class="text-muted">
                                    Uploaded: <?php echo date('M d, Y', strtotime($document['upload_date'])); ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Project Patents -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Patents</h6>
                <a href="patent_form.php?project_id=<?php echo $projectId; ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Add Patent
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($projectPatents)): ?>
                    <p class="text-center text-muted">No patents associated with this project.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($projectPatents as $patent): ?>
                            <a href="patent_detail.php?id=<?php echo $patent['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($patent['title']); ?></h6>
                                    <small class="badge <?php 
                                        echo $patent['status'] === 'approved' ? 'bg-success' : 
                                            ($patent['status'] === 'rejected' ? 'bg-danger' : 
                                                ($patent['status'] === 'filed' ? 'bg-info' : 'bg-secondary')); 
                                    ?>">
                                        <?php echo ucfirst($patent['status']); ?>
                                    </small>
                                </div>
                                <small class="text-muted">
                                    <?php if (!empty($patent['filing_date'])): ?>
                                        Filed: <?php echo date('M d, Y', strtotime($patent['filing_date'])); ?>
                                    <?php else: ?>
                                        Created: <?php echo date('M d, Y', strtotime($patent['created_at'])); ?>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($patent['patent_number'])): ?>
                                        <span class="ms-2">
                                            Patent #: <?php echo htmlspecialchars($patent['patent_number']); ?>
                                        </span>
                                    <?php endif; ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Project Actions -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="document_form.php?project_id=<?php echo $projectId; ?>" class="btn btn-primary">
                        <i class="fas fa-file-upload"></i> Upload Document
                    </a>
                    <a href="patent_form.php?project_id=<?php echo $projectId; ?>" class="btn btn-info">
                        <i class="fas fa-certificate"></i> Register Patent
                    </a>
                    <a href="report_form.php?project_id=<?php echo $projectId; ?>" class="btn btn-success">
                        <i class="fas fa-chart-bar"></i> Generate Report
                    </a>
                    <a href="journal_submission.php?project_id=<?php echo $projectId; ?>" class="btn btn-warning">
                        <i class="fas fa-paper-plane"></i> Submit to IRJSTEM Journal
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>