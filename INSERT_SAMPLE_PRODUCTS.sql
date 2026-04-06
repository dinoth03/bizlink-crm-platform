-- ================================================================
-- BIZLINK CRM MARKETPLACE SAMPLE PRODUCTS INSERT SCRIPT
-- ================================================================
-- This script inserts all 48 sample products into the products table
-- Date: April 6, 2026
-- 
-- WARNING: Make sure you have:
-- 1. Database 'bizlink_crm' created
-- 2. 'products' and 'vendors' tables created
-- 3. At least 1 vendor in the 'vendors' table
-- 
-- HOW TO USE:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Select database: bizlink_crm
-- 3. Click SQL tab
-- 4. Copy and paste this entire script
-- 5. Click "Go" button
-- 6. Refresh marketplace page with Ctrl+F5
-- ================================================================

-- Get the first vendor ID (adjust if you have different vendors)
-- If you get an error, set vendor_id manually to your vendor's ID

-- Clear existing test products (OPTIONAL - comment out to keep existing data)
-- DELETE FROM products WHERE product_id > 100;

INSERT INTO products (product_id, product_name, category, vendor_id, price, quantity_in_stock, is_active, created_at) VALUES

-- Electronics (5 products)
(1, 'Galaxy Pro Laptop 15"', 'electronics', 1, 189000, 15, 1, NOW()),
(2, 'Wireless Noise-Cancel Headphones', 'electronics', 1, 14500, 45, 1, NOW()),
(3, 'Smart CCTV 4-Camera Kit', 'electronics', 1, 32000, 8, 1, NOW()),
(4, 'Power Backup UPS 2000VA', 'electronics', 1, 21500, 20, 1, NOW()),
(5, 'POS System Touch Terminal', 'electronics', 1, 55000, 5, 1, NOW()),

-- Fashion (4 products)
(6, 'Handloom Cotton Saree', 'fashion', 2, 4800, 50, 1, NOW()),
(7, 'Men''s Linen Business Shirt', 'fashion', 2, 2800, 100, 1, NOW()),
(8, 'Batik Casual Dress', 'fashion', 2, 3500, 75, 1, NOW()),
(9, 'Leather Sandals – Handmade', 'fashion', 2, 1950, 60, 1, NOW()),

-- Home (3 products)
(10, 'Teak Wood Coffee Table', 'home', 1, 28500, 10, 1, NOW()),
(11, 'Coconut Shell Decor Set', 'home', 1, 1200, 120, 1, NOW()),
(12, 'Solar LED Garden Lights (Set 6)', 'home', 1, 5400, 35, 1, NOW()),

-- Grocery (4 products)
(13, 'Ceylon Black Tea – 1kg Premium', 'grocery', 3, 1850, 200, 1, NOW()),
(14, 'Cold-Pressed Coconut Oil 1L', 'grocery', 3, 950, 150, 1, NOW()),
(15, 'Organic Jaggery – 500g', 'grocery', 3, 380, 300, 1, NOW()),
(16, 'Spice Mix Bundle – 6 Varieties', 'grocery', 3, 1200, 180, 1, NOW()),

-- Agriculture (4 products)
(17, 'Organic Vegetable Seeds Pack', 'agriculture', 4, 650, 250, 1, NOW()),
(18, 'Drip Irrigation Starter Kit', 'agriculture', 4, 8500, 12, 1, NOW()),
(19, 'Organic Fertilizer 25kg Bag', 'agriculture', 4, 2200, 80, 1, NOW()),
(20, 'Rubber Tapper''s Tool Set', 'agriculture', 4, 3400, 25, 1, NOW()),

-- Construction (3 products)
(21, 'Cement – 50kg Premium Bag', 'construction', 5, 1750, 500, 1, NOW()),
(22, 'Steel Rebar – 12mm (per bundle)', 'construction', 5, 42000, 100, 1, NOW()),
(23, 'Roof Sheet Aluminum – 10ft', 'construction', 5, 3200, 200, 1, NOW()),

-- Health (3 products)
(24, 'Herbal Ayurvedic Oil – 100ml', 'health', 1, 780, 400, 1, NOW()),
(25, 'Digital Blood Pressure Monitor', 'health', 1, 6500, 30, 1, NOW()),
(26, 'Moringa Leaf Powder – 250g', 'health', 1, 890, 150, 1, NOW()),

-- Office Supplies (3 products)
(27, 'Ergonomic Office Chair', 'office', 1, 18500, 20, 1, NOW()),
(28, 'A4 Printing Paper – 5 Ream Box', 'office', 1, 4200, 500, 1, NOW()),
(29, 'Brother Printer Ink Set (4-colour)', 'office', 1, 3800, 200, 1, NOW()),

-- Industrial (3 products)
(30, 'Industrial Safety Gloves (12 pairs)', 'industrial', 5, 1800, 300, 1, NOW()),
(31, 'Electric Power Drill 800W', 'industrial', 5, 14500, 18, 1, NOW()),
(32, 'Industrial Fan 36" Heavy Duty', 'industrial', 5, 22000, 8, 1, NOW()),

-- Packaging (2 products)
(33, 'Kraft Paper Bags (100 pcs)', 'packaging', 3, 1600, 250, 1, NOW()),
(34, 'Bubble Wrap Roll 50m', 'packaging', 3, 2400, 180, 1, NOW()),

-- IT Services (2 products)
(35, 'Website Design & Development', 'it', 1, 45000, 10, 1, NOW()),
(36, 'Cloud Hosting – 1 Year Business Plan', 'it', 1, 18000, 50, 1, NOW()),

-- Marketing (2 products)
(37, 'Social Media Management – Monthly', 'marketing', 2, 25000, 20, 1, NOW()),
(38, 'Branded Flyer Design (10 designs)', 'marketing', 2, 8500, 100, 1, NOW()),

-- Accounting (2 products)
(39, 'Monthly Bookkeeping Service', 'accounting', 3, 12000, 30, 1, NOW()),
(40, 'Annual Tax Filing & Compliance', 'accounting', 3, 35000, 15, 1, NOW()),

-- Logistics (2 products)
(41, 'Island-Wide Courier – Per Shipment', 'logistics', 4, 350, 1000, 1, NOW()),
(42, 'Cold Chain Logistics – Perishables', 'logistics', 4, 1800, 50, 1, NOW()),

-- Extra Products (6 products)
(43, 'Paddy Thresher Machine – Small', 'agriculture', 4, 95000, 3, 1, NOW()),
(44, 'Coir Rope – 100m Roll', 'agriculture', 4, 1100, 200, 1, NOW()),
(45, 'Floor Tiles – Marble Finish 60x60cm', 'construction', 5, 480, 1000, 1, NOW()),
(46, 'Casio Scientific Calculator', 'office', 1, 2200, 100, 1, NOW()),
(47, 'Stainless Steel Water Bottle 1L', 'health', 1, 1450, 200, 1, NOW()),
(48, 'Jumbo Cardboard Box (10 pcs)', 'packaging', 3, 1900, 400, 1, NOW());

-- ================================================================
-- INSERT COMPLETED
-- ================================================================
-- 
-- Summary:
-- - Total products inserted: 48
-- - Categories: 14
-- - Vendors assigned: Distributed across vendors 1-5
-- - All products set to is_active = 1 (visible on marketplace)
-- - Default stock quantities assigned
--
-- Next Steps:
-- 1. Refresh marketplace page: Ctrl+F5
-- 2. You should now see 48 products instead of 6
-- 3. If you still see only 6, check:
--    - Are both Apache AND MySQL running?
--    - Clear browser cache (Ctrl+Shift+Del)
--    - Check browser console (F12) for [Marketplace] messages
--
-- Note: If you need to delete these test products later, run:
-- DELETE FROM products WHERE product_id BETWEEN 1 AND 48;
-- ================================================================
