<?php
/**
 * Bootstrap file for X402 Paywall plugin
 * Handles dependency loading with fallback mechanisms
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if dependencies are installed
 */
function x402_paywall_check_dependencies() {
    // Check for autoloader
    $autoloader = X402_PAYWALL_PLUGIN_DIR . 'autoloader.php';
    if (!file_exists($autoloader)) {
        return false;
    }
    
    // Check for x402-php source files
    $x402_facilitator = X402_PAYWALL_PLUGIN_DIR . 'vendor/x402-php/src/Facilitator/FacilitatorClient.php';
    if (!file_exists($x402_facilitator)) {
        return false;
    }
    
    // Check for Guzzle (from Composer)
    $composer_autoload = X402_PAYWALL_PLUGIN_DIR . 'vendor/autoload.php';
    if (!file_exists($composer_autoload)) {
        return false;
    }
    
    return true;
}

/**
 * Load dependencies
 */
function x402_paywall_load_dependencies() {
    // Load our custom autoloader
    $autoloader = X402_PAYWALL_PLUGIN_DIR . 'autoloader.php';
    
    if (file_exists($autoloader)) {
        require_once $autoloader;
        return true;
    }
    
    return false;
}

/**
 * Show admin notice if dependencies are missing
 */
function x402_paywall_missing_dependencies_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e('X402 Paywall Error:', 'x402-paywall'); ?></strong>
            <?php esc_html_e('Required dependencies are missing. Please run the following command in the plugin directory:', 'x402-paywall'); ?>
        </p>
        <p>
            <code>composer install --no-dev</code>
        </p>
        <p>
            <?php
            printf(
                wp_kses(
                    /* translators: %s: link to installation documentation */
                    __('See the <a href="%s" target="_blank">installation documentation</a> for detailed instructions.', 'x402-paywall'),
                    array('a' => array('href' => array(), 'target' => array()))
                ),
                esc_url('https://github.com/mondb-dev/x402-wp/blob/main/INSTALLATION.md')
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Show admin notice if BCMath extension is missing
 */
function x402_paywall_missing_bcmath_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e('X402 Paywall Warning:', 'x402-paywall'); ?></strong>
            <?php esc_html_e('The BCMath PHP extension is not available. The plugin will use a fallback implementation for precise token amount calculations, but enabling BCMath is recommended.', 'x402-paywall'); ?>
        </p>
        <p><?php esc_html_e('Please enable the BCMath extension in your PHP configuration for optimal accuracy and performance.', 'x402-paywall'); ?></p>
    </div>
    <?php
}

/**
 * Show admin notice with instructions for first-time setup
 */
function x402_paywall_setup_notice() {
    // Only show to users who can manage options
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if user has dismissed the notice
    if (get_option('x402_paywall_setup_notice_dismissed')) {
        return;
    }
    
    // Check if user has already configured payment addresses
    $user_id = get_current_user_id();
    
    // Only check profile if the DB class is available
    if (class_exists('X402_Paywall_DB')) {
        $profile = X402_Paywall_DB::get_user_profile($user_id);
        
        if ($profile && ($profile->evm_address || $profile->spl_address)) {
            // User has configured addresses, dismiss the notice
            update_option('x402_paywall_setup_notice_dismissed', true);
            return;
        }
    }
    
    ?>
    <div class="notice notice-info is-dismissible" id="x402-paywall-setup-notice">
        <h2><?php esc_html_e('Welcome to X402 Paywall!', 'x402-paywall'); ?></h2>
        <p><?php esc_html_e('Thank you for installing X402 Paywall. To get started:', 'x402-paywall'); ?></p>
        <ol>
            <li>
                <a href="<?php echo esc_url(admin_url('profile.php#x402-paywall-profile')); ?>">
                    <?php esc_html_e('Configure your payment addresses', 'x402-paywall'); ?>
                </a>
            </li>
            <li><?php esc_html_e('Edit any post or page to add a paywall', 'x402-paywall'); ?></li>
            <li>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=x402-paywall-settings')); ?>">
                    <?php esc_html_e('Review plugin settings', 'x402-paywall'); ?>
                </a>
            </li>
        </ol>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=x402-paywall')); ?>" class="button button-primary">
                <?php esc_html_e('Get Started', 'x402-paywall'); ?>
            </a>
            <button type="button" class="button" onclick="x402PaywallDismissNotice()">
                <?php esc_html_e('Dismiss', 'x402-paywall'); ?>
            </button>
        </p>
    </div>
    <script>
    function x402PaywallDismissNotice() {
        jQuery.post(ajaxurl, {
            action: 'x402_paywall_dismiss_setup_notice',
            nonce: '<?php echo esc_js(wp_create_nonce('x402_paywall_dismiss_notice')); ?>'
        }, function() {
            jQuery('#x402-paywall-setup-notice').fadeOut();
        });
    }
    </script>
    <?php
}

/**
 * AJAX handler to dismiss setup notice
 */
function x402_paywall_dismiss_setup_notice_ajax() {
    check_ajax_referer('x402_paywall_dismiss_notice', 'nonce');
    
    if (current_user_can('manage_options')) {
        update_option('x402_paywall_setup_notice_dismissed', true);
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}
add_action('wp_ajax_x402_paywall_dismiss_setup_notice', 'x402_paywall_dismiss_setup_notice_ajax');

// Check and load dependencies
if (!x402_paywall_check_dependencies()) {
    // Dependencies not installed
    add_action('admin_notices', 'x402_paywall_missing_dependencies_notice');
    return;
}

// Load dependencies
if (!x402_paywall_load_dependencies()) {
    // Failed to load dependencies
    add_action('admin_notices', 'x402_paywall_missing_dependencies_notice');
    return;
}

// Warn if BCMath extension is unavailable
if (!extension_loaded('bcmath')) {
    add_action('admin_notices', 'x402_paywall_missing_bcmath_notice');
}

// Dependencies loaded successfully - show setup notice if needed
add_action('admin_notices', 'x402_paywall_setup_notice');
