<?php
/**
 * Plugin settings page
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings class
 */
class X402_Paywall_Settings {
    
    /**
     * Initialize settings
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_options_page(
            __('X402 Paywall Settings', 'x402-paywall'),
            __('X402 Paywall', 'x402-paywall'),
            'manage_options',
            'x402-paywall-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('x402_paywall_settings', 'x402_paywall_facilitator_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'https://facilitator.x402.org',
        ));
        
        register_setting('x402_paywall_settings', 'x402_paywall_auto_settle', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => '1',
        ));
        
        register_setting('x402_paywall_settings', 'x402_paywall_valid_before_buffer', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 6,
        ));
        
        register_setting('x402_paywall_settings', 'x402_paywall_enable_evm', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => '1',
        ));
        
        register_setting('x402_paywall_settings', 'x402_paywall_enable_spl', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => '1',
        ));
        
        // Add settings sections
        add_settings_section(
            'x402_paywall_facilitator_section',
            __('Facilitator Configuration', 'x402-paywall'),
            array($this, 'render_facilitator_section'),
            'x402-paywall-settings'
        );
        
        add_settings_section(
            'x402_paywall_network_section',
            __('Network Settings', 'x402-paywall'),
            array($this, 'render_network_section'),
            'x402-paywall-settings'
        );
        
        // Add settings fields
        add_settings_field(
            'x402_paywall_facilitator_url',
            __('Facilitator URL', 'x402-paywall'),
            array($this, 'render_facilitator_url_field'),
            'x402-paywall-settings',
            'x402_paywall_facilitator_section'
        );
        
        add_settings_field(
            'x402_paywall_auto_settle',
            __('Auto-settle Payments', 'x402-paywall'),
            array($this, 'render_auto_settle_field'),
            'x402-paywall-settings',
            'x402_paywall_facilitator_section'
        );
        
        add_settings_field(
            'x402_paywall_valid_before_buffer',
            __('Valid Before Buffer (seconds)', 'x402-paywall'),
            array($this, 'render_valid_before_buffer_field'),
            'x402-paywall-settings',
            'x402_paywall_facilitator_section'
        );
        
        add_settings_field(
            'x402_paywall_enable_evm',
            __('Enable EVM Networks', 'x402-paywall'),
            array($this, 'render_enable_evm_field'),
            'x402-paywall-settings',
            'x402_paywall_network_section'
        );
        
        add_settings_field(
            'x402_paywall_enable_spl',
            __('Enable Solana (SPL)', 'x402-paywall'),
            array($this, 'render_enable_spl_field'),
            'x402-paywall-settings',
            'x402_paywall_network_section'
        );
    }
    
    /**
     * Sanitize checkbox value
     */
    public function sanitize_checkbox($value) {
        return $value === '1' ? '1' : '0';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('x402_paywall_settings');
                do_settings_sections('x402-paywall-settings');
                submit_button(__('Save Settings', 'x402-paywall'));
                ?>
            </form>
            
            <div class="x402-paywall-settings-info">
                <h2><?php esc_html_e('About X402 Protocol', 'x402-paywall'); ?></h2>
                <p><?php esc_html_e('The x402 protocol is a modern, HTTP-based payment standard for digital commerce. It enables:', 'x402-paywall'); ?></p>
                <ul>
                    <li><?php esc_html_e('Low friction payments without credit card forms', 'x402-paywall'); ?></li>
                    <li><?php esc_html_e('Support for micropayments', 'x402-paywall'); ?></li>
                    <li><?php esc_html_e('Zero platform fees (only blockchain gas costs)', 'x402-paywall'); ?></li>
                    <li><?php esc_html_e('Fast settlement (~2 seconds)', 'x402-paywall'); ?></li>
                </ul>
                
                <h3><?php esc_html_e('Resources', 'x402-paywall'); ?></h3>
                <ul>
                    <li><a href="https://github.com/coinbase/x402" target="_blank"><?php esc_html_e('X402 Protocol Specification', 'x402-paywall'); ?></a></li>
                    <li><a href="https://github.com/mondb-dev/x402-php" target="_blank"><?php esc_html_e('X402 PHP Library Documentation', 'x402-paywall'); ?></a></li>
                    <li><a href="https://x402.gitbook.io/x402" target="_blank"><?php esc_html_e('X402 Documentation', 'x402-paywall'); ?></a></li>
                </ul>
            </div>
        </div>
        
        <style>
            .x402-paywall-settings-info {
                margin-top: 30px;
                padding: 20px;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
            }
            .x402-paywall-settings-info h2 {
                margin-top: 0;
            }
            .x402-paywall-settings-info ul {
                list-style: disc;
                margin-left: 20px;
            }
        </style>
        <?php
    }
    
    /**
     * Render facilitator section description
     */
    public function render_facilitator_section() {
        ?>
        <p><?php esc_html_e('Configure the x402 facilitator service that handles payment verification and settlement.', 'x402-paywall'); ?></p>
        <?php
    }
    
    /**
     * Render network section description
     */
    public function render_network_section() {
        ?>
        <p><?php esc_html_e('Choose which blockchain networks to enable for payments.', 'x402-paywall'); ?></p>
        <?php
    }
    
    /**
     * Render facilitator URL field
     */
    public function render_facilitator_url_field() {
        $value = get_option('x402_paywall_facilitator_url', 'https://facilitator.x402.org');
        ?>
        <input 
            type="url" 
            name="x402_paywall_facilitator_url" 
            id="x402_paywall_facilitator_url" 
            value="<?php echo esc_attr($value); ?>" 
            class="regular-text"
        />
        <p class="description">
            <?php esc_html_e('URL of the x402 facilitator service. Default: https://facilitator.x402.org', 'x402-paywall'); ?>
        </p>
        <?php
    }
    
    /**
     * Render auto-settle field
     */
    public function render_auto_settle_field() {
        $value = get_option('x402_paywall_auto_settle', '1');
        ?>
        <label>
            <input 
                type="checkbox" 
                name="x402_paywall_auto_settle" 
                id="x402_paywall_auto_settle" 
                value="1" 
                <?php checked($value, '1'); ?>
            />
            <?php esc_html_e('Automatically settle payments after verification', 'x402-paywall'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Recommended: Enables automatic settlement of verified payments.', 'x402-paywall'); ?>
        </p>
        <?php
    }
    
    /**
     * Render valid before buffer field
     */
    public function render_valid_before_buffer_field() {
        $value = get_option('x402_paywall_valid_before_buffer', 6);
        ?>
        <input 
            type="number" 
            name="x402_paywall_valid_before_buffer" 
            id="x402_paywall_valid_before_buffer" 
            value="<?php echo esc_attr($value); ?>" 
            min="1" 
            max="60" 
            class="small-text"
        />
        <p class="description">
            <?php esc_html_e('Time buffer in seconds for payment validity. Recommended: 6 seconds for Base/EVM L2s, 36 seconds for Ethereum mainnet, 2 seconds for Solana.', 'x402-paywall'); ?>
        </p>
        <?php
    }
    
    /**
     * Render enable EVM field
     */
    public function render_enable_evm_field() {
        $value = get_option('x402_paywall_enable_evm', '1');
        ?>
        <label>
            <input 
                type="checkbox" 
                name="x402_paywall_enable_evm" 
                id="x402_paywall_enable_evm" 
                value="1" 
                <?php checked($value, '1'); ?>
            />
            <?php esc_html_e('Enable EVM networks (Ethereum, Base, Optimism, Arbitrum, Polygon)', 'x402-paywall'); ?>
        </label>
        <?php
    }
    
    /**
     * Render enable SPL field
     */
    public function render_enable_spl_field() {
        $value = get_option('x402_paywall_enable_spl', '1');
        ?>
        <label>
            <input 
                type="checkbox" 
                name="x402_paywall_enable_spl" 
                id="x402_paywall_enable_spl" 
                value="1" 
                <?php checked($value, '1'); ?>
            />
            <?php esc_html_e('Enable Solana network (SPL tokens)', 'x402-paywall'); ?>
        </label>
        <?php
    }
}
