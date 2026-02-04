================================================================================
  AR-Enhanced Presentation Generator - README
================================================================================

  Author:  Dimitar Shtregarski, 9MI0800370
  Course: Web Technologies, edition 25

================================================================================
  CHANGELOG (since v1.0.0)
================================================================================

v1.0.0 - Initial Release
  - Base presentation processing system
  - PPTX file upload and slide analysis
  - Mock mode and AI mode (DALL-E 3, GPT-4o Vision)
  - QR code generation and embedding into slides
  - Processed presentation download

v1.0.1
  - Added QR code hyperlinks (clicking a QR code in the presentation opens
    the generated content URL directly)
  - Added configurable API rate-limit sleep (API_RATE_LIMIT_SLEEP constant in the config.php); If you want faster processing, change the API_RATE_LIMIT_SLEEP to fewer seconds (default is 5)
  - Added download history logging

v1.0.2
  - Added user authentication system (login, registration, sessions)
  - Added user dashboard with presentation management (view, download, delete)
  - Added admin dashboard with three tabs: Users, Presentations, Logs
  - UI improvements and styling updates

v1.0.3
  - Added QR code duplicate detection: slides that already contain a QR code
    are automatically skipped during reprocessing
  - QR code records are now saved to the database (qr_codes table)
  - Various UI tweaks and code formatting cleanup

v1.0.4
  - Existing processed presentations can now be selected for reprocessing;
    slides with QR codes are automatically skipped
  - Added QR code position selector: users can choose where QR codes are
    placed on slides (top-left, top-right, bottom-left, bottom-right)
  - Enhanced view_content.php to display:
      * Slide number of the content
      * Presentation name
      * Presentation owner
      * Download button linking back to the presentation

================================================================================
  PROJECT STRUCTURE
================================================================================

AR_Enhanced_Presentations/
├── assets/
│   ├── style.css                  # Main stylesheet
│   ├── style_ai.css               # AI mode specific styles
│   └── style_description.css      # Content description styles
│
├── database/
│   └── schema.sql                 # Database schema (users, presentations, qr_codes, etc.)
│
├── includes/
│   └── db_functions.php           # PresentationDB class (CRUD operations)
│
├── javascript/
│   └── main.js                    # Client-side logic (source toggle, form handling)
│
├── qrcodes/                       # Generated QR code images (*.png)
│
├── uploads/
│   ├── presentations/             # Original uploaded PPTX files
│   ├── processed/                 # Enhanced PPTX files (with QR codes embedded)
│   ├── ai_generated/
│   │   ├── images/                # AI/mock generated images (*.png)
│   │   └── texts/                 # AI/mock generated text descriptions (*.txt)
│   └── temp_images/               # Images extracted from slides for AI analysis
│
├── config.php                     # Configuration (DB credentials, paths, API key, constants)
├── auth.php                       # Authentication middleware (requireLogin, requireAdmin, sessions)
├── login.php                      # Login page
├── register.php                   # Registration page
├── logout.php                     # Session destroy & redirect
├── index.php                      # Main page (upload form, mode/source/QR position selection)
├── upload.php                     # File upload handler (validation, DB insert, redirect)
├── process_mock.php               # Processing engine (slide analysis, AI/mock generation, QR embedding)
├── view_content.php               # QR content viewer (displays AI-generated image or text)
├── download.php                   # File download handler
├── dashboard.php                  # User dashboard (list/manage own presentations)
├── admin.php                      # Admin dashboard (users, all presentations, logs)
├── readme.txt                     # Changelog and user guide
├── composer.json                  # PHP dependencies (endroid/qr-code, openai-php/client)
├── composer.lock
├── .gitignore
├── LICENSE
└── README.md

================================================================================
  USER GUIDE - Step by Step
================================================================================

  Prerequisites
  -------------
  - PHP 7.4 or higher with extensions:

   extension=gd
   extension=pdo_mysql
   extension=mysqli
   extension=zip
   extension=mbstring
   extension=openssl
   extension=curl
   extension=fileinfo

  - MySQL database server
  - Composer (PHP dependency manager)
  - An OpenAI API key (only required for AI mode)

  Installation
  ------------
  1. Clone or copy the project files to your web server directory.
  
  2. Run "composer install" in the project root to install PHP dependencies.

  3. Create a MySQL database and import the schema:
       mysql -u root -p < database/schema.sql

  4. Create a .env file in the project root with your OpenAI API key:
       OPENAI_API_KEY=sk-your-api-key-here

  5. Open config.php and update the following if needed:
       - DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS (database credentials)
       - PUBLIC_BASE_URL (the public URL where your app is accessible, e.g.
         your ngrok URL followed by the project folder name)

  6. Make sure the following directories are writable by the web server:
       uploads/presentations/
       uploads/processed/
       uploads/ai_generated/images/
       uploads/ai_generated/texts/
       uploads/temp_images/
       qrcodes/
     (These are created automatically on first run if they do not exist.)

  Getting Started
  ---------------
  1. REGISTER AN ACCOUNT
     - Open the application in your browser.
     - You will be redirected to the login page.
     - Click "Register here" to create a new account.
     - Fill in a username (3+ characters), email, and password (6+ characters).
     - After registering you are automatically logged in.

  2. UPLOAD A PRESENTATION
     - On the main page, select your processing mode:
         * Mock Mode (default) - generates placeholder content for free testing
         * AI Mode - uses OpenAI APIs to generate real content (costs apply)
     - Under "Presentation Source", keep "Upload a new file" selected.
     - Choose where QR codes should appear on slides by clicking one of the
       four position options (top-left, top-right, bottom-left, bottom-right).
     - Click "Choose File" and select a .pptx file (max 20 MB).
     - Click "Process & Enhance Presentation".

  3. PROCESSING
     - The system analyzes each slide and classifies it as:
         * Text-only  -> an image is generated from the text
         * Image-only -> a text description is generated from the image
         * Mixed      -> the slide is skipped (contains both text and images)
     - A QR code linking to the generated content is placed on the slide at
       the position you selected.
     - Progress is displayed in real time on the processing page.
     - When processing finishes, a summary shows how many slides were enhanced.

  4. DOWNLOAD THE ENHANCED PRESENTATION
     - After processing, click "Download Enhanced Presentation" to save the
       modified .pptx file to your computer.
     - The downloaded file contains QR codes on each enhanced slide.

  5. USE THE ENHANCED PRESENTATION
     - Open the downloaded .pptx in PowerPoint, Google Slides, or any
       compatible application.
     - During your presentation, audience members can scan the QR codes on
       slides using their phone cameras.
     - Scanning a QR code opens a web page showing the AI-generated content
       for that slide (image or text description), along with the slide number,
       presentation name, and owner.
     - From the content page, users can also download the full presentation.

  6. MANAGE YOUR PRESENTATIONS
     - Click "My Dashboard" in the navigation to see all your presentations.
     - From the dashboard you can:
         * Download the original uploaded file
         * Download the processed (enhanced) file
         * Delete a presentation and all its associated files

  7. REPROCESS AN EXISTING PRESENTATION
     - On the main page, select "Choose from my processed presentations"
       under Presentation Source.
     - Pick a previously processed file from the dropdown.
     - Choose a new QR position if desired.
     - Click "Process & Enhance Presentation".
     - Slides that already have QR codes will be skipped automatically.

  Admin Features
  --------------
  - Admin users can access the admin dashboard at admin.php.
  - The admin dashboard has three tabs:
      * Users   - view all registered users and their last login times
      * Files   - view and manage all presentations across all users
      * Logs    - view processing logs (uploads, processing steps, downloads)

  Troubleshooting
  ---------------
  - "File not found" errors: Make sure PUBLIC_BASE_URL in config.php matches
    the URL you use to access the application. If using ngrok, update the URL
    each time ngrok assigns a new address.

  - QR codes not scanning: Ensure PUBLIC_BASE_URL is set to a publicly
    accessible URL (e.g. your ngrok URL). QR codes encode absolute URLs, so
    they will not work with localhost addresses on external devices.

  - API errors in AI mode: Check that your OpenAI API key is valid and has
    sufficient credits. The system waits 5 seconds between API calls to
    avoid rate limiting.

  - Upload failures: Verify the file is a valid .pptx file under 20 MB and
    that the uploads directory is writable.

================================================================================
