AR-Enhanced Presentation Generator - Change Log
================================================

Current setup and deployment instructions live in README.md.

v2.0.0
------
- Split public pages and static assets into frontend/.
- Moved configuration, authentication, database code, and services into backend/.
- Added environment-based database configuration for local MySQL and Amazon RDS.
- Added a storage abstraction with local disk and Amazon S3 implementations.
- Added S3 persistence for presentations, generated content, and QR codes.
- Added private-bucket IAM policy guidance under infrastructure/aws/.
- Removed the unused PHPPresentation dependency and its vulnerable transitive packages.
- Added a CloudFormation deployment for EC2, private RDS MySQL, private S3,
  Secrets Manager, networking, IAM roles, and an Elastic IP.
- Added an S3/EventBridge Lambda that validates uploaded PPTX signatures and
  records the result as S3 object tags.
- Added generated-file cleanup across local storage and S3.

v1.0.x
------
- Added PPTX upload and processing.
- Added mock and OpenAI-powered content generation.
- Added QR code generation and positioning.
- Added user authentication, dashboards, processing logs, and downloads.
