<?php
require_once __DIR__ . '/../backend/auth.php';
requireLogin();

$user = getCurrentUser();
$mockMode = isset($_POST['mock_mode']) ? $_POST['mock_mode'] : '1';
$source = $_POST['source'] ?? 'new';
$qrPosition = $_POST['qr_position'] ?? 'bottom-right';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($source === 'existing') {
        $existingFile = basename($_POST['existing_file'] ?? '');
        if (empty($existingFile)) {
            displayError(['Please select a presentation from the list.']);
            exit;
        }

        $filepath = PROCESSED_DIR . $existingFile;
        if (!ensureLocalFile('processed', $existingFile, $filepath)) {
            displayError(['The selected presentation file was not found.']);
            exit;
        }

        $originalStoredFilename = preg_replace('/^processed_/', '', $existingFile);

        $db = getDB();
        $stmt = $db->prepare("SELECT presentation_id FROM presentations WHERE stored_filename = :sf AND user_id = :uid LIMIT 1");
        $stmt->execute([':sf' => $originalStoredFilename, ':uid' => $user['user_id']]);
        $existing = $stmt->fetch();
        $pid = $existing ? $existing['presentation_id'] : '';

        header("Location: process_mock.php?file=" . urlencode($existingFile) . "&pid=" . $pid . "&mock_mode=" . $mockMode . "&qr_pos=" . urlencode($qrPosition));
        exit;
    }

    if (!isset($_FILES['presentation']) || $_FILES['presentation']['error'] === UPLOAD_ERR_NO_FILE) {
        displayError(['Please select a file to upload.']);
        exit;
    }

    $file = $_FILES['presentation'];
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Upload error code: " . $file['error'];
    }

    $allowedExtensions = ['pptx'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExtension, $allowedExtensions)) {
        $errors[] = "Invalid file type. Only PPTX files are allowed.";
    }

    $allowedMimeTypes = [
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimeTypes)) {
        $errors[] = "Invalid file type detected.";
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "File too large. Maximum size is 20MB.";
    }

    if (!empty($errors)) {
        displayError($errors);
        exit;
    }

    $uniqueId = uniqid();
    $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
    $newFilename = $uniqueId . '_' . $sanitizedName;
    $destination = UPLOAD_DIR . $newFilename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        try {
            persistFile('presentations', $newFilename, $destination);
        } catch (Throwable $e) {
            @unlink($destination);
            error_log('Storage error in upload.php: ' . $e->getMessage());
            displayError(['Failed to store uploaded file.']);
            exit;
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            $presentationId = PresentationDB::createPresentation(
                $user['user_id'],
                $file['name'],
                $newFilename,
                $file['size'],
                $ipAddress,
                $userAgent
            );

            PresentationDB::logProcessing(
                $presentationId,
                'info',
                'Presentation uploaded successfully',
                [
                    'original_filename' => $file['name'],
                    'stored_filename' => $newFilename,
                    'file_size' => $file['size']
                ]
            );

            header("Location: process_mock.php?file=" . urlencode($newFilename) . "&pid=" . $presentationId . "&mock_mode=" . $mockMode . "&qr_pos=" . urlencode($qrPosition));
            exit;
        } catch (Exception $e) {
            error_log("Database error in upload.php: " . $e->getMessage());

            header("Location: process_mock.php?file=" . urlencode($newFilename) . "&mock_mode=" . $mockMode . "&qr_pos=" . urlencode($qrPosition));
            exit;
        }
    } else {
        displayError(["Failed to save uploaded file."]);
    }
} else {
    header('Location: index.php');
    exit;
}

function displayError($errors)
{
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Upload Error</title>
        <link rel="stylesheet" href="assets/style.css">
    </head>

    <body>
        <div class="auth-container error">
            <h1>Upload Failed</h1>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <p><a href="index.php">← Go back and try again</a></p>
        </div>
    </body>

    </html>
<?php
}
?>
