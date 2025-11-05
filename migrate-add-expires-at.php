<?php
/**
 * Migration script to add expires_at column to existing installations
 * Run this once if upgrading from a previous version
 * 
 * This is automatically handled by the activator, but this script
 * can be run manually if needed.
 * 
 * Run: php migrate-add-expires-at.php
 */

// Load WordPress
require_once dirname(__DIR__, 3) . '/wp-load.php';

if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

global $wpdb;

echo "=== X402 Paywall: Add expires_at Column Migration ===\n\n";

$table_name = $wpdb->prefix . 'x402_payment_logs';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

if (!$table_exists) {
    echo "❌ Table {$table_name} does not exist\n";
    echo "   Please activate the plugin first to create tables.\n";
    exit(1);
}

echo "✓ Table {$table_name} exists\n\n";

// Check if expires_at column already exists
$column_exists = $wpdb->get_var("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = '{$table_name}' 
    AND COLUMN_NAME = 'expires_at'
") === 'expires_at';

if ($column_exists) {
    echo "✓ Column 'expires_at' already exists\n";
    echo "   No migration needed.\n\n";
    
    // Show sample data
    $sample = $wpdb->get_row("
        SELECT post_id, payment_status, expires_at, created_at 
        FROM {$table_name} 
        WHERE payment_status = 'verified' 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    if ($sample) {
        echo "Sample payment record:\n";
        echo "  Post ID: {$sample->post_id}\n";
        echo "  Status: {$sample->payment_status}\n";
        echo "  Created: {$sample->created_at}\n";
        echo "  Expires: " . ($sample->expires_at ?? 'Never (permanent)') . "\n";
    }
    
    exit(0);
}

echo "Adding 'expires_at' column...\n";

// Add the column
$result = $wpdb->query("
    ALTER TABLE {$table_name}
    ADD COLUMN expires_at datetime DEFAULT NULL AFTER facilitator_message
");

if ($result === false) {
    echo "❌ Failed to add column\n";
    echo "   Error: " . $wpdb->last_error . "\n";
    exit(1);
}

echo "✓ Column added successfully\n\n";

// Add index for performance
echo "Adding index on expires_at...\n";

$index_result = $wpdb->query("
    ALTER TABLE {$table_name}
    ADD INDEX idx_expires (expires_at)
");

if ($index_result === false) {
    echo "⚠ Warning: Could not add index (may already exist)\n";
    echo "   Error: " . $wpdb->last_error . "\n";
} else {
    echo "✓ Index added successfully\n";
}

echo "\n=== Migration Complete ===\n\n";

// Show stats
$total_payments = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE payment_status = 'verified'");
$expired_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE expires_at < NOW() AND payment_status = 'verified'");
$permanent_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE expires_at IS NULL AND payment_status = 'verified'");

echo "Payment Statistics:\n";
echo "  Total verified payments: {$total_payments}\n";
echo "  Permanent access (NULL): {$permanent_count}\n";
echo "  Expired payments: {$expired_count}\n";
echo "  Active with expiry: " . ($total_payments - $permanent_count - $expired_count) . "\n";
echo "\n";

echo "Next Steps:\n";
echo "1. All existing payments have NULL expires_at (permanent access)\n";
echo "2. New payments will use the configured access duration\n";
echo "3. Configure access duration per post in the meta box\n";
echo "4. Default: 1 year (maintains current behavior)\n";
echo "\n";

echo "Done!\n";
