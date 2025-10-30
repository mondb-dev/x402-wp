<?php
/**
 * Plugin Name: X402 Paywall
 * Plugin URI: https://github.com/mondb-dev/x402-wp
 * Description: Implement x402 payment protocol paywalls on WordPress pages and posts. Support for EVM and Solana blockchain payments.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: mondb-dev
 * Author URI: https://github.com/mondb-dev
 * License: Apache-2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 * Text Domain: x402-paywall
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('X402_PAYWALL_VERSION', '1.0.0');
define('X402_PAYWALL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('X402_PAYWALL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('X402_PAYWALL_PLUGIN_FILE', __FILE__);

// Check PHP version
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('X402 Paywall requires PHP 8.1 or higher. Please upgrade your PHP version.', 'x402-paywall'); ?></p>
        </div>
        <?php
    });
    return;
}

// Load bootstrap file (handles dependency loading)
require_once X402_PAYWALL_PLUGIN_DIR . 'bootstrap.php';

// If dependencies aren't loaded, stop here
if (!class_exists('X402\Facilitator\FacilitatorClient')) {
    return;
}

// Include core plugin class
require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall.php';

/**
 * Initialize the plugin
 */
function x402_paywall_init() {
    $plugin = new X402_Paywall();
    $plugin->run();
}
add_action('plugins_loaded', 'x402_paywall_init');

/**
 * Plugin activation hook
 */
function x402_paywall_activate() {
    require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-activator.php';
    X402_Paywall_Activator::activate();
}
register_activation_hook(__FILE__, 'x402_paywall_activate');

/**
 * Plugin deactivation hook
 */
function x402_paywall_deactivate() {
    require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-deactivator.php';
    X402_Paywall_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'x402_paywall_deactivate');
