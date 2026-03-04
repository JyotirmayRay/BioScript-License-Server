<?php
declare(strict_types=1);

// System Configuration & Secrets

// The shared secret used to sign API responses (HMAC-SHA256)
// This MUST match the client-side configuration exactly.
define('SHARED_SECRET', 'b1oScR1pT_S3cr3t_2026_xYz987!');

// Lemon Squeezy Webhook Secret (from Lemon Squeezy Dashboard)
define('LS_WEBHOOK_SECRET', 'your_lemon_squeezy_secret_here');

// Admin Credentials (for future dashboard use)
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'supersecure');

// Database Path
define('DB_PATH', __DIR__ . '/authority.db');