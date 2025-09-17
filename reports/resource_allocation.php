<?php
// This file is included by generate_report.php
// $reportData contains the report data

// Extract data
$resources = $reportData['resources'] ?? [];
$total = $reportData['total'] ?? 0;
$stats = $reportData['stats'] ?? [];
?>

<!-- Report Summary -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Resource Summary</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo $total; ?></div>
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Resources</div>
                    </div>
                    
                    <?php
                    // Count resources by type
                    $typeCounts = [
                        'equipment' => 0,
                        'personnel' => 0,
                        'facility' => 0,
                        'software' => 0,
                        'other' => 0
                    ];
                    
                    // Count available resources
                    $availableCount = 0;
                    
                    foreach ($resources as $resource) {
                        if (isset($typeCounts[$resource['type']])) {
                            $typeCounts[$resource['type']]++;
                        }
                        
                        if ($resource['is_available']) {
                            $availableCount++;
                        }
                    }
                    ?>
                    
                    <div class="col-md-3 text-center">
                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo $availableCount; ?></div>
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Available Resources</div>
                    </div>
                    
                    <div class="col-md-3 text-center">
                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo $total - $availableCount; ?></div>
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Unavailable Resources</div>
                    </div>
                    
                    <div class="col-md-3 text-center">
                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo array_sum(array_map(function($resource) { return $resource['total_allocations']; }, $resources)); ?></div>
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Allocations</div>
                    </div>
                </div>
                
                <?php if ($total > 0): ?>
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h5>Resource Types</h5>
                            <div class="progress" style="height: 25px;">
                                <?php
                                $typeColors = [
                                    'equipment' => 'bg-primary',
                                    'personnel' => 'bg-success',
                                    'facility' => 'bg-info',
                                    'software' => 'bg-warning',
                                    'other' => 'bg-secondary'
                                ];
                                
                                foreach ($typeCounts as $type => $count) {
                                    $percentage = ($total > 0) ? ($count / $total) * 100 : 0;
                                    if ($percentage > 0):
                                ?>
                                    <div class="progress-bar <?php echo $typeColors[$type]; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100" title="<?php echo ucfirst($type); ?>: <?php echo $count; ?> resources">
                                        <?php if ($percentage >= 5): ?>
                                            <?php echo ucfirst($type); ?> (<?php echo $count; ?>)
                                        <?php endif; ?>
                                    </div>
                                <?php
                                    endif;
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Availability Status</h5>
                            <div class="progress" style="height: 25px;">
                                <?php
                                $availablePercentage = ($total > 0) ? ($availableCount / $total) * 100 : 0;
                                $unavailablePercentage = 100 - $availablePercentage;
                                ?>
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $availablePercentage; ?>%;" aria-valuenow="<?php echo $availablePercentage; ?>" aria-valuemin="0" aria-valuemax="100" title="Available: <?php echo $availableCount; ?> resources">
                                    <?php if ($availablePercentage >= 5): ?>
                                        Available (<?php echo $availableCount; ?>)
                                    <?php endif; ?>
                                </div>
                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $unavailablePercentage; ?>%;" aria-valuenow="<?php echo $unavailablePercentage; ?>" aria-valuemin="0" aria-valuemax="100" title="Unavailable: <?php echo $total - $availableCount; ?> resources">
                                    <?php if ($unavailablePercentage >= 5): ?>
                                        Unavailable (<?php echo $total - $availableCount; ?>)
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($filters['include_usage_charts']) && $filters['include_usage_charts'] && $total > 0): ?>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <h5>Resource Usage Overview</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Resource Type</th>
                                            <th>Total Quantity</th>
                                            <th>Allocated</th>
                                            <th>Available</th>
                                            <th>Usage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $typeStats = [];
                                        
                                        // Initialize type stats
                                        foreach ($typeCounts as $type => $count) {
                                            $typeStats[$type] = [
                                                'total_quantity' => 0,
                                                'allocated_quantity' => 0,
                                                'available_quantity' => 0
                                            ];
                                        }
                                        
                                        // Calculate type stats
                                        foreach ($resources as $resource) {
                                            $type = $resource['type'];
                                            $typeStats[$type]['total_quantity'] += $resource['quantity'];
                                            $typeStats[$type]['allocated_quantity'] += $resource['total_allocated_quantity'] ?? 0;
                                            $typeStats[$type]['available_quantity'] += $resource['available_quantity'] ?? ($resource['quantity'] - ($resource['total_allocated_quantity'] ?? 0));
                                        }
                                        
                                        foreach ($typeStats as $type => $stats):
                                            if ($stats['total_quantity'] > 0):
                                                $usagePercentage = ($stats['allocated_quantity'] / $stats['total_quantity']) * 100;
                                        ?>
                                            <tr>
                                                <td><?php echo ucfirst($type); ?></td>
                                                <td><?php echo $stats['total_quantity']; ?></td>
                                                <td><?php echo $stats['allocated_quantity']; ?></td>
                                                <td><?php echo $stats['available_quantity']; ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar <?php echo $usagePercentage >= 90 ? 'bg-danger' : ($usagePercentage >= 70 ? 'bg-warning' : 'bg-success'); ?>" role="progressbar" style="width: <?php echo $usagePercentage; ?>%;" aria-valuenow="<?php echo $usagePercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                            <?php echo round($usagePercentage); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php
                                            endif;
                                        endforeach;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Resources List -->
<div class="row">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Resources List</h6>
            </div>
            <div class="card-body">
                <?php if (empty($resources)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No resources found matching your criteria.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Department</th>
                                    <th>Quantity</th>
                                    <th>Usage</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resources as $resource): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($resource['name']); ?></strong>
                                            <?php if (!empty($resource['description'])): ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars(substr($resource['description'], 0, 100)) . (strlen($resource['description']) > 100 ? '...' : ''); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge resource-type-<?php echo $resource['type']; ?>">
                                                <?php echo ucfirst($resource['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($resource['department_name']); ?></td>
                                        <td>
                                            <?php echo $resource['quantity']; ?>
                                            <?php if (!empty($resource['unit'])): ?>
                                                <?php echo htmlspecialchars($resource['unit']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <?php
                                                $usagePercentage = $resource['usage_percentage'] ?? 0;
                                                $usageColor = $usagePercentage >= 90 ? 'bg-danger' : ($usagePercentage >= 70 ? 'bg-warning' : 'bg-success');
                                                ?>
                                                <div class="progress-bar <?php echo $usageColor; ?>" role="progressbar" style="width: <?php echo $usagePercentage; ?>%;" aria-valuenow="<?php echo $usagePercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo round($usagePercentage); ?>%
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $resource['available_quantity'] ?? ($resource['quantity'] - ($resource['total_allocated_quantity'] ?? 0)); ?> available
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($resource['is_available']): ?>
                                                <span class="badge bg-success">Available</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Not Available</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    
                                    <?php if (!empty($resource['allocations'])): ?>
                                        <tr>
                                            <td colspan="6" class="bg-light">
                                                <h6 class="font-weight-bold">Allocation History</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Project</th>
                                                                <th>Quantity</th>
                                                                <th>Allocation Date</th>
                                                                <th>Return Date</th>
                                                                <th>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($resource['allocations'] as $allocation): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($allocation['project_title']); ?></td>
                                                                    <td><?php echo $allocation['quantity']; ?></td>
                                                                    <td><?php echo date('M d, Y', strtotime($allocation['allocation_date'])); ?></td>
                                                                    <td>
                                                                        <?php echo !empty($allocation['return_date']) ? date('M d, Y', strtotime($allocation['return_date'])) : 'Not returned'; ?>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge <?php echo $allocation['status'] === 'allocated' ? 'bg-success' : ($allocation['status'] === 'returned' ? 'bg-secondary' : 'bg-warning'); ?>">
                                                                            <?php echo ucfirst($allocation['status']); ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
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