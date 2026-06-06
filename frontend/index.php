<?php
require_once __DIR__ . '/../backend/auth.php';
requireLogin();

$user = getCurrentUser();

$userPresentations = PresentationDB::getPresentationsForUser($user['user_id']);

$processedFiles = [];
{
    $dbPresentations = isAdmin()
        ? PresentationDB::getAllPresentations()
        : PresentationDB::getPresentationsForUser($user['user_id']);

    foreach ($dbPresentations as $p) {
        $processedName = 'processed_' . $p['stored_filename'];
        if (storedFileExists('processed', $processedName)) {
            $processedFiles[] = [
                'filename' => $processedName,
                'display_name' => $p['original_filename'],
                'size' => storedFileSize('processed', $processedName) ?? 0,
                'date' => strtotime($p['created_at']),
                'pid' => $p['presentation_id'],
                'owner' => $p['username'] ?? null,
            ];
        }
    }
    usort($processedFiles, function ($a, $b) {
        return $b['date'] - $a['date'];
    });
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AR-Enhanced Presentation Generator</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="javascript/main.js" defer></script>
</head>

<body>
    <div class="container">
        <div class="topbar">
            <span class="user-info"><?php echo htmlspecialchars($user['username']); ?></span>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">Dashboard</a>
            <?php if (isAdmin()): ?>
                <a href="admin.php" class="btn btn-warning btn-sm">Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>

        <h1>AR-Enhanced Presentations</h1>
        <p class="subtitle">Transform your slides with AI-powered augmented reality</p>

        <form action="upload.php" method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
            <label><strong>Processing Mode:</strong></label>
            <div class="mode-toggle">
                <label>
                    <input type="radio" name="mock_mode" value="1" checked>
                    Mock Mode (no API costs)
                </label>
                <label>
                    <input type="radio" name="mock_mode" value="0">
                    AI Mode (uses OpenAI)
                </label>
            </div>

            <div class="source-toggle">
                <label for="source"><strong>Presentation Source:</strong></label>
                <select name="source" id="source" onchange="toggleSource(this.value)">
                    <option value="new">Upload a new file</option>
                    <?php if (!empty($processedFiles)): ?>
                        <option value="existing">Choose from my processed presentations</option>
                    <?php endif; ?>
                </select>
            </div>

            <div id="source-new">
                <input type="file" name="presentation" id="presentation" accept=".pptx">
            </div>

            <?php if (!empty($processedFiles)): ?>
                <div class="source-existing" id="source-existing">
                    <select name="existing_file" id="existing_file">
                        <option value="">-- Select a processed presentation --</option>
                        <?php foreach ($processedFiles as $pf): ?>
                            <option value="<?php echo htmlspecialchars($pf['filename']); ?>" data-pid="<?php echo $pf['pid']; ?>">
                                <?php echo htmlspecialchars($pf['display_name']); ?>
                                (<?php echo round($pf['size'] / 1024); ?> KB &mdash; <?php echo date('Y-m-d', $pf['date']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="qr-position-section">
                <label><strong>QR Code Position:</strong></label>
                <div class="qr-position-grid">
                    <label class="qr-pos">
                        <input type="radio" name="qr_position" value="top-left">
                        Top-left
                    </label>
                    <label class="qr-pos">
                        <input type="radio" name="qr_position" value="top-right">
                        Top-right
                    </label>
                    <label class="qr-pos">
                        <input type="radio" name="qr_position" value="bottom-left">
                        Bottom-left
                    </label>
                    <label class="qr-pos">
                        <input type="radio" name="qr_position" value="bottom-right" checked>
                        Bottom-right
                    </label>
                </div>
            </div>

            <button type="submit">Process &amp; Enhance Presentation</button>
        </form>

        <div class="features">
            <h3>What We'll Do:</h3>
            <div class="feature-item">
                <strong>Text Slides:</strong> Generate AI images based on your content and add scannable QR codes
            </div>
            <div class="feature-item">
                <strong>Image Slides:</strong> Create detailed AI descriptions and add scannable QR codes
            </div>
            <div class="feature-item">
                <strong>Interactive Experience:</strong> Audience scans QR codes to access enhanced content
            </div>
        </div>

        <div class="info">
            <strong>Requirements:</strong>
            <ul>
                <li>Maximum file size: 20MB</li>
                <li>Only PPTX format supported</li>
                <li>Processing may take 1-2 minutes depending on slide count</li>
            </ul>
        </div>

        <?php if (!empty($processedFiles)): ?>
            <div class="features process">
                <h3>Previously Processed Presentations:</h3>
                <?php foreach ($processedFiles as $pf): ?>
                    <div class="feature-item processed">
                        <div>
                            <strong><?php echo htmlspecialchars($pf['display_name']); ?></strong>
                            <?php if (isAdmin() && !empty($pf['owner'])): ?>
                                <span class="badge badge-user"><?php echo htmlspecialchars($pf['owner']); ?></span>
                            <?php endif; ?>
                            <br>
                            <small>
                                <?php echo date('Y-m-d H:i', $pf['date']); ?>
                                &mdash; <?php echo round($pf['size'] / 1024); ?> KB
                            </small>
                        </div>
                        <a href="download.php?file=<?php echo urlencode($pf['filename']); ?>&type=processed&pid=<?php echo $pf['pid']; ?>"
                            class="btn btn-success btn-sm">
                            Download
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
