<?php
/**
 * CENTRALIZED EMAIL SERVICE
 *
 * Handles all transactional emails: license delivery, reseller onboarding,
 * post-activation welcome, and SMTP testing.
 */
class EmailService
{
    /**
     * Resolves the PHPMailer library path by checking multiple common locations.
     */
    private static function getLibsPath(): string
    {
        $possible_paths = [
            __DIR__ . '/../../libs/PHPMailer/src/',
            __DIR__ . '/../libs/PHPMailer/src/',
            dirname(__DIR__, 2) . '/libs/PHPMailer/src/',
        ];

        foreach ($possible_paths as $path) {
            if (file_exists($path . 'PHPMailer.php')) {
                return $path;
            }
        }

        $tried = implode("\n", $possible_paths);
        throw new Exception("PHPMailer library not found. Checked locations:\n" . $tried);
    }

    /**
     * Public helper: creates a fully configured PHPMailer instance.
     * Used by EmailService methods internally and by external callers for custom templates.
     */
    public static function createMailer(PDO $pdo, string $to_email): \PHPMailer\PHPMailer\PHPMailer
    {
        $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
        $settings = $stmt->fetch();

        if (!$settings || empty($settings['smtp_host'])) {
            throw new Exception("SMTP settings are not configured in the database.");
        }

        $libs_path = self::getLibsPath();
        require_once $libs_path . 'Exception.php';
        require_once $libs_path . 'PHPMailer.php';
        require_once $libs_path . 'SMTP.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_user'];
        $mail->Password = $settings['smtp_pass'];

        $port = (int)$settings['smtp_port'];
        $mail->Port = $port;
        if ($port === 465) {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }
        elseif ($port === 587 || $port === 2525) {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        else {
            $mail->SMTPSecure = '';
        }

        $mail->setFrom($settings['smtp_from_email'], $settings['smtp_from_name']);
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        return $mail;
    }

    /**
     * Sends the initial license key email with download link and setup instructions.
     */
    public static function sendLicense(PDO $pdo, $order_id, string $license, string $email): bool
    {
        $mail = self::createMailer($pdo, $email);
        $mail->Subject = 'Your BioScript License Key - Download Ready';

        $download_url = 'https://license.bioscript.link/download/bioscript?license=' . urlencode($license);

        $mail->Body = '<html><body style="font-family:Arial,sans-serif;background:#0f172a;color:#fff;padding:40px;margin:0;">'
            . '<div style="max-width:600px;margin:0 auto;background:#1e293b;border-radius:12px;overflow:hidden;border:1px solid #334155;">'
            . '<div style="padding:30px;border-bottom:1px solid #334155;background:linear-gradient(135deg,#0f172a,#1e293b);">'
            . '<h2 style="margin:0;color:#10b981;font-size:24px;text-transform:uppercase;letter-spacing:2px;">License Ready</h2>'
            . '</div>'
            . '<div style="padding:40px;">'
            . '<p style="margin-top:0;font-size:16px;color:#94a3b8;">Your BioScript license key has been generated. Use it to install and activate your bio page builder.</p>'
            . '<div style="margin:30px 0;padding:25px;background:#0f172a;border-radius:8px;border:1px dashed #334155;text-align:center;">'
            . '<p style="margin:0 0 10px 0;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#64748b;">Your License Key</p>'
            . '<div style="font-family:monospace;font-size:24px;font-weight:bold;color:#fff;letter-spacing:2px;">' . htmlspecialchars($license) . '</div>'
            . '</div>'
            . '<div style="margin:24px 0;padding:20px;background:#0f172a;border-radius:8px;border:1px solid #1e3a5f;">'
            . '<p style="margin:0 0 12px 0;font-size:13px;font-weight:bold;color:#38bdf8;text-transform:uppercase;letter-spacing:1px;">Getting Started</p>'
            . '<ol style="margin:0;padding-left:18px;color:#94a3b8;font-size:14px;line-height:2;">'
            . '<li>Download BioScript using the button below.</li>'
            . '<li>Upload the ZIP to your web server and extract it.</li>'
            . '<li>Visit <code>your-domain.com/install.php</code> and enter your license key.</li>'
            . '<li>Complete the setup wizard.</li>'
            . '</ol>'
            . '</div>'
            . '<div style="text-align:center;margin:30px 0;">'
            . '<a href="' . htmlspecialchars($download_url) . '" style="display:inline-block;background:linear-gradient(135deg,#10b981,#059669);color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:bold;font-size:15px;letter-spacing:1px;">Download BioScript</a>'
            . '</div>'
            . '<table style="width:100%;font-size:14px;color:#64748b;">'
            . '<tr><td style="padding:5px 0;">Order ID:</td><td style="padding:5px 0;text-align:right;color:#94a3b8;">#' . htmlspecialchars((string)$order_id) . '</td></tr>'
            . '<tr><td style="padding:5px 0;">Status:</td><td style="padding:5px 0;text-align:right;color:#10b981;">ACTIVE</td></tr>'
            . '</table>'
            . '</div>'
            . '<div style="padding:20px;text-align:center;background:#0f172a;border-top:1px solid #334155;">'
            . '<p style="margin:0;font-size:12px;color:#475569;">&copy; ' . date('Y') . ' BioScript. Keep your license key secure.</p>'
            . '</div>'
            . '</div></body></html>';

        $mail->AltBody = "Your BioScript License Key: $license\nDownload URL: $download_url\nOrder ID: #$order_id";
        $mail->send();
        return true;
    }

    /**
     * Sends reseller onboarding email with login credentials and panel instructions.
     */
    public static function sendResellerOnboarding(PDO $pdo, string $email, string $plain_password, string $reseller_license): bool
    {
        $login_url = 'https://license.bioscript.link/reseller/login.php';
        $mail = self::createMailer($pdo, $email);
        $mail->Subject = 'Your BioScript Reseller Access';

        $mail->Body = '<html><body style="font-family:Arial,sans-serif;background:#0f172a;color:#fff;padding:40px;margin:0;">'
            . '<div style="max-width:600px;margin:0 auto;background:#1e293b;border-radius:12px;overflow:hidden;border:1px solid #334155;">'
            . '<div style="padding:30px;border-bottom:1px solid #334155;background:linear-gradient(135deg,#0f172a,#0a2540);">'
            . '<h2 style="margin:0;color:#38bdf8;font-size:22px;text-transform:uppercase;letter-spacing:2px;">Reseller Access Granted</h2>'
            . '</div>'
            . '<div style="padding:40px;">'
            . '<p style="margin-top:0;color:#94a3b8;">Welcome to the BioScript Reseller Program. Your account has been created and is ready to use.</p>'
            . '<div style="margin:24px 0;padding:24px;background:#0f172a;border-radius:8px;border:1px solid #1e3a5f;">'
            . '<p style="margin:0 0 16px 0;font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:1.5px;color:#64748b;">Your Credentials</p>'
            . '<table style="width:100%;font-size:14px;border-collapse:collapse;">'
            . '<tr><td style="padding:8px 0;color:#64748b;width:40%;">Login URL</td><td style="padding:8px 0;color:#38bdf8;"><a href="' . htmlspecialchars($login_url) . '" style="color:#38bdf8;">license.bioscript.link/reseller</a></td></tr>'
            . '<tr style="border-top:1px solid #1e293b;"><td style="padding:8px 0;color:#64748b;">Email</td><td style="padding:8px 0;color:#fff;">' . htmlspecialchars($email) . '</td></tr>'
            . '<tr style="border-top:1px solid #1e293b;"><td style="padding:8px 0;color:#64748b;">Temp Password</td><td style="padding:8px 0;font-family:monospace;color:#f59e0b;">' . htmlspecialchars($plain_password) . '</td></tr>'
            . '<tr style="border-top:1px solid #1e293b;"><td style="padding:8px 0;color:#64748b;">Reseller Key</td><td style="padding:8px 0;font-family:monospace;color:#10b981;">' . htmlspecialchars($reseller_license) . '</td></tr>'
            . '</table>'
            . '</div>'
            . '<div style="padding:16px;background:#1c1917;border-left:3px solid #f59e0b;border-radius:4px;margin-bottom:24px;">'
            . '<p style="margin:0;font-size:13px;color:#fbbf24;">Please change your password after logging in for the first time.</p>'
            . '</div>'
            . '<p style="font-size:14px;color:#94a3b8;">From your dashboard you can generate licenses for customers, track activations, and monitor your sales performance.</p>'
            . '<div style="text-align:center;margin-top:24px;">'
            . '<a href="' . htmlspecialchars($login_url) . '" style="display:inline-block;background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:bold;font-size:14px;">Access Reseller Panel &rarr;</a>'
            . '</div>'
            . '</div>'
            . '<div style="padding:20px;text-align:center;background:#0f172a;border-top:1px solid #334155;">'
            . '<p style="margin:0;font-size:12px;color:#475569;">&copy; ' . date('Y') . ' BioScript. Keep your credentials confidential.</p>'
            . '</div>'
            . '</div></body></html>';

        $mail->AltBody = "Reseller Credentials:\nURL: $login_url\nEmail: $email\nTemp Password: $plain_password\nLicense: $reseller_license";
        $mail->send();
        return true;
    }

    /**
     * Sends a post-activation "Installation Is Ready" email to the customer.
     */
    public static function sendCustomerOnboarding(PDO $pdo, string $email, string $license, string $domain): bool
    {
        $mail = self::createMailer($pdo, $email);
        $mail->Subject = 'Your BioScript Installation Is Ready';

        $mail->Body = '<html><body style="font-family:Arial,sans-serif;background:#0f172a;color:#fff;padding:40px;margin:0;">'
            . '<div style="max-width:600px;margin:0 auto;background:#1e293b;border-radius:12px;overflow:hidden;border:1px solid #334155;">'
            . '<div style="padding:30px;border-bottom:1px solid #334155;background:linear-gradient(135deg,#064e3b,#065f46);">'
            . '<h2 style="margin:0;color:#34d399;font-size:22px;text-transform:uppercase;letter-spacing:2px;">&#x2713; Installation Active</h2>'
            . '</div>'
            . '<div style="padding:40px;">'
            . '<p style="margin-top:0;color:#94a3b8;">Your BioScript installation on <strong style="color:#fff;">' . htmlspecialchars($domain) . '</strong> has been successfully activated. You are all set!</p>'
            . '<div style="margin:24px 0;padding:20px;background:#0f172a;border-radius:8px;border:1px solid #064e3b;">'
            . '<p style="margin:0 0 12px 0;font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:1.5px;color:#64748b;">Quick Setup Guide</p>'
            . '<ol style="margin:0;padding-left:18px;color:#94a3b8;font-size:14px;line-height:2.2;">'
            . '<li>Log in to your BioScript admin at <code>your-domain.com/user_admin</code>.</li>'
            . '<li>Set your display name, bio text, and profile photo.</li>'
            . '<li>Choose a theme from the Themes tab.</li>'
            . '<li>Add your links, social profiles, or embed blocks.</li>'
            . '<li>Your bio page is live at <code>your-domain.com</code>.</li>'
            . '</ol>'
            . '</div>'
            . '<p style="font-size:14px;color:#94a3b8;">Need help? Contact support at <a href="mailto:support@bioscript.link" style="color:#10b981;">support@bioscript.link</a> and we will get back to you shortly.</p>'
            . '</div>'
            . '<div style="padding:20px;text-align:center;background:#0f172a;border-top:1px solid #334155;">'
            . '<p style="margin:0;font-size:12px;color:#475569;">&copy; ' . date('Y') . ' BioScript. Enjoy your bio page!</p>'
            . '</div>'
            . '</div></body></html>';

        $mail->AltBody = "Your BioScript installation on $domain has been successfully activated. Visit your dashboard to complete setup.";
        $mail->send();
        return true;
    }

    /**
     * Sends a verification link to the customer.
     * Includes full SMTP debug logging for troubleshooting delivery issues.
     */
    public static function sendVerification(PDO $pdo, string $email, string $verify_url): bool
    {
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }

        try {
            $mail = self::createMailer($pdo, $email);

            // Enable SMTP debug — capture the full server conversation
            $mail->SMTPDebug = 2;
            $smtp_log = '';
            $mail->Debugoutput = function ($str, $level) use (&$smtp_log) {
                $smtp_log .= "[L$level] $str";
            };

            // Deliverability essentials
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $from_email = $mail->From;
            $mail->addReplyTo($from_email, $mail->FromName);

            $mail->Subject = 'Verify your email for BioScript';

            // Clean, minimal HTML — no dark backgrounds, no complex CSS
            $mail->Body = '<html><body style="font-family: Arial, sans-serif; color: #333333; padding: 20px;">'
                . '<p>Hello,</p>'
                . '<p>Please click the link below to verify your email address and unlock your BioScript dashboard:</p>'
                . '<p><a href="' . htmlspecialchars($verify_url) . '">' . htmlspecialchars($verify_url) . '</a></p>'
                . '<p>This link will expire in 30 minutes.</p>'
                . '<p>If you did not request this, you can safely ignore this email.</p>'
                . '<p>— BioScript Team</p>'
                . '</body></html>';

            $mail->AltBody = "Hello,\n\nPlease verify your email for BioScript by visiting:\n$verify_url\n\nThis link expires in 30 minutes.\n\nIf you did not request this, you can safely ignore this email.\n\n— BioScript Team";

            $sent = $mail->send();

            // Always log the SMTP conversation for verification emails
            @file_put_contents($log_dir . '/smtp_debug.log',
                "[" . date('Y-m-d H:i:s') . "] TO: $email | RESULT: " . ($sent ? 'SUCCESS' : 'FAILED') . "\n" . $smtp_log . "\n---\n",
                FILE_APPEND
            );

            return $sent;
        }
        catch (\Exception $e) {
            @file_put_contents($log_dir . '/smtp_debug.log',
                "[" . date('Y-m-d H:i:s') . "] EXCEPTION for $email: " . $e->getMessage() . "\n" . ($smtp_log ?? '') . "\n---\n",
                FILE_APPEND
            );
            return false;
        }
    }

    /**
     * Sends a test email to verify SMTP configuration is working.
     */
    public static function sendTestEmail(PDO $pdo, string $test_email): bool
    {
        try {
            $mail = self::createMailer($pdo, $test_email);
            $mail->Subject = 'SMTP Config Test — BioScript';
            $mail->Body = '<html><body><h3>SMTP Settings Verified</h3><p>This is a test email from your BioScript License Server.</p></body></html>';
            return $mail->send();
        }
        catch (Exception $e) {
            return false;
        }
    }
}