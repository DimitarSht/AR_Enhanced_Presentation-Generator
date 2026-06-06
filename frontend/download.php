<?php
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/includes/db_functions.php';

if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $type = $_GET['type'] ?? 'original';
    $presentationId = isset($_GET['pid']) ? intval($_GET['pid']) : null;

    if ($type === 'processed') {
        $filepath = PROCESSED_DIR . $filename;
        $category = 'processed';
    } else {
        $filepath = UPLOAD_DIR . $filename;
        $category = 'presentations';
    }

    if (!ensureLocalFile($category, $filename, $filepath)) {
        http_response_code(404);
        die("File not found.");
    }

    if (pathinfo($filepath, PATHINFO_EXTENSION) !== 'pptx') {
        http_response_code(403);
        die("Invalid file type.");
    }

    if ($presentationId) {
        try {
            PresentationDB::logProcessing(
                $presentationId,
                'info',
                'Presentation downloaded',
                [
                    'filename' => $filename,
                    'type' => $type,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                ]
            );
        } catch (Exception $e) {
            error_log("Database error logging download: " . $e->getMessage());
        }
    }

    $parts = explode('_', $filename, 2);
    $originalName = isset($parts[1]) ? $parts[1] : $filename;

    $originalName = str_replace('processed_', '', $originalName);

    header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
    header('Content-Disposition: attachment; filename="' . $originalName . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    ob_clean();
    flush();

    readfile($filepath);
    exit;
} else {
    http_response_code(400);
    die("No file specified.");
}
