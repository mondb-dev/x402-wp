<?php
/**
 * Admin functionality
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 */
class X402_Paywall_Admin {
    
    /**
     * Initialize admin hooks
     */
    public function init() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_x402_detect_token', array($this, 'ajax_detect_token'));
    }
    
    /**
     * Enqueue admin CSS and JavaScript
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'x402-paywall') !== false) {
            wp_enqueue_style(
                'x402-paywall-admin',
                X402_PAYWALL_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                X402_PAYWALL_VERSION
            );
            
            wp_enqueue_script(
                'x402-paywall-admin',
                X402_PAYWALL_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                X402_PAYWALL_VERSION,
                true
            );
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('X402 Paywall', 'x402-paywall'),
            __('X402 Paywall', 'x402-paywall'),
            'manage_options',
            'x402-paywall',
            array($this, 'render_main_page'),
            'dashicons-lock',
            30
        );
    }
    
    /**
     * Render main admin page
     */
    public function render_main_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="x402-paywall-admin-container">
                <div class="x402-paywall-card">
                    <h2><?php esc_html_e('Welcome to X402 Paywall', 'x402-paywall'); ?></h2>
                    <p><?php esc_html_e('This plugin allows you to set up payment paywalls on your WordPress posts and pages using the x402 payment protocol.', 'x402-paywall'); ?></p>
                    
                    <h3><?php esc_html_e('Getting Started', 'x402-paywall'); ?></h3>
                    <ol>
                        <li><?php esc_html_e('Configure your payment addresses in your user profile', 'x402-paywall'); ?></li>
                        <li><?php esc_html_e('Edit any post or page and enable the paywall in the X402 Paywall meta box', 'x402-paywall'); ?></li>
                        <li><?php esc_html_e('Set the payment amount and choose your preferred token', 'x402-paywall'); ?></li>
                        <li><?php esc_html_e('Publish and start receiving payments!', 'x402-paywall'); ?></li>
                    </ol>
                    
                    <h3><?php esc_html_e('Quick Links', 'x402-paywall'); ?></h3>
                    <ul>
                        <li><a href="<?php echo esc_url(admin_url('profile.php#x402-paywall-profile')); ?>"><?php esc_html_e('Configure Your Payment Profile', 'x402-paywall'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('options-general.php?page=x402-paywall-settings')); ?>"><?php esc_html_e('Plugin Settings', 'x402-paywall'); ?></a></li>
                        <li><a href="https://github.com/mondb-dev/x402-php" target="_blank"><?php esc_html_e('X402 Documentation', 'x402-paywall'); ?></a></li>
                    </ul>
                </div>
                
                <div class="x402-paywall-card">
                    <h3><?php esc_html_e('Supported Networks', 'x402-paywall'); ?></h3>
                    <p><?php esc_html_e('The plugin supports the following blockchain networks:', 'x402-paywall'); ?></p>
                    <ul>
                        <li><strong><?php esc_html_e('EVM Networks:', 'x402-paywall'); ?></strong> Ethereum, Base, Optimism, Arbitrum, Polygon</li>
                        <li><strong><?php esc_html_e('SVM Networks:', 'x402-paywall'); ?></strong> Solana</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for token detection
     */
    public function ajax_detect_token() {
        // Verify nonce
        check_ajax_referer('x402_detect_token', 'nonce');
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'x402-paywall')
            ));
        }
        
        // Get parameters
        $contract_address = isset($_POST['contract_address']) ? sanitize_text_field($_POST['contract_address']) : '';
        $network = isset($_POST['network']) ? sanitize_text_field($_POST['network']) : '';
        
        if (empty($contract_address)) {
            wp_send_json_error(array(
                'message' => __('Contract address is required.', 'x402-paywall')
            ));
        }
        
        if (empty($network)) {
            wp_send_json_error(array(
                'message' => __('Network is required.', 'x402-paywall')
            ));
        }
        
        // Get token detector instance
        $detector = X402_Paywall_Token_Detector::get_instance();
        
        // Detect token
        $token_info = $detector->detect_token($contract_address, $network);
        
        if (is_wp_error($token_info)) {
            wp_send_json_error(array(
                'message' => $token_info->get_error_message()
            ));
        }
        
        // Return success with token data
        wp_send_json_success($token_info);
    }
}
