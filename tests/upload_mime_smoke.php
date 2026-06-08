<?php

$uploadSource = file_get_contents(__DIR__ . '/../frontend/upload.php');

if ($uploadSource === false) {
    fwrite(STDERR, "Unable to read upload.php.\n");
    exit(1);
}

$requiredChecks = [
    "\$finfo !== false",
    "\$detectedMimeType !== false",
    "\$file['type'] ?? ''",
    "isValidPptxPackage(\$file['tmp_name'])",
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
