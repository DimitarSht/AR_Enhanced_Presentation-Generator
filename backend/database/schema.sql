-- AR-Enhanced Presentations Database Schema
-- Import this file into the database selected by DB_NAME.

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE presentations (
    presentation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    status ENUM('uploaded', 'processing', 'completed', 'failed') DEFAULT 'uploaded',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE processing_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    presentation_id INT NOT NULL,
    level ENUM('info', 'warning', 'error') DEFAULT 'info',
    message TEXT NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (presentation_id) REFERENCES presentations(presentation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_generated_content (
    content_id INT AUTO_INCREMENT PRIMARY KEY,
    presentation_id INT NOT NULL,
    slide_number INT NOT NULL,
    content_type ENUM('image', 'text') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    ai_model VARCHAR(100) NULL,
    is_mock TINYINT(1) DEFAULT 0,
    generation_cost DECIMAL(10, 6) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (presentation_id) REFERENCES presentations(presentation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE qr_codes (
    qr_id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NULL,
    presentation_id INT NOT NULL,
    slide_number INT NOT NULL,
    qr_image_path VARCHAR(500) NOT NULL,
    target_url TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES ai_generated_content(content_id) ON DELETE SET NULL,
    FOREIGN KEY (presentation_id) REFERENCES presentations(presentation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
