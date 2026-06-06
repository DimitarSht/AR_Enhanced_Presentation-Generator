<?php
require_once __DIR__ . '/../backend/auth.php';
requireAdmin();

$tab = $_GET['tab'] ?? 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_presentation'])) {
    $pid = intval($_POST['delete_presentation']);
    PresentationDB::deletePresentation($pid);
    header('Location: admin.php?tab=presentations');
    exit;
}

$stats = PresentationDB::getStats();
$users = PresentationDB::getAllUsers();
$presentations = PresentationDB::getAllPresentations();
$logs = PresentationDB::getProcessingLogs(200);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AR Presentations</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <div class="container admin-page">
        <div class="topbar">
            <span class="user-info">Admin: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="index.php" class="btn btn-primary btn-sm">Home</a>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">User Dashboard</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>

        <h1>Admin Dashboard</h1>
        <p class="subtitle">System overview and management</p>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $stats['total_users']; ?></div>
                <div class="label">Registered Users</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['total_presentations']; ?></div>
                <div class="label">Presentations</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a class="tab <?php echo $tab === 'overview' ? 'active' : ''; ?>" href="admin.php?tab=overview">Users</a>
            <a class="tab <?php echo $tab === 'presentations' ? 'active' : ''; ?>" href="admin.php?tab=presentations">Presentations</a>
            <a class="tab <?php echo $tab === 'logs' ? 'active' : ''; ?>" href="admin.php?tab=logs">Logs</a>
        </div>

        <!-- Users tab -->
        <?php if ($tab === 'overview'): ?>
            <div class="section">
                <h2>Registered Users</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registered</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo $u['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><span class="badge badge-<?php echo $u['role']; ?>"><?php echo $u['role']; ?></span></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($u['created_at'])); ?></td>
                                    <td><?php echo $u['last_login'] ? date('Y-m-d H:i', strtotime($u['last_login'])) : 'Never'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Presentations tab -->
        <?php if ($tab === 'presentations'): ?>
            <div class="section">
                <h2>All Presentations</h2>
                <?php if (empty($presentations)): ?>
                    <p>No presentations uploaded yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>File</th>
                                    <th>User</th>
                                    <th>Size</th>
                                    <th>Status</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($presentations as $p): ?>
                                    <tr>
                                        <td><?php echo $p['presentation_id']; ?></td>
                                        <td><?php echo htmlspecialchars($p['original_filename']); ?></td>
                                        <td><?php echo htmlspecialchars($p['username'] ?? 'Unknown'); ?></td>
                                        <td><?php echo round($p['file_size'] / 1024); ?> KB</td>
                                        <td><?php echo $p['status']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($p['created_at'])); ?></td>
                                        <td class="td-last">
                                            <?php if (storedFileExists('presentations', $p['stored_filename'])): ?>
                                                <a href="download.php?file=<?php echo urlencode($p['stored_filename']); ?>&type=original&pid=<?php echo $p['presentation_id']; ?>"
                                                    class="btn btn-primary btn-sm">Original</a>
                                            <?php endif; ?>
                                            <?php
                                            $processedName = 'processed_' . $p['stored_filename'];
                                            if (storedFileExists('processed', $processedName)):
                                            ?>
                                                <a href="download.php?file=<?php echo urlencode('processed_' . $p['stored_filename']); ?>&type=processed" class="btn btn-success btn-sm">Download</a>
                                            <?php endif; ?>
                                            <form method="POST" class="form-delete" onsubmit="return confirm('Delete this presentation?');">
                                                <input type="hidden" name="delete_presentation" value="<?php echo $p['presentation_id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Logs tab -->
        <?php if ($tab === 'logs'): ?>
            <div class="section">
                <h2>Processing Logs</h2>
                <?php if (empty($logs)): ?>
                    <p>No logs recorded yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>File</th>
                                    <th>Level</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="td-last"><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['original_filename']); ?></td>
                                        <td><?php echo $log['level']; ?></td>
                                        <td><?php echo htmlspecialchars($log['message']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</body>

</html>
