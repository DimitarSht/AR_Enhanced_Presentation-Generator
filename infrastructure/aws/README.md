# AWS deployment

The CloudFormation stack provisions:

- an EC2 Ubuntu server running Apache and PHP
- an encrypted, private RDS MySQL database
- a private, encrypted, versioned S3 bucket
- Secrets Manager credentials for RDS
- an Elastic IP so QR-code URLs remain stable
- an S3-triggered Lambda that validates the ZIP signature of uploaded PPTX files
- IAM roles, security groups, networking, and CloudWatch Logs permissions

## Before deploying

1. Merge or push the Git branch you want EC2 to clone.
2. Install and authenticate AWS CLI:

   ```powershell
   aws configure
   aws sts get-caller-identity
   ```

   Use an AWS deployment role or administrator identity that can create CloudFormation, EC2, IAM, RDS, S3, Lambda, EventBridge, Secrets Manager, and VPC resources. The `ar-presentations-local` IAM user created for local S3 testing is intentionally too limited for stack deployment.

3. Create an EC2 key pair in the target AWS Region:

   ```powershell
   aws ec2 create-key-pair `
     --region eu-north-1 `
     --key-name ar-presentations `
     --query KeyMaterial `
     --output text |
     Set-Content -Encoding ascii ar-presentations.pem
   ```

   Store the private key securely. CloudFormation only receives the key-pair name.

4. Understand the costs. EC2, RDS, public IPv4, S3, Secrets Manager, and Lambda can incur charges. Delete the stack when it is no longer needed, then review retained RDS snapshots, the retained S3 bucket, and the retained secret.

## Deploy

From the repository root:

```powershell
.\infrastructure\aws\deploy.ps1 `
  -KeyPairName ar-presentations `
  -Region eu-north-1 `
  -GitRef codex/refactor-frontend-backend-aws `
  -AllowedHttpCidr 203.0.113.10/32
```

Use `main` for `GitRef` after the pull request is merged.
Replace `203.0.113.10` with the public IP allowed to open the application. Omitting
`AllowedHttpCidr` keeps HTTP public at `0.0.0.0/0`. If your ISP changes your public
IP, deploy again with the new address before trying to open the application.

The script prints the application URL, S3 bucket, RDS endpoint, EC2 instance ID, secret ARN, and Lambda function name.

## Configure OpenAI

OpenAI API billing is separate from AWS and ChatGPT. After creating an OpenAI
project API key, configure it without putting the key in source control:

```powershell
.\infrastructure\aws\configure-openai.ps1
```

The script prompts for the key securely, stores it in AWS Secrets Manager as
`ar-presentations/openai`, refreshes the EC2 application configuration through
Systems Manager, and restarts Apache. The EC2 role can read only the configured
OpenAI secret and the generated database secret.

The stack creates a new S3 bucket under CloudFormation management. It does not adopt the bucket created during local testing. After deployment, use the bucket from the `StorageBucketName` output. Existing objects can be copied separately if they must be preserved.

The schema does not create a default administrator. Register a user first, then promote the selected account in RDS:

```sql
UPDATE users SET role = 'admin' WHERE username = 'your-username';
```

## What bootstrap does

EC2 user data:

1. Installs Apache, PHP, Composer, MySQL client, AWS CLI, Git, and required PHP extensions.
2. Clones the selected Git ref into `/var/www/ar-presentations`.
3. Retrieves RDS credentials through the EC2 IAM role.
4. Writes the production `.env` without static AWS access keys.
5. Imports `backend/database/schema.sql`.
6. Configures Apache with `frontend/` as its document root.
7. Calls `/health.php` and signals CloudFormation when bootstrap completes.

Bootstrap logs are available at:

```text
/var/log/ar-presentations-bootstrap.log
/var/log/cloud-init-output.log
/var/log/apache2/ar-presentations-error.log
```

AWS Systems Manager Session Manager is enabled on the EC2 role, so normal administration does not require opening SSH.

## Lambda behavior

S3 sends object-created events through EventBridge. For objects under:

```text
ar-presentations/presentations/
```

the Lambda reads only the first four bytes, verifies a ZIP-compatible signature, and adds these S3 object tags:

```text
pptx-signature=valid|invalid
validated-by=<lambda-function-name>
```

This is asynchronous audit validation. The PHP upload request does not wait for Lambda.

## Production hardening

The starter stack exposes HTTP on the Elastic IP. Before handling real user data:

- add a domain name
- put an Application Load Balancer in front of EC2
- issue an ACM certificate and redirect HTTP to HTTPS
- restrict `AllowedHttpCidr` to trusted public IP addresses
- restrict SSH or leave it disabled and use Session Manager
- enable Multi-AZ RDS for higher availability
- add CloudWatch alarms and AWS Backup
- move OpenAI credentials to Secrets Manager

## Delete

```powershell
aws cloudformation delete-stack `
  --region eu-north-1 `
  --stack-name ar-presentations
```

The template intentionally retains the S3 bucket, RDS snapshot, and database secret. Remove those separately only after confirming the data is no longer needed.
