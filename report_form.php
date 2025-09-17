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
require_once 'classes/Resource.php';
require_once 'classes/Document.php';
require_once 'classes/Patent.php';

// Initialize objects
$userObj = new User();
$projectObj = new Project();
$departmentObj = new Department();
$resourceObj = new Resource();
$documentObj = new Document();
$patentObj = new Patent();

// Get current user
$currentUser = $userObj->getById($_SESSION['user_id']);

// Check if user has permission to generate reports
$canGenerateReports = false;

// Admins, Project Managers, and Department Heads can generate reports
if ($currentUser['role_id'] <= 4) {
    $canGenerateReports = true;
}

if (!$canGenerateReports) {
    $_SESSION['error_message'] = "You don't have permission to generate reports.";
    header("Location: index.php");
    exit;
}

// Get project ID from URL if provided
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$project = null;
if ($projectId > 0) {
    $project = $projectObj->getById($projectId);
}

// Get departments for filter
$departments = [];
if ($currentUser['role_id'] <= 2) {
    // Admins and Project Managers can see all departments
    $departments = $departmentObj->getAll();
} elseif ($currentUser['role_id'] == 4) {
    // Department Heads can only see their department
    $departments = [$departmentObj->getById($currentUser['department_id'])];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $reportType = $_POST['report_type'] ?? '';
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $selectedProjectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $format = $_POST['format'] ?? 'html';
    
    // Validate form data
    $errors = [];
    
    if (empty($reportType)) {
        $errors[] = "Report type is required.";
    }
    
    // If Department Head, force their department
    if ($currentUser['role_id'] == 4) {
        $departmentId = $currentUser['department_id'];
    }
    
    // If no errors, generate report
    if (empty($errors)) {
        // Redirect to report generation page with parameters
        $params = [
            'type' => $reportType,
            'format' => $format
        ];
        
        if ($startDate) {
            $params['start_date'] = $startDate;
        }
        
        if ($endDate) {
            $params['end_date'] = $endDate;
        }
        
        if ($selectedProjectId) {
            $params['project_id'] = $selectedProjectId;
        }
        
        if ($departmentId) {
            $params['department_id'] = $departmentId;
        }
        
        $queryString = http_build_query($params);
        header("Location: generate_report.php?" . $queryString);
        exit;
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
                <?php if ($project): ?>
                    <li class="breadcrumb-item"><a href="projects.php">Projects</a></li>
                    <li class="breadcrumb-item"><a href="project_detail.php?id=<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['title']); ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active" aria-current="page">Generate Report</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="h3 mb-0 text-gray-800">Generate Report</h1>
        <p class="mb-0">Create detailed reports on project status, resource allocation, and more</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Report Parameters</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" id="reportForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="report_type" class="form-label">Report Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="report_type" name="report_type" required>
                                <option value="">-- Select Report Type --</option>
                                <option value="project_status" <?php echo isset($_POST['report_type']) && $_POST['report_type'] === 'project_status' ? 'selected' : ''; ?>>Project Status Report</option>
                                <option value="resource_allocation" <?php echo isset($_POST['report_type']) && $_POST['report_type'] === 'resource_allocation' ? 'selected' : ''; ?>>Resource Allocation Report</option>
                                <option value="document_summary" <?php echo isset($_POST['report_type']) && $_POST['report_type'] === 'document_summary' ? 'selected' : ''; ?>>Document Summary Report</option>
                                <option value="patent_tracking" <?php echo isset($_POST['report_type']) && $_POST['report_type'] === 'patent_tracking' ? 'selected' : ''; ?>>Patent Tracking Report</option>
                                <option value="budget_spending" <?php echo isset($_POST['report_type']) && $_POST['report_type'] === 'budget_spending' ? 'selected' : ''; ?>>Budget & Spending Report</option>
                                <option value="timeline_analysis" <?php echo isset($_POST['report_type']) && $_POST['report_type'] === 'timeline_analysis' ? 'selected' : ''; ?>>Timeline Analysis Report</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="format" class="form-label">Report Format</label>
                            <select class="form-select" id="format" name="format">
                                <option value="html" <?php echo isset($_POST['format']) && $_POST['format'] === 'html' ? 'selected' : ''; ?>>HTML (View in Browser)</option>
                                <option value="pdf" <?php echo isset($_POST['format']) && $_POST['format'] === 'pdf' ? 'selected' : ''; ?>>PDF (Download)</option>
                                <option value="csv" <?php echo isset($_POST['format']) && $_POST['format'] === 'csv' ? 'selected' : ''; ?>>CSV (Excel Compatible)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="project_id" class="form-label">Project</label>
                            <select class="form-select" id="project_id" name="project_id">
                                <option value="">All Projects</option>
                                <?php
                                $projects = [];
                                if ($currentUser['role_id'] <= 2) {
                                    // Admins and Project Managers can see all projects
                                    $projects = $projectObj->getAll();
                                } elseif ($currentUser['role_id'] == 4) {
                                    // Department Heads can see projects in their department
                                    $projects = $projectObj->getByDepartment($currentUser['department_id']);
                                } else {
                                    // Other users can see projects they're members of
                                    $projects = $projectObj->getByMember($currentUser['id']);
                                }
                                
                                foreach ($projects as $proj):
                                    $selected = ($project && $proj['id'] == $project['id']) || (isset($_POST['project_id']) && $_POST['project_id'] == $proj['id']);
                                ?>
                                    <option value="<?php echo $proj['id']; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($proj['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id" <?php echo $currentUser['role_id'] == 4 ? 'disabled' : ''; ?>>
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) || ($currentUser['role_id'] == 4 && $dept['id'] == $currentUser['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($currentUser['role_id'] == 4): ?>
                                <input type="hidden" name="department_id" value="<?php echo $currentUser['department_id']; ?>">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div id="reportTypeOptions">
                        <!-- Dynamic options based on report type will be loaded here via JavaScript -->
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-file-alt"></i> Generate Report
                            </button>
                            <a href="<?php echo $project ? 'project_detail.php?id=' . $project['id'] : 'index.php'; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Report Types</h6>
            </div>
            <div class="card-body">
                <div class="accordion" id="reportTypesAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                Project Status Report
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#reportTypesAccordion">
                            <div class="accordion-body">
                                <p>Provides a comprehensive overview of project status, progress, timelines, and milestones. Includes information on team members, allocated resources, and associated documents.</p>
                                <p><strong>Best for:</strong> Project managers, department heads, and administrators who need to track project progress.</p>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                Resource Allocation Report
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#reportTypesAccordion">
                            <div class="accordion-body">
                                <p>Details how resources (equipment, personnel, facilities) are allocated across projects. Shows usage patterns, availability, and allocation history.</p>
                                <p><strong>Best for:</strong> Resource managers and project planners who need to optimize resource utilization.</p>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                Document Summary Report
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#reportTypesAccordion">
                            <div class="accordion-body">
                                <p>Summarizes all documents (research papers, faculty evaluations, etc.) associated with projects or departments. Includes document types, upload dates, and submission status.</p>
                                <p><strong>Best for:</strong> Researchers and faculty members who need to track document submissions and publications.</p>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingFour">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                Patent Tracking Report
                            </button>
                        </h2>
                        <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#reportTypesAccordion">
                            <div class="accordion-body">
                                <p>Tracks patents and copyrights associated with R&D projects. Includes filing dates, approval status, and related documents.</p>
                                <p><strong>Best for:</strong> Intellectual property managers and researchers who need to monitor patent status.</p>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingFive">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                                Budget & Spending Report
                            </button>
                        </h2>
                        <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#reportTypesAccordion">
                            <div class="accordion-body">
                                <p>Analyzes project budgets and actual spending. Includes cost breakdowns, resource expenses, and budget variance analysis.</p>
                                <p><strong>Best for:</strong> Financial managers and project administrators who need to monitor project finances.</p>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingSix">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                                Timeline Analysis Report
                            </button>
                        </h2>
                        <div id="collapseSix" class="accordion-collapse collapse" aria-labelledby="headingSix" data-bs-parent="#reportTypesAccordion">
                            <div class="accordion-body">
                                <p>Provides detailed analysis of project timelines, including milestone completion, delays, and projected completion dates.</p>
                                <p><strong>Best for:</strong> Project managers and stakeholders who need to track project schedules and identify potential delays.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Report type change handler
        const reportTypeSelect = document.getElementById('report_type');
        const reportTypeOptions = document.getElementById('reportTypeOptions');
        
        if (reportTypeSelect && reportTypeOptions) {
            reportTypeSelect.addEventListener('change', function() {
                const reportType = this.value;
                let optionsHTML = '';
                
                // Clear previous options
                reportTypeOptions.innerHTML = '';
                
                // Add specific options based on report type
                switch (reportType) {
                    case 'project_status':
                        optionsHTML = `
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="status_filter" class="form-label">Status Filter</label>
                                    <select class="form-select" id="status_filter" name="status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="not_started">Not Started</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="on_hold">On Hold</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="include_details" class="form-label">Include Details</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="include_members" name="include_members" value="1" checked>
                                        <label class="form-check-label" for="include_members">Project Members</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include_resources" name="include_resources" value="1" checked>
                                        <label class="form-check-label" for="include_resources">Allocated Resources</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include_documents" name="include_documents" value="1" checked>
                                        <label class="form-check-label" for="include_documents">Associated Documents</label>
                                    </div>
                                </div>
                            </div>
                        `;
                        break;
                        
                    case 'resource_allocation':
                        optionsHTML = `
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="resource_type" class="form-label">Resource Type</label>
                                    <select class="form-select" id="resource_type" name="resource_type">
                                        <option value="">All Types</option>
                                        <option value="equipment">Equipment</option>
                                        <option value="personnel">Personnel</option>
                                        <option value="facility">Facility</option>
                                        <option value="software">Software</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="allocation_status" class="form-label">Allocation Status</label>
                                    <select class="form-select" id="allocation_status" name="allocation_status">
                                        <option value="">All</option>
                                        <option value="allocated">Currently Allocated</option>
                                        <option value="returned">Returned</option>
                                        <option value="available">Available Resources</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include_usage_charts" name="include_usage_charts" value="1" checked>
                                        <label class="form-check-label" for="include_usage_charts">Include Usage Charts</label>
                                    </div>
                                </div>
                            </div>
                        `;
                        break;
                        
                    case 'document_summary':
                        optionsHTML = `
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="document_type" class="form-label">Document Type</label>
                                    <select class="form-select" id="document_type" name="document_type">
                                        <option value="">All Types</option>
                                        <option value="research_paper">Research Paper</option>
                                        <option value="faculty_evaluation">Faculty Evaluation</option>
                                        <option value="patent_document">Patent Document</option>
                                        <option value="report">Report</option>
                                        <option value="presentation">Presentation</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="journal_submission" class="form-label">Journal Submission</label>
                                    <select class="form-select" id="journal_submission" name="journal_submission">
                                        <option value="">All</option>
                                        <option value="1">Submitted to Journal</option>
                                        <option value="0">Not Submitted</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include_document_stats" name="include_document_stats" value="1" checked>
                                        <label class="form-check-label" for="include_document_stats">Include Document Statistics</label>
                                    </div>
                                </div>
                            </div>
                        `;
                        break;
                        
                    case 'patent_tracking':
                        optionsHTML = `
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="patent_status" class="form-label">Patent Status</label>
                                    <select class="form-select" id="patent_status" name="patent_status">
                                        <option value="">All Statuses</option>
                                        <option value="draft">Draft</option>
                                        <option value="filed">Filed</option>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="include_patent_details" class="form-label">Include Details</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="include_inventors" name="include_inventors" value="1" checked>
                                        <label class="form-check-label" for="include_inventors">Inventors</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include_documents" name="include_documents" value="1" checked>
                                        <label class="form-check-label" for="include_documents">Associated Documents</label>
                                    </div>
                                </div>
                            </div>
                        `;
                        break;
                        
                    case 'budget_spending':
                        optionsHTML = `
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="expense_type" class="form-label">Expense Type</label>
                                    <select class="form-select" id="expense_type" name="expense_type">
                                        <option value="">All Types</option>
                                        <option value="resource">Resource Costs</option>
                                        <option value="personnel">Personnel Costs</option>
                                        <option value="operational">Operational Costs</option>
                                        <option value="other">Other Expenses</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="budget_analysis" class="form-label">Budget Analysis</label>
                                    <select class="form-select" id="budget_analysis" name="budget_analysis">
                                        <option value="all">All Projects</option>
                                        <option value="over_budget">Over Budget</option>
                                        <option value="under_budget">Under Budget</option>
                                        <option value="on_budget">On Budget</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include_charts" name="include_charts" value="1" checked>
                                        <label class="form-check-label" for="include_charts">Include Budget Charts</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include_forecast" name="include_forecast" value="1" checked>
                                        <label class="form-check-label" for="include_forecast">Include Spending Forecast</label>
                                    </div>
                                </div>
                            </div>
                        `;
                        break;
                        
                    case 'timeline_analysis':
                        optionsHTML = `
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="timeline_status" class="form-label">Timeline Status</label>
                                    <select class="form-select" id="timeline_status" name="timeline_status">
                                        <option value="">All</option>
                                        <option value="on_schedule">On Schedule</option>
                                        <option value="delayed">Delayed</option>
                                        <option value="ahead">Ahead of Schedule</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="milestone_filter" class="form-label">Milestone Filter</label>
                                    <select class="form-select" id="milestone_filter" name="milestone_filter">
                                        <option value="">All Milestones</option>
                                        <option value="completed">Completed Milestones</option>
                                        <option value="upcoming">Upcoming Milestones</option>
                                        <option value="overdue">Overdue Milestones</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include_gantt" name="include_gantt" value="1" checked>
                                        <label class="form-check-label" for="include_gantt">Include Gantt Chart</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include_critical_path" name="include_critical_path" value="1" checked>
                                        <label class="form-check-label" for="include_critical_path">Include Critical Path Analysis</label>
                                    </div>
                                </div>
                            </div>
                        `;
                        break;
                }
                
                // Add options to the form
                if (optionsHTML) {
                    const div = document.createElement('div');
                    div.innerHTML = `
                        <hr class="my-4">
                        <h5>Report Options</h5>
                        ${optionsHTML}
                    `;
                    reportTypeOptions.appendChild(div);
                }
            });
            
            // Trigger change event if a value is already selected
            if (reportTypeSelect.value) {
                reportTypeSelect.dispatchEvent(new Event('change'));
            }
        }
        
        // Form validation
        const form = document.getElementById('reportForm');
        if (form) {
            form.addEventListener('submit', function(event) {
                let isValid = true;
                
                // Validate report type
                const reportTypeSelect = document.getElementById('report_type');
                if (!reportTypeSelect.value) {
                    isValid = false;
                    reportTypeSelect.classList.add('is-invalid');
                } else {
                    reportTypeSelect.classList.remove('is-invalid');
                }
                
                // Validate date range if both are provided
                const startDateInput = document.getElementById('start_date');
                const endDateInput = document.getElementById('end_date');
                
                if (startDateInput.value && endDateInput.value) {
                    const startDate = new Date(startDateInput.value);
                    const endDate = new Date(endDateInput.value);
                    
                    if (startDate > endDate) {
                        isValid = false;
                        startDateInput.classList.add('is-invalid');
                        endDateInput.classList.add('is-invalid');
                        
                        // Add error message
                        const dateRangeError = document.createElement('div');
                        dateRangeError.className = 'alert alert-danger mt-3';
                        dateRangeError.textContent = 'Start date must be before end date.';
                        
                        if (!document.querySelector('.alert-danger')) {
                            form.insertBefore(dateRangeError, form.firstChild);
                        }
                    } else {
                        startDateInput.classList.remove('is-invalid');
                        endDateInput.classList.remove('is-invalid');
                    }
                }
                
                if (!isValid) {
                    event.preventDefault();
                }
            });
        }
    });
</script>

<?php
// Include footer
include 'includes/footer.php';
?>