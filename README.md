# AR-Enhanced Presentation Generator

A server-rendered PHP application that enhances PPTX presentations with AI-generated content and QR codes.

## Project structure

```text
.
|-- backend/
|   |-- config.php               # Environment, database, and storage configuration
|   |-- auth.php                 # Authentication and authorization
|   |-- database/schema.sql      # MySQL schema
|   |-- includes/                # Database access
|   |-- src/Storage/             # Local and AWS S3 storage implementations
|   `-- storage/                 # Local files and S3 staging area (gitignored)
|-- frontend/
|   |-- assets/                  # CSS
|   |-- javascript/              # Browser JavaScript
|   `-- *.php                    # Pages and HTTP entry points
|-- infrastructure/aws/          # IAM examples for AWS deployment
|-- composer.json
`-- .env.example
```

Configure the web server document root as `frontend/`. Backend PHP files remain outside the public document root.

## Requirements

- PHP 8.2 or newer
- Composer
- MySQL 8 or an Amazon RDS MySQL-compatible database
- PHP extensions: `curl`, `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `zip`
- An OpenAI API key only when AI mode is enabled

## Local setup

```bash
composer install
cp .env.example .env
mysql -u root -p ar_presentations < backend/database/schema.sql
php -S localhost:8000 -t frontend
```

Update `.env` with the database connection and set:

```dotenv
PUBLIC_BASE_URL=http://localhost:8000
STORAGE_DRIVER=local
```

The application creates writable local storage under `backend/storage/`.

Register through the application, then promote an administrator explicitly when needed:

```sql
UPDATE users SET role = 'admin' WHERE username = 'your-username';
```

## AWS support

For a complete EC2, RDS, S3, and Lambda deployment, see [infrastructure/aws/README.md](infrastructure/aws/README.md). The included CloudFormation template creates the network, IAM roles, stable Elastic IP, Apache/PHP server, private database, private storage bucket, and PPTX validation Lambda.

### Amazon S3

Set the following values to store original presentations, processed presentations, QR codes, generated images, and generated text in a private S3 bucket:

```dotenv
STORAGE_DRIVER=s3
AWS_REGION=eu-central-1
AWS_S3_BUCKET=your-private-bucket
AWS_S3_PREFIX=ar-presentations
```

PPTX files are downloaded to `backend/storage/` as a local processing cache when needed. New and generated files are uploaded with S3 server-side encryption (`AES256`).

In AWS, attach an IAM role to the EC2 instance, ECS task, or Elastic Beanstalk environment. For local development, either use an AWS profile or add `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` to `.env`. Temporary credentials can also provide `AWS_SESSION_TOKEN`. Never commit `.env`.

The recommended policy is [infrastructure/aws/s3-iam-policy.json](infrastructure/aws/s3-iam-policy.json). This is an **IAM identity policy**, so attach it to the IAM user or role used by the application. Replace `REPLACE_WITH_BUCKET_NAME` before applying it. Do not paste this file into the S3 bucket policy editor; identity policies intentionally do not contain a `Principal`.

A bucket policy is usually unnecessary when the application identity and bucket are in the same AWS account. If you need a resource-based bucket policy, use [infrastructure/aws/s3-bucket-policy.example.json](infrastructure/aws/s3-bucket-policy.example.json) and replace both the bucket name and `REPLACE_WITH_IAM_PRINCIPAL_ARN`.

### Amazon RDS

No code change is required for RDS. Configure the database endpoint and credentials:

```dotenv
DB_HOST=your-db.cluster-id.region.rds.amazonaws.com
DB_PORT=3306
DB_NAME=ar_presentations
DB_USER=app_user
DB_PASS=replace-me
```

Keep the RDS instance private, allow inbound MySQL only from the application security group, and store production secrets in AWS Secrets Manager or the deployment platform's secret configuration.

## Configuration

See [.env.example](.env.example) for all supported variables. `PUBLIC_BASE_URL` must be the externally reachable frontend URL because generated QR codes use it.

## Verification

```bash
composer validate --no-check-publish
composer audit
php -l frontend/index.php
php tests/storage_smoke.php
```

## License

Created for educational purposes for the Sofia University Web Technologies course.
