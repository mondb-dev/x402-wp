<?php
/**
 * Plugin uninstall script
 *
 * @package X402_Paywall
 */

// Exit if accessed directly or not uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete plugin options
$options = array(
    'x402_paywall_facilitator_url',
    'x402_paywall_auto_settle',
    'x402_paywall_valid_before_buffer',
    'x402_paywall_enable_evm',
    'x402_paywall_enable_spl',
    'x402_paywall_version',
);

foreach ($options as $option) {
    delete_option($option);
}

// Drop custom tables
$table_names = array(
    $wpdb->prefix . 'x402_user_profiles',
    $wpdb->prefix . 'x402_payment_logs',
);

foreach ($table_names as $table_name) {
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

// Delete post meta for all posts
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_x402_paywall_%'");

// Delete user meta
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'x402_paywall_%'");

// Clear any cached data
wp_cache_flush();
