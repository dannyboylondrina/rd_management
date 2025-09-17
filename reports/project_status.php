<?php
// This file is included by generate_report.php
// $reportData contains the report data

// Extract data
$projects = $reportData['projects'] ?? [];
$total = $reportData['total'] ?? 0;
?>

<!-- Report Summary -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Report Summary</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo $total; ?></div>
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Projects</div>
                    </div>
                    
                    <?php
                    // Count projects by status
                    $statusCounts = [
                        'not_started' => 0,
                        'in_progress' => 0,
                        'on_hold' => 0,
                        'completed' => 0,
                        'cancelled' => 0
                    ];
                    
                    foreach ($projects as $project) {
                        if (isset($statusCounts[$project['status']])) {
                            $statusCounts[$project['status']]++;
                        }
                    }
                    ?>
                    
                    <div class="col-md-3 text-center">
                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo $statusCounts['in_progress']; ?></div>
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">In Progress</div>
                    </div>
                    
                    <div class="col-md-3 text-center">
                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo $statusCounts['completed']; ?></div>
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                    </div>
                    
                    <div class="col-md-3 text-center">
                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo $statusCounts['on_hold']; ?></div>
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">On Hold</div>
                    </div>
                </div>
                
                <?php if ($total > 0): ?>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <h5>Status Distribution</h5>
                            <div class="progress" style="height: 25px;">
                                <?php
                                $statusColors = [
                                    'not_started' => 'bg-secondary',
                                    'in_progress' => 'bg-info',
                                    'on_hold' => 'bg-warning',
                                    'completed' => 'bg-success',
                                    'cancelled' => 'bg-danger'
                                ];
                                
                                foreach ($statusCounts as $status => $count) {
                                    $percentage = ($total > 0) ? ($count / $total) * 100 : 0;
                                    if ($percentage > 0):
                                ?>
                                    <div class="progress-bar <?php echo $statusColors[$status]; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100" title="<?php echo ucfirst(str_replace('_', ' ', $status)); ?>: <?php echo $count; ?> projects">
                                        <?php if ($percentage >= 5): ?>
                                            <?php echo ucfirst(str_replace('_', ' ', $status)); ?> (<?php echo $count; ?>)
                                        <?php endif; ?>
                                    </div>
                                <?php
                                    endif;
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Projects List -->
<div class="row">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Projects List</h6>
            </div>
            <div class="card-body">
                <?php if (empty($projects)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No projects found matching your criteria.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Timeline</th>
                                    <th>Progress</th>
                                    <th>Budget</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($project['title']); ?></strong>
                                            <?php if (!empty($project['description'])): ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars(substr($project['description'], 0, 100)) . (strlen($project['description']) > 100 ? '...' : ''); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($project['department_name']); ?></td>
                                        <td>
                                            <span class="badge project-status-<?php echo $project['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($project['start_date']) && !empty($project['end_date'])): ?>
                                                <?php echo date('M d, Y', strtotime($project['start_date'])); ?> to <?php echo date('M d, Y', strtotime($project['end_date'])); ?>
                                            <?php elseif (!empty($project['start_date'])): ?>
                                                Starts on <?php echo date('M d, Y', strtotime($project['start_date'])); ?>
                                            <?php elseif (!empty($project['end_date'])): ?>
                                                Ends on <?php echo date('M d, Y', strtotime($project['end_date'])); ?>
                                            <?php else: ?>
                                                Not specified
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <?php
                                                $progressColor = 'bg-info';
                                                if ($project['progress'] >= 100) {
                                                    $progressColor = 'bg-success';
                                                } elseif ($project['status'] === 'on_hold') {
                                                    $progressColor = 'bg-warning';
                                                } elseif ($project['status'] === 'cancelled') {
                                                    $progressColor = 'bg-danger';
                                                }
                                                ?>
                                                <div class="progress-bar <?php echo $progressColor; ?>" role="progressbar" style="width: <?php echo $project['progress']; ?>%;" aria-valuenow="<?php echo $project['progress']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo round($project['progress']); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($project['budget'])): ?>
                                                $<?php echo number_format($project['budget'], 2); ?>
                                            <?php else: ?>
                                                Not specified
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    
                                    <?php
                                    // Show project details if requested
                                    $showMembers = isset($filters['include_members']) && $filters['include_members'] && !empty($project['members']);
                                    $showResources = isset($filters['include_resources']) && $filters['include_resources'] && !empty($project['resources']);
                                    $showDocuments = isset($filters['include_documents']) && $filters['include_documents'] && !empty($project['documents']);
                                    
                                    if ($showMembers || $showResources || $showDocuments):
                                    ?>
                                        <tr>
                                            <td colspan="6" class="bg-light">
                                                <div class="row">
                                                    <?php if ($showMembers): ?>
                                                        <div class="col-md-4">
                                                            <h6 class="font-weight-bold">Project Members</h6>
                                                            <ul class="list-group list-group-flush">
                                                                <?php foreach ($project['members'] as $member): ?>
                                                                    <li class="list-group-item bg-light px-0 py-1">
                                                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                                                        <?php if (!empty($member['role'])): ?>
                                                                            <small class="text-muted">(<?php echo htmlspecialchars($member['role']); ?>)</small>
                                                                        <?php endif; ?>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($showResources): ?>
                                                        <div class="col-md-4">
                                                            <h6 class="font-weight-bold">Allocated Resources</h6>
                                                            <ul class="list-group list-group-flush">
                                                                <?php foreach ($project['resources'] as $resource): ?>
                                                                    <li class="list-group-item bg-light px-0 py-1">
                                                                        <?php echo htmlspecialchars($resource['name']); ?>
                                                                        <small class="text-muted">(<?php echo $resource['allocated_quantity']; ?> <?php echo htmlspecialchars($resource['unit'] ?? ''); ?>)</small>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($showDocuments): ?>
                                                        <div class="col-md-4">
                                                            <h6 class="font-weight-bold">Documents</h6>
                                                            <ul class="list-group list-group-flush">
                                                                <?php foreach ($project['documents'] as $document): ?>
                                                                    <li class="list-group-item bg-light px-0 py-1">
                                                                        <?php echo htmlspecialchars($document['title']); ?>
                                                                        <small class="text-muted">(<?php echo ucfirst(str_replace('_', ' ', $document['type'])); ?>)</small>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>