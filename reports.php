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

// Get user role
$userObj = new User();
$currentUser = $userObj->getById($_SESSION['user_id']);
$userRole = $currentUser['role_id'];

// Check if user has permission to access reports
if ($userRole > 2) { // Only Admin (1) and Project Manager (2) can access
    $_SESSION['error_message'] = "You don't have permission to access reports.";
    header("Location: index.php");
    exit;
}

// Set page title
$pageTitle = "Reports";

// Define available report types
$reportTypes = [
    [
        'id' => 'project_status',
        'title' => 'Project Status Report',
        'description' => 'Overview of all projects with their current status, progress, and timelines.',
        'icon' => 'fas fa-project-diagram',
        'color' => 'primary'
    ],
    [
        'id' => 'resource_allocation',
        'title' => 'Resource Allocation Report',
        'description' => 'Analysis of resource allocation across projects, including usage statistics and availability.',
        'icon' => 'fas fa-tools',
        'color' => 'info'
    ],
    [
        'id' => 'document_summary',
        'title' => 'Document Summary Report',
        'description' => 'Summary of all documents by type, project, and department.',
        'icon' => 'fas fa-file-alt',
        'color' => 'success'
    ],
    [
        'id' => 'patent_tracking',
        'title' => 'Patent Tracking Report',
        'description' => 'Status and progress of all patents, including filing dates and approval rates.',
        'icon' => 'fas fa-certificate',
        'color' => 'warning'
    ],
    [
        'id' => 'budget_spending',
        'title' => 'Budget & Spending Report',
        'description' => 'Financial analysis of project budgets, actual spending, and variances.',
        'icon' => 'fas fa-dollar-sign',
        'color' => 'danger'
    ],
    [
        'id' => 'timeline_analysis',
        'title' => 'Timeline Analysis Report',
        'description' => 'Analysis of project timelines, including delays, extensions, and completion rates.',
        'icon' => 'fas fa-calendar-alt',
        'color' => 'secondary'
    ]
];

// Include header
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $pageTitle; ?></h1>
        <a href="report_form.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Generate New Report
        </a>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-body">
                    <p>
                        Welcome to the Reports section. Here you can generate detailed reports on various aspects of your R&D activities.
                        Select a report type below to get started, or use the "Generate New Report" button to access the advanced report generator.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <?php foreach ($reportTypes as $report): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3 bg-<?php echo $report['color']; ?> text-white">
                        <h6 class="m-0 font-weight-bold">
                            <i class="<?php echo $report['icon']; ?> mr-2"></i>
                            <?php echo $report['title']; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo $report['description']; ?></p>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="report_form.php?type=<?php echo $report['id']; ?>" class="btn btn-<?php echo $report['color']; ?> btn-block">
                            Generate Report
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Reports</h6>
                </div>
                <div class="card-body">
                    <p class="text-center text-muted">
                        Your recently generated reports will appear here.
                        <br>
                        Use the "Generate New Report" button to create a report.
                    </p>
                    
                    <!-- This section would typically show a list of recently generated reports -->
                    <!-- For now, we'll just show a placeholder message -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>