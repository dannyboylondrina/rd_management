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

// Get report parameters
$reportType = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'html';
$startDate = !empty($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = !empty($_GET['end_date']) ? $_GET['end_date'] : null;
$projectId = !empty($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$departmentId = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;

// If Department Head, force their department
if ($currentUser['role_id'] == 4) {
    $departmentId = $currentUser['department_id'];
}

// Validate report type
if (empty($reportType)) {
    $_SESSION['error_message'] = "Report type is required.";
    header("Location: report_form.php");
    exit;
}

// Set report title and description based on type
$reportTitle = '';
$reportDescription = '';

switch ($reportType) {
    case 'project_status':
        $reportTitle = 'Project Status Report';
        $reportDescription = 'Comprehensive overview of project status, progress, timelines, and milestones.';
        break;
    case 'resource_allocation':
        $reportTitle = 'Resource Allocation Report';
        $reportDescription = 'Details of how resources are allocated across projects, including usage patterns and availability.';
        break;
    case 'document_summary':
        $reportTitle = 'Document Summary Report';
        $reportDescription = 'Summary of all documents associated with projects or departments.';
        break;
    case 'patent_tracking':
        $reportTitle = 'Patent Tracking Report';
        $reportDescription = 'Tracking of patents and copyrights associated with R&D projects.';
        break;
    case 'budget_spending':
        $reportTitle = 'Budget & Spending Report';
        $reportDescription = 'Analysis of project budgets and actual spending.';
        break;
    case 'timeline_analysis':
        $reportTitle = 'Timeline Analysis Report';
        $reportDescription = 'Detailed analysis of project timelines, including milestone completion and delays.';
        break;
    default:
        $_SESSION['error_message'] = "Invalid report type.";
        header("Location: report_form.php");
        exit;
}

// Get report data based on type
$reportData = [];
$filters = [];

// Add date filters if provided
if ($startDate) {
    $filters['start_date'] = $startDate;
}
if ($endDate) {
    $filters['end_date'] = $endDate;
}
// Add project filter if provided
if ($projectId) {
    $filters['project_id'] = $projectId;
}
// Add department filter if provided
if ($departmentId) {
    $filters['department_id'] = $departmentId;
}

// Add specific filters based on report type
foreach ($_GET as $key => $value) {
    if (!in_array($key, ['type', 'format', 'start_date', 'end_date', 'project_id', 'department_id']) && !empty($value)) {
        $filters[$key] = $value;
    }
}

// Generate report data based on type
switch ($reportType) {
    case 'project_status':
        $reportData = generateProjectStatusReport($filters);
        break;
    case 'resource_allocation':
        $reportData = generateResourceAllocationReport($filters);
        break;
    case 'document_summary':
        $reportData = generateDocumentSummaryReport($filters);
        break;
    case 'patent_tracking':
        $reportData = generatePatentTrackingReport($filters);
        break;
    case 'budget_spending':
        $reportData = generateBudgetSpendingReport($filters);
        break;
    case 'timeline_analysis':
        $reportData = generateTimelineAnalysisReport($filters);
        break;
}

// Output report based on format
switch ($format) {
    case 'pdf':
        outputPDFReport($reportTitle, $reportDescription, $reportData, $filters);
        break;
    case 'csv':
        outputCSVReport($reportTitle, $reportData, $filters);
        break;
    case 'html':
    default:
        // Continue to HTML output below
        break;
}

// Function to generate Project Status Report
function generateProjectStatusReport($filters) {
    global $projectObj, $userObj, $departmentObj;
    
    $projects = [];
    $statusFilter = $filters['status_filter'] ?? '';
    
    // Get projects based on filters
    if (!empty($filters['project_id'])) {
        // Single project
        $project = $projectObj->getById($filters['project_id']);
        if ($project && (empty($statusFilter) || $project['status'] === $statusFilter)) {
            $projects[] = $project;
        }
    } else {
        // Multiple projects
        $projectFilters = [];
        
        if (!empty($filters['department_id'])) {
            $projectFilters['department_id'] = $filters['department_id'];
        }
        
        if (!empty($statusFilter)) {
            $projectFilters['status'] = $statusFilter;
        }
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $projectFilters['date_range'] = [
                'start' => $filters['start_date'],
                'end' => $filters['end_date']
            ];
        }
        
        $projects = $projectObj->getAll(0, 0, $projectFilters);
    }
    
    // Enhance project data with additional information
    foreach ($projects as &$project) {
        // Add department name
        if (!empty($project['department_id'])) {
            $department = $departmentObj->getById($project['department_id']);
            $project['department_name'] = $department ? $department['name'] : 'N/A';
        } else {
            $project['department_name'] = 'N/A';
        }
        
        // Add creator name
        if (!empty($project['created_by'])) {
            $creator = $userObj->getById($project['created_by']);
            $project['creator_name'] = $creator ? $creator['first_name'] . ' ' . $creator['last_name'] : 'N/A';
        } else {
            $project['creator_name'] = 'N/A';
        }
        
        // Calculate progress
        if (!empty($project['start_date']) && !empty($project['end_date'])) {
            $startDate = strtotime($project['start_date']);
            $endDate = strtotime($project['end_date']);
            $currentDate = time();
            $totalDuration = $endDate - $startDate;
            $elapsedDuration = $currentDate - $startDate;
            
            if ($totalDuration > 0 && $elapsedDuration > 0) {
                $project['progress'] = min(100, max(0, ($elapsedDuration / $totalDuration) * 100));
            } else {
                $project['progress'] = 0;
            }
        } else {
            $project['progress'] = 0;
        }
        
        // Add members if requested
        if (isset($filters['include_members']) && $filters['include_members']) {
            $project['members'] = $projectObj->getMembers($project['id']);
        }
        
        // Add resources if requested
        if (isset($filters['include_resources']) && $filters['include_resources']) {
            $project['resources'] = $projectObj->getAllocatedResources($project['id']);
        }
        
        // Add documents if requested
        if (isset($filters['include_documents']) && $filters['include_documents']) {
            global $documentObj;
            $project['documents'] = $documentObj->getByProject($project['id']);
        }
    }
    
    return [
        'projects' => $projects,
        'total' => count($projects),
        'filters' => $filters
    ];
}

// Function to generate Resource Allocation Report
function generateResourceAllocationReport($filters) {
    global $resourceObj, $projectObj, $departmentObj;
    
    $resources = [];
    $resourceType = $filters['resource_type'] ?? '';
    $allocationStatus = $filters['allocation_status'] ?? '';
    
    // Get resources based on filters
    $resourceFilters = [];
    
    if (!empty($resourceType)) {
        $resourceFilters['type'] = $resourceType;
    }
    
    if (!empty($filters['department_id'])) {
        $resourceFilters['department_id'] = $filters['department_id'];
    }
    
    if ($allocationStatus === 'available') {
        $resourceFilters['is_available'] = 1;
    }
    
    $resources = $resourceObj->getAll(0, 0, $resourceFilters);
    
    // Enhance resource data with additional information
    foreach ($resources as &$resource) {
        // Add department name
        if (!empty($resource['department_id'])) {
            $department = $departmentObj->getById($resource['department_id']);
            $resource['department_name'] = $department ? $department['name'] : 'N/A';
        } else {
            $resource['department_name'] = 'N/A';
        }
        
        // Get allocations
        $allocations = $resourceObj->getAllocations($resource['id']);
        
        // Filter allocations based on status
        if ($allocationStatus === 'allocated') {
            $allocations = array_filter($allocations, function($allocation) {
                return $allocation['status'] === 'allocated';
            });
        } elseif ($allocationStatus === 'returned') {
            $allocations = array_filter($allocations, function($allocation) {
                return $allocation['status'] === 'returned';
            });
        }
        
        // Filter allocations based on date range
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $startDate = strtotime($filters['start_date']);
            $endDate = strtotime($filters['end_date']);
            
            $allocations = array_filter($allocations, function($allocation) use ($startDate, $endDate) {
                $allocationDate = strtotime($allocation['allocation_date']);
                return $allocationDate >= $startDate && $allocationDate <= $endDate;
            });
        }
        
        // Filter allocations based on project
        if (!empty($filters['project_id'])) {
            $allocations = array_filter($allocations, function($allocation) use ($filters) {
                return $allocation['project_id'] == $filters['project_id'];
            });
        }
        
        // Enhance allocations with project details
        foreach ($allocations as &$allocation) {
            $project = $projectObj->getById($allocation['project_id']);
            $allocation['project_title'] = $project ? $project['title'] : 'N/A';
            $allocation['project_status'] = $project ? $project['status'] : 'N/A';
        }
        
        $resource['allocations'] = array_values($allocations);
        
        // Calculate usage statistics
        $resource['total_allocations'] = count($allocations);
        $resource['currently_allocated'] = 0;
        $resource['total_allocated_quantity'] = 0;
        
        foreach ($allocations as $allocation) {
            if ($allocation['status'] === 'allocated') {
                $resource['currently_allocated']++;
                $resource['total_allocated_quantity'] += $allocation['quantity'];
            }
        }
        
        $resource['available_quantity'] = $resource['quantity'] - $resource['total_allocated_quantity'];
        $resource['usage_percentage'] = ($resource['quantity'] > 0) ? ($resource['total_allocated_quantity'] / $resource['quantity']) * 100 : 0;
    }
    
    // If only showing allocated or returned resources, filter out resources with no matching allocations
    if ($allocationStatus === 'allocated' || $allocationStatus === 'returned') {
        $resources = array_filter($resources, function($resource) {
            return count($resource['allocations']) > 0;
        });
    }
    
    return [
        'resources' => array_values($resources),
        'total' => count($resources),
        'filters' => $filters
    ];
}

// Function to generate Document Summary Report
function generateDocumentSummaryReport($filters) {
    global $documentObj, $projectObj, $userObj, $departmentObj;
    
    $documents = [];
    $documentType = $filters['document_type'] ?? '';
    $journalSubmission = isset($filters['journal_submission']) ? (int)$filters['journal_submission'] : null;
    
    // Get documents based on filters
    $documentFilters = [];
    
    if (!empty($documentType)) {
        $documentFilters['type'] = $documentType;
    }
    
    if ($journalSubmission !== null) {
        $documentFilters['submit_to_journal'] = $journalSubmission;
    }
    
    if (!empty($filters['project_id'])) {
        $documentFilters['project_id'] = $filters['project_id'];
    }
    
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $documentFilters['date_range'] = [
            'start' => $filters['start_date'],
            'end' => $filters['end_date']
        ];
    }
    
    // If department filter is provided, we need to get projects in that department first
    if (!empty($filters['department_id'])) {
        $departmentProjects = $projectObj->getByDepartment($filters['department_id']);
        $projectIds = array_column($departmentProjects, 'id');
        
        if (!empty($projectIds)) {
            $documentFilters['project_ids'] = $projectIds;
        } else {
            // No projects in department, so no documents
            return [
                'documents' => [],
                'total' => 0,
                'filters' => $filters
            ];
        }
    }
    
    $documents = $documentObj->getAll(0, 0, $documentFilters);
    
    // Enhance document data with additional information
    foreach ($documents as &$document) {
        // Add uploader name
        if (!empty($document['uploaded_by'])) {
            $uploader = $userObj->getById($document['uploaded_by']);
            $document['uploader_name'] = $uploader ? $uploader['first_name'] . ' ' . $uploader['last_name'] : 'N/A';
        } else {
            $document['uploader_name'] = 'N/A';
        }
        
        // Add project details
        if (!empty($document['project_id'])) {
            $project = $projectObj->getById($document['project_id']);
            $document['project_title'] = $project ? $project['title'] : 'N/A';
            $document['project_status'] = $project ? $project['status'] : 'N/A';
            
            // Add department name
            if ($project && !empty($project['department_id'])) {
                $department = $departmentObj->getById($project['department_id']);
                $document['department_name'] = $department ? $department['name'] : 'N/A';
            } else {
                $document['department_name'] = 'N/A';
            }
        } else {
            $document['project_title'] = 'N/A';
            $document['project_status'] = 'N/A';
            $document['department_name'] = 'N/A';
        }
        
        // Get file size
        $filePath = 'uploads/' . $document['file_path'];
        if (file_exists($filePath)) {
            $fileSize = filesize($filePath);
            if ($fileSize < 1024) {
                $document['file_size'] = $fileSize . ' bytes';
            } elseif ($fileSize < 1024 * 1024) {
                $document['file_size'] = round($fileSize / 1024, 2) . ' KB';
            } else {
                $document['file_size'] = round($fileSize / (1024 * 1024), 2) . ' MB';
            }
        } else {
            $document['file_size'] = 'File not found';
        }
    }
    
    // Generate document statistics if requested
    $documentStats = [];
    if (isset($filters['include_document_stats']) && $filters['include_document_stats']) {
        // Count by type
        $typeStats = [];
        foreach ($documents as $document) {
            $type = $document['type'];
            if (!isset($typeStats[$type])) {
                $typeStats[$type] = 0;
            }
            $typeStats[$type]++;
        }
        $documentStats['by_type'] = $typeStats;
        
        // Count by project
        $projectStats = [];
        foreach ($documents as $document) {
            if (!empty($document['project_id'])) {
                $projectTitle = $document['project_title'];
                if (!isset($projectStats[$projectTitle])) {
                    $projectStats[$projectTitle] = 0;
                }
                $projectStats[$projectTitle]++;
            }
        }
        $documentStats['by_project'] = $projectStats;
        
        // Count by department
        $departmentStats = [];
        foreach ($documents as $document) {
            $departmentName = $document['department_name'];
            if ($departmentName !== 'N/A') {
                if (!isset($departmentStats[$departmentName])) {
                    $departmentStats[$departmentName] = 0;
                }
                $departmentStats[$departmentName]++;
            }
        }
        $documentStats['by_department'] = $departmentStats;
        
        // Count by journal submission
        $journalStats = [
            'submitted' => 0,
            'not_submitted' => 0
        ];
        foreach ($documents as $document) {
            if ($document['submit_to_journal']) {
                $journalStats['submitted']++;
            } else {
                $journalStats['not_submitted']++;
            }
        }
        $documentStats['by_journal_submission'] = $journalStats;
    }
    
    return [
        'documents' => $documents,
        'total' => count($documents),
        'stats' => $documentStats,
        'filters' => $filters
    ];
}

// Function to generate Patent Tracking Report
function generatePatentTrackingReport($filters) {
    global $patentObj, $projectObj, $userObj, $departmentObj, $documentObj;
    
    $patents = [];
    $patentStatus = $filters['patent_status'] ?? '';
    
    // Get patents based on filters
    $patentFilters = [];
    
    if (!empty($patentStatus)) {
        $patentFilters['status'] = $patentStatus;
    }
    
    if (!empty($filters['project_id'])) {
        $patentFilters['project_id'] = $filters['project_id'];
    }
    
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $patentFilters['date_range'] = [
            'start' => $filters['start_date'],
            'end' => $filters['end_date']
        ];
    }
    
    // If department filter is provided, we need to get projects in that department first
    if (!empty($filters['department_id'])) {
        $departmentProjects = $projectObj->getByDepartment($filters['department_id']);
        $projectIds = array_column($departmentProjects, 'id');
        
        if (!empty($projectIds)) {
            $patentFilters['project_ids'] = $projectIds;
        } else {
            // No projects in department, so no patents
            return [
                'patents' => [],
                'total' => 0,
                'filters' => $filters
            ];
        }
    }
    
    $patents = $patentObj->getAll(0, 0, $patentFilters);
    
    // Enhance patent data with additional information
    foreach ($patents as &$patent) {
        // Add creator name
        if (!empty($patent['created_by'])) {
            $creator = $userObj->getById($patent['created_by']);
            $patent['creator_name'] = $creator ? $creator['first_name'] . ' ' . $creator['last_name'] : 'N/A';
        } else {
            $patent['creator_name'] = 'N/A';
        }
        
        // Add project details
        if (!empty($patent['project_id'])) {
            $project = $projectObj->getById($patent['project_id']);
            $patent['project_title'] = $project ? $project['title'] : 'N/A';
            $patent['project_status'] = $project ? $project['status'] : 'N/A';
            
            // Add department name
            if ($project && !empty($project['department_id'])) {
                $department = $departmentObj->getById($project['department_id']);
                $patent['department_name'] = $department ? $department['name'] : 'N/A';
            } else {
                $patent['department_name'] = 'N/A';
            }
        } else {
            $patent['project_title'] = 'N/A';
            $patent['project_status'] = 'N/A';
            $patent['department_name'] = 'N/A';
        }
        
        // Add inventors if requested
        if (isset($filters['include_inventors']) && $filters['include_inventors']) {
            $patent['inventors'] = $patentObj->getInventors($patent['id']);
        }
        
        // Add documents if requested
        if (isset($filters['include_documents']) && $filters['include_documents']) {
            $patent['documents'] = [];
            
            // Get document if one is associated
            if (!empty($patent['document_id'])) {
                $document = $documentObj->getById($patent['document_id']);
                if ($document) {
                    $patent['documents'][] = $document;
                }
            }
            
            // Get other related documents
            if (!empty($patent['project_id'])) {
                $projectDocuments = $documentObj->getByProject($patent['project_id'], 0, 0, 'patent_document');
                foreach ($projectDocuments as $doc) {
                    if (empty($patent['document_id']) || $doc['id'] != $patent['document_id']) {
                        $patent['documents'][] = $doc;
                    }
                }
            }
        }
    }
    
    // Generate patent statistics
    $patentStats = [];
    
    // Count by status
    $statusStats = [];
    foreach ($patents as $patent) {
        $status = $patent['status'];
        if (!isset($statusStats[$status])) {
            $statusStats[$status] = 0;
        }
        $statusStats[$status]++;
    }
    $patentStats['by_status'] = $statusStats;
    
    // Count by project
    $projectStats = [];
    foreach ($patents as $patent) {
        if (!empty($patent['project_id'])) {
            $projectTitle = $patent['project_title'];
            if (!isset($projectStats[$projectTitle])) {
                $projectStats[$projectTitle] = 0;
            }
            $projectStats[$projectTitle]++;
        }
    }
    $patentStats['by_project'] = $projectStats;
    
    // Count by department
    $departmentStats = [];
    foreach ($patents as $patent) {
        $departmentName = $patent['department_name'];
        if ($departmentName !== 'N/A') {
            if (!isset($departmentStats[$departmentName])) {
                $departmentStats[$departmentName] = 0;
            }
            $departmentStats[$departmentName]++;
        }
    }
    $patentStats['by_department'] = $departmentStats;
    
    return [
        'patents' => $patents,
        'total' => count($patents),
        'stats' => $patentStats,
        'filters' => $filters
    ];
}

// Function to generate Budget & Spending Report
function generateBudgetSpendingReport($filters) {
    global $projectObj, $resourceObj, $departmentObj;
    
    $projects = [];
    $expenseType = $filters['expense_type'] ?? '';
    $budgetAnalysis = $filters['budget_analysis'] ?? 'all';
    
    // Get projects based on filters
    $projectFilters = [];
    
    if (!empty($filters['department_id'])) {
        $projectFilters['department_id'] = $filters['department_id'];
    }
    
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $projectFilters['date_range'] = [
            'start' => $filters['start_date'],
            'end' => $filters['end_date']
        ];
    }
    
    if (!empty($filters['project_id'])) {
        // Single project
        $project = $projectObj->getById($filters['project_id']);
        if ($project) {
            $projects[] = $project;
        }
    } else {
        // Multiple projects
        $projects = $projectObj->getAll(0, 0, $projectFilters);
    }
    
    // Enhance project data with budget information
    foreach ($projects as &$project) {
        // Add department name
        if (!empty($project['department_id'])) {
            $department = $departmentObj->getById($project['department_id']);
            $project['department_name'] = $department ? $department['name'] : 'N/A';
        } else {
            $project['department_name'] = 'N/A';
        }
        
        // Get resource allocations
        $allocations = $projectObj->getAllocatedResources($project['id']);
        
        // Calculate resource costs
        $resourceCosts = 0;
        foreach ($allocations as $allocation) {
            if (!empty($allocation['cost'])) {
                $resourceCosts += $allocation['cost'] * $allocation['allocated_quantity'];
            }
        }
        
        // Calculate personnel costs (simplified for demo)
        $personnelCosts = $project['budget'] * 0.6; // Assume 60% of budget is personnel costs
        
        // Calculate operational costs (simplified for demo)
        $operationalCosts = $project['budget'] * 0.2; // Assume 20% of budget is operational costs
        
        // Calculate other costs (simplified for demo)
        $otherCosts = $project['budget'] * 0.1; // Assume 10% of budget is other costs
        
        // Calculate total costs
        $totalCosts = $resourceCosts + $personnelCosts + $operationalCosts + $otherCosts;
        
        // Calculate budget variance
        $budgetVariance = $project['budget'] - $totalCosts;
        $budgetVariancePercentage = ($project['budget'] > 0) ? ($budgetVariance / $project['budget']) * 100 : 0;
        
        // Add budget information to project
        $project['resource_costs'] = $resourceCosts;
        $project['personnel_costs'] = $personnelCosts;
        $project['operational_costs'] = $operationalCosts;
        $project['other_costs'] = $otherCosts;
        $project['total_costs'] = $totalCosts;
        $project['budget_variance'] = $budgetVariance;
        $project['budget_variance_percentage'] = $budgetVariancePercentage;
        $project['budget_status'] = ($budgetVariance >= 0) ? 'under_budget' : 'over_budget';
        
        // Add resource allocations
        $project['resource_allocations'] = $allocations;
    }
    
    // Filter projects based on budget analysis
    if ($budgetAnalysis !== 'all') {
        $projects = array_filter($projects, function($project) use ($budgetAnalysis) {
            return $project['budget_status'] === $budgetAnalysis;
        });
    }
    
    // Filter projects based on expense type
    if (!empty($expenseType)) {
        foreach ($projects as &$project) {
            switch ($expenseType) {
                case 'resource':
                    $project['filtered_costs'] = $project['resource_costs'];
                    break;
                case 'personnel':
                    $project['filtered_costs'] = $project['personnel_costs'];
                    break;
                case 'operational':
                    $project['filtered_costs'] = $project['operational_costs'];
                    break;
                case 'other':
                    $project['filtered_costs'] = $project['other_costs'];
                    break;
                default:
                    $project['filtered_costs'] = $project['total_costs'];
                    break;
            }
        }
    } else {
        foreach ($projects as &$project) {
            $project['filtered_costs'] = $project['total_costs'];
        }
    }
    
    // Generate budget statistics
    $budgetStats = [];
    
    // Total budget
    $totalBudget = array_sum(array_column($projects, 'budget'));
    $totalCosts = array_sum(array_column($projects, 'total_costs'));
    $totalVariance = $totalBudget - $totalCosts;
    
    $budgetStats['total_budget'] = $totalBudget;
    $budgetStats['total_costs'] = $totalCosts;
    $budgetStats['total_variance'] = $totalVariance;
    $budgetStats['total_variance_percentage'] = ($totalBudget > 0) ? ($totalVariance / $totalBudget) * 100 : 0;
    
    // Count by budget status
    $budgetStatusStats = [
        'under_budget' => 0,
        'on_budget' => 0,
        'over_budget' => 0
    ];
    
    foreach ($projects as $project) {
        if ($project['budget_variance'] > 0) {
            $budgetStatusStats['under_budget']++;
        } elseif ($project['budget_variance'] == 0) {
            $budgetStatusStats['on_budget']++;
        } else {
            $budgetStatusStats['over_budget']++;
        }
    }
    
    $budgetStats['by_status'] = $budgetStatusStats;
    
    // Cost breakdown
    $costBreakdown = [
        'resource' => array_sum(array_column($projects, 'resource_costs')),
        'personnel' => array_sum(array_column($projects, 'personnel_costs')),
        'operational' => array_sum(array_column($projects, 'operational_costs')),
        'other' => array_sum(array_column($projects, 'other_costs'))
    ];
    
    $budgetStats['cost_breakdown'] = $costBreakdown;
    
    return [
        'projects' => array_values($projects),
        'total' => count($projects),
        'stats' => $budgetStats,
        'filters' => $filters
    ];
}

// Function to generate Timeline Analysis Report
function generateTimelineAnalysisReport($filters) {
    global $projectObj, $departmentObj;
    
    $projects = [];
    $timelineStatus = $filters['timeline_status'] ?? '';
    $milestoneFilter = $filters['milestone_filter'] ?? '';
    
    // Get projects based on filters
    $projectFilters = [];
    
    if (!empty($filters['department_id'])) {
        $projectFilters['department_id'] = $filters['department_id'];
    }
    
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $projectFilters['date_range'] = [
            'start' => $filters['start_date'],
            'end' => $filters['end_date']
        ];
    }
    
    if (!empty($filters['project_id'])) {
        // Single project
        $project = $projectObj->getById($filters['project_id']);
        if ($project) {
            $projects[] = $project;
        }
    } else {
        // Multiple projects
        $projects = $projectObj->getAll(0, 0, $projectFilters);
    }
    
    // Enhance project data with timeline information
    foreach ($projects as &$project) {
        // Add department name
        if (!empty($project['department_id'])) {
            $department = $departmentObj->getById($project['department_id']);
            $project['department_name'] = $department ? $department['name'] : 'N/A';
        } else {
            $project['department_name'] = 'N/A';
        }
        
        // Calculate timeline status
        if (!empty($project['start_date']) && !empty($project['end_date'])) {
            $startDate = strtotime($project['start_date']);
            $endDate = strtotime($project['end_date']);
            $currentDate = time();
            $totalDuration = $endDate - $startDate;
            $elapsedDuration = $currentDate - $startDate;
            
            if ($totalDuration > 0 && $elapsedDuration > 0) {
                $progress = min(100, max(0, ($elapsedDuration / $totalDuration) * 100));
                $project['progress'] = $progress;
                
                // Determine if project is on schedule, delayed, or ahead
                $expectedProgress = 0;
                
                if ($project['status'] === 'completed') {
                    $expectedProgress = 100;
                } elseif ($project['status'] === 'not_started') {
                    $expectedProgress = 0;
                } else {
                    $expectedProgress = ($elapsedDuration / $totalDuration) * 100;
                }
                
                $progressDifference = $progress - $expectedProgress;
                
                if (abs($progressDifference) <= 5) {
                    $project['timeline_status'] = 'on_schedule';
                } elseif ($progressDifference < -5) {
                    $project['timeline_status'] = 'delayed';
                } else {
                    $project['timeline_status'] = 'ahead';
                }
                
                $project['expected_progress'] = $expectedProgress;
                $project['progress_difference'] = $progressDifference;
            } else {
                $project['progress'] = 0;
                $project['timeline_status'] = 'not_started';
                $project['expected_progress'] = 0;
                $project['progress_difference'] = 0;
            }
        } else {
            $project['progress'] = 0;
            $project['timeline_status'] = 'unknown';
            $project['expected_progress'] = 0;
            $project['progress_difference'] = 0;
        }
        
        // Add milestones (simplified for demo)
        $project['milestones'] = [
            [
                'title' => 'Project Start',
                'date' => $project['start_date'],
                'status' => ($currentDate >= strtotime($project['start_date'])) ? 'completed' : 'upcoming'
            ]
        ];
        
        if (!empty($project['end_date'])) {
            $project['milestones'][] = [
                'title' => 'Project End',
                'date' => $project['end_date'],
                'status' => ($currentDate >= strtotime($project['end_date'])) ? 'completed' : 'upcoming'
            ];
        }
        
        // Add intermediate milestones (simplified for demo)
        if (!empty($project['start_date']) && !empty($project['end_date'])) {
            $startDate = strtotime($project['start_date']);
            $endDate = strtotime($project['end_date']);
            $duration = $endDate - $startDate;
            
            // Add 25% milestone
            $milestone25Date = date('Y-m-d', $startDate + ($duration * 0.25));
            $project['milestones'][] = [
                'title' => '25% Completion',
                'date' => $milestone25Date,
                'status' => ($currentDate >= strtotime($milestone25Date)) ? 'completed' : (($currentDate < strtotime($milestone25Date) && $currentDate > strtotime($milestone25Date) - 86400 * 7) ? 'upcoming' : (($currentDate > strtotime($milestone25Date) + 86400 * 7) ? 'overdue' : 'upcoming'))
            ];
            
            // Add 50% milestone
            $milestone50Date = date('Y-m-d', $startDate + ($duration * 0.5));
            $project['milestones'][] = [
                'title' => '50% Completion',
                'date' => $milestone50Date,
                'status' => ($currentDate >= strtotime($milestone50Date)) ? 'completed' : (($currentDate < strtotime($milestone50Date) && $currentDate > strtotime($milestone50Date) - 86400 * 7) ? 'upcoming' : (($currentDate > strtotime($milestone50Date) + 86400 * 7) ? 'overdue' : 'upcoming'))
            ];
            
            // Add 75% milestone
            $milestone75Date = date('Y-m-d', $startDate + ($duration * 0.75));
            $project['milestones'][] = [
                'title' => '75% Completion',
                'date' => $milestone75Date,
                'status' => ($currentDate >= strtotime($milestone75Date)) ? 'completed' : (($currentDate < strtotime($milestone75Date) && $currentDate > strtotime($milestone75Date) - 86400 * 7) ? 'upcoming' : (($currentDate > strtotime($milestone75Date) + 86400 * 7) ? 'overdue' : 'upcoming'))
            ];
        }
    }
    
    // Filter projects based on timeline status
    if (!empty($timelineStatus)) {
        $projects = array_filter($projects, function($project) use ($timelineStatus) {
            return $project['timeline_status'] === $timelineStatus;
        });
    }
    
    // Filter milestones based on milestone filter
    if (!empty($milestoneFilter)) {
        foreach ($projects as &$project) {
            $project['milestones'] = array_filter($project['milestones'], function($milestone) use ($milestoneFilter) {
                return $milestone['status'] === $milestoneFilter;
            });
        }
    }
    
    // Generate timeline statistics
    $timelineStats = [];
    
    // Count by timeline status
    $timelineStatusStats = [
        'on_schedule' => 0,
        'delayed' => 0,
        'ahead' => 0,
        'unknown' => 0
    ];
    
    foreach ($projects as $project) {
        $timelineStatusStats[$project['timeline_status']]++;
    }
    
    $timelineStats['by_status'] = $timelineStatusStats;
    
    // Count milestones by status
    $milestoneStats = [
        'completed' => 0,
        'upcoming' => 0,
        'overdue' => 0
    ];
    
    foreach ($projects as $project) {
        foreach ($project['milestones'] as $milestone) {
            $milestoneStats[$milestone['status']]++;
        }
    }
    
    $timelineStats['milestones'] = $milestoneStats;
    
    return [
        'projects' => array_values($projects),
        'total' => count($projects),
        'stats' => $timelineStats,
        'filters' => $filters
    ];
}

// Function to output PDF report
function outputPDFReport($title, $description, $data, $filters) {
    // In a real implementation, this would use a PDF library like FPDF or TCPDF
    // For this demo, we'll redirect to HTML with a message
    $_SESSION['info_message'] = "PDF generation would be implemented here. Showing HTML report instead.";
    // Continue to HTML output
}

// Function to output CSV report
function outputCSVReport($title, $data, $filters) {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $title . '.csv"');
    
    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Output CSV based on report type
    switch ($_GET['type']) {
        case 'project_status':
            // Output header row
            fputcsv($output, ['ID', 'Title', 'Status', 'Department', 'Start Date', 'End Date', 'Progress', 'Budget']);
            
            // Output data rows
            foreach ($data['projects'] as $project) {
                fputcsv($output, [
                    $project['id'],
                    $project['title'],
                    ucfirst(str_replace('_', ' ', $project['status'])),
                    $project['department_name'],
                    $project['start_date'],
                    $project['end_date'],
                    round($project['progress']) . '%',
                    '$' . number_format($project['budget'], 2)
                ]);
            }
            break;
            
        case 'resource_allocation':
            // Output header row
            fputcsv($output, ['ID', 'Name', 'Type', 'Department', 'Quantity', 'Available', 'Usage']);
            
            // Output data rows
            foreach ($data['resources'] as $resource) {
                fputcsv($output, [
                    $resource['id'],
                    $resource['name'],
                    ucfirst($resource['type']),
                    $resource['department_name'],
                    $resource['quantity'],
                    $resource['available_quantity'],
                    round($resource['usage_percentage']) . '%'
                ]);
            }
            break;
            
        // Add cases for other report types
        
        default:
            // Generic CSV output
            fputcsv($output, ['Report Type', $_GET['type']]);
            fputcsv($output, ['Generated On', date('Y-m-d H:i:s')]);
            fputcsv($output, ['Total Items', $data['total']]);
    }
    
    // Close the file pointer
    fclose($output);
    exit;
}

// Include header for HTML output
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="h3 mb-0 text-gray-800"><?php echo $reportTitle; ?></h1>
        <p class="mb-0"><?php echo $reportDescription; ?></p>
    </div>
    <div class="col-md-4 text-md-end">
        <a href="<?php echo $_SERVER['REQUEST_URI'] . '&format=pdf'; ?>" class="btn btn-primary">
            <i class="fas fa-file-pdf"></i> Download as PDF
        </a>
        <a href="<?php echo $_SERVER['REQUEST_URI'] . '&format=csv'; ?>" class="btn btn-success">
            <i class="fas fa-file-csv"></i> Download as CSV
        </a>
        <a href="report_form.php" class="btn btn-secondary">
            <i class="fas fa-edit"></i> Modify Report
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Report Parameters</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Report Type:</strong> <?php echo ucwords(str_replace('_', ' ', $reportType)); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Date Range:</strong> 
                        <?php 
                        if (!empty($startDate) && !empty($endDate)) {
                            echo date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate));
                        } elseif (!empty($startDate)) {
                            echo 'From ' . date('M d, Y', strtotime($startDate));
                        } elseif (!empty($endDate)) {
                            echo 'Until ' . date('M d, Y', strtotime($endDate));
                        } else {
                            echo 'All Time';
                        }
                        ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Project:</strong> 
                        <?php 
                        if (!empty($projectId)) {
                            $project = $projectObj->getById($projectId);
                            echo $project ? htmlspecialchars($project['title']) : 'N/A';
                        } else {
                            echo 'All Projects';
                        }
                        ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Department:</strong> 
                        <?php 
                        if (!empty($departmentId)) {
                            $department = $departmentObj->getById($departmentId);
                            echo $department ? htmlspecialchars($department['name']) : 'N/A';
                        } else {
                            echo 'All Departments';
                        }
                        ?>
                    </div>
                </div>
                
                <?php if (!empty($filters)): ?>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <strong>Additional Filters:</strong>
                            <ul class="list-inline">
                                <?php foreach ($filters as $key => $value): ?>
                                    <?php if (!in_array($key, ['type', 'format', 'start_date', 'end_date', 'project_id', 'department_id']) && !empty($value)): ?>
                                        <li class="list-inline-item badge bg-info">
                                            <?php echo ucwords(str_replace('_', ' ', $key)); ?>: <?php echo ucwords(str_replace('_', ' ', $value)); ?>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Output report content based on type
switch ($reportType) {
    case 'project_status':
        include 'reports/project_status.php';
        break;
    case 'resource_allocation':
        include 'reports/resource_allocation.php';
        break;
    case 'document_summary':
        include 'reports/document_summary.php';
        break;
    case 'patent_tracking':
        include 'reports/patent_tracking.php';
        break;
    case 'budget_spending':
        include 'reports/budget_spending.php';
        break;
    case 'timeline_analysis':
        include 'reports/timeline_analysis.php';
        break;
    default:
        echo '<div class="alert alert-danger">Invalid report type.</div>';
}
?>

<?php
// Include footer
include 'includes/footer.php';
?>