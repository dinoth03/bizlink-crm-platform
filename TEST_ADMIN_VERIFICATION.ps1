# Admin Email Verification - PowerShell Test Script
# Run this in PowerShell to test all endpoints

$baseUrl = "http://localhost/bizlink-crm-platform"

# Colors for output
$successColor = "Green"
$errorColor = "Red"
$infoColor = "Cyan"

Write-Host "============================================" -ForegroundColor $infoColor
Write-Host "Admin Email Verification - System Test" -ForegroundColor $infoColor
Write-Host "============================================" -ForegroundColor $infoColor
Write-Host ""

# Test 1: Diagnostic Check
Write-Host "Step 1: Running Diagnostic Check..." -ForegroundColor $infoColor
try {
    $diagnostic = Invoke-WebRequest -Uri "$baseUrl/api/admin_diagnostic.php" -Method GET -ContentType "application/json"
    $result = $diagnostic.Content | ConvertFrom-Json
    
    if ($result.success) {
        Write-Host "✓ System Diagnostic PASSED" -ForegroundColor $successColor
        Write-Host "  - Checks Passed: $($result.diagnostic_report.checks_passed)" -ForegroundColor $infoColor
        if ($result.diagnostic_report.errors.Count -gt 0) {
            Write-Host "  - Errors Found: $($result.diagnostic_report.errors.Count)" -ForegroundColor $errorColor
            foreach ($error in $result.diagnostic_report.errors) {
                Write-Host "    ✗ $error" -ForegroundColor $errorColor
            }
        }
    } else {
        Write-Host "✗ System Diagnostic FAILED" -ForegroundColor $errorColor
        foreach ($detail in $result.diagnostic_report.errors) {
            Write-Host "  ✗ $detail" -ForegroundColor $errorColor
        }
    }
} catch {
    Write-Host "✗ Diagnostic Failed: $_" -ForegroundColor $errorColor
    exit 1
}

Write-Host ""
Write-Host "============================================" -ForegroundColor $infoColor
Write-Host "Step 2: Test Admin Registration" -ForegroundColor $infoColor
Write-Host "============================================" -ForegroundColor $infoColor
Write-Host ""

$testEmail = "admintester_$(Get-Random)@test.local"
$testPassword = "TestPassword123!@#"

Write-Host "Testing with email: $testEmail" -ForegroundColor $infoColor

$registrationBody = @{
    email = $testEmail
    password = $testPassword
    first_name = "Test"
    last_name = "Admin"
    admin_level = "manager"
    department = "Testing"
} | ConvertTo-Json

try {
    $registerResponse = Invoke-WebRequest -Uri "$baseUrl/api/admin_register.php" `
        -Method POST `
        -ContentType "application/json" `
        -Body $registrationBody `
        -ErrorAction Stop
    
    $registerResult = $registerResponse.Content | ConvertFrom-Json
    
    if ($registerResult.success) {
        Write-Host "✓ Registration SUCCESSFUL" -ForegroundColor $successColor
        Write-Host "  - User ID: $($registerResult.data.user_id)" -ForegroundColor $infoColor
        Write-Host "  - Email: $($registerResult.data.email)" -ForegroundColor $infoColor
        Write-Host "  - Email Sent: $($registerResult.data.email_sent)" -ForegroundColor $infoColor
        $userId = $registerResult.data.user_id
        $registeredEmail = $registerResult.data.email
    } else {
        Write-Host "✗ Registration FAILED" -ForegroundColor $errorColor
        Write-Host "  Error: $($registerResult.error.message)" -ForegroundColor $errorColor
        Write-Host "  Code: $($registerResult.error.code)" -ForegroundColor $errorColor
        exit 1
    }
} catch {
    Write-Host "✗ Registration Request Failed" -ForegroundColor $errorColor
    Write-Host "  Error: $_" -ForegroundColor $errorColor
    exit 1
}

Write-Host ""
Write-Host "============================================" -ForegroundColor $infoColor
Write-Host "Step 3: Test Login Before Verification" -ForegroundColor $infoColor
Write-Host "============================================" -ForegroundColor $infoColor
Write-Host ""

$loginBody = @{
    email = $testEmail
    password = $testPassword
} | ConvertTo-Json

try {
    $loginResponse = Invoke-WebRequest -Uri "$baseUrl/api/admin_login.php" `
        -Method POST `
        -ContentType "application/json" `
        -Body $loginBody `
        -ErrorAction SilentlyContinue
    
    $loginResult = $loginResponse.Content | ConvertFrom-Json
    
    if ($loginResult.success) {
        Write-Host "✗ ERROR: Login succeeded BEFORE verification (should fail)" -ForegroundColor $errorColor
    } else {
        if ($loginResult.error.code -eq "EMAIL_NOT_VERIFIED") {
            Write-Host "✓ Correctly blocked unverified login" -ForegroundColor $successColor
            Write-Host "  - Error: $($loginResult.error.message)" -ForegroundColor $infoColor
        } else {
            Write-Host "! Unexpected error: $($loginResult.error.code)" -ForegroundColor $errorColor
        }
    }
} catch {
    Write-Host "✗ Login request failed: $_" -ForegroundColor $errorColor
}

Write-Host ""
Write-Host "============================================" -ForegroundColor $infoColor
Write-Host "Step 4: Check Email Verification Tokens" -ForegroundColor $infoColor
Write-Host "============================================" -ForegroundColor $infoColor
Write-Host ""

Write-Host "To manually verify in database, run:" -ForegroundColor $infoColor
Write-Host "SELECT * FROM email_verification_tokens WHERE user_id = $userId ORDER BY verification_id DESC LIMIT 1;" -ForegroundColor "Yellow"
Write-Host ""
Write-Host "Or check admin security logs:" -ForegroundColor $infoColor
Write-Host "SELECT * FROM admin_security_log WHERE admin_user_id = $userId ORDER BY logged_at DESC LIMIT 10;" -ForegroundColor "Yellow"

Write-Host ""
Write-Host "============================================" -ForegroundColor $infoColor
Write-Host "Step 5: Resend Verification Email Test" -ForegroundColor $infoColor
Write-Host "============================================" -ForegroundColor $infoColor
Write-Host ""

$resendBody = @{
    email = $testEmail
} | ConvertTo-Json

try {
    $resendResponse = Invoke-WebRequest -Uri "$baseUrl/api/admin_resend_verification.php" `
        -Method POST `
        -ContentType "application/json" `
        -Body $resendBody `
        -ErrorAction Stop
    
    $resendResult = $resendResponse.Content | ConvertFrom-Json
    
    if ($resendResult.success) {
        Write-Host "✓ Resend Verification SUCCESSFUL" -ForegroundColor $successColor
        Write-Host "  - Email Sent: $($resendResult.data.email_sent)" -ForegroundColor $infoColor
        Write-Host "  - Expires: $($resendResult.data.expires_in_hours) hours" -ForegroundColor $infoColor
    } else {
        Write-Host "✗ Resend FAILED" -ForegroundColor $errorColor
        Write-Host "  Error: $($resendResult.error.message)" -ForegroundColor $errorColor
    }
} catch {
    Write-Host "✗ Resend Request Failed" -ForegroundColor $errorColor
    Write-Host "  Error: $_" -ForegroundColor $errorColor
}

Write-Host ""
Write-Host "============================================" -ForegroundColor $infoColor
Write-Host "Test Complete!" -ForegroundColor $infoColor
Write-Host "============================================" -ForegroundColor $infoColor
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor $infoColor
Write-Host "1. Check your email for the verification link" -ForegroundColor $infoColor
Write-Host "2. Click the link or use the token to verify" -ForegroundColor $infoColor
Write-Host "3. Then try logging in again" -ForegroundColor $infoColor
