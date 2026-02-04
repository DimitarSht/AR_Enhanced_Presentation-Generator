## Final project for university course WEB Technologies at Sofia University
The archive consists of:
- Documentation : [Docs](9MI0800370_documentation.docx)
- Source code
- README.md - this file

##  PROJECT STRUCTURE
```
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
├── README_Changes.txt             # Changelog and user guide
├── composer.json                  # PHP dependencies (endroid/qr-code, openai-php/client)
├── composer.lock
├── .gitignore
└── README.md
```
## Author:  
Name: Dimitar Shtregarski<br>
Major: Computer Science<br>
Student ID: 9MI0800370<br>
Course: Web Technologies, edition 25

## License
This project was created for educational purposes for the course "Web Technologies".