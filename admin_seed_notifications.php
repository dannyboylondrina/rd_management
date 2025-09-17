<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/classes/Notification.php';
require_once __DIR__ . '/classes/User.php';

$note = new Notification();
$user = new User();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    if ($targetUserId > 0) {
        $title = 'Test notification';
        $msg = 'This is a seeded notification for testing.';
        $id = $note->createNotification($targetUserId, $title, $msg, 'info', null, null);
        $message = $id ? 'Seeded 1 notification to user ' . $targetUserId : 'Failed to seed notification.';
    }
}

$users = $user->getAll('username', 'ASC');
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Admin: Seed Notification</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Select User</label>
                    <select class="form-select" name="user_id" required>
                        <option value="">Choose...</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username'] . ' - ' . $u['first_name'] . ' ' . $u['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Seed Notification</button>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>





