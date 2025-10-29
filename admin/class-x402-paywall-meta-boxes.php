<?php
/**
 * Meta boxes for post/page paywall configuration
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta boxes class
 */
class X402_Paywall_Meta_Boxes {
    
    /**
     * Token configurations
     */
    private $tokens = array(
        'evm' => array(
            'base-mainnet' => array(
                'name' => 'Base Mainnet',
                'tokens' => array(
                    '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913' => array(
                        'name' => 'USDC',
                        'decimals' => 6,
                        'token_name' => 'USD Coin',
                        'token_version' => '2',
                    ),
                ),
            ),
            'base-sepolia' => array(
                'name' => 'Base Sepolia (Testnet)',
                'tokens' => array(
                    '0x036CbD53842c5426634e7929541eC2318f3dCF7e' => array(
                        'name' => 'USDC',
                        'decimals' => 6,
                        'token_name' => 'USD Coin',
                        'token_version' => '2',
                    ),
                ),
            ),
            'ethereum-mainnet' => array(
                'name' => 'Ethereum Mainnet',
                'tokens' => array(
                    '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48' => array(
                        'name' => 'USDC',
                        'decimals' => 6,
                        'token_name' => 'USD Coin',
                        'token_version' => '2',
                    ),
                ),
            ),
            'ethereum-sepolia' => array(
                'name' => 'Ethereum Sepolia (Testnet)',
                'tokens' => array(
                    '0x1c7D4B196Cb0C7B01d743Fbc6116a902379C7238' => array(
                        'name' => 'USDC',
                        'decimals' => 6,
                        'token_name' => 'USD Coin',
                        'token_version' => '2',
                    ),
                ),
            ),
        ),
        'spl' => array(
            'solana-mainnet' => array(
                'name' => 'Solana Mainnet',
                'tokens' => array(
                    'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v' => array(
                        'name' => 'USDC',
                        'decimals' => 6,
                    ),
                ),
            ),
            'solana-devnet' => array(
                'name' => 'Solana Devnet (Testnet)',
                'tokens' => array(
                    '4zMMC9srt5Ri5X14GAgXhaHii3GnPAEERYPJgZJDncDU' => array(
                        'name' => 'USDC',
                        'decimals' => 6,
                    ),
                ),
            ),
        ),
    );
    
    /**
     * Initialize meta boxes
     */
    public function init() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box'));
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        $post_types = array('post', 'page');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'x402_paywall_meta_box',
                __('X402 Paywall Settings', 'x402-paywall'),
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render meta box
     *
     * @param WP_Post $post Post object
     */
    public function render_meta_box($post) {
        // Check if user can manage paywalls
        if (!current_user_can('publish_posts')) {
            echo '<p>' . esc_html__('You need author permissions or higher to configure paywalls.', 'x402-paywall') . '</p>';
            return;
        }
        
        // Get user profile
        $profile = X402_Paywall_DB::get_user_profile(get_current_user_id());
        
        if (!$profile || (empty($profile->evm_address) && empty($profile->spl_address))) {
            ?>
            <p><?php esc_html_e('Please configure your payment addresses in your profile before setting up a paywall.', 'x402-paywall'); ?></p>
            <a href="<?php echo esc_url(admin_url('profile.php#x402-paywall-profile')); ?>" class="button">
                <?php esc_html_e('Configure Payment Addresses', 'x402-paywall'); ?>
            </a>
            <?php
            return;
        }
        
        wp_nonce_field('x402_paywall_meta_box', 'x402_paywall_meta_box_nonce');
        
        // Get current values
        $enabled = get_post_meta($post->ID, '_x402_paywall_enabled', true);
        $network_type = get_post_meta($post->ID, '_x402_paywall_network_type', true) ?: 'evm';
        $network = get_post_meta($post->ID, '_x402_paywall_network', true) ?: 'base-mainnet';
        $token_address = get_post_meta($post->ID, '_x402_paywall_token_address', true);
        $amount = get_post_meta($post->ID, '_x402_paywall_amount', true) ?: '1';
        
        ?>
        
        <div class="x402-paywall-meta-box">
            <p>
                <label>
                    <input 
                        type="checkbox" 
                        name="x402_paywall_enabled" 
                        id="x402_paywall_enabled" 
                        value="1" 
                        <?php checked($enabled, '1'); ?>
                    />
                    <?php esc_html_e('Enable Paywall', 'x402-paywall'); ?>
                </label>
            </p>
            
            <div id="x402_paywall_settings" style="<?php echo $enabled !== '1' ? 'display:none;' : ''; ?>">
                <p>
                    <label for="x402_paywall_network_type">
                        <strong><?php esc_html_e('Network Type', 'x402-paywall'); ?></strong>
                    </label>
                    <select name="x402_paywall_network_type" id="x402_paywall_network_type" style="width: 100%;">
                        <?php if (!empty($profile->evm_address)): ?>
                            <option value="evm" <?php selected($network_type, 'evm'); ?>>
                                <?php esc_html_e('EVM (Ethereum, Base, etc.)', 'x402-paywall'); ?>
                            </option>
                        <?php endif; ?>
                        
                        <?php if (!empty($profile->spl_address)): ?>
                            <option value="spl" <?php selected($network_type, 'spl'); ?>>
                                <?php esc_html_e('Solana (SPL)', 'x402-paywall'); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </p>
                
                <p>
                    <label for="x402_paywall_network">
                        <strong><?php esc_html_e('Network', 'x402-paywall'); ?></strong>
                    </label>
                    <select name="x402_paywall_network" id="x402_paywall_network" style="width: 100%;">
                        <?php $this->render_network_options($network_type, $network); ?>
                    </select>
                </p>
                
                <p>
                    <label for="x402_paywall_token_address">
                        <strong><?php esc_html_e('Token', 'x402-paywall'); ?></strong>
                    </label>
                    <select name="x402_paywall_token_address" id="x402_paywall_token_address" style="width: 100%;">
                        <?php $this->render_token_options($network_type, $network, $token_address); ?>
                    </select>
                </p>
                
                <p>
                    <label for="x402_paywall_amount">
                        <strong><?php esc_html_e('Amount', 'x402-paywall'); ?></strong>
                    </label>
                    <input 
                        type="number" 
                        name="x402_paywall_amount" 
                        id="x402_paywall_amount" 
                        value="<?php echo esc_attr($amount); ?>" 
                        step="0.01" 
                        min="0.01" 
                        style="width: 100%;"
                    />
                    <span class="description"><?php esc_html_e('Payment amount in selected token', 'x402-paywall'); ?></span>
                </p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Show/hide settings based on enabled checkbox
            $('#x402_paywall_enabled').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#x402_paywall_settings').slideDown();
                } else {
                    $('#x402_paywall_settings').slideUp();
                }
            });
            
            // Update network options when network type changes
            $('#x402_paywall_network_type').on('change', function() {
                var networkType = $(this).val();
                // Trigger AJAX to get updated network options
                updateNetworkOptions(networkType);
            });
            
            // Update token options when network changes
            $('#x402_paywall_network').on('change', function() {
                var networkType = $('#x402_paywall_network_type').val();
                var network = $(this).val();
                updateTokenOptions(networkType, network);
            });
            
            function updateNetworkOptions(networkType) {
                var $networkSelect = $('#x402_paywall_network');
                $networkSelect.empty();
                
                <?php foreach ($this->tokens as $type => $networks): ?>
                if (networkType === '<?php echo esc_js($type); ?>') {
                    <?php foreach ($networks as $net_key => $net_data): ?>
                    $networkSelect.append($('<option>', {
                        value: '<?php echo esc_js($net_key); ?>',
                        text: '<?php echo esc_js($net_data['name']); ?>'
                    }));
                    <?php endforeach; ?>
                }
                <?php endforeach; ?>
                
                $networkSelect.trigger('change');
            }
            
            function updateTokenOptions(networkType, network) {
                var $tokenSelect = $('#x402_paywall_token_address');
                $tokenSelect.empty();
                
                <?php foreach ($this->tokens as $type => $networks): ?>
                if (networkType === '<?php echo esc_js($type); ?>') {
                    <?php foreach ($networks as $net_key => $net_data): ?>
                    if (network === '<?php echo esc_js($net_key); ?>') {
                        <?php foreach ($net_data['tokens'] as $token_addr => $token_data): ?>
                        $tokenSelect.append($('<option>', {
                            value: '<?php echo esc_js($token_addr); ?>',
                            text: '<?php echo esc_js($token_data['name']); ?>'
                        }));
                        <?php endforeach; ?>
                    }
                    <?php endforeach; ?>
                }
                <?php endforeach; ?>
            }
        });
        </script>
        
        <style>
            .x402-paywall-meta-box p {
                margin-bottom: 15px;
            }
            .x402-paywall-meta-box label {
                display: block;
                margin-bottom: 5px;
            }
        </style>
        <?php
    }
    
    /**
     * Render network options
     */
    private function render_network_options($network_type, $selected_network) {
        if (isset($this->tokens[$network_type])) {
            foreach ($this->tokens[$network_type] as $net_key => $net_data) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($net_key),
                    selected($selected_network, $net_key, false),
                    esc_html($net_data['name'])
                );
            }
        }
    }
    
    /**
     * Render token options
     */
    private function render_token_options($network_type, $network, $selected_token) {
        if (isset($this->tokens[$network_type][$network]['tokens'])) {
            foreach ($this->tokens[$network_type][$network]['tokens'] as $token_addr => $token_data) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($token_addr),
                    selected($selected_token, $token_addr, false),
                    esc_html($token_data['name'])
                );
            }
        }
    }
    
    /**
     * Save meta box data
     *
     * @param int $post_id Post ID
     */
    public function save_meta_box($post_id) {
        // Check nonce
        if (!isset($_POST['x402_paywall_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['x402_paywall_meta_box_nonce'], 'x402_paywall_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id) || !current_user_can('publish_posts')) {
            return;
        }
        
        // Save enabled status
        $enabled = isset($_POST['x402_paywall_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_x402_paywall_enabled', $enabled);
        
        if ($enabled === '1') {
            // Save paywall configuration
            $network_type = isset($_POST['x402_paywall_network_type']) ? sanitize_text_field($_POST['x402_paywall_network_type']) : 'evm';
            $network = isset($_POST['x402_paywall_network']) ? sanitize_text_field($_POST['x402_paywall_network']) : '';
            $token_address = isset($_POST['x402_paywall_token_address']) ? sanitize_text_field($_POST['x402_paywall_token_address']) : '';
            $amount = isset($_POST['x402_paywall_amount']) ? floatval($_POST['x402_paywall_amount']) : 1.0;
            
            update_post_meta($post_id, '_x402_paywall_network_type', $network_type);
            update_post_meta($post_id, '_x402_paywall_network', $network);
            update_post_meta($post_id, '_x402_paywall_token_address', $token_address);
            update_post_meta($post_id, '_x402_paywall_amount', $amount);
            
            // Store token metadata for later use
            if (isset($this->tokens[$network_type][$network]['tokens'][$token_address])) {
                $token_meta = $this->tokens[$network_type][$network]['tokens'][$token_address];
                update_post_meta($post_id, '_x402_paywall_token_decimals', $token_meta['decimals']);
                
                if (isset($token_meta['token_name'])) {
                    update_post_meta($post_id, '_x402_paywall_token_name', $token_meta['token_name']);
                }
                if (isset($token_meta['token_version'])) {
                    update_post_meta($post_id, '_x402_paywall_token_version', $token_meta['token_version']);
                }
            }
        }
    }
    
    /**
     * Get token configuration
     *
     * @param string $network_type Network type
     * @param string $network Network ID
     * @param string $token_address Token address
     * @return array|null Token configuration
     */
    public function get_token_config($network_type, $network, $token_address) {
        if (isset($this->tokens[$network_type][$network]['tokens'][$token_address])) {
            return $this->tokens[$network_type][$network]['tokens'][$token_address];
        }
        return null;
    }
}
