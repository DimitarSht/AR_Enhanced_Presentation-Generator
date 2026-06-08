<?php

$configSource = file_get_contents(__DIR__ . '/../backend/config.php');
$processSource = file_get_contents(__DIR__ . '/../frontend/process_mock.php');

if ($configSource === false || $processSource === false) {
    fwrite(STDERR, "Unable to read OpenAI integration files.\n");
    exit(1);
}

$requiredChecks = [
    "envValue('OPENAI_IMAGE_MODEL', 'gpt-image-2')",
    "'model' => OPENAI_IMAGE_MODEL",
    "'quality' => 'low'",
    'base64_decode($response->data[0]->b64_json, true)',
    "catch (Throwable \$e)",
];

foreach ($requiredChecks as $check) {
    if (strpos($configSource . $processSource, $check) === false) {
        fwrite(STDERR, "Missing OpenAI image integration check: {$check}\n");
        exit(1);
    }
}

echo "OpenAI image smoke test passed.\n";
