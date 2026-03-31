<?php
/**
 * Admin Email Templates
 * Provides HTML and text email templates for admin verification, registration, and notifications
 */

// Ensure token utilities are loaded
if (!function_exists('buildPublicUrl')) {
    require_once 'auth_token_utils.php';
}

/**
 * Generate Admin Registration Confirmation Email (verification link version)
 */
function getAdminRegistrationVerificationEmailHtml(string $verificationToken, string $adminName, string $adminEmail): string {
    $baseUrl = buildPublicUrl('');
    $verificationLink = buildPublicUrl('api/admin_verify_email.php', [
        'verify_token' => $verificationToken,
        'email' => urlencode($adminEmail)
    ]);

    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Email Verification - BizLink CRM</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f9f9f9; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .header { border-bottom: 3px solid #2c3e50; padding-bottom: 20px; margin-bottom: 20px; }
            .logo { font-size: 24px; font-weight: bold; color: #2c3e50; }
            .content { padding: 20px 0; }
            .greeting { font-size: 16px; font-weight: bold; margin-bottom: 15px; color: #2c3e50; }
            .message { margin: 15px 0; font-size: 14px; }
            .verification-section { background-color: #ecf0f1; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center; }
            .verification-link { display: inline-block; padding: 12px 30px; background-color: #27ae60; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 15px 0; }
            .verification-link:hover { background-color: #229954; }
            .expiry-warning { color: #e74c3c; font-weight: bold; font-size: 12px; margin-top: 10px; }
            .footer { border-top: 1px solid #ecf0f1; padding-top: 20px; margin-top: 30px; font-size: 12px; color: #7f8c8d; text-align: center; }
            .footer-link { color: #2980b9; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">🔐 BizLink CRM</div>
                <p style="margin: 5px 0; color: #7f8c8d; font-size: 12px;">Admin Portal</p>
            </div>

            <div class="content">
                <div class="greeting">Hello {$adminName},</div>

                <div class="message">
                    Welcome to the BizLink CRM Admin Portal! Your admin account has been created and is ready for use. To complete your registration, please verify your email address by clicking the button below.
                </div>

                <div class="verification-section">
                    <div style="font-size: 14px; margin-bottom: 15px; color: #2c3e50;">
                        <strong>Email Verification Required</strong>
                    </div>
                    <a href="{$verificationLink}" class="verification-link">Verify Email Address</a>
                    <div class="expiry-warning">
                        ⏱️ This link expires in 24 hours (86400 seconds)
                    </div>
                </div>

                <div class="message">
                    <strong>If you didn't create this account,</strong> please ignore this email or contact our support team immediately. This link is unique and cannot be shared.
                </div>

                <div class="message">
                    <strong>Account Details:</strong><br>
                    📧 Email: {$adminEmail}<br>
                    👤 Name: {$adminName}<br>
                    🔐 Role: Admin
                </div>

                <div class="message">
                    For security reasons, do not share this email or the verification link with anyone else. Our support team will never ask for your login credentials via email.
                </div>
            </div>

            <div class="footer">
                <p>© 2026 BizLink CRM Platform. All rights reserved.</p>
                <p><a href="{$baseUrl}" class="footer-link">Visit BizLink</a> | <a href="{$baseUrl}#support" class="footer-link">Contact Support</a></p>
                <p style="margin-top: 15px; color: #95a5a6;">This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Generate Admin Verification Code Email (6-digit code version)
 */
function getAdminVerificationCodeEmailHtml(string $verificationCode, string $adminName): string {
    $baseUrl = buildPublicUrl('');

    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Verification Code - BizLink CRM</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f9f9f9; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .header { border-bottom: 3px solid #2c3e50; padding-bottom: 20px; margin-bottom: 20px; }
            .logo { font-size: 24px; font-weight: bold; color: #2c3e50; }
            .content { padding: 20px 0; }
            .greeting { font-size: 16px; font-weight: bold; margin-bottom: 15px; color: #2c3e50; }
            .message { margin: 15px 0; font-size: 14px; }
            .code-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px; text-align: center; margin: 25px 0; }
            .verification-code { font-size: 48px; font-weight: bold; letter-spacing: 8px; margin: 20px 0; font-family: 'Courier New', monospace; }
            .code-instruction { font-size: 13px; color: rgba(255,255,255,0.9); }
            .expiry-warning { color: #e74c3c; font-weight: bold; font-size: 12px; margin-top: 10px; }
            .footer { border-top: 1px solid #ecf0f1; padding-top: 20px; margin-top: 30px; font-size: 12px; color: #7f8c8d; text-align: center; }
            .footer-link { color: #2980b9; text-decoration: none; }
            .security-note { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0; font-size: 12px; color: #856404; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">🔐 BizLink CRM</div>
                <p style="margin: 5px 0; color: #7f8c8d; font-size: 12px;">Admin Portal - Verification Required</p>
            </div>

            <div class="content">
                <div class="greeting">Hello {$adminName},</div>

                <div class="message">
                    Your admin account email verification code is ready. Use the code below to complete your registration.
                </div>

                <div class="code-section">
                    <div class="code-instruction">Your Verification Code</div>
                    <div class="verification-code">{$verificationCode}</div>
                    <div class="code-instruction">Valid for 15 minutes</div>
                </div>

                <div class="security-note">
                    <strong>⚠️ Security Notice:</strong> Never share this code with anyone. Our team will never ask for verification codes via email or phone.
                </div>

                <div class="message">
                    If you didn't request this code, please ignore this email or contact our support team immediately.
                </div>

                <div class="expiry-warning">
                    ⏱️ This verification code expires in 15 minutes
                </div>
            </div>

            <div class="footer">
                <p>© 2026 BizLink CRM Platform. All rights reserved.</p>
                <p><a href="{$baseUrl}" class="footer-link">Visit BizLink</a> | <a href="{$baseUrl}#support" class="footer-link">Contact Support</a></p>
                <p style="margin-top: 15px; color: #95a5a6;">This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Generate Admin Account Activation Success Email
 */
function getAdminActivationSuccessEmailHtml(string $adminName, string $adminEmail, string $loginUrl): string {
    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Activated - BizLink CRM</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f9f9f9; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .header { border-bottom: 3px solid #27ae60; padding-bottom: 20px; margin-bottom: 20px; }
            .logo { font-size: 24px; font-weight: bold; color: #27ae60; }
            .success-badge { display: inline-block; background-color: #27ae60; color: white; padding: 10px 20px; border-radius: 20px; font-weight: bold; margin: 20px 0; }
            .content { padding: 20px 0; }
            .greeting { font-size: 16px; font-weight: bold; margin-bottom: 15px; color: #2c3e50; }
            .message { margin: 15px 0; font-size: 14px; }
            .login-section { background-color: #ecf0f1; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center; }
            .login-button { display: inline-block; padding: 12px 30px; background-color: #27ae60; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 15px 0; }
            .login-button:hover { background-color: #229954; }
            .footer { border-top: 1px solid #ecf0f1; padding-top: 20px; margin-top: 30px; font-size: 12px; color: #7f8c8d; text-align: center; }
            .footer-link { color: #2980b9; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">✅ BizLink CRM</div>
                <p style="margin: 5px 0; color: #7f8c8d; font-size: 12px;">Admin Portal</p>
            </div>

            <div class="content">
                <div class="success-badge">✓ Account Activated Successfully</div>

                <div class="greeting">Welcome to BizLink Admin Portal, {$adminName}!</div>

                <div class="message">
                    Your email has been successfully verified. Your admin account is now active and ready to use.
                </div>

                <div class="login-section">
                    <div style="font-size: 14px; margin-bottom: 15px; color: #2c3e50;">
                        <strong>You can now log in to the admin panel</strong>
                    </div>
                    <a href="{$loginUrl}" class="login-button">Go to Admin Login</a>
                </div>

                <div class="message">
                    <strong>Your Admin Account Details:</strong><br>
                    📧 Email: {$adminEmail}<br>
                    👤 Name: {$adminName}<br>
                    🔐 Role: System Administrator
                </div>

                <div class="message">
                    For security best practices:<br>
                    ✓ Keep your login credentials confidential<br>
                    ✓ Use a strong, unique password<br>
                    ✓ Enable two-factor authentication if available<br>
                    ✓ Report suspicious activity immediately
                </div>
            </div>

            <div class="footer">
                <p>© 2026 BizLink CRM Platform. All rights reserved.</p>
                <p>Need help? <a href="mailto:support@bizlink.local" class="footer-link">Contact Admin Support</a></p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Generate Admin Verification Failed Email (security alert)
 */
function getAdminVerificationFailedEmailHtml(string $adminName, string $adminEmail): string {
    $baseUrl = buildPublicUrl('');

    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Security Alert - BizLink CRM</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f9f9f9; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .header { border-bottom: 3px solid #e74c3c; padding-bottom: 20px; margin-bottom: 20px; }
            .logo { font-size: 24px; font-weight: bold; color: #e74c3c; }
            .alert-section { background-color: #fadbd8; border-left: 4px solid #e74c3c; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .content { padding: 20px 0; }
            .greeting { font-size: 16px; font-weight: bold; margin-bottom: 15px; color: #2c3e50; }
            .message { margin: 15px 0; font-size: 14px; }
            .footer { border-top: 1px solid #ecf0f1; padding-top: 20px; margin-top: 30px; font-size: 12px; color: #7f8c8d; text-align: center; }
            .footer-link { color: #2980b9; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">⚠️ Security Alert</div>
                <p style="margin: 5px 0; color: #7f8c8d; font-size: 12px;">BizLink CRM - Admin Portal</p>
            </div>

            <div class="content">
                <div class="alert-section">
                    <strong style="color: #c0392b;">Multiple Failed Verification Attempts Detected</strong>
                </div>

                <div class="greeting">Hello {$adminName},</div>

                <div class="message">
                    We detected multiple failed email verification attempts on your admin account. For your security, verification attempts are rate-limited.
                </div>

                <div class="message">
                    <strong>What you should do:</strong><br>
                    ✓ If this was you, please try again in a few moments<br>
                    ✓ If you didn't attempt this, ignore this message<br>
                    ✓ Contact admin support if you believe your account is compromised
                </div>

                <div class="message">
                    <strong>Account Security:</strong><br>
                    Your admin account email: {$adminEmail}<br>
                    This is a security measure to protect your account.
                </div>
            </div>

            <div class="footer">
                <p>© 2026 BizLink CRM Platform. All rights reserved.</p>
                <p>Questions? <a href="{$baseUrl}#support" class="footer-link">Contact Support</a></p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

?>
