@echo off
REM Admin Email Verification - Test Script for Windows PowerShell
REM This script tests all the admin verification endpoints

setlocal enabledelayedexpansion

set BASE_URL=http://localhost/bizlink-crm-platform

echo ============================================
echo Admin Email Verification - System Diagnostic
echo ============================================
echo.
echo Running diagnostic check...
curl -s %BASE_URL%/api/admin_diagnostic.php | findstr /I "success error"
echo.
echo ============================================
echo To test registration, use:
echo.
echo PowerShell:
echo $body = @{ email="test@example.com"; password="TestPassword123!@#"; first_name="Test"; last_name="Admin"; admin_level="manager" } ^| ConvertTo-Json
echo Invoke-WebRequest -Uri "%BASE_URL%/api/admin_register.php" -Method POST -ContentType "application/json" -Body $body
echo.
echo Batch/CMD:
echo curl -X POST %BASE_URL%/api/admin_register.php -H "Content-Type: application/json" -d "{\"email\":\"test@example.com\",\"password\":\"TestPassword123!@#\",\"first_name\":\"Test\",\"last_name\":\"Admin\"}"
echo.
echo ============================================
echo To test verification, use the token from registration email
echo.
echo PowerShell:
echo $body = @{ verify_token="TOKEN_HERE"; email="test@example.com" } ^| ConvertTo-Json
echo Invoke-WebRequest -Uri "%BASE_URL%/api/admin_verify_email.php" -Method POST -ContentType "application/json" -Body $body
echo.
echo ============================================
echo To test login, use:
echo.
echo PowerShell:
echo $body = @{ email="test@example.com"; password="TestPassword123!@#" } ^| ConvertTo-Json
echo Invoke-WebRequest -Uri "%BASE_URL%/api/admin_login.php" -Method POST -ContentType "application/json" -Body $body
echo.
echo ============================================
