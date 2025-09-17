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

// Initialize Notification object
$notificationObj = new Notification();

// Process actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Mark all as read
        if ($_POST['action'] === 'mark_all_read') {
            $notificationObj->markAllAsRead($_SESSION['user_id']);
            $_SESSION['success_message'] = "All notifications marked as read.";
            header("Location: notifications.php");
            exit;
        }
        
        // Delete all
        if ($_POST['action'] === 'delete_all') {
            $notificationObj->deleteAllForUser($_SESSION['user_id']);
            $_SESSION['success_message'] = "All notifications deleted.";
            header("Location: notifications.php");
            exit;
        }
        
        // Mark as read
        if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
            $notificationId = (int)$_POST['notification_id'];
            $notificationObj->markAsRead($notificationId);
            $_SESSION['success_message'] = "Notification marked as read.";
            header("Location: notifications.php");
            exit;
        }
        
        // Delete notification
        if ($_POST['action'] === 'delete' && isset($_POST['notification_id'])) {
            $notificationId = (int)$_POST['notification_id'];
            $notificationObj->delete($notificationId);
            $_SESSION['success_message'] = "Notification deleted.";
            header("Location: notifications.php");
            exit;
        }
    }
}

// Get page parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get notifications
$notifications = $notificationObj->getForUser($_SESSION['user_id'], false, $limit, $offset);

// Get total count for pagination
$totalCount = $notificationObj->count(['user_id' => $_SESSION['user_id']]);
$totalPages = ceil($totalCount / $limit);

// Get unread count
$unreadCount = $notificationObj->countUnread($_SESSION['user_id']);

// Include header
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Notifications</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Notifications</h6>
                <div>
                    <?php if ($unreadCount > 0): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($totalCount > 0): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="delete_all">
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete all notifications?');">
                                <i class="fas fa-trash"></i> Delete All
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                        <p class="lead text-muted">You have no notifications.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="list-group-item list-group-item-action <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1 <?php echo $notification['is_read'] ? '' : 'fw-bold'; ?>">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </h5>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y, g:i a', strtotime($notification['created_at'])); ?>
                                    </small>
                                </div>
                                <p class="mb-1">
                                    <?php echo htmlspecialchars(substr($notification['message'], 0, 150)) . (strlen($notification['message']) > 150 ? '...' : ''); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <a href="notification_detail.php?id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <div>
                                        <?php if (!$notification['is_read']): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-check"></i> Mark as Read
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this notification?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Notifications pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>