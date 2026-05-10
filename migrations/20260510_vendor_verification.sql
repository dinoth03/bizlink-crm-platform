-- Migration: Add Vendor Verification Documents Table
-- Created At: 2026-05-10

USE bizlink_crm;

CREATE TABLE IF NOT EXISTS vendor_verification_documents (
    document_id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    document_type ENUM('business_license', 'tax_certificate', 'identity_proof', 'other') NOT NULL,
    document_url VARCHAR(500) NOT NULL,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at DATETIME,
    verified_by INT,
    CONSTRAINT fk_docs_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE CASCADE,
    CONSTRAINT fk_docs_verified_by FOREIGN KEY (verified_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add verification tracking to vendors table if not already present
-- The vendors table already has verification_status, verification_date, verified_by.
-- We might want to add kyc_status to track the overall document verification progress.

ALTER TABLE vendors ADD COLUMN IF NOT EXISTS kyc_status ENUM('not_started', 'pending', 'partially_verified', 'verified', 'rejected') DEFAULT 'not_started' AFTER verification_status;
