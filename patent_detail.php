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

// Check if patent ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Patent ID is required.";
    header("Location: patents.php");
    exit;
}

// Include necessary files
require_once 'classes/Patent.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';
require_once 'classes/Document.php';

// Initialize objects
$patent = new Patent();
$project = new Project();
$user = new User();
$document = new Document();

// Get patent details
$patentId = $_GET['id'];
$patentData = $patent->getById($patentId);

if (!$patentData) {
    $_SESSION['error_message'] = "Patent not found.";
    header("Location: patents.php");
    exit;
}

// Get user role
$currentUser = $user->getById($_SESSION['user_id']);
$userRole = $currentUser['role_id'];
$userDepartment = $currentUser['department_id'];

// Check if user has permission to view
$canView = false;

// Admin can view any patent
if ($userRole == 1) {
    $canView = true;
}
// Patent creator can view their own patents
else if ($patentData['created_by'] == $_SESSION['user_id']) {
    $canView = true;
}
// Project managers can view patents in their projects
else if ($userRole == 2 && $patentData['project_id']) {
    $projectData = $project->getById($patentData['project_id']);
    if ($projectData && $projectData['created_by'] == $_SESSION['user_id']) {
        $canView = true;
    }
}
// Department heads can view patents in their department's projects
else if ($userRole == 4 && $patentData['project_id']) {
    $projectData = $project->getById($patentData['project_id']);
    if ($projectData && $projectData['department_id'] == $userDepartment) {
        $canView = true;
    }
}
// Researchers and faculty can view patents in projects they're members of
else if ($patentData['project_id']) {
    $projectData = $project->getById($patentData['project_id']);
    if ($projectData) {
        $projectMembers = $project->getMembers($patentData['project_id']);
        foreach ($projectMembers as $member) {
            if ($member['user_id'] == $_SESSION['user_id']) {
                $canView = true;
                break;
            }
        }
    }
}

if (!$canView) {
    $_SESSION['error_message'] = "You don't have permission to view this patent.";
    header("Location: patents.php");
    exit;
}

// Check if user has permission to edit
$canEdit = false;

// Admin can edit any patent
if ($userRole == 1) {
    $canEdit = true;
}
// Patent creator can edit their own patents
else if ($patentData['created_by'] == $_SESSION['user_id']) {
    $canEdit = true;
}
// Project managers can edit patents in their projects
else if ($userRole == 2 && $patentData['project_id']) {
    $projectData = $project->getById($patentData['project_id']);
    if ($projectData && $projectData['created_by'] == $_SESSION['user_id']) {
        $canEdit = true;
    }
}

// Get associated project
$projectData = null;
if ($patentData['project_id']) {
    $projectData = $project->getById($patentData['project_id']);
}

// Get associated document
$documentData = null;
if ($patentData['document_id']) {
    $documentData = $document->getById($patentData['document_id']);
}

// Get creator
$creatorData = null;
if ($patentData['created_by']) {
    $creatorData = $user->getById($patentData['created_by']);
}

// Set page title
$pageTitle = "Patent Details: " . $patentData['title'];

// Include header
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($patentData['title']); ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="patents.php">Patents</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Patent Details</li>
                </ol>
            </nav>
        </div>
        <div>
            <?php if ($canEdit): ?>
                <a href="patent_form.php?id=<?php echo $patentId; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Patent
                </a>
            <?php endif; ?>
            <a href="patents.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Patents
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Patent Details -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Patent Information</h6>
                    <div>
                        <?php 
                            $statusLabels = [
                                'draft' => '<span class="badge bg-secondary">Draft</span>',
                                'filed' => '<span class="badge bg-primary">Filed</span>',
                                'approved' => '<span class="badge bg-success">Approved</span>',
                                'rejected' => '<span class="badge bg-danger">Rejected</span>'
                            ];
                            echo $statusLabels[$patentData['status']] ?? $patentData['status'];
                        ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3 font-weight-bold">Patent Number:</div>
                        <div class="col-md-9">
                            <?php echo !empty($patentData['patent_number']) ? htmlspecialchars($patentData['patent_number']) : '<span class="text-muted">Not Assigned</span>'; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 font-weight-bold">Description:</div>
                        <div class="col-md-9">
                            <?php echo !empty($patentData['description']) ? nl2br(htmlspecialchars($patentData['description'])) : '<span class="text-muted">No description provided</span>'; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 font-weight-bold">Filing Date:</div>
                        <div class="col-md-9">
                            <?php echo !empty($patentData['filing_date']) ? date('F d, Y', strtotime($patentData['filing_date'])) : '<span class="text-muted">Not Filed</span>'; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 font-weight-bold">Approval Date:</div>
                        <div class="col-md-9">
                            <?php echo !empty($patentData['approval_date']) ? date('F d, Y', strtotime($patentData['approval_date'])) : '<span class="text-muted">Not Approved</span>'; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 font-weight-bold">Associated Project:</div>
                        <div class="col-md-9">
                            <?php 
                                if ($projectData) {
                                    echo '<a href="project_detail.php?id=' . $projectData['id'] . '">' . htmlspecialchars($projectData['title']) . '</a>';
                                } else {
                                    echo '<span class="text-muted">None</span>';
                                }
                            ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 font-weight-bold">Associated Document:</div>
                        <div class="col-md-9">
                            <?php 
                                if ($documentData) {
                                    echo '<a href="document_detail.php?id=' . $documentData['id'] . '">' . htmlspecialchars($documentData['title']) . '</a>';
                                    echo ' <a href="' . htmlspecialchars($documentData['file_path']) . '" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-download"></i> Download</a>';
                                } else {
                                    echo '<span class="text-muted">None</span>';
                                }
                            ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 font-weight-bold">Registered By:</div>
                        <div class="col-md-9">
                            <?php 
                                if ($creatorData) {
                                    echo htmlspecialchars($creatorData['first_name'] . ' ' . $creatorData['last_name']);
                                } else {
                                    echo '<span class="text-muted">Unknown</span>';
                                }
                            ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 font-weight-bold">Registration Date:</div>
                        <div class="col-md-9">
                            <?php echo date('F d, Y', strtotime($patentData['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 font-weight-bold">Last Updated:</div>
                        <div class="col-md-9">
                            <?php echo date('F d, Y', strtotime($patentData['updated_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($projectData): ?>
            <!-- Project Members -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Project Members</h6>
                </div>
                <div class="card-body">
                    <?php
                    $projectMembers = $project->getMembers($projectData['id']);
                    if (empty($projectMembers)):
                    ?>
                        <p class="text-center text-muted">No members assigned to this project.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Responsibilities</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projectMembers as $member): 
                                        $memberData = $user->getById($member['user_id']);
                                        if ($memberData):
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($memberData['first_name'] . ' ' . $memberData['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($member['role']); ?></td>
                                            <td><?php echo htmlspecialchars($member['responsibilities']); ?></td>
                                        </tr>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <!-- Patent Status -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Patent Status</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php
                        $statusIcons = [
                            'draft' => '<i class="fas fa-pencil-alt fa-3x text-secondary mb-3"></i>',
                            'filed' => '<i class="fas fa-file-alt fa-3x text-primary mb-3"></i>',
                            'approved' => '<i class="fas fa-check-circle fa-3x text-success mb-3"></i>',
                            'rejected' => '<i class="fas fa-times-circle fa-3x text-danger mb-3"></i>'
                        ];
                        echo $statusIcons[$patentData['status']] ?? '';
                        
                        $statusDescriptions = [
                            'draft' => 'This patent is in the draft stage. It has not been filed with the patent office yet.',
                            'filed' => 'This patent has been filed with the patent office and is awaiting review.',
                            'approved' => 'This patent has been approved and granted by the patent office.',
                            'rejected' => 'This patent application has been rejected by the patent office.'
                        ];
                        echo '<p>' . ($statusDescriptions[$patentData['status']] ?? '') . '</p>';
                        ?>
                    </div>
                    
                    <div class="patent-timeline">
                        <div class="timeline-item <?php echo in_array($patentData['status'], ['draft', 'filed', 'approved', 'rejected']) ? 'active' : ''; ?>">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title">Draft</h6>
                                <p class="timeline-date">
                                    <?php echo date('M d, Y', strtotime($patentData['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="timeline-item <?php echo in_array($patentData['status'], ['filed', 'approved', 'rejected']) ? 'active' : ''; ?>">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title">Filed</h6>
                                <p class="timeline-date">
                                    <?php echo !empty($patentData['filing_date']) ? date('M d, Y', strtotime($patentData['filing_date'])) : 'Not Filed'; ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="timeline-item <?php echo in_array($patentData['status'], ['approved', 'rejected']) ? 'active' : ''; ?>">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo $patentData['status'] == 'rejected' ? 'Rejected' : 'Approved'; ?></h6>
                                <p class="timeline-date">
                                    <?php 
                                        if ($patentData['status'] == 'approved' && !empty($patentData['approval_date'])) {
                                            echo date('M d, Y', strtotime($patentData['approval_date']));
                                        } else if ($patentData['status'] == 'rejected' && !empty($patentData['updated_at'])) {
                                            echo date('M d, Y', strtotime($patentData['updated_at']));
                                        } else {
                                            echo 'Pending';
                                        }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($canEdit): ?>
                            <a href="patent_form.php?id=<?php echo $patentId; ?>" class="btn btn-primary btn-block">
                                <i class="fas fa-edit"></i> Edit Patent
                            </a>
                            
                            <button type="button" class="btn btn-danger btn-block" data-toggle="modal" data-target="#deleteModal">
                                <i class="fas fa-trash"></i> Delete Patent
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($documentData): ?>
                            <a href="<?php echo htmlspecialchars($documentData['file_path']); ?>" target="_blank" class="btn btn-info btn-block">
                                <i class="fas fa-download"></i> Download Document
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($projectData): ?>
                            <a href="project_detail.php?id=<?php echo $projectData['id']; ?>" class="btn btn-secondary btn-block">
                                <i class="fas fa-project-diagram"></i> View Project
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<?php if ($canEdit): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete the patent "<?php echo htmlspecialchars($patentData['title']); ?>"? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form method="POST" action="patents.php">
                    <input type="hidden" name="patent_id" value="<?php echo $patentId; ?>">
                    <button type="submit" name="delete_patent" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.patent-timeline {
    position: relative;
    padding-left: 30px;
}

.patent-timeline:before {
    content: '';
    position: absolute;
    top: 0;
    left: 15px;
    height: 100%;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-item.active .timeline-marker {
    background-color: #4e73df;
    border-color: #4e73df;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    border: 2px solid #e9ecef;
    background-color: white;
}

.timeline-content {
    padding-bottom: 10px;
}

.timeline-title {
    margin-bottom: 5px;
}

.timeline-date {
    font-size: 0.8rem;
    color: #6c757d;
}
</style>

<?php include 'includes/footer.php'; ?>