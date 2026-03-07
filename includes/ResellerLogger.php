<?php
/**
 * RESELLER ACTIVITY LOGGER
 * 
 * Dual-write logging: system_events table + file-based audit log.
 * Used for reseller login, license generation, abuse detection, and email verification.
 */
class ResellerLogger
{
    private const LOG_DIR = __DIR__ . '/../logs';
    private const LOG_FILE = 'reseller_activity.log';

    /**
     * Log an event to both the database and a file.
     * 
     * @param PDO    $pdo        Database connection
     * @param string $event_type Event type identifier (e.g., 'reseller_login', 'license_generated')
     * @param string $details    Human-readable details
     * @param array  $context    Optional context (reseller_id, ip, email, etc.)
     */
    public static function log(PDO $pdo, string $event_type, string $details, array $context = []): void
    {
        // 1. Database: system_events table
        try {
            $stmt = $pdo->prepare("INSERT INTO system_events (event_type, details) VALUES (?, ?)");
            $stmt->execute([$event_type, $details]);
        }
        catch (\Exception $e) {
        // Silently fail DB logging — never break main flow
        }

        // 2. File: logs/reseller_activity.log
        try {
            if (!is_dir(self::LOG_DIR)) {
                @mkdir(self::LOG_DIR, 0755, true);
            }

            $ip = $context['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'cli');
            $reseller_id = $context['reseller_id'] ?? '-';
            $email = $context['email'] ?? '-';

            $line = sprintf(
                "[%s] [%s] [IP:%s] [Reseller:%s] [Email:%s] %s\n",
                date('Y-m-d H:i:s'),
                strtoupper($event_type),
                $ip,
                $reseller_id,
                $email,
                $details
            );

            @file_put_contents(self::LOG_DIR . '/' . self::LOG_FILE, $line, FILE_APPEND | LOCK_EX);
        }
        catch (\Exception $e) {
        // Silently fail file logging
        }
    }

    /**
     * Count events of a specific type for a reseller within a time window.
     * Used for rate limiting and abuse detection.
     * 
     * @param PDO    $pdo         Database connection
     * @param string $event_type  Event type to count
     * @param int    $reseller_id Reseller ID
     * @param string $window      SQLite time modifier (e.g., '-1 hour', '-1 day')
     * @return int
     */
    public static function countEvents(PDO $pdo, string $event_type, int $reseller_id, string $window): int
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM system_events 
                 WHERE event_type = ? 
                 AND details LIKE ? 
                 AND created_at >= datetime('now', ?)"
            );
            $stmt->execute([$event_type, "Reseller: $reseller_id |%", $window]);
            return (int)$stmt->fetchColumn();
        }
        catch (\Exception $e) {
            return 0; // Fail open on count errors — don't block legitimate users
        }
    }

    /**
     * Count login attempts from an IP within a time window.
     * Used for brute force protection.
     */
    public static function countLoginAttempts(PDO $pdo, string $ip, string $window): int
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM system_events 
                 WHERE event_type = 'login_failed' 
                 AND details LIKE ? 
                 AND created_at >= datetime('now', ?)"
            );
            $stmt->execute(["%IP: $ip%", $window]);
            return (int)$stmt->fetchColumn();
        }
        catch (\Exception $e) {
            return 0;
        }
    }
}