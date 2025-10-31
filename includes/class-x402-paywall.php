<?php
/**
 * Main plugin class
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main X402_Paywall Class
 */
class X402_Paywall {
    
    /**
     * Plugin version
     *
     * @var string
     */
    protected $version = '1.0.0';
    
    /**
     * Initialize the plugin
     */
    public function __construct() {
        $this->load_dependencies();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Load Composer autoloader first (for X402 library)
        $autoload_path = X402_PAYWALL_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
        }
        
        // Check x402-php library version compatibility
        $this->check_x402_php_version();
        
        // X402 Library Wrapper (must load first)
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-x402-client.php';
        
        // Core classes
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-db.php';
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-payment-handler.php';
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-token-registry.php';
        
        // New core handler classes
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-security.php';
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-hooks.php';
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-template-loader.php';
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-protocol.php';
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-finance.php';
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-rest-api.php';
        
        // Blockchain-specific handlers
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-spl-handler.php';
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-token-detector.php';
        
        // Admin classes
        if (is_admin()) {
            require_once X402_PAYWALL_PLUGIN_DIR . 'admin/class-x402-paywall-admin.php';
            require_once X402_PAYWALL_PLUGIN_DIR . 'admin/class-x402-paywall-profile.php';
            require_once X402_PAYWALL_PLUGIN_DIR . 'admin/class-x402-paywall-meta-boxes.php';
            require_once X402_PAYWALL_PLUGIN_DIR . 'admin/class-x402-paywall-settings.php';
        }
        
        // Public-facing classes
        require_once X402_PAYWALL_PLUGIN_DIR . 'public/class-x402-paywall-public.php';
    }
    
    /**
     * Run the plugin
     */
    public function run() {
        // Initialize X402 client wrapper first
        X402_Paywall_X402_Client::get_instance();
        
        // Initialize core handlers (singletons)
        X402_Paywall_Security::get_instance();
        X402_Paywall_Hooks::get_instance();
        X402_Paywall_Template_Loader::get_instance();
        X402_Paywall_Protocol::get_instance();
        X402_Paywall_Finance::get_instance();
        X402_Paywall_REST_API::get_instance();
        
        // Initialize blockchain-specific handlers
        X402_Paywall_SPL_Handler::get_instance();
        X402_Paywall_Token_Detector::get_instance();
        
        // Fire plugin initialization hook
        do_action('x402_paywall_before_init');
        
        // Initialize admin functionality
        if (is_admin()) {
            $admin = new X402_Paywall_Admin();
            $admin->init();
            
            $profile = new X402_Paywall_Profile();
            $profile->init();
            
            $meta_boxes = new X402_Paywall_Meta_Boxes();
            $meta_boxes->init();
            
            $settings = new X402_Paywall_Settings();
            $settings->init();
        }
        
        // Initialize public functionality
        $public = new X402_Paywall_Public();
        $public->init();
        
        // Fire plugin loaded hook
        do_action('x402_paywall_loaded');
    }
    
    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Check x402-php library version compatibility
     *
     * @return void
     */
    private function check_x402_php_version() {
        // Check if x402-php classes are available
        if (!class_exists('X402\Facilitator\FacilitatorClient')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>';
                echo '<strong>X402 Paywall Error:</strong> x402-php library not found. ';
                echo 'Please run <code>composer install</code> in the plugin directory.';
                echo '</p></div>';
            });
            return;
        }
        
        // Check for version constant or class method
        $min_version = '1.0.0';
        $current_version = null;
        
        // Try to get version from x402-php library
        if (defined('X402_PHP_VERSION')) {
            $current_version = X402_PHP_VERSION;
        } elseif (class_exists('X402\Version') && method_exists('X402\Version', 'get')) {
            $current_version = call_user_func(array('X402\Version', 'get'));
        }
        
        // If version is available, check compatibility
        if ($current_version !== null) {
            if (version_compare($current_version, $min_version, '<')) {
                add_action('admin_notices', function() use ($current_version, $min_version) {
                    echo '<div class="error"><p>';
                    echo '<strong>X402 Paywall Error:</strong> x402-php library version ' . esc_html($current_version) . ' is too old. ';
                    echo 'Minimum required version is ' . esc_html($min_version) . '. ';
                    echo 'Please run <code>composer update mondb-dev/x402-php</code> to upgrade.';
                    echo '</p></div>';
                });
            }
        } else {
            // Version not available - log warning but continue
            error_log('X402 Paywall: Unable to determine x402-php library version. Proceeding without version check.');
        }
    }
}
