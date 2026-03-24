-- ============================================================================
-- BizLink CRM Platform - MySQL Database Schema
-- Version: 1.0
-- Created: March 2, 2026
-- ============================================================================

-- Create Database
CREATE DATABASE IF NOT EXISTS bizlink_crm;
USE bizlink_crm;

-- Set default character set to UTF-8
ALTER DATABASE bizlink_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================================================
-- 1. USER MANAGEMENT TABLES
-- ============================================================================

-- Core Users Table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('admin', 'vendor', 'customer') NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    profile_picture_url VARCHAR(500),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other', 'prefer_not_to_say'),
    address VARCHAR(500),
    city VARCHAR(100),
    province VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'Sri Lanka',
    currency VARCHAR(10) DEFAULT 'LKR',
    account_status ENUM('active', 'inactive', 'suspended', 'pending_verification') DEFAULT 'pending_verification',
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_account_status (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admins Table
CREATE TABLE admins (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    admin_level ENUM('superadmin', 'manager', 'support') DEFAULT 'manager',
    permissions JSON,
    department VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admins_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendors Table
CREATE TABLE vendors (
    vendor_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    business_registration_number VARCHAR(100) UNIQUE,
    business_type VARCHAR(100),
    business_category VARCHAR(100),
    business_description TEXT,
    business_logo_url VARCHAR(500),
    business_banner_url VARCHAR(500),
    business_website VARCHAR(500),
    business_phone VARCHAR(20),
    business_email VARCHAR(255),
    tax_id VARCHAR(100),
    bank_account_number VARCHAR(100),
    bank_name VARCHAR(100),
    bank_branch VARCHAR(100),
    account_holder_name VARCHAR(255),
    verification_status ENUM('pending', 'verified', 'rejected', 'suspended') DEFAULT 'pending',
    verification_date DATETIME,
    verified_by INT,
    avg_rating DECIMAL(3,2) DEFAULT 0,
    total_reviews INT DEFAULT 0,
    total_products INT DEFAULT 0,
    total_orders INT DEFAULT 0,
    commission_rate DECIMAL(5,2) DEFAULT 5.00,
    is_premium BOOLEAN DEFAULT FALSE,
    premium_expiry_date DATE,
    store_url_slug VARCHAR(255) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vendors_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_vendors_verified_by FOREIGN KEY (verified_by) REFERENCES users(user_id),
    INDEX idx_verification_status (verification_status),
    INDEX idx_category (business_category),
    INDEX idx_store_slug (store_url_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customers Table
CREATE TABLE customers (
    customer_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    preferred_language VARCHAR(50) DEFAULT 'en',
    preferred_currency VARCHAR(10) DEFAULT 'LKR',
    total_orders INT DEFAULT 0,
    total_spent DECIMAL(15,2) DEFAULT 0,
    loyalty_points INT DEFAULT 0,
    account_tier ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    preferred_vendors JSON,
    shipping_addresses JSON,
    billing_address_id INT,
    newsletter_subscribed BOOLEAN DEFAULT TRUE,
    sms_notifications_enabled BOOLEAN DEFAULT FALSE,
    email_notifications_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_account_tier (account_tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login Activity Table
CREATE TABLE login_activity (
    activity_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    login_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_login_activity_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_login_activity_user (user_id),
    INDEX idx_login_activity_time (login_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed Login Attempts Table
CREATE TABLE failed_login_attempts (
    attempt_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    email VARCHAR(255) NOT NULL,
    role VARCHAR(20),
    failure_reason VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_failed_login_attempts_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_failed_login_user (user_id),
    INDEX idx_failed_login_email (email),
    INDEX idx_failed_login_time (attempted_at),
    INDEX idx_failed_login_reason (failure_reason)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password Reset Tokens Table
CREATE TABLE password_reset_tokens (
    reset_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_password_reset_user (user_id),
    UNIQUE INDEX uq_password_reset_token_hash (token_hash),
    INDEX idx_password_reset_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email Verification Tokens Table
CREATE TABLE email_verification_tokens (
    verification_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_verify_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_email_verify_user (user_id),
    UNIQUE INDEX uq_email_verify_token_hash (token_hash),
    INDEX idx_email_verify_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CSRF Protection Tokens Table
CREATE TABLE csrf_tokens (
    csrf_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    session_id VARCHAR(255),
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_csrf_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_csrf_user (user_id),
    INDEX idx_csrf_session (session_id),
    INDEX idx_csrf_expires (expires_at),
    INDEX idx_csrf_used (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate Limiting Log Table
CREATE TABLE rate_limit_log (
    rate_limit_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    limit_key CHAR(64) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    identifier VARCHAR(255) NOT NULL,
    timestamp INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate_limit_key (limit_key),
    INDEX idx_rate_limit_endpoint (endpoint),
    INDEX idx_rate_limit_timestamp (timestamp),
    INDEX idx_rate_limit_identifier (identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. PRODUCT/SERVICE MANAGEMENT TABLES
-- ============================================================================

-- Product Categories Table
CREATE TABLE product_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(255) UNIQUE NOT NULL,
    category_slug VARCHAR(255) UNIQUE,
    category_description TEXT,
    parent_category_id INT,
    category_image_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_category_id) REFERENCES product_categories(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products Table
CREATE TABLE products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_slug VARCHAR(255) UNIQUE NOT NULL,
    product_description TEXT,
    short_description VARCHAR(500),
    category VARCHAR(100) NOT NULL,
    sub_category VARCHAR(100),
    product_type ENUM('physical', 'service') DEFAULT 'physical',
    price DECIMAL(15,2) NOT NULL,
    discount_price DECIMAL(15,2),
    discount_percentage DECIMAL(5,2),
    currency VARCHAR(10) DEFAULT 'LKR',
    sku VARCHAR(100) UNIQUE,
    quantity_in_stock INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,
    reorder_quantity INT DEFAULT 20,
    dimensions JSON,
    weight DECIMAL(10,2),
    weight_unit VARCHAR(10),
    primary_image_url VARCHAR(500),
    additional_images JSON,
    product_video_url VARCHAR(500),
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    avg_rating DECIMAL(3,2) DEFAULT 0,
    total_reviews INT DEFAULT 0,
    total_sold INT DEFAULT 0,
    tags JSON,
    attributes JSON,
    manufacturing_date DATE,
    expiry_date DATE,
    country_of_origin VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE CASCADE,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_category (category),
    INDEX idx_product_slug (product_slug),
    INDEX idx_is_active (is_active),
    FULLTEXT INDEX idx_product_search (product_name, product_description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Variants Table
CREATE TABLE product_variants (
    variant_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    variant_name VARCHAR(255),
    variant_sku VARCHAR(100) UNIQUE,
    variant_price DECIMAL(15,2),
    variant_cost DECIMAL(15,2),
    quantity_in_stock INT DEFAULT 0,
    attributes JSON,
    variant_image_url VARCHAR(500),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    INDEX idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. ORDER MANAGEMENT TABLES
-- ============================================================================

-- Orders Table
CREATE TABLE orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    vendor_id INT NOT NULL,
    order_status ENUM('pending', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'cancelled', 'returned') DEFAULT 'pending',
    payment_status ENUM('unpaid', 'paid', 'partially_paid', 'failed', 'refunded') DEFAULT 'unpaid',
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    expected_delivery_date DATE,
    actual_delivery_date DATETIME,
    shipping_address VARCHAR(500),
    billing_address VARCHAR(500),
    subtotal DECIMAL(15,2) NOT NULL,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    shipping_cost DECIMAL(15,2) DEFAULT 0,
    commission_amount DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'LKR',
    shipping_method VARCHAR(100),
    tracking_number VARCHAR(255),
    carrier_name VARCHAR(100),
    notes TEXT,
    customer_notes TEXT,
    return_reason VARCHAR(500),
    return_status ENUM('no_return', 'requested', 'approved', 'rejected', 'completed'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE RESTRICT,
    CONSTRAINT fk_orders_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE RESTRICT,
    INDEX idx_order_number (order_number),
    INDEX idx_customer_id (customer_id),
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_order_status (order_status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_order_date (order_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items Table
CREATE TABLE order_items (
    order_item_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT,
    product_name VARCHAR(255) NOT NULL,
    price_at_purchase DECIMAL(15,2) NOT NULL,
    quantity INT NOT NULL,
    discount_applied DECIMAL(15,2) DEFAULT 0,
    subtotal DECIMAL(15,2) NOT NULL,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(product_id),
    CONSTRAINT fk_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(variant_id),
    INDEX idx_order_id (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Shipments Table
CREATE TABLE order_shipments (
    shipment_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    shipment_date DATETIME,
    tracking_number VARCHAR(255) UNIQUE,
    carrier_name VARCHAR(100),
    carrier_url VARCHAR(500),
    estimated_delivery DATE,
    actual_delivery_date DATETIME,
    shipment_status ENUM('picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned') DEFAULT 'picked_up',
    signature_required BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_shipments_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. PAYMENT & COMMISSION TABLES
-- ============================================================================

-- Payments Table
CREATE TABLE payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    payment_method ENUM('credit_card', 'debit_card', 'bank_transfer', 'digital_wallet', 'cash_on_delivery') NOT NULL,
    payment_amount DECIMAL(15,2) NOT NULL,
    payment_date DATETIME,
    payment_status ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',
    transaction_id VARCHAR(255) UNIQUE,
    transaction_reference VARCHAR(255),
    gateway_response TEXT,
    gateway_name VARCHAR(100),
    card_last_four VARCHAR(4),
    card_type VARCHAR(50),
    receipt_url VARCHAR(500),
    refund_amount DECIMAL(15,2) DEFAULT 0,
    refund_date DATETIME,
    refund_reason VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE RESTRICT,
    INDEX idx_order_id (order_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_transaction_id (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendor Commissions Table
CREATE TABLE vendor_commissions (
    commission_id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    order_id INT,
    commission_amount DECIMAL(15,2) NOT NULL,
    commission_percentage DECIMAL(5,2),
    commission_period_start DATE,
    commission_period_end DATE,
    status ENUM('pending', 'calculated', 'approved', 'paid', 'disputed') DEFAULT 'pending',
    payment_date DATETIME,
    payment_method VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_commissions_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    CONSTRAINT fk_commissions_order FOREIGN KEY (order_id) REFERENCES orders(order_id),
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendor Payouts Table
CREATE TABLE vendor_payouts (
    payout_id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    payout_amount DECIMAL(15,2) NOT NULL,
    payout_period_start DATE,
    payout_period_end DATE,
    payout_date DATETIME,
    payout_status ENUM('pending', 'processed', 'failed', 'cancelled') DEFAULT 'pending',
    payout_method ENUM('bank_transfer', 'check', 'digital_wallet') DEFAULT 'bank_transfer',
    transaction_id VARCHAR(255),
    bank_name VARCHAR(100),
    account_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payouts_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_payout_status (payout_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. CUSTOMER RELATIONSHIP MANAGEMENT TABLES
-- ============================================================================

-- Vendor Customers Table (Vendor's CRM)
CREATE TABLE vendor_customers (
    vendor_customer_id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    customer_id INT NOT NULL,
    customer_status ENUM('prospect', 'active', 'inactive', 'vip', 'lost') DEFAULT 'active',
    lifetime_value DECIMAL(15,2) DEFAULT 0,
    total_purchases INT DEFAULT 0,
    last_purchase_date DATETIME,
    purchase_frequency INT DEFAULT 0,
    preferred_contact_method ENUM('email', 'phone', 'whatsapp', 'sms') DEFAULT 'email',
    customer_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vendor_cust_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE CASCADE,
    CONSTRAINT fk_vendor_cust_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_customer_id (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Interactions Table
CREATE TABLE customer_interactions (
    interaction_id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    customer_id INT NOT NULL,
    interaction_type ENUM('email', 'phone_call', 'chat', 'visit', 'note') NOT NULL,
    interaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    interaction_details TEXT,
    outcome VARCHAR(255),
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_date DATETIME,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_interactions_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    CONSTRAINT fk_interactions_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    CONSTRAINT fk_interactions_created_by FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. MESSAGING & CHAT TABLES
-- ============================================================================

-- Conversations Table
CREATE TABLE conversations (
    conversation_id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    conversation_type ENUM('vendor_customer', 'admin_vendor', 'admin_customer') DEFAULT 'vendor_customer',
    subject VARCHAR(255),
    last_message_date DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_conv_sender FOREIGN KEY (sender_id) REFERENCES users(user_id),
    CONSTRAINT fk_conv_receiver FOREIGN KEY (receiver_id) REFERENCES users(user_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_receiver_id (receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages Table
CREATE TABLE messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_content TEXT NOT NULL,
    message_type ENUM('text', 'file', 'image', 'audio') DEFAULT 'text',
    file_url VARCHAR(500),
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_conv FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(user_id),
    CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(user_id),
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversation Participants Table
CREATE TABLE conversation_participants (
    participant_id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    participant_role ENUM('admin', 'vendor', 'customer') NOT NULL,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    left_at DATETIME NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_read_message_id INT NULL,
    CONSTRAINT fk_cp_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uq_cp_conversation_user (conversation_id, user_id),
    INDEX idx_cp_user (user_id),
    INDEX idx_cp_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message Reads Table
CREATE TABLE message_reads (
    message_read_id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mr_message FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE,
    CONSTRAINT fk_mr_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uq_mr_message_user (message_id, user_id),
    INDEX idx_mr_user (user_id),
    INDEX idx_mr_read_at (read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. REVIEWS & RATINGS TABLES
-- ============================================================================

-- Product Reviews Table
CREATE TABLE product_reviews (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    customer_id INT NOT NULL,
    order_id INT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    review_title VARCHAR(255),
    review_content TEXT,
    pros JSON,
    cons JSON,
    verified_purchase BOOLEAN DEFAULT FALSE,
    helpful_count INT DEFAULT 0,
    unhelpful_count INT DEFAULT 0,
    is_approved BOOLEAN DEFAULT TRUE,
    images JSON,
    response_from_vendor TEXT,
    response_date DATETIME,
    sentiment ENUM('positive', 'neutral', 'negative'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(product_id),
    CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders(order_id),
    INDEX idx_product_id (product_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendor Reviews Table
CREATE TABLE vendor_reviews (
    vendor_review_id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    customer_id INT NOT NULL,
    order_id INT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    review_content TEXT,
    delivery_speed_rating INT,
    product_quality_rating INT,
    communication_rating INT,
    packaging_rating INT,
    verified_purchase BOOLEAN DEFAULT TRUE,
    is_approved BOOLEAN DEFAULT TRUE,
    response_from_vendor TEXT,
    response_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vendor_reviews_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    CONSTRAINT fk_vendor_reviews_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    CONSTRAINT fk_vendor_reviews_order FOREIGN KEY (order_id) REFERENCES orders(order_id),
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_customer_id (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. WISHLIST & PREFERENCES TABLES
-- ============================================================================

-- Wishlist Table
CREATE TABLE wishlists (
    wishlist_item_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    note VARCHAR(500),
    is_priority BOOLEAN DEFAULT FALSE,
    CONSTRAINT fk_wishlist_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    UNIQUE KEY unique_customer_product (customer_id, product_id),
    INDEX idx_customer_id (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Followed Vendors Table
CREATE TABLE followed_vendors (
    follow_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    vendor_id INT NOT NULL,
    followed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_follow_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    CONSTRAINT fk_follow_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE CASCADE,
    UNIQUE KEY unique_customer_vendor (customer_id, vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. NOTIFICATIONS SYSTEM TABLES
-- ============================================================================

-- Notifications Table
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    notification_type ENUM('order_status', 'payment', 'message', 'promotion', 'system', 'review', 'commission') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_entity_type VARCHAR(50),
    related_entity_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    action_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATETIME,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification Reads Table
CREATE TABLE notification_reads (
    notification_read_id INT PRIMARY KEY AUTO_INCREMENT,
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_reads_notification FOREIGN KEY (notification_id) REFERENCES notifications(notification_id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_reads_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uq_notification_read (notification_id, user_id),
    INDEX idx_notification_reads_user (user_id),
    INDEX idx_notification_reads_read_at (read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification Preferences Table
CREATE TABLE notification_preferences (
    preference_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    email_notifications BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT FALSE,
    push_notifications BOOLEAN DEFAULT TRUE,
    order_updates BOOLEAN DEFAULT TRUE,
    payment_updates BOOLEAN DEFAULT TRUE,
    promotional_emails BOOLEAN DEFAULT TRUE,
    newsletter BOOLEAN DEFAULT TRUE,
    quiet_hours_enabled BOOLEAN DEFAULT FALSE,
    quiet_hours_start TIME,
    quiet_hours_end TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_prefs_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. ANALYTICS & REPORTING TABLES
-- ============================================================================

-- Sales Analytics Table
CREATE TABLE sales_analytics (
    analytics_id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT,
    record_date DATE NOT NULL,
    total_sales INT,
    total_revenue DECIMAL(15,2),
    average_order_value DECIMAL(15,2),
    total_customers INT,
    new_customers INT,
    returning_customers INT,
    order_completion_rate DECIMAL(5,2),
    cancelled_orders INT,
    returned_orders INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_analytics_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    UNIQUE KEY unique_vendor_date (vendor_id, record_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Analytics Table
CREATE TABLE customer_analytics (
    customer_analytics_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    record_date DATE,
    total_purchases INT,
    total_spent DECIMAL(15,2),
    average_order_value DECIMAL(15,2),
    last_purchase_date DATETIME,
    days_since_last_purchase INT,
    favorite_vendors JSON,
    favorite_categories JSON,
    customer_segment VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cust_analytics_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    UNIQUE KEY unique_customer_date (customer_id, record_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Traffic Analytics Table
CREATE TABLE traffic_analytics (
    traffic_id INT PRIMARY KEY AUTO_INCREMENT,
    record_date DATE NOT NULL,
    page_name VARCHAR(255),
    page_views INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    bounce_rate DECIMAL(5,2),
    avg_session_duration INT,
    conversion_rate DECIMAL(5,2),
    revenue_generated DECIMAL(15,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date_page (record_date, page_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. PLATFORM MANAGEMENT TABLES
-- ============================================================================

-- Platform Settings Table
CREATE TABLE platform_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Promotions & Discounts Table
CREATE TABLE promotions (
    promotion_id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    promotion_name VARCHAR(255) NOT NULL,
    promotion_code VARCHAR(50) UNIQUE,
    description TEXT,
    discount_type ENUM('fixed_amount', 'percentage', 'buy_one_get_one') DEFAULT 'percentage',
    discount_amount DECIMAL(15,2) NOT NULL,
    min_purchase_amount DECIMAL(15,2),
    max_discount_amount DECIMAL(15,2),
    applicable_products JSON,
    applicable_categories JSON,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    usage_limit INT,
    usage_count INT DEFAULT 0,
    max_uses_per_customer INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_promotions_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Platform Audit Log Table
CREATE TABLE audit_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INSERT DEFAULT DATA
-- ============================================================================

-- Insert default product categories
INSERT INTO product_categories (category_name, category_slug, category_description, sort_order) VALUES
('Tea', 'tea', 'Premium Ceylon Tea Products', 1),
('Textiles', 'textiles', 'Traditional and Modern Textiles', 2),
('Handicrafts', 'handicrafts', 'Handmade Crafts and Artisan Products', 3),
('Electronics', 'electronics', 'Electronic Devices and Gadgets', 4),
('Fashion', 'fashion', 'Clothing, Accessories and Apparel', 5),
('Home & Living', 'home-living', 'Home Decor, Furniture and Living Essentials', 6),
('Beauty & Wellness', 'beauty', 'Cosmetics, Health and Wellness Products', 7),
('Books & Media', 'books', 'Books, Educational Materials and Media', 8),
('Food & Beverages', 'food-beverages', 'Food Products and Beverages', 9),
('Services', 'services', 'Professional Services and Consulting', 10);

-- Insert default platform settings
INSERT INTO platform_settings (setting_key, setting_value, setting_type, description) VALUES
('platform_name', 'BizLink', 'string', 'Platform name'),
('platform_url', 'https://bizlink.lk', 'string', 'Platform URL'),
('platform_currency', 'LKR', 'string', 'Default currency'),
('platform_tax_rate', '18', 'integer', 'Default tax rate percentage'),
('default_commission_rate', '5', 'integer', 'Default vendor commission percentage'),
('max_login_attempts', '5', 'integer', 'Maximum login attempts allowed'),
('session_timeout', '3600', 'integer', 'Session timeout in seconds'),
('support_email', 'support@bizlink.lk', 'string', 'Support email address'),
('admin_email', 'admin@bizlink.lk', 'string', 'Admin email address'),
('enable_two_factor', 'true', 'boolean', 'Enable two-factor authentication'),
('max_upload_size', '5242880', 'integer', 'Max file upload size in bytes (5MB)'),
('allowed_file_types', '"jpg","png","pdf","docx","xlsx"', 'json', 'Allowed file types for upload');

-- ============================================================================
-- INSERT DEFAULT DATA
-- ============================================================================

-- Insert default product categories
INSERT INTO product_categories (category_name, category_slug, category_description, sort_order) VALUES
('Tea', 'tea', 'Premium Ceylon Tea Products', 1),
('Textiles', 'textiles', 'Traditional and Modern Textiles', 2),
('Handicrafts', 'handicrafts', 'Handmade Crafts and Artisan Products', 3),
('Electronics', 'electronics', 'Electronic Devices and Gadgets', 4),
('Fashion', 'fashion', 'Clothing, Accessories and Apparel', 5),
('Home & Living', 'home-living', 'Home Decor, Furniture and Living Essentials', 6),
('Beauty & Wellness', 'beauty', 'Cosmetics, Health and Wellness Products', 7),
('Books & Media', 'books', 'Books, Educational Materials and Media', 8),
('Food & Beverages', 'food-beverages', 'Food Products and Beverages', 9),
('Services', 'services', 'Professional Services and Consulting', 10);

-- Insert default platform settings
INSERT INTO platform_settings (setting_key, setting_value, setting_type, description) VALUES
('platform_name', 'BizLink', 'string', 'Platform name'),
('platform_url', 'https://bizlink.lk', 'string', 'Platform URL'),
('platform_currency', 'LKR', 'string', 'Default currency'),
('platform_tax_rate', '18', 'integer', 'Default tax rate percentage'),
('default_commission_rate', '5', 'integer', 'Default vendor commission percentage'),
('max_login_attempts', '5', 'integer', 'Maximum login attempts allowed'),
('session_timeout', '3600', 'integer', 'Session timeout in seconds'),
('support_email', 'support@bizlink.lk', 'string', 'Support email address'),
('admin_email', 'admin@bizlink.lk', 'string', 'Admin email address'),
('enable_two_factor', 'true', 'boolean', 'Enable two-factor authentication'),
('max_upload_size', '5242880', 'integer', 'Max file upload size in bytes (5MB)'),
('allowed_file_types', '"jpg","png","pdf","docx","xlsx"', 'json', 'Allowed file types for upload');

-- ============================================================================
-- CREATE VIEWS FOR EASY REPORTING
-- ============================================================================

-- View: Vendor Sales Summary
CREATE VIEW vendor_sales_summary AS
SELECT 
    v.vendor_id,
    v.business_name,
    COUNT(DISTINCT o.order_id) as total_orders,
    SUM(o.total_amount) as total_revenue,
    AVG(v.avg_rating) as avg_rating,
    COUNT(DISTINCT o.customer_id) as unique_customers,
    DATE(MAX(o.order_date)) as last_order_date
FROM vendors v
LEFT JOIN orders o ON v.vendor_id = o.vendor_id AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY v.vendor_id;

-- View: Product Performance
CREATE VIEW product_performance AS
SELECT 
    p.product_id,
    p.product_name,
    p.vendor_id,
    v.business_name as vendor_name,
    COUNT(oi.order_item_id) as times_sold,
    SUM(oi.quantity) as total_quantity_sold,
    SUM(oi.total_amount) as total_revenue,
    AVG(pr.rating) as avg_product_rating,
    p.total_reviews,
    p.quantity_in_stock
FROM products p
LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
LEFT JOIN order_items oi ON p.product_id = oi.product_id
LEFT JOIN product_reviews pr ON p.product_id = pr.product_id
GROUP BY p.product_id;

-- View: Customer Lifetime Value
CREATE VIEW customer_lifetime_value AS
SELECT 
    c.customer_id,
    u.full_name,
    u.email,
    COUNT(o.order_id) as total_orders,
    SUM(o.total_amount) as lifetime_value,
    AVG(o.total_amount) as avg_order_value,
    MAX(o.order_date) as last_purchase_date,
    DATEDIFF(CURDATE(), MAX(o.order_date)) as days_since_purchase
FROM customers c
LEFT JOIN users u ON c.user_id = u.user_id
LEFT JOIN orders o ON c.customer_id = o.customer_id
GROUP BY c.customer_id;

-- ============================================================================
-- END OF DATABASE SCHEMA
-- ============================================================================
