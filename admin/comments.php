<?php
require_once '../includes/config.php';
require_once '../includes/db-connection.php';
require_once '../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$admin_username = $_SESSION['admin_username'] ?? 'Admin';
$message = '';
$error = '';

$conn->query("CREATE TABLE IF NOT EXISTS `comments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `post_id` int(11) NOT NULL,
    `parent_id` int(11) DEFAULT NULL,
    `name` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `comment` text NOT NULL,
    `status` enum('pending','approved','spam') DEFAULT 'pending',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `post_id` (`post_id`),
    KEY `parent_id` (`parent_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Check if parent_id column exists, add if not
$column_check = $conn->query("SHOW COLUMNS FROM `comments` LIKE 'parent_id'");
if ($column_check->num_rows == 0) {
    $conn->query("ALTER TABLE `comments` ADD `parent_id` int(11) DEFAULT NULL AFTER `post_id`, ADD KEY `parent_id` (`parent_id`)");
}

if (isset($_POST['comment_action'], $_POST['comment_id'])) {
    $comment_id = (int) $_POST['comment_id'];
    $comment_action = $_POST['comment_action'];

    if (in_array($comment_action, ['approve', 'pending', 'spam'], true)) {
        $new_status = $comment_action === 'approve' ? 'approved' : $comment_action;
        $stmt = $conn->prepare("UPDATE comments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $comment_id);
        if ($stmt->execute()) {
            $message = 'Comment updated successfully.';
        } else {
            $error = 'Failed to update comment.';
        }
        $stmt->close();
    } elseif ($comment_action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->bind_param("i", $comment_id);
        if ($stmt->execute()) {
            $message = 'Comment deleted successfully.';
        } else {
            $error = 'Failed to delete comment.';
        }
        $stmt->close();
    } elseif ($comment_action === 'reply') {
        $reply_text = trim($_POST['reply_text'] ?? '');
        if (!empty($reply_text)) {
            // Get admin name from session
            $admin_name = $_SESSION['admin_username'] ?? 'Admin';
            $admin_email = 'admin@' . $_SERVER['HTTP_HOST']; // Default admin email
            
            // Get the original comment to get post_id
            $get_comment_stmt = $conn->prepare("SELECT post_id FROM comments WHERE id = ?");
            $get_comment_stmt->bind_param("i", $comment_id);
            $get_comment_stmt->execute();
            $original_comment = $get_comment_stmt->get_result()->fetch_assoc();
            $get_comment_stmt->close();
            
            if ($original_comment) {
                $insert_reply = $conn->prepare("INSERT INTO comments (post_id, parent_id, name, email, comment, status, created_at) VALUES (?, ?, ?, ?, ?, 'approved', NOW())");
                $insert_reply->bind_param("iisss", $original_comment['post_id'], $comment_id, $admin_name, $admin_email, $reply_text);
                if ($insert_reply->execute()) {
                    $message = 'Reply posted successfully.';
                } else {
                    $error = 'Failed to post reply.';
                }
                $insert_reply->close();
            } else {
                $error = 'Original comment not found.';
            }
        } else {
            $error = 'Reply text cannot be empty.';
        }
    }
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['comment_ids']) && is_array($_POST['comment_ids'])) {
    $comment_ids = array_filter(array_map('intval', $_POST['comment_ids']));
    $bulk_action = $_POST['bulk_action'];

    if (empty($comment_ids)) {
        $error = 'No comments selected.';
    } elseif ($bulk_action === 'delete') {
        $del = $conn->prepare("DELETE FROM comments WHERE id = ? OR parent_id = ?");
        foreach ($comment_ids as $cid) {
            $del->bind_param("ii", $cid, $cid);
            $del->execute();
        }
        $del->close();
        $message = count($comment_ids) . ' comment(s) deleted successfully.';
        logAdminAction('bulk_delete_comments', 'Deleted ' . count($comment_ids) . ' comments');
    } elseif (in_array($bulk_action, ['approve', 'pending', 'spam'], true)) {
        $new_status = $bulk_action === 'approve' ? 'approved' : $bulk_action;
        $ids = implode(',', $comment_ids);
        $conn->query("UPDATE comments SET status = '$new_status' WHERE id IN ($ids)");
        $message = count($comment_ids) . ' comment(s) marked as ' . $new_status . '.';
        logAdminAction('bulk_status_comments', 'Bulk ' . $new_status . ' on ' . count($comment_ids) . ' comments');
    }
}

$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];
$types = '';

if ($status_filter && in_array($status_filter, ['pending', 'approved', 'spam'], true)) {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search !== '') {
    $where_conditions[] = "(c.name LIKE ? OR c.email LIKE ? OR c.comment LIKE ? OR p.title LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
// Handle "delete all matching current filter" bulk action
if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete_all') {
    error_log('[comments.php] delete_all triggered. GET: ' . json_encode($_GET) . ' POST: ' . json_encode($_POST));
    $id_sql = "SELECT c.id FROM comments c LEFT JOIN posts p ON c.post_id = p.id $where_clause";
    error_log('[comments.php] delete_all SQL: ' . $id_sql . ' params: ' . json_encode($params) . ' types: ' . $types);

    $id_stmt = $conn->prepare($id_sql);
    if (!$id_stmt) {
        $error = 'Failed to prepare delete_all query: ' . $conn->error;
        error_log('[comments.php] delete_all prepare error: ' . $conn->error);
    } else {
        if (!empty($params)) {
            $bind_result = $id_stmt->bind_param($types, ...$params);
            if (!$bind_result) {
                $error = 'Failed to bind params: ' . $id_stmt->error;
                error_log('[comments.php] delete_all bind error: ' . $id_stmt->error);
            }
        }
        $exec_result = $id_stmt->execute();
        if (!$exec_result) {
            $error = 'Failed to execute delete_all query: ' . $id_stmt->error;
            error_log('[comments.php] delete_all execute error: ' . $id_stmt->error);
        } else {
            $matching_ids = [];
            $id_res = $id_stmt->get_result();
            while ($r = $id_res->fetch_assoc()) { $matching_ids[] = (int) $r['id']; }
            $id_stmt->close();
            error_log('[comments.php] delete_all found IDs: ' . json_encode($matching_ids));

            if (!empty($matching_ids)) {
                $in_list = implode(',', $matching_ids);
                $del_sql = "DELETE FROM comments WHERE id IN ($in_list) OR parent_id IN ($in_list)";
                error_log('[comments.php] delete_all DELETE SQL: ' . $del_sql);
                $del_result = $conn->query($del_sql);
                if ($del_result === false) {
                    $error = 'Delete query failed: ' . $conn->error;
                    error_log('[comments.php] delete_all DELETE error: ' . $conn->error);
                } else {
                    $affected = $conn->affected_rows;
                    $message = count($matching_ids) . ' comment(s) (and their replies) deleted successfully. Affected rows: ' . $affected;
                    logAdminAction('bulk_delete_all_comments', 'Deleted all ' . count($matching_ids) . ' comments matching current filter');
                    error_log('[comments.php] delete_all success: ' . $message);
                }
            } else {
                $message = 'No comments match the current filter.';
                error_log('[comments.php] delete_all: no matching comments');
            }
        }
    }
}

$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'spam' => 0
];
$stats_result = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END) AS spam
    FROM comments");
if ($stats_result) {
    $stats = array_merge($stats, $stats_result->fetch_assoc() ?: []);
}

$count_sql = "SELECT COUNT(*) AS total
              FROM comments c
              LEFT JOIN posts p ON c.post_id = p.id
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_comments = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

$total_pages = max(1, (int) ceil($total_comments / $per_page));

$sql = "SELECT c.*, p.title AS post_title, p.slug AS post_slug,
        parent.name AS parent_name, parent.comment AS parent_comment
        FROM comments c
        LEFT JOIN posts p ON c.post_id = p.id
        LEFT JOIN comments parent ON c.parent_id = parent.id
        $where_clause
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?";
$query_params = $params;
$query_types = $types . 'ii';
$query_params[] = $per_page;
$query_params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($query_types, ...$query_params);
$stmt->execute();
$comments = $stmt->get_result();
$stmt->close();

function formatAdminDate($date) {
    return $date ? date('M j, Y g:i A', strtotime($date)) : '-';
}

$site_title = getSetting('site_title', 'Painlesslyf');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Comments - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-theme.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; color: #333; -webkit-font-smoothing: antialiased; }
        .admin-wrapper { display: flex; min-height: 100vh; width: 100%; position: relative; }
        .admin-sidebar { width: 280px; background: linear-gradient(135deg, #2c3e50 0%, #1e2b37 100%); color: white; height: 100vh; overflow-y: auto; box-shadow: 2px 0 10px rgba(0,0,0,0.1); flex-shrink: 0; position: sticky; top: 0; transition: transform 0.3s ease; z-index: 1000; }
        .admin-sidebar::-webkit-scrollbar { width: 6px; }
        .admin-sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        .admin-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 3px; }
        .sidebar-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); position: sticky; top: 0; background: linear-gradient(135deg, #2c3e50 0%, #1e2b37 100%); z-index: 5; }
        .sidebar-header img { max-width: 150px; margin-bottom: 15px; background: white; padding: 10px; border-radius: 10px; }
        .sidebar-header h3 { font-size: 18px; font-weight: 600; color: rgba(255,255,255,0.9); }
        .sidebar-header p { font-size: 14px; color: rgba(255,255,255,0.6); margin-top: 5px; }
        .sidebar-menu { padding: 20px 0 40px 0; }
        .sidebar-menu-label { padding: 10px 25px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.4); }
        .sidebar-menu-item { padding: 12px 25px; display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.3s; border-left: 4px solid transparent; }
        .sidebar-menu-item i { width: 20px; font-size: 18px; }
        .sidebar-menu-item:hover, .sidebar-menu-item.active { background: rgba(255,255,255,0.1); color: white; border-left-color: #4a7c59; }
        .sidebar-menu-item.active { background: rgba(74, 124, 89, 0.2); }
        .sidebar-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 15px 20px; }
        .mobile-menu-toggle { display: none; position: fixed; top: 15px; left: 15px; z-index: 1500; background: #4a7c59; color: white; width: 45px; height: 45px; border-radius: 12px; align-items: center; justify-content: center; cursor: pointer; border: none; font-size: 20px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1999; }
        .sidebar-overlay.active { display: block; }
        .admin-main { flex: 1; min-height: 100vh; padding: 30px; background: #f4f6f9; }
        .top-nav, .panel, .stat-card { background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .top-nav { padding: 15px 25px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .top-nav-title h1 { font-size: 24px; font-weight: 600; color: #333; }
        .top-nav-title p { color: #666; font-size: 14px; margin-top: 5px; }
        .top-nav-user { display: flex; align-items: center; gap: 20px; }
        .user-name { font-weight: 600; color: #333; }
        .user-role { font-size: 12px; color: #666; }
        .user-avatar { width: 45px; height: 45px; background: linear-gradient(135deg, #4a7c59, #2c4a3b); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { padding: 20px; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: linear-gradient(135deg, #4a7c59, #2c4a3b); }
        .stat-icon { width: 50px; height: 50px; background: rgba(74, 124, 89, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; }
        .stat-icon i { font-size: 24px; color: #4a7c59; }
        .stat-value { font-size: 28px; font-weight: 700; color: #333; margin-bottom: 5px; }
        .stat-label { color: #666; font-size: 13px; }
        .panel { padding: 20px; margin-bottom: 20px; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; }
        .form-control { padding: 10px 12px; border: 1px solid #ddd; border-radius: 10px; font-size: 14px; min-width: 180px; }
        .btn { padding: 10px 16px; border: none; border-radius: 10px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; }
        .btn-primary { background: #4a7c59; color: white; }
        .btn-secondary { background: #eef2f6; color: #445; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; color: white; }
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #1b5e20; }
        .alert-error { background: #fdecea; color: #b71c1c; }
        .comments-table { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th, td { padding: 14px 12px; border-bottom: 1px solid #eef2f6; text-align: left; vertical-align: top; }
        th { background: #f8fafc; font-size: 13px; color: #556; }
        .comment-text { max-width: 360px; white-space: pre-wrap; line-height: 1.5; color: #444; }
        .comment-meta { font-size: 12px; color: #667; line-height: 1.5; }
        .status-badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-spam { background: #f8d7da; color: #721c24; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .actions form { display: inline; }
        .post-link { color: #4a7c59; text-decoration: none; }
        .reply-indicator {
            color: #4a7c59;
            font-weight: 500;
            font-size: 11px;
        }

        .parent-comment {
            margin-bottom: 8px;
        }

        .reply-form textarea {
            margin-bottom: 8px;
        }
        .pagination { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 20px; }
        .pagination a { display: inline-flex; min-width: 40px; height: 40px; align-items: center; justify-content: center; text-decoration: none; border-radius: 10px; background: white; color: #445; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .pagination a.active { background: #4a7c59; color: white; }
        .empty-state { text-align: center; padding: 50px 20px; color: #667; }
        .empty-state i { font-size: 56px; margin-bottom: 15px; opacity: 0.45; }
        @media (max-width: 1024px) { .mobile-menu-toggle { display: flex; } .admin-sidebar { position: fixed; left: 0; top: 0; transform: translateX(-100%); z-index: 2000; width: 280px; } .admin-sidebar.sidebar-open { transform: translateX(0); } .admin-main { padding: 80px 20px 20px 20px; } }
    .bulk-actions { background:#f8f9fa; border-radius:12px; padding:15px; margin-bottom:15px; display:none; align-items:center; gap:12px; flex-wrap:wrap; }
.bulk-actions.visible { display:flex; }
.bulk-select-all { display:flex; align-items:center; gap:8px; }
.bulk-select-all input { width:18px; height:18px; cursor:pointer; }
.comment-checkbox { width:18px; height:18px; cursor:pointer; vertical-align:middle; }
</style>
</head>
<body>
    <button class="mobile-menu-toggle" id="mobileMenuToggle"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-wrapper">
        <?php $current_page = basename(__FILE__); include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="admin-main">
            <div class="top-nav">
                <div class="top-nav-title">
                    <h1>Blog Comments</h1>
                    <p>Review, approve, and moderate blog discussion</p>
                </div>
                <div class="top-nav-user">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($admin_username); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                    <div class="user-avatar"><i class="fas fa-user"></i></div>
                </div>
            </div>
            <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-comments"></i></div><div class="stat-value"><?php echo number_format($stats['total'] ?? 0); ?></div><div class="stat-label">Total Comments</div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-value"><?php echo number_format($stats['pending'] ?? 0); ?></div><div class="stat-label">Pending</div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-value"><?php echo number_format($stats['approved'] ?? 0); ?></div><div class="stat-label">Approved</div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-ban"></i></div><div class="stat-value"><?php echo number_format($stats['spam'] ?? 0); ?></div><div class="stat-label">Spam</div></div>
            </div>
            <div class="panel">
                <form method="GET" action="comments.php" class="filters">
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="spam" <?php echo $status_filter === 'spam' ? 'selected' : ''; ?>>Spam</option>
                    </select>
                    <input type="text" name="search" class="form-control" placeholder="Search name, email, post, or comment..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 260px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                    <a href="comments.php" class="btn btn-secondary">Reset</a>
                </form>
            </div>
            <div class="panel comments-table">
                <div class="bulk-actions" id="bulkActions">
    <div class="bulk-select-all">
        <input type="checkbox" id="selectAll">
        <label for="selectAll">Select All</label>
    </div>
    <select id="bulkActionSelect" class="form-control" style="width:auto;">
        <option value="">Bulk Actions</option>
        <option value="approve">Approve</option>
        <option value="pending">Mark Pending</option>
        <option value="spam">Mark Spam</option>
        <option value="delete">Delete</option>
        <option value="delete_all" style="color:#dc3545;">Delete All Matching</option>
    </select>
    <button type="button" class="btn btn-danger btn-sm" onclick="applyBulkAction()">Apply</button>
    <span id="selectedCount" style="font-size:12px;color:#667;"></span>
</div>
<form method="POST" action="" id="bulkSubmitForm" style="display:none;">
    <input type="hidden" name="bulk_action" id="bulkActionInput">
</form>
<table>
                    <thead>
                        <tr>
                            <th>Author</th>
                            <th>Post</th>
                            <th>Comment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($comments && $comments->num_rows > 0): ?>
                            <?php while ($comment = $comments->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="comment_ids[]" value="<?php echo (int) $comment['id']; ?>" class="comment-checkbox" style="margin-right:8px;vertical-align:middle;"> <strong><?php echo htmlspecialchars($comment['name']); ?></strong>
                                    <div class="comment-meta"><?php echo htmlspecialchars($comment['email']); ?></div>
                                    <?php if ($comment['parent_id']): ?>
                                    <div class="comment-meta reply-indicator">Reply to: <?php echo htmlspecialchars($comment['parent_name']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($comment['post_slug'])): ?>
                                    <a class="post-link" href="../blog-post.php?slug=<?php echo urlencode($comment['post_slug']); ?>" target="_blank"><?php echo htmlspecialchars($comment['post_title'] ?: 'View post'); ?></a>
                                    <?php else: ?>
                                    <span class="comment-meta"><?php echo htmlspecialchars($comment['post_title'] ?: 'Post unavailable'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="comment-text">
                                        <?php if ($comment['parent_id']): ?>
                                        <div class="parent-comment" style="border-left: 2px solid #ddd; padding-left: 10px; margin-bottom: 10px; font-style: italic; color: #666;">
                                            <small>Replying to: "<?php echo htmlspecialchars(substr($comment['parent_comment'], 0, 100)); ?><?php echo strlen($comment['parent_comment']) > 100 ? '..."' : '"'; ?></small>
                                        </div>
                                        <?php endif; ?>
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    </div>
                                </td>
                                <td><span class="status-badge status-<?php echo htmlspecialchars($comment['status']); ?>"><?php echo htmlspecialchars($comment['status']); ?></span></td>
                                <td><?php echo htmlspecialchars(formatAdminDate($comment['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if (!$comment['parent_id'] && $comment['status'] === 'approved'): ?>
                                        <button type="button" class="btn btn-primary reply-btn" data-comment-id="<?php echo (int) $comment['id']; ?>" data-comment-author="<?php echo htmlspecialchars($comment['name']); ?>">Reply</button>
                                        <?php endif; ?>
                                        <?php if ($comment['status'] !== 'approved'): ?>
                                        <form method="POST"><input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>"><input type="hidden" name="comment_action" value="approve"><button type="submit" class="btn btn-success">Approve</button></form>
                                        <?php endif; ?>
                                        <?php if ($comment['status'] !== 'pending'): ?>
                                        <form method="POST"><input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>"><input type="hidden" name="comment_action" value="pending"><button type="submit" class="btn btn-warning">Pending</button></form>
                                        <?php endif; ?>
                                        <?php if ($comment['status'] !== 'spam'): ?>
                                        <form method="POST"><input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>"><input type="hidden" name="comment_action" value="spam"><button type="submit" class="btn btn-secondary">Spam</button></form>
                                        <?php endif; ?>
                                        <form method="POST" onsubmit="return confirm('Delete this comment?');"><input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>"><input type="hidden" name="comment_action" value="delete"><button type="submit" class="btn btn-danger">Delete</button></form>
                                    </div>
                                    <?php if (!$comment['parent_id'] && $comment['status'] === 'approved'): ?>
                                    <div class="reply-form" id="reply-form-<?php echo (int) $comment['id']; ?>" style="display: none; margin-top: 15px;">
                                        <form method="POST">
                                            <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                                            <input type="hidden" name="comment_action" value="reply">
                                            <textarea name="reply_text" class="form-control" rows="3" placeholder="Write your reply..." required></textarea>
                                            <div style="margin-top: 10px;">
                                                <button type="submit" class="btn btn-primary">Post Reply</button>
                                                <button type="button" class="btn btn-secondary cancel-reply" data-comment-id="<?php echo (int) $comment['id']; ?>">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-comments"></i><p>No comments found for the current filter.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php $query_string = http_build_query(['status' => $status_filter, 'search' => $search, 'page' => $i]); ?>
                        <a href="comments.php?<?php echo $query_string; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const adminSidebar = document.getElementById('adminSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        function toggleSidebar() {
            adminSidebar.classList.toggle('sidebar-open');
            sidebarOverlay.classList.toggle('active');
            const icon = mobileMenuToggle.querySelector('i');
            icon.className = adminSidebar.classList.contains('sidebar-open') ? 'fas fa-times' : 'fas fa-bars';
            document.body.style.overflow = adminSidebar.classList.contains('sidebar-open') ? 'hidden' : '';
        }
        function closeSidebar() {
            adminSidebar.classList.remove('sidebar-open');
            sidebarOverlay.classList.remove('active');
            const icon = mobileMenuToggle.querySelector('i');
            icon.className = 'fas fa-bars';
            document.body.style.overflow = '';
        }
        if (mobileMenuToggle) { mobileMenuToggle.addEventListener('click', toggleSidebar); }
        if (sidebarOverlay) { sidebarOverlay.addEventListener('click', closeSidebar); }
        document.querySelectorAll('.sidebar-menu-item').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) { setTimeout(closeSidebar, 150); }
            });
        });
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    if (alert.parentNode) { alert.remove(); }
                }, 500);
            });
        }, 5000);

        // Reply functionality
        document.querySelectorAll('.reply-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const commentId = this.getAttribute('data-comment-id');
                const replyForm = document.getElementById('reply-form-' + commentId);
                if (replyForm) {
                    replyForm.style.display = replyForm.style.display === 'none' ? 'block' : 'none';
                }
            });
        });

        document.querySelectorAll('.cancel-reply').forEach(btn => {
            btn.addEventListener('click', function() {
                const commentId = this.getAttribute('data-comment-id');
                const replyForm = document.getElementById('reply-form-' + commentId);
                if (replyForm) {
                    replyForm.style.display = 'none';
                }
            });
        });
            // Bulk actions for comments
        var checkboxes = document.querySelectorAll('.comment-checkbox');
        var selectAll = document.getElementById('selectAll');
        var bulkActions = document.getElementById('bulkActions');
        var selectedCount = document.getElementById('selectedCount');

        function updateBulk() {
            var checked = document.querySelectorAll('.comment-checkbox:checked');
            var n = checked.length;
            if (bulkActions) { bulkActions.classList.toggle('visible', n > 0); }
            if (selectedCount) { selectedCount.textContent = n + ' selected'; }
            if (selectAll) { selectAll.checked = (n > 0 && n === checkboxes.length); }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                for (var i = 0; i < checkboxes.length; i++) { checkboxes[i].checked = this.checked; }
                updateBulk();
            });
        }
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].addEventListener('change', updateBulk);
        }

        function applyBulkAction() {
            var sel = document.getElementById('bulkActionSelect');
            var action = sel ? sel.value : '';
            if (!action) { alert('Please select a bulk action.'); return; }
            if (action === 'delete_all') {
                if (!confirm('Delete ALL comments matching the current filter? This cannot be undone. Replies to these comments will also be removed.')) { return; }
                var form = document.getElementById('bulkSubmitForm');
                var actionInput = document.getElementById('bulkActionInput');
                if (!form || !actionInput) { alert('Bulk form not available.'); return; }
                actionInput.value = action;
                var old = form.querySelectorAll('input[name="comment_ids[]"]');
                for (var j = 0; j < old.length; j++) { old[j].parentNode.removeChild(old[j]); }
                form.submit();
                return;
            }
            var checked = document.querySelectorAll('.comment-checkbox:checked');
            if (checked.length === 0) { alert('Please select at least one comment.'); return; }
            if (action === 'delete' && !confirm('Delete the selected ' + checked.length + ' comment(s)? Replies are removed too.')) return;
            var form = document.getElementById('bulkSubmitForm');
            var actionInput = document.getElementById('bulkActionInput');
            if (!form || !actionInput) { alert('Bulk form not available.'); return; }
            actionInput.value = action;
            var old = form.querySelectorAll('input[name="comment_ids[]"]');
            for (var j = 0; j < old.length; j++) { old[j].parentNode.removeChild(old[j]); }
            checked.forEach(function(cb) {
                var hi = document.createElement('input');
                hi.type = 'hidden';
                hi.name = 'comment_ids[]';
                hi.value = cb.value;
                form.appendChild(hi);
            });
            form.submit();
        }
    </script>
</body>
</html>
