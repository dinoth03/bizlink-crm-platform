# bizlink-crm-platform

BizLink CRM Platform is a web-based CRM and marketplace project with a static frontend (HTML/CSS/JS), PHP APIs, and a MySQL database.

## Local Setup (No Remote Repository Required)

This project runs fully on your local machine using XAMPP.

1. Install XAMPP (Apache + MySQL + PHP).
2. Copy this project folder to:
	`C:\xampp\htdocs\bizlink-crm-platform`
3. Start Apache and MySQL from XAMPP Control Panel.
4. Open `http://localhost/phpmyadmin` and import:
	`bizlink-crm-platform.sql`
5. Open the app in your browser:
	`http://localhost/bizlink-crm-platform/pages/home.html`

## Main Project Folders

- `api/` PHP endpoints and database configuration
- `pages/` public pages
- `admin/` admin pages
- `customer/` customer pages
- `vendor/` vendor pages
- `assets/` CSS and JavaScript files

## Backend Setup Guide

For full backend instructions, open:

- `BACKEND_COMPLETE_SETUP.md`

It includes:

- complete XAMPP setup
- database import
- API file details
- frontend-to-backend connection instructions
- testing and troubleshooting

## Quick Health Check

After setup, verify these URLs:

- `http://localhost/bizlink-crm-platform/api/get_dashboard_stats.php`
- `http://localhost/bizlink-crm-platform/api/get_orders.php`
- `http://localhost/bizlink-crm-platform/pages/marketplace.html`

If API URLs return JSON, backend setup is working.
