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
require_once 'classes/Notification.php';
require_once 'classes/Project.php';
require_once 'classes/Document.php';
require_once 'classes/User.php';

// Get notification ID from URL
$notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Initialize Notification object
$notificationObj = new Notification();

// Get notification details
$notification = $notificationObj->getById($notificationId);

// Check if notification exists and belongs to the current user
if (!$notification || $notification['user_id'] != $_SESSION['user_id']) {
    $_SESSION['error_message'] = "Notification not found or access denied.";
    header("Location: index.php");
    exit;
}

// Mark notification as read if it's unread
if (!$notification['is_read']) {
    $notificationObj->markAsRead($notificationId);
    $notification['is_read'] = true;
}

// Get related entity details if available
$relatedEntity = null;
$relatedEntityType = '';
$relatedEntityUrl = '';

if (!empty($notification['related_id']) && !empty($notification['related_type'])) {
    switch ($notification['related_type']) {
        case 'project':
            $projectObj = new Project();
            $relatedEntity = $projectObj->getById($notification['related_id']);
            $relatedEntityType = 'Project';
            $relatedEntityUrl = 'project_detail.php?id=' . $notification['related_id'];
            break;
        
        case 'document':
            $documentObj = new Document();
            $relatedEntity = $documentObj->getById($notification['related_id']);
            $relatedEntityType = 'Document';
            $relatedEntityUrl = 'document_detail.php?id=' . $notification['related_id'];
            break;
        
        case 'user':
            $userObj = new User();
            $relatedEntity = $userObj->getById($notification['related_id']);
            $relatedEntityType = 'User';
            $relatedEntityUrl = 'user_detail.php?id=' . $notification['related_id'];
            break;
        
        // Add more cases as needed for other entity types
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
                <li class="breadcrumb-item"><a href="notifications.php">Notifications</a></li>
                <li class="breadcrumb-item active" aria-current="page">Notification Detail</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Notification Detail</h6>
                <div>
                    <a href="notifications.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Notifications
                    </a>
                </div>
            </div>
            <div class="card-body">
                <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                <p class="text-muted">
                    <small>
                        <i class="fas fa-clock"></i> <?php echo date('F j, Y, g:i a', strtotime($notification['created_at'])); ?>
                        <?php if ($notification['is_read']): ?>
                            <span class="badge bg-secondary ms-2">Read</span>
                        <?php else: ?>
                            <span class="badge bg-primary ms-2">Unread</span>
                        <?php endif; ?>
                    </small>
                </p>
                <hr>
                <div class="notification-message">
                    <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                </div>
                
                <?php if ($relatedEntity): ?>
                <hr>
                <div class="related-entity mt-4">
                    <h5>Related <?php echo htmlspecialchars($relatedEntityType); ?></h5>
                    <div class="card">
                        <div class="card-body">
                            <?php if ($relatedEntityType === 'Project'): ?>
                                <h5 class="card-title"><?php echo htmlspecialchars($relatedEntity['title']); ?></h5>
                                <p class="card-text">
                                    <?php 
                                    $desc = $relatedEntity['description'] ?? '';
                                    echo htmlspecialchars(substr($desc, 0, 200)) . (strlen($desc) > 200 ? '...' : '');
                                    ?>
                                </p>
                                <p class="card-text">
                                    <small class="text-muted">
                                        Status: <span class="project-status status-<?php echo $relatedEntity['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $relatedEntity['status'])); ?>
                                        </span>
                                    </small>
                                </p>
                            <?php elseif ($relatedEntityType === 'Document'): ?>
                                <h5 class="card-title"><?php echo htmlspecialchars($relatedEntity['title']); ?></h5>
                                <p class="card-text">
                                    <?php 
                                    $desc = $relatedEntity['description'] ?? '';
                                    echo htmlspecialchars(substr($desc, 0, 200)) . (strlen($desc) > 200 ? '...' : '');
                                    ?>
                                </p>
                                <p class="card-text">
                                    <small class="text-muted">
                                        Type: <?php echo ucfirst(str_replace('_', ' ', $relatedEntity['type'])); ?>
                                    </small>
                                </p>
                            <?php elseif ($relatedEntityType === 'User'): ?>
                                <h5 class="card-title"><?php echo htmlspecialchars($relatedEntity['first_name'] . ' ' . $relatedEntity['last_name']); ?></h5>
                                <p class="card-text">
                                    <small class="text-muted">
                                        Email: <?php echo htmlspecialchars($relatedEntity['email']); ?>
                                    </small>
                                </p>
                            <?php endif; ?>
                            
                            <a href="<?php echo htmlspecialchars($relatedEntityUrl); ?>" class="btn btn-primary">
                                View <?php echo htmlspecialchars($relatedEntityType); ?>
                            </a>
                        </div>
                    </div>
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