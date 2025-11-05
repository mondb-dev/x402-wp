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
        
        // Fire deactivation hook for cron and other handlers
        do_action('x402_paywall_deactivated');
    }
}
