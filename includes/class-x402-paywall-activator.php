<?php
/**
 * Plugin activation class
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin activator class
 */
class X402_Paywall_Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create user payment profiles table
        $table_name = $wpdb->prefix . 'x402_user_profiles';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            evm_address varchar(42) DEFAULT NULL,
            spl_address varchar(44) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY evm_address (evm_address),
            KEY spl_address (spl_address)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create payment logs table
        $table_name = $wpdb->prefix . 'x402_payment_logs';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            user_address varchar(200) NOT NULL,
            normalized_address varchar(200) NOT NULL,
            amount varchar(78) NOT NULL,
            token_address varchar(100) NOT NULL,
            network varchar(50) NOT NULL,
            transaction_hash varchar(100) DEFAULT NULL,
            payer_identifier varchar(200) DEFAULT NULL,
            settlement_proof longtext DEFAULT NULL,
            payment_status varchar(20) NOT NULL DEFAULT 'pending',
            facilitator_signature text DEFAULT NULL,
            facilitator_reference varchar(191) DEFAULT NULL,
            failure_status_code smallint(5) DEFAULT NULL,
            facilitator_error_code varchar(191) DEFAULT NULL,
            facilitator_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY user_address (user_address),
            KEY normalized_address (normalized_address),
            KEY payer_identifier (payer_identifier),
            KEY payment_status (payment_status),
            KEY facilitator_reference (facilitator_reference),
            KEY failure_status_code (failure_status_code),
            KEY facilitator_error_code (facilitator_error_code),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Create financial audit trail table
        $table_name = $wpdb->prefix . 'x402_financial_audit';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id varchar(36) NOT NULL,
            timestamp datetime NOT NULL,
            post_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            user_address varchar(200) NOT NULL,
            recipient_address varchar(200) NOT NULL,
            amount decimal(65,18) NOT NULL,
            token_address varchar(100) NOT NULL,
            network varchar(50) NOT NULL,
            transaction_hash varchar(100) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            ip_address varchar(45) NOT NULL,
            user_agent text NOT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY user_address (user_address),
            KEY recipient_address (recipient_address),
            KEY transaction_hash (transaction_hash),
            KEY status (status),
            KEY timestamp (timestamp),
            KEY network (network)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Set default options
        $default_options = array(
            'facilitator_url' => 'https://facilitator.x402.org',
            'auto_settle' => '1',
            'valid_before_buffer' => '6',
            'enable_evm' => '1',
            'enable_spl' => '1',
        );
        
        foreach ($default_options as $key => $value) {
            if (get_option('x402_paywall_' . $key) === false) {
                add_option('x402_paywall_' . $key, $value);
            }
        }
        
        // Store plugin version
        update_option('x402_paywall_version', X402_PAYWALL_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
