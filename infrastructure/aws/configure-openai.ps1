param(
    [string]$StackName = "ar-presentations",
    [string]$Region = "eu-north-1",
    [string]$Profile = "ar-deployment",
    [string]$SecretName = "ar-presentations/openai"
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command aws -ErrorAction SilentlyContinue)) {
    throw "AWS CLI is required."
}

$secureKey = Read-Host "Enter the OpenAI API key" -AsSecureString
$plainKey = [System.Net.NetworkCredential]::new("", $secureKey).Password

if ([string]::IsNullOrWhiteSpace($plainKey)) {
    throw "The OpenAI API key cannot be empty."
}

$tempFile = Join-Path ([System.IO.Path]::GetTempPath()) ("openai-key-" + [guid]::NewGuid() + ".txt")

try {
    [System.IO.File]::WriteAllText(
        $tempFile,
        $plainKey,
        [System.Text.UTF8Encoding]::new($false)
    )

    $secretFileUri = "file://" + ($tempFile -replace "\\", "/")
    $previousErrorActionPreference = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    try {
        aws secretsmanager describe-secret `
            --secret-id $SecretName `
            --region $Region `
            --profile $Profile *> $null
        $secretExists = $LASTEXITCODE -eq 0
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    if ($secretExists) {
        aws secretsmanager put-secret-value `
            --secret-id $SecretName `
            --secret-string $secretFileUri `
            --region $Region `
            --profile $Profile *> $null
    } else {
        aws secretsmanager create-secret `
            --name $SecretName `
            --description "OpenAI API key for the AR presentation application" `
            --secret-string $secretFileUri `
            --region $Region `
            --profile $Profile *> $null
    }

    if ($LASTEXITCODE -ne 0) {
        throw "Unable to create or update the OpenAI secret."
    }
} finally {
    $plainKey = $null
    if (Test-Path -LiteralPath $tempFile) {
        Remove-Item -LiteralPath $tempFile -Force
    }
}

$instanceId = aws cloudformation describe-stacks `
    --stack-name $StackName `
    --region $Region `
    --profile $Profile `
    --query "Stacks[0].Outputs[?OutputKey=='WebServerInstanceId'].OutputValue | [0]" `
    --output text

if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($instanceId)) {
    throw "Unable to find the deployed EC2 instance."
}

$commands = @(
    "set -e",
    "OPENAI_API_KEY=`$(aws secretsmanager get-secret-value --region '$Region' --secret-id '$SecretName' --query SecretString --output text)",
    "sudo sed -i '/^OPENAI_API_KEY=/d' /var/www/ar-presentations/.env",
    "printf 'OPENAI_API_KEY=%s\n' `"`$OPENAI_API_KEY`" | sudo tee -a /var/www/ar-presentations/.env >/dev/null",
    "sudo chown root:www-data /var/www/ar-presentations/.env",
    "sudo chmod 640 /var/www/ar-presentations/.env",
    "sudo systemctl restart apache2"
)
$parameters = @{ commands = $commands } | ConvertTo-Json -Compress
$parametersFile = Join-Path ([System.IO.Path]::GetTempPath()) ("ssm-parameters-" + [guid]::NewGuid() + ".json")

try {
    [System.IO.File]::WriteAllText(
        $parametersFile,
        $parameters,
        [System.Text.UTF8Encoding]::new($false)
    )
    $parametersFileUri = "file://" + ($parametersFile -replace "\\", "/")

    $commandId = aws ssm send-command `
        --instance-ids $instanceId `
        --document-name AWS-RunShellScript `
        --parameters $parametersFileUri `
        --region $Region `
        --profile $Profile `
        --query Command.CommandId `
        --output text

    if ($LASTEXITCODE -ne 0) {
        throw "Unable to refresh the application configuration."
    }
} finally {
    if (Test-Path -LiteralPath $parametersFile) {
        Remove-Item -LiteralPath $parametersFile -Force
    }
}

aws ssm wait command-executed `
    --command-id $commandId `
    --instance-id $instanceId `
    --region $Region `
    --profile $Profile

$status = aws ssm get-command-invocation `
    --command-id $commandId `
    --instance-id $instanceId `
    --region $Region `
    --profile $Profile `
    --query Status `
    --output text

if ($status -ne "Success") {
    throw "The secret was saved, but EC2 configuration refresh failed with status: $status"
}

Write-Host "OpenAI API key configured successfully."
