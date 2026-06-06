<?php
require_once __DIR__ . '/../backend/auth.php';
requireLogin();

$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_presentation'])) {
    $pid = intval($_POST['delete_presentation']);
    PresentationDB::deletePresentation($pid, $user['user_id']);
    header('Location: dashboard.php');
    exit;
}

$presentations = PresentationDB::getPresentationsForUser($user['user_id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - AR Presentations</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <div class="container">
        <div class="topbar">
            <span class="user-info"><?php echo htmlspecialchars($user['username']); ?></span>
            <a href="index.php" class="btn btn-primary btn-sm">Home</a>
            <?php if (isAdmin()): ?>
                <a href="admin.php" class="btn btn-warning btn-sm">Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>

        <h1>My Presentations</h1>
        <p class="subtitle">View, download, or delete your uploaded presentations</p>

        <?php if (empty($presentations)): ?>
            <div class="alert alert-info">
                You have no presentations yet. <a href="index.php">Upload one now</a>.
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($presentations as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['original_filename']); ?></td>
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
                                        <a href="download.php?file=<?php echo urlencode('processed_' . $p['stored_filename']); ?>&type=processed&pid=<?php echo $p['presentation_id']; ?>"
                                            class="btn btn-success btn-sm">Processed</a>
                                    <?php endif; ?>

                                    <form method="POST" class="form-delete" onsubmit="return confirm('Delete this presentation and all associated files?');">
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
</body>

</html>
