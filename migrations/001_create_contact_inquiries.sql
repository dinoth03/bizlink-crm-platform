-- Migration: Create contact_inquiries table
-- Purpose: Store contact form submissions with status tracking

CREATE TABLE IF NOT EXISTS contact_inquiries (
    inquiry_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    target_role ENUM('admin', 'vendor', 'customer') NOT NULL,
    message TEXT NOT NULL,
    inquiry_status ENUM('new', 'in_progress', 'resolved', 'closed') DEFAULT 'new',
    admin_notes TEXT,
    source_page VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_target_role (target_role),
    INDEX idx_status (inquiry_status),
    INDEX idx_created_at (created_at),
    INDEX idx_email (email),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
