param(
    [Parameter(Mandatory = $true)]
    [string]$KeyPairName,

    [string]$StackName = "ar-presentations",
    [string]$Region = "eu-north-1",
    [string]$GitRef = "main",
    [string]$AllowedHttpCidr = "0.0.0.0/0",
    [string]$AllowedSshCidr = "127.0.0.1/32"
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command aws -ErrorAction SilentlyContinue)) {
    throw "AWS CLI is required. Install it and run 'aws configure' before deploying."
}

$template = Join-Path $PSScriptRoot "cloudformation.yaml"

aws cloudformation deploy `
    --region $Region `
    --stack-name $StackName `
    --template-file $template `
    --capabilities CAPABILITY_NAMED_IAM `
    --parameter-overrides `
        "KeyPairName=$KeyPairName" `
        "GitRef=$GitRef" `
        "AllowedHttpCidr=$AllowedHttpCidr" `
        "AllowedSshCidr=$AllowedSshCidr" `
    --no-fail-on-empty-changeset

if ($LASTEXITCODE -ne 0) {
    throw "CloudFormation deployment failed."
}

aws cloudformation describe-stacks `
    --region $Region `
    --stack-name $StackName `
    --query "Stacks[0].Outputs[].{Name:OutputKey,Value:OutputValue}" `
    --output table
