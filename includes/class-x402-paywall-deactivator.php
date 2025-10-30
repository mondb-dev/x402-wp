<?php
/**
 * Plugin deactivation class
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin deactivator class
 */
class X402_Paywall_Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any scheduled cron jobs if we add them later
        wp_clear_scheduled_hook('x402_paywall_cleanup');
    }
}
