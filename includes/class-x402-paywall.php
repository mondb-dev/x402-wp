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
        // Core classes
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-db.php';
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-payment-handler.php';
        require_once X402_PAYWALL_PLUGIN_DIR . 'includes/class-x402-paywall-token-registry.php';
        
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
    }
    
    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
}
