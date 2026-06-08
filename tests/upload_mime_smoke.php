<?php

$uploadSource = file_get_contents(__DIR__ . '/../frontend/upload.php');

if ($uploadSource === false) {
    fwrite(STDERR, "Unable to read upload.php.\n");
    exit(1);
}

$requiredChecks = [
    "\$finfo !== false",
    "\$detectedMimeType !== false",
    "catch (Throwable \$e)",
    "\$file['type'] ?? ''",
    "\$isValidPptx = isValidPptxPackage(\$file['tmp_name'])",
    "\$path === '' || !is_file(\$path)",
    "uploadErrorMessage((int) \$file['error'])",
    "UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE",
    "\$zip->locateName('[Content_Types].xml')",
    "\$zip->locateName('ppt/presentation.xml')",
];

foreach ($requiredChecks as $check) {
    if (strpos($uploadSource, $check) === false) {
        fwrite(STDERR, "Missing defensive upload MIME check: {$check}\n");
        exit(1);
    }
}

echo "Upload MIME smoke test passed.\n";
