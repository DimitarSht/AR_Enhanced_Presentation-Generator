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
mysql -u root -p < backend/database/schema.sql
php -S localhost:8000 -t frontend
```

Update `.env` with the database connection and set:

```dotenv
PUBLIC_BASE_URL=http://localhost:8000
STORAGE_DRIVER=local
```

The application creates writable local storage under `backend/storage/`.

## AWS support

### Amazon S3

Set the following values to store original presentations, processed presentations, QR codes, generated images, and generated text in a private S3 bucket:

```dotenv
STORAGE_DRIVER=s3
AWS_REGION=eu-central-1
AWS_S3_BUCKET=your-private-bucket
AWS_S3_PREFIX=ar-presentations
```

PPTX files are downloaded to `backend/storage/` as a local processing cache when needed. New and generated files are uploaded with S3 server-side encryption (`AES256`).

The AWS SDK default credential provider chain is used. In AWS, attach an IAM role to the EC2 instance, ECS task, or Elastic Beanstalk environment. For local development, use an AWS profile or the standard `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` environment variables.

The role can start from [infrastructure/aws/s3-iam-policy.json](infrastructure/aws/s3-iam-policy.json). Replace the bucket name before applying it.

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
