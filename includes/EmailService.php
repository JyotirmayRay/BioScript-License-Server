<?php
/**
 * CENTRALIZED EMAIL SERVICE
 * 
 * Handles license delivery via SMTP with robust error tracking.
 */
class EmailService
{
    /**
     * Resolves the PHPMailer library path by checking multiple common locations.
     * 
     * @return string
     * @throws Exception
     */
    private static function getLibsPath(): string
    {
        $possible_paths = [
            __DIR__ . '/../../libs/PHPMailer/src/', // Root libs
            __DIR__ . '/../libs/PHPMailer/src/', // Inside super_admin/libs
            dirname(__DIR__, 2) . '/libs/PHPMailer/src/', // Absolute fallback
        ];

        foreach ($possible_paths as $path) {
            if (file_exists($path . 'PHPMailer.php')) {
                return $path;
            }
        }

        // Diagnostic info if all fail
        $tried = implode("\n", $possible_paths);
        throw new Exception("PHPMailer library not found. Checked locations:\n" . $tried);
    }

    /**
     * Sends a license key email to the customer.
     */
    public static function sendLicense(PDO $pdo, $order_id, string $license, string $email): bool
    {
        // 1. Fetch SMTP settings
        $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
        $settings = $stmt->fetch();

        if (!$settings || empty($settings['smtp_host'])) {
            throw new Exception("SMTP settings are not configured in the database.");
        }

        // 2. Load PHPMailer
        try {
            $libs_path = self::getLibsPath();
            require_once $libs_path . 'Exception.php';
            require_once $libs_path . 'PHPMailer.php';
            require_once $libs_path . 'SMTP.php';
        }
        catch (Exception $e) {
            throw new Exception("Library Load Error: " . $e->getMessage());
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $settings['smtp_user'];
            $mail->Password = $settings['smtp_pass'];

            $port = (int)$settings['smtp_port'];
            if ($port === 465) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
            elseif ($port === 587) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            else {
                $mail->SMTPSecure = ''; // Unencrypted
            }
            $mail->Port = $port;

            // Recipients
            $mail->setFrom($settings['smtp_from_email'], $settings['smtp_from_name']);
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your BioScript Architecture License Key';

            // Premium HTML template
            $mail->Body = '
            <html>
            <body style="font-family: Arial, sans-serif; background-color: #0f172a; color: #ffffff; padding: 40px; margin: 0;">
                <div style="max-width: 600px; margin: 0 auto; background-color: #1e293b; border-radius: 12px; overflow: hidden; border: 1px solid #334155;">
                    <div style="padding: 30px; border-bottom: 1px solid #334155; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                        <h2 style="margin: 0; color: #10b981; font-size: 24px; text-transform: uppercase; letter-spacing: 2px;">License Ready</h2>
                    </div>
                    <div style="padding: 40px;">
                        <p style="margin-top: 0; font-size: 16px; color: #94a3b8;">Hello,</p>
                        <p style="font-size: 16px; color: #94a3b8; line-height: 1.6;">Your BioScript Architecture license key has been generated successfully.</p>
                        
                        <div style="margin: 30px 0; padding: 25px; background-color: #0f172a; border-radius: 8px; border: 1px dashed #334155; text-align: center;">
                            <p style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #64748b;">Your License Key</p>
                            <div style="font-family: \'Courier New\', Courier, monospace; font-size: 24px; font-weight: bold; color: #ffffff; letter-spacing: 2px;">
                                ' . htmlspecialchars($license) . '
                            </div>
                        </div>
                        
                        <table style="width: 100%; font-size: 14px; color: #64748b;">
                            <tr>
                                <td style="padding: 5px 0;">Order ID:</td>
                                <td style="padding: 5px 0; text-align: right; color: #94a3b8;">#' . htmlspecialchars((string)$order_id) . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 0;">Status:</td>
                                <td style="padding: 5px 0; text-align: right; color: #10b981;">ACTIVE</td>
                            </tr>
                        </table>
                    </div>
                    <div style="padding: 20px; text-align: center; background-color: #0f172a; border-top: 1px solid #334155;">
                        <p style="margin: 0; font-size: 12px; color: #475569;">&copy; ' . date('Y') . ' BioScript Architecture Control. Keep this key secure.</p>
                    </div>
                </div>
            </body>
            </html>';

            $mail->send();
            return true;

        }
        catch (Exception $e) {
            throw new Exception("Mailer Error: " . $e->getMessage());
        }
    }

    /**
     * Sends a test email to verify SMTP configuration.
     */
    public static function sendTestEmail(PDO $pdo, string $test_email): bool
    {
        $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
        $settings = $stmt->fetch();

        if (!$settings) {
            throw new Exception("SMTP settings missing.");
        }

        // Load PHPMailer safely
        try {
            $libs_path = self::getLibsPath();
            require_once $libs_path . 'Exception.php';
            require_once $libs_path . 'PHPMailer.php';
            require_once $libs_path . 'SMTP.php';
        }
        catch (Exception $e) {
            throw new Exception("Library Load Error: " . $e->getMessage());
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $settings['smtp_user'];
            $mail->Password = $settings['smtp_pass'];
            $mail->Port = (int)$settings['smtp_port'];

            if ($mail->Port === 465) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
            elseif ($mail->Port === 587 || $mail->Port === 2525) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            else {
                $mail->SMTPSecure = '';
            }

            $mail->setFrom($settings['smtp_from_email'], $settings['smtp_from_name']);
            $mail->addAddress($test_email);
            $mail->isHTML(true);
            $mail->Subject = 'SMTP Config Test';
            $mail->Body = 'Settings verified! This is a test email from BioScript Architecture.';

            return $mail->send();
        }
        catch (Exception $e) {
            throw new Exception("Test failed: " . $e->getMessage());
        }
    }
}