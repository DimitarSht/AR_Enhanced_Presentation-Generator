<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_functions.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$type = $_GET['type'] ?? '';
$file = basename($_GET['file'] ?? '');
$contentId = isset($_GET['cid']) ? intval($_GET['cid']) : null;
$presentationId = isset($_GET['pid']) ? intval($_GET['pid']) : null;
$slideNumber = isset($_GET['slide']) ? intval($_GET['slide']) : null;

if (empty($type) || empty($file)) {
    displayError("Invalid request", "Missing type or file parameter");
    exit;
}

$contentInfo = null;
if ($contentId) {
    try {
        $db = getDB();
        $sql = "SELECT ac.*, q.qr_id
                FROM ai_generated_content ac
                LEFT JOIN qr_codes q ON ac.content_id = q.content_id
                WHERE ac.content_id = :content_id";

        $stmt = $db->prepare($sql);
        $stmt->execute([':content_id' => $contentId]);
        $contentInfo = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Database error in view_content.php: " . $e->getMessage());
    }
}

$presentationInfo = null;
if ($presentationId) {
    try {
        $db = getDB();
        $sql = "SELECT p.presentation_id, p.original_filename, p.stored_filename, p.created_at,
                       u.username AS owner_name
                FROM presentations p
                LEFT JOIN users u ON p.user_id = u.user_id
                WHERE p.presentation_id = :pid
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':pid' => $presentationId]);
        $presentationInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error in view_content.php (presentation lookup): " . $e->getMessage());
    }
}

if ($type === 'image') {
    $filepath = AI_IMAGES_DIR . $file;

    if (!file_exists($filepath)) {
        displayError("Image Not Found", "The requested image could not be found: " . htmlspecialchars($file));
        exit;
    }

    $imageData = base64_encode(file_get_contents($filepath));
    $mimeType = mime_content_type($filepath);

    displayImageContent($imageData, $mimeType, $contentInfo, $presentationInfo, $slideNumber);
} elseif ($type === 'text') {
    $filepath = AI_TEXTS_DIR . $file;

    if (!file_exists($filepath)) {
        displayError("Text Not Found", "The requested text could not be found: " . htmlspecialchars($file));
        exit;
    }

    $textContent = file_get_contents($filepath);

    displayTextContent($textContent, $contentInfo, $presentationInfo, $slideNumber);
} else {
    displayError("Invalid Type", "Invalid content type: " . htmlspecialchars($type));
}

function displayError($title, $message)
{
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error</title>
        <link rel="stylesheet" href="assets/style.css">
    </head>

    <body>
        <div class="container error-page">
            <h1>❌ <?php echo htmlspecialchars($title); ?></h1>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </body>

    </html>
<?php
}

function displayImageContent($imageData, $mimeType, $contentInfo, $presentationInfo = null, $slideNumber = null)
{
    $isMock = $contentInfo && $contentInfo['is_mock'];
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AI Generated Image</title>
        <link rel="stylesheet" href="assets/style_ai.css">
    </head>

    <body>
        <div class="container">
            <div class="badge <?php echo $isMock ? 'mock-badge' : ''; ?>">
                <?php echo $isMock ? 'Mock Generated Image' : 'AI Generated Image'; ?>
            </div>
            <h1>Visual Representation</h1>
            <img src="data:<?php echo $mimeType; ?>;base64,<?php echo $imageData; ?>" alt="AI Generated Image">
            <p>
                This image was generated <?php echo $isMock ? 'as test data' : 'using AI'; ?> based on the slide's text content.
            </p>

            <?php if ($presentationInfo || $slideNumber): ?>
                <div class="pres-info">
                    <strong>Source Information:</strong><br>
                    <?php if ($slideNumber): ?>
                        Slide: <strong>#<?php echo $slideNumber; ?></strong><br>
                    <?php endif; ?>
                    <?php if ($presentationInfo): ?>
                        Presentation: <strong><?php echo htmlspecialchars($presentationInfo['original_filename']); ?></strong><br>
                        Owner: <strong><?php echo htmlspecialchars($presentationInfo['owner_name'] ?? 'Unknown'); ?></strong><br>
                        <a href="download.php?file=<?php echo urlencode('processed_' . $presentationInfo['stored_filename']); ?>&type=processed&pid=<?php echo $presentationInfo['presentation_id']; ?>" class="btn-download">
                            Download Presentation (Go to Slide #<?php echo $slideNumber ?: '1'; ?>)
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($contentInfo): ?>
                <div class="stats">
                    <strong>Generation Info:</strong><br>
                    Model: <?php echo htmlspecialchars($contentInfo['ai_model']); ?><br>
                    <?php if (!$isMock && $contentInfo['generation_cost']): ?>
                        Cost: $<?php echo number_format($contentInfo['generation_cost'], 4); ?><br>
                    <?php endif; ?>
                    Created: <?php echo date('Y-m-d H:i:s', strtotime($contentInfo['created_at'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </body>

    </html>
<?php
}

function displayTextContent($textContent, $contentInfo, $presentationInfo = null, $slideNumber = null)
{
    $isMock = $contentInfo && $contentInfo['is_mock'];
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AI Generated Description</title>
        <link rel="stylesheet" href="assets/style_description.css">
    </head>

    <body>
        <div class="container">
            <center>
                <div class="badge <?php echo $isMock ? 'mock-badge' : ''; ?>">
                    <?php echo $isMock ? 'Mock Generated Description' : 'AI Generated Description'; ?>
                </div>
            </center>
            <h1>Image Analysis</h1>
            <div class="content">
                <?php echo htmlspecialchars($textContent); ?>
            </div>
            <p>
                This description was generated <?php echo $isMock ? 'as test data' : 'using AI'; ?> based on the slide's image content.
            </p>

            <?php if ($presentationInfo || $slideNumber): ?>
                <div class="pres-info">
                    <strong>Source Information:</strong><br>
                    <?php if ($slideNumber): ?>
                        Slide: <strong>#<?php echo $slideNumber; ?></strong><br>
                    <?php endif; ?>
                    <?php if ($presentationInfo): ?>
                        Presentation: <strong><?php echo htmlspecialchars($presentationInfo['original_filename']); ?></strong><br>
                        Owner: <strong><?php echo htmlspecialchars($presentationInfo['owner_name'] ?? 'Unknown'); ?></strong><br>
                        <a href="download.php?file=<?php echo urlencode('processed_' . $presentationInfo['stored_filename']); ?>&type=processed&pid=<?php echo $presentationInfo['presentation_id']; ?>" class="btn-download">
                            Download Presentation (Go to Slide #<?php echo $slideNumber ?: '1'; ?>)
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($contentInfo): ?>
                <div class="stats">
                    <strong>Generation Info:</strong><br>
                    Model: <?php echo htmlspecialchars($contentInfo['ai_model']); ?><br>
                    <?php if (!$isMock && $contentInfo['generation_cost']): ?>
                        Cost: $<?php echo number_format($contentInfo['generation_cost'], 4); ?><br>
                    <?php endif; ?>
                    Created: <?php echo date('Y-m-d H:i:s', strtotime($contentInfo['created_at'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </body>

    </html>
<?php
}
?>