USE bizlink_crm;

START TRANSACTION;

-- Users (vendors + customers)
INSERT IGNORE INTO users (email, password_hash, role, full_name, phone, city, province, country, account_status, is_verified)
VALUES
('niroshan.perera@ceylontech.lk', 'demo_hash_123', 'vendor', 'Niroshan Perera', '+94 77 112 3344', 'Colombo', 'Western', 'Sri Lanka', 'active', 1),
('tharindu.jayasekara@lankafresh.lk', 'demo_hash_123', 'vendor', 'Tharindu Jayasekara', '+94 71 445 7788', 'Kandy', 'Central', 'Sri Lanka', 'active', 1),
('kasun.fernando@serendibstyle.lk', 'demo_hash_123', 'vendor', 'Kasun Fernando', '+94 75 667 8899', 'Galle', 'Southern', 'Sri Lanka', 'active', 1),
('dilani.silva@gmail.com', 'demo_hash_123', 'customer', 'Dilani Silva', '+94 70 901 1122', 'Maharagama', 'Western', 'Sri Lanka', 'active', 1),
('sahan.wijeratne@gmail.com', 'demo_hash_123', 'customer', 'Sahan Wijeratne', '+94 76 221 3344', 'Kurunegala', 'North Western', 'Sri Lanka', 'active', 1),
('malithi.ranasinghe@gmail.com', 'demo_hash_123', 'customer', 'Malithi Ranasinghe', '+94 78 556 7788', 'Matara', 'Southern', 'Sri Lanka', 'active', 1),
('chathura.bandara@gmail.com', 'demo_hash_123', 'customer', 'Chathura Bandara', '+94 72 889 1100', 'Kegalle', 'Sabaragamuwa', 'Sri Lanka', 'active', 1),
('nadeesha.karunarathne@gmail.com', 'demo_hash_123', 'customer', 'Nadeesha Karunarathne', '+94 74 334 5566', 'Negombo', 'Western', 'Sri Lanka', 'active', 1);

-- Vendors
INSERT IGNORE INTO vendors (
  user_id, business_name, business_registration_number, business_category, business_description,
  business_phone, business_email, verification_status, avg_rating, total_reviews, commission_rate, store_url_slug
)
SELECT u.user_id, 'Ceylon Tech Hub', 'PV-CTH-2026-001', 'Electronics',
       'Smart devices and office electronics for SMEs', '+94 11 245 6677',
       'sales@ceylontech.lk', 'verified', 4.70, 128, 5.00, 'ceylon-tech-hub'
FROM users u
WHERE u.email = 'niroshan.perera@ceylontech.lk';

INSERT IGNORE INTO vendors (
  user_id, business_name, business_registration_number, business_category, business_description,
  business_phone, business_email, verification_status, avg_rating, total_reviews, commission_rate, store_url_slug
)
SELECT u.user_id, 'Lanka Fresh Mart', 'PV-LFM-2026-002', 'Grocery',
       'Fresh produce and pantry essentials sourced locally', '+94 81 222 3344',
       'orders@lankafresh.lk', 'verified', 4.50, 94, 5.00, 'lanka-fresh-mart'
FROM users u
WHERE u.email = 'tharindu.jayasekara@lankafresh.lk';

INSERT IGNORE INTO vendors (
  user_id, business_name, business_registration_number, business_category, business_description,
  business_phone, business_email, verification_status, avg_rating, total_reviews, commission_rate, store_url_slug
)
SELECT u.user_id, 'Serendib Style House', 'PV-SSH-2026-003', 'Fashion',
       'Sri Lankan contemporary and traditional fashion', '+94 91 334 5566',
       'hello@serendibstyle.lk', 'verified', 4.80, 176, 5.00, 'serendib-style-house'
FROM users u
WHERE u.email = 'kasun.fernando@serendibstyle.lk';

-- Customers
INSERT IGNORE INTO customers (user_id, preferred_language, preferred_currency, account_tier)
SELECT user_id, 'en', 'LKR', 'silver' FROM users WHERE email = 'dilani.silva@gmail.com';

INSERT IGNORE INTO customers (user_id, preferred_language, preferred_currency, account_tier)
SELECT user_id, 'en', 'LKR', 'bronze' FROM users WHERE email = 'sahan.wijeratne@gmail.com';

INSERT IGNORE INTO customers (user_id, preferred_language, preferred_currency, account_tier)
SELECT user_id, 'en', 'LKR', 'gold' FROM users WHERE email = 'malithi.ranasinghe@gmail.com';

INSERT IGNORE INTO customers (user_id, preferred_language, preferred_currency, account_tier)
SELECT user_id, 'en', 'LKR', 'silver' FROM users WHERE email = 'chathura.bandara@gmail.com';

INSERT IGNORE INTO customers (user_id, preferred_language, preferred_currency, account_tier)
SELECT user_id, 'en', 'LKR', 'gold' FROM users WHERE email = 'nadeesha.karunarathne@gmail.com';

-- Products
INSERT IGNORE INTO products (
  vendor_id, product_name, product_slug, product_description, category, price, quantity_in_stock, is_active
)
SELECT v.vendor_id, 'Dell Inspiron 15 Laptop', 'dell-inspiron-15-lk', '15-inch business laptop', 'electronics', 245000.00, 25, 1
FROM vendors v WHERE v.store_url_slug = 'ceylon-tech-hub';

INSERT IGNORE INTO products (
  vendor_id, product_name, product_slug, product_description, category, price, quantity_in_stock, is_active
)
SELECT v.vendor_id, 'Logitech Wireless Mouse', 'logitech-wireless-mouse-lk', 'Wireless ergonomic mouse', 'electronics', 8500.00, 120, 1
FROM vendors v WHERE v.store_url_slug = 'ceylon-tech-hub';

INSERT IGNORE INTO products (
  vendor_id, product_name, product_slug, product_description, category, price, quantity_in_stock, is_active
)
SELECT v.vendor_id, 'Organic Samba Rice 5kg', 'organic-samba-rice-5kg', 'Premium local samba rice', 'grocery', 2450.00, 200, 1
FROM vendors v WHERE v.store_url_slug = 'lanka-fresh-mart';

INSERT IGNORE INTO products (
  vendor_id, product_name, product_slug, product_description, category, price, quantity_in_stock, is_active
)
SELECT v.vendor_id, 'Ceylon Cinnamon Pack 250g', 'ceylon-cinnamon-250g', 'Pure Ceylon cinnamon sticks', 'grocery', 1800.00, 160, 1
FROM vendors v WHERE v.store_url_slug = 'lanka-fresh-mart';

INSERT IGNORE INTO products (
  vendor_id, product_name, product_slug, product_description, category, price, quantity_in_stock, is_active
)
SELECT v.vendor_id, 'Handloom Cotton Saree', 'handloom-cotton-saree-lk', 'Traditional handloom saree', 'fashion', 12800.00, 45, 1
FROM vendors v WHERE v.store_url_slug = 'serendib-style-house';

INSERT IGNORE INTO products (
  vendor_id, product_name, product_slug, product_description, category, price, quantity_in_stock, is_active
)
SELECT v.vendor_id, 'Batik Office Shirt', 'batik-office-shirt-lk', 'Formal batik shirt for office wear', 'fashion', 5200.00, 70, 1
FROM vendors v WHERE v.store_url_slug = 'serendib-style-house';

-- Orders
INSERT IGNORE INTO orders (
  order_number, customer_id, vendor_id, order_status, payment_status, order_date,
  shipping_address, billing_address, subtotal, discount_amount, tax_amount, shipping_cost,
  total_amount, currency, shipping_method, tracking_number, carrier_name, created_at
)
SELECT 'BLK-2026-0001', c.customer_id, v.vendor_id, 'delivered', 'paid', '2026-03-10 09:30:00',
       'No. 42, High Level Road, Maharagama', 'No. 42, High Level Road, Maharagama',
       245000.00, 5000.00, 0.00, 500.00, 240500.00, 'LKR', 'Express', 'SLP-0001', 'Sri Lanka Post', '2026-03-10 09:30:00'
FROM customers c
JOIN users u ON c.user_id = u.user_id
JOIN vendors v ON v.store_url_slug = 'ceylon-tech-hub'
WHERE u.email = 'dilani.silva@gmail.com';

INSERT IGNORE INTO orders (
  order_number, customer_id, vendor_id, order_status, payment_status, order_date,
  shipping_address, billing_address, subtotal, discount_amount, tax_amount, shipping_cost,
  total_amount, currency, shipping_method, tracking_number, carrier_name, created_at
)
SELECT 'BLK-2026-0002', c.customer_id, v.vendor_id, 'processing', 'paid', '2026-03-11 11:15:00',
       'No. 15, Kurunegala Road, Kurunegala', 'No. 15, Kurunegala Road, Kurunegala',
       4900.00, 0.00, 0.00, 300.00, 5200.00, 'LKR', 'Standard', 'SLP-0002', 'Domex', '2026-03-11 11:15:00'
FROM customers c
JOIN users u ON c.user_id = u.user_id
JOIN vendors v ON v.store_url_slug = 'lanka-fresh-mart'
WHERE u.email = 'sahan.wijeratne@gmail.com';

INSERT IGNORE INTO orders (
  order_number, customer_id, vendor_id, order_status, payment_status, order_date,
  shipping_address, billing_address, subtotal, discount_amount, tax_amount, shipping_cost,
  total_amount, currency, shipping_method, tracking_number, carrier_name, created_at
)
SELECT 'BLK-2026-0003', c.customer_id, v.vendor_id, 'pending', 'unpaid', '2026-03-12 08:45:00',
       'No. 88, Beach Road, Matara', 'No. 88, Beach Road, Matara',
       12800.00, 0.00, 0.00, 400.00, 13200.00, 'LKR', 'Standard', NULL, NULL, '2026-03-12 08:45:00'
FROM customers c
JOIN users u ON c.user_id = u.user_id
JOIN vendors v ON v.store_url_slug = 'serendib-style-house'
WHERE u.email = 'malithi.ranasinghe@gmail.com';

INSERT IGNORE INTO orders (
  order_number, customer_id, vendor_id, order_status, payment_status, order_date,
  shipping_address, billing_address, subtotal, discount_amount, tax_amount, shipping_cost,
  total_amount, currency, shipping_method, tracking_number, carrier_name, created_at
)
SELECT 'BLK-2026-0004', c.customer_id, v.vendor_id, 'cancelled', 'refunded', '2026-03-09 14:20:00',
       'No. 7, Main Street, Kegalle', 'No. 7, Main Street, Kegalle',
       17000.00, 1000.00, 0.00, 300.00, 16300.00, 'LKR', 'Standard', 'SLP-0004', 'Koombiyo', '2026-03-09 14:20:00'
FROM customers c
JOIN users u ON c.user_id = u.user_id
JOIN vendors v ON v.store_url_slug = 'ceylon-tech-hub'
WHERE u.email = 'chathura.bandara@gmail.com';

INSERT IGNORE INTO orders (
  order_number, customer_id, vendor_id, order_status, payment_status, order_date,
  shipping_address, billing_address, subtotal, discount_amount, tax_amount, shipping_cost,
  total_amount, currency, shipping_method, tracking_number, carrier_name, created_at
)
SELECT 'BLK-2026-0005', c.customer_id, v.vendor_id, 'delivered', 'paid', '2026-03-08 10:00:00',
       'No. 23, Lewis Place, Negombo', 'No. 23, Lewis Place, Negombo',
       5200.00, 0.00, 0.00, 300.00, 5500.00, 'LKR', 'Express', 'SLP-0005', 'PickMe Flash', '2026-03-08 10:00:00'
FROM customers c
JOIN users u ON c.user_id = u.user_id
JOIN vendors v ON v.store_url_slug = 'serendib-style-house'
WHERE u.email = 'nadeesha.karunarathne@gmail.com';

INSERT IGNORE INTO orders (
  order_number, customer_id, vendor_id, order_status, payment_status, order_date,
  shipping_address, billing_address, subtotal, discount_amount, tax_amount, shipping_cost,
  total_amount, currency, shipping_method, tracking_number, carrier_name, created_at
)
SELECT 'BLK-2026-0006', c.customer_id, v.vendor_id, 'shipped', 'paid', '2026-03-12 16:30:00',
       'No. 42, High Level Road, Maharagama', 'No. 42, High Level Road, Maharagama',
       3600.00, 0.00, 0.00, 300.00, 3900.00, 'LKR', 'Standard', 'SLP-0006', 'Sri Lanka Post', '2026-03-12 16:30:00'
FROM customers c
JOIN users u ON c.user_id = u.user_id
JOIN vendors v ON v.store_url_slug = 'lanka-fresh-mart'
WHERE u.email = 'dilani.silva@gmail.com';

-- Order items (only insert if missing)
INSERT INTO order_items (order_id, product_id, product_name, price_at_purchase, quantity, subtotal, tax_amount, total_amount)
SELECT o.order_id, p.product_id, p.product_name, 245000.00, 1, 245000.00, 0.00, 245000.00
FROM orders o
JOIN products p ON p.product_slug = 'dell-inspiron-15-lk'
WHERE o.order_number = 'BLK-2026-0001'
  AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.product_id = p.product_id);

INSERT INTO order_items (order_id, product_id, product_name, price_at_purchase, quantity, subtotal, tax_amount, total_amount)
SELECT o.order_id, p.product_id, p.product_name, 2450.00, 2, 4900.00, 0.00, 4900.00
FROM orders o
JOIN products p ON p.product_slug = 'organic-samba-rice-5kg'
WHERE o.order_number = 'BLK-2026-0002'
  AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.product_id = p.product_id);

INSERT INTO order_items (order_id, product_id, product_name, price_at_purchase, quantity, subtotal, tax_amount, total_amount)
SELECT o.order_id, p.product_id, p.product_name, 12800.00, 1, 12800.00, 0.00, 12800.00
FROM orders o
JOIN products p ON p.product_slug = 'handloom-cotton-saree-lk'
WHERE o.order_number = 'BLK-2026-0003'
  AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.product_id = p.product_id);

INSERT INTO order_items (order_id, product_id, product_name, price_at_purchase, quantity, subtotal, tax_amount, total_amount)
SELECT o.order_id, p.product_id, p.product_name, 8500.00, 2, 17000.00, 0.00, 17000.00
FROM orders o
JOIN products p ON p.product_slug = 'logitech-wireless-mouse-lk'
WHERE o.order_number = 'BLK-2026-0004'
  AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.product_id = p.product_id);

INSERT INTO order_items (order_id, product_id, product_name, price_at_purchase, quantity, subtotal, tax_amount, total_amount)
SELECT o.order_id, p.product_id, p.product_name, 5200.00, 1, 5200.00, 0.00, 5200.00
FROM orders o
JOIN products p ON p.product_slug = 'batik-office-shirt-lk'
WHERE o.order_number = 'BLK-2026-0005'
  AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.product_id = p.product_id);

INSERT INTO order_items (order_id, product_id, product_name, price_at_purchase, quantity, subtotal, tax_amount, total_amount)
SELECT o.order_id, p.product_id, p.product_name, 1800.00, 2, 3600.00, 0.00, 3600.00
FROM orders o
JOIN products p ON p.product_slug = 'ceylon-cinnamon-250g'
WHERE o.order_number = 'BLK-2026-0006'
  AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.product_id = p.product_id);

-- Extra common project orders for Dilani Silva (customer dashboard baseline)
INSERT IGNORE INTO orders (
  order_number, customer_id, vendor_id, order_status, payment_status, order_date,
  shipping_address, billing_address, subtotal, discount_amount, tax_amount, shipping_cost,
  total_amount, currency, shipping_method, tracking_number, carrier_name, created_at
)
SELECT 'BLK-2026-0010', c.customer_id, v.vendor_id, 'processing', 'paid', '2026-03-13 09:10:00',
       'No. 42, High Level Road, Maharagama', 'No. 42, High Level Road, Maharagama',
       25500.00, 500.00, 0.00, 300.00, 25300.00, 'LKR', 'Standard', 'SLP-0010', 'Domex', '2026-03-13 09:10:00'
FROM customers c
JOIN users u ON c.user_id = u.user_id
JOIN vendors v ON v.store_url_slug = 'ceylon-tech-hub'
WHERE u.email = 'dilani.silva@gmail.com';

INSERT IGNORE INTO orders (
  order_number, customer_id, vendor_id, order_status, payment_status, order_date,
  shipping_address, billing_address, subtotal, discount_amount, tax_amount, shipping_cost,
  total_amount, currency, shipping_method, tracking_number, carrier_name, created_at
)
SELECT 'BLK-2026-0011', c.customer_id, v.vendor_id, 'pending', 'unpaid', '2026-03-13 11:45:00',
       'No. 42, High Level Road, Maharagama', 'No. 42, High Level Road, Maharagama',
       3600.00, 0.00, 0.00, 250.00, 3850.00, 'LKR', 'Standard', NULL, NULL, '2026-03-13 11:45:00'
FROM customers c
JOIN users u ON c.user_id = u.user_id
JOIN vendors v ON v.store_url_slug = 'lanka-fresh-mart'
WHERE u.email = 'dilani.silva@gmail.com';

INSERT IGNORE INTO orders (
  order_number, customer_id, vendor_id, order_status, payment_status, order_date,
  shipping_address, billing_address, subtotal, discount_amount, tax_amount, shipping_cost,
  total_amount, currency, shipping_method, tracking_number, carrier_name, created_at
)
SELECT 'BLK-2026-0012', c.customer_id, v.vendor_id, 'delivered', 'paid', '2026-03-12 14:05:00',
       'No. 42, High Level Road, Maharagama', 'No. 42, High Level Road, Maharagama',
       12800.00, 800.00, 0.00, 300.00, 12300.00, 'LKR', 'Express', 'SLP-0012', 'PickMe Flash', '2026-03-12 14:05:00'
FROM customers c
JOIN users u ON c.user_id = u.user_id
JOIN vendors v ON v.store_url_slug = 'serendib-style-house'
WHERE u.email = 'dilani.silva@gmail.com';

INSERT IGNORE INTO orders (
  order_number, customer_id, vendor_id, order_status, payment_status, order_date,
  shipping_address, billing_address, subtotal, discount_amount, tax_amount, shipping_cost,
  total_amount, currency, shipping_method, tracking_number, carrier_name, created_at
)
SELECT 'BLK-2026-0013', c.customer_id, v.vendor_id, 'shipped', 'paid', '2026-03-14 08:20:00',
       'No. 42, High Level Road, Maharagama', 'No. 42, High Level Road, Maharagama',
       5200.00, 0.00, 0.00, 250.00, 5450.00, 'LKR', 'Standard', 'SLP-0013', 'Sri Lanka Post', '2026-03-14 08:20:00'
FROM customers c
JOIN users u ON c.user_id = u.user_id
JOIN vendors v ON v.store_url_slug = 'serendib-style-house'
WHERE u.email = 'dilani.silva@gmail.com';

INSERT IGNORE INTO orders (
  order_number, customer_id, vendor_id, order_status, payment_status, order_date,
  shipping_address, billing_address, subtotal, discount_amount, tax_amount, shipping_cost,
  total_amount, currency, shipping_method, tracking_number, carrier_name, created_at
)
SELECT 'BLK-2026-0014', c.customer_id, v.vendor_id, 'cancelled', 'refunded', '2026-03-11 17:30:00',
       'No. 42, High Level Road, Maharagama', 'No. 42, High Level Road, Maharagama',
       8900.00, 0.00, 0.00, 250.00, 9150.00, 'LKR', 'Standard', 'SLP-0014', 'Koombiyo', '2026-03-11 17:30:00'
FROM customers c
JOIN users u ON c.user_id = u.user_id
JOIN vendors v ON v.store_url_slug = 'ceylon-tech-hub'
WHERE u.email = 'dilani.silva@gmail.com';

INSERT INTO order_items (order_id, product_id, product_name, price_at_purchase, quantity, subtotal, tax_amount, total_amount)
SELECT o.order_id, p.product_id, p.product_name, 8500.00, 3, 25500.00, 0.00, 25500.00
FROM orders o
JOIN products p ON p.product_slug = 'logitech-wireless-mouse-lk'
WHERE o.order_number = 'BLK-2026-0010'
  AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.product_id = p.product_id);

INSERT INTO order_items (order_id, product_id, product_name, price_at_purchase, quantity, subtotal, tax_amount, total_amount)
SELECT o.order_id, p.product_id, p.product_name, 1800.00, 2, 3600.00, 0.00, 3600.00
FROM orders o
JOIN products p ON p.product_slug = 'ceylon-cinnamon-250g'
WHERE o.order_number = 'BLK-2026-0011'
  AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.product_id = p.product_id);

INSERT INTO order_items (order_id, product_id, product_name, price_at_purchase, quantity, subtotal, tax_amount, total_amount)
SELECT o.order_id, p.product_id, p.product_name, 12800.00, 1, 12800.00, 0.00, 12800.00
FROM orders o
JOIN products p ON p.product_slug = 'handloom-cotton-saree-lk'
WHERE o.order_number = 'BLK-2026-0012'
  AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.product_id = p.product_id);

INSERT INTO order_items (order_id, product_id, product_name, price_at_purchase, quantity, subtotal, tax_amount, total_amount)
SELECT o.order_id, p.product_id, p.product_name, 5200.00, 1, 5200.00, 0.00, 5200.00
FROM orders o
JOIN products p ON p.product_slug = 'batik-office-shirt-lk'
WHERE o.order_number = 'BLK-2026-0013'
  AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.product_id = p.product_id);

INSERT INTO order_items (order_id, product_id, product_name, price_at_purchase, quantity, subtotal, tax_amount, total_amount)
SELECT o.order_id, p.product_id, p.product_name, 8900.00, 1, 8900.00, 0.00, 8900.00
FROM orders o
JOIN products p ON p.product_slug = 'dell-inspiron-15-lk'
WHERE o.order_number = 'BLK-2026-0014'
  AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.product_id = p.product_id);

COMMIT;

SELECT order_number, order_status, total_amount, created_at
FROM orders
WHERE order_number LIKE 'BLK-2026-%'
ORDER BY created_at DESC;
