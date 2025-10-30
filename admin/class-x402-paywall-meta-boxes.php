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
        add_action('admin_notices', array($this, 'display_admin_notices'));
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
        $amount_atomic = get_post_meta($post->ID, '_x402_paywall_amount', true);
        $amount_format = get_post_meta($post->ID, '_x402_paywall_amount_format', true);
        $token_decimals = get_post_meta($post->ID, '_x402_paywall_token_decimals', true);

        if (!$token_decimals) {
            $selected_token_config = $this->get_token_config($network_type, $network, $token_address);
            if ($selected_token_config && isset($selected_token_config['decimals'])) {
                $token_decimals = (int) $selected_token_config['decimals'];
            }
        }

        if (!$token_decimals) {
            $token_decimals = 6;
        }

        if ($amount_atomic !== '' && $amount_format !== 'atomic') {
            $legacy_amount = $this->validate_and_convert_amount($amount_atomic, $token_decimals);
            if (!is_wp_error($legacy_amount)) {
                $amount_atomic = $legacy_amount;
            }
        }

        $amount = $this->format_amount_for_display($amount_atomic, $token_decimals);

        if ($amount === '') {
            $amount = '1';
        }

        $step_value = $this->get_step_value($token_decimals);
        $min_value = $step_value;
        
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
                        step="<?php echo esc_attr($step_value); ?>"
                        min="<?php echo esc_attr($min_value); ?>"
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

            $('#x402_paywall_token_address').on('change', function() {
                updateAmountInputDecimals();
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
                            text: '<?php echo esc_js($token_data['name']); ?>',
                            'data-decimals': '<?php echo esc_js(isset($token_data['decimals']) ? (int) $token_data['decimals'] : 0); ?>'
                        }));
                        <?php endforeach; ?>
                    }
                    <?php endforeach; ?>
                }
                <?php endforeach; ?>

                updateAmountInputDecimals();
            }

            function updateAmountInputDecimals() {
                var $tokenSelect = $('#x402_paywall_token_address');
                var decimals = parseInt($tokenSelect.find('option:selected').data('decimals'), 10);

                if (isNaN(decimals) || decimals < 0) {
                    decimals = 6;
                }

                var step;

                if (decimals === 0) {
                    step = '1';
                } else {
                    step = '0.' + '0'.repeat(Math.max(0, decimals - 1)) + '1';
                }

                $('#x402_paywall_amount')
                    .attr('step', step)
                    .attr('min', step);
            }

            updateAmountInputDecimals();
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
                $decimals = isset($token_data['decimals']) ? (int) $token_data['decimals'] : 0;
                printf(
                    '<option value="%s" %s data-decimals="%d">%s</option>',
                    esc_attr($token_addr),
                    selected($selected_token, $token_addr, false),
                    $decimals,
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
            $network_type = isset($_POST['x402_paywall_network_type'])
                ? sanitize_text_field(wp_unslash($_POST['x402_paywall_network_type']))
                : 'evm';
            $network = isset($_POST['x402_paywall_network'])
                ? sanitize_text_field(wp_unslash($_POST['x402_paywall_network']))
                : '';
            $token_address = isset($_POST['x402_paywall_token_address'])
                ? sanitize_text_field(wp_unslash($_POST['x402_paywall_token_address']))
                : '';
            $raw_amount = isset($_POST['x402_paywall_amount'])
                ? sanitize_text_field(wp_unslash($_POST['x402_paywall_amount']))
                : '';

            $errors = array();

            $token_meta = $this->get_token_config($network_type, $network, $token_address);
            if (!$token_meta) {
                $errors[] = esc_html__('The selected token is not recognized. Please select a valid token.', 'x402-paywall');
            }

            $token_decimals = $token_meta && isset($token_meta['decimals']) ? (int) $token_meta['decimals'] : 0;

            $amount_atomic = null;
            if (empty($errors)) {
                $amount_validation = $this->validate_and_convert_amount($raw_amount, $token_decimals);

                if (is_wp_error($amount_validation)) {
                    $errors[] = $amount_validation->get_error_message();
                } else {
                    $amount_atomic = $amount_validation;
                }
            }

            if (!empty($errors)) {
                update_post_meta($post_id, '_x402_paywall_enabled', '0');
                $this->store_admin_errors($errors);
                return;
            }

            update_post_meta($post_id, '_x402_paywall_network_type', $network_type);
            update_post_meta($post_id, '_x402_paywall_network', $network);
            update_post_meta($post_id, '_x402_paywall_token_address', $token_address);
            update_post_meta($post_id, '_x402_paywall_amount', $amount_atomic);
            update_post_meta($post_id, '_x402_paywall_amount_format', 'atomic');

            // Store token metadata for later use
            if ($token_meta) {
                update_post_meta($post_id, '_x402_paywall_token_decimals', $token_decimals);

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

    /**
     * Display admin error notices for paywall configuration.
     */
    public function display_admin_notices() {
        if (!is_admin()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || strpos($screen->base, 'post') === false) {
            return;
        }

        $messages = get_transient($this->get_error_transient_key());
        if (empty($messages) || !is_array($messages)) {
            return;
        }

        delete_transient($this->get_error_transient_key());

        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>' . esc_html__('The X402 Paywall configuration could not be saved:', 'x402-paywall') . '</p>';
        echo '<ul>';
        foreach ($messages as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Validate and convert human-readable amount to atomic units.
     *
     * @param string $amount_string Amount entered by the user.
     * @param int    $decimals      Token decimals.
     * @return string|WP_Error Atomic amount or error.
     */
    private function validate_and_convert_amount($amount_string, $decimals) {
        $amount_string = trim($amount_string);

        if ($amount_string === '') {
            return new WP_Error('x402_paywall_amount_required', esc_html__('Please enter a payment amount.', 'x402-paywall'));
        }

        if (!preg_match('/^\d+(?:\.\d+)?$/', $amount_string)) {
            return new WP_Error('x402_paywall_amount_invalid', esc_html__('The payment amount must be a numeric value.', 'x402-paywall'));
        }

        $parts = explode('.', $amount_string, 2);
        $integer_part = $parts[0];
        $fractional_part = isset($parts[1]) ? $parts[1] : '';

        $decimals = max(0, (int) $decimals);

        if ($fractional_part !== '' && strlen($fractional_part) > $decimals) {
            return new WP_Error(
                'x402_paywall_amount_precision',
                sprintf(
                    /* translators: %d: number of allowed decimal places */
                    esc_html__('The selected token only supports %d decimal places.', 'x402-paywall'),
                    $decimals
                )
            );
        }

        $has_integer_value = (bool) preg_match('/[1-9]/', $integer_part);
        $has_fractional_value = $fractional_part !== '' && (bool) preg_match('/[1-9]/', $fractional_part);

        if (!$has_integer_value && !$has_fractional_value) {
            return new WP_Error('x402_paywall_amount_positive', esc_html__('The payment amount must be greater than zero.', 'x402-paywall'));
        }

        $fractional_part = str_pad($fractional_part, $decimals, '0', STR_PAD_RIGHT);
        $atomic_amount = ltrim($integer_part . $fractional_part, '0');

        if ($atomic_amount === '') {
            $atomic_amount = '0';
        }

        if ($atomic_amount === '0') {
            return new WP_Error('x402_paywall_amount_positive', esc_html__('The payment amount must be greater than zero.', 'x402-paywall'));
        }

        return $atomic_amount;
    }

    /**
     * Format atomic amount for human display.
     *
     * @param string $amount_atomic Stored atomic amount.
     * @param int    $decimals      Token decimals.
     * @return string
     */
    private function format_amount_for_display($amount_atomic, $decimals) {
        if ($amount_atomic === '' || $amount_atomic === null) {
            return '';
        }

        $amount_atomic = preg_replace('/[^0-9]/', '', (string) $amount_atomic);

        if ($amount_atomic === '') {
            return '';
        }

        $decimals = max(0, (int) $decimals);

        if ($decimals === 0) {
            return $amount_atomic;
        }

        if (strlen($amount_atomic) <= $decimals) {
            $amount_atomic = str_pad($amount_atomic, $decimals, '0', STR_PAD_LEFT);
            $whole = '0';
            $fraction = $amount_atomic;
        } else {
            $whole = substr($amount_atomic, 0, -$decimals);
            $fraction = substr($amount_atomic, -$decimals);
        }

        $fraction = rtrim($fraction, '0');

        if ($fraction === '') {
            return ltrim($whole, '0') !== '' ? ltrim($whole, '0') : '0';
        }

        $whole = ltrim($whole, '0');
        if ($whole === '') {
            $whole = '0';
        }

        return $whole . '.' . $fraction;
    }

    /**
     * Get numeric step value for input based on token decimals.
     *
     * @param int $decimals Token decimals.
     * @return string
     */
    private function get_step_value($decimals) {
        $decimals = max(0, (int) $decimals);

        if ($decimals === 0) {
            return '1';
        }

        return '0.' . str_repeat('0', max(0, $decimals - 1)) . '1';
    }

    /**
     * Store error messages for later display in admin notices.
     *
     * @param array $messages Error messages.
     */
    private function store_admin_errors($messages) {
        set_transient($this->get_error_transient_key(), $messages, MINUTE_IN_SECONDS);
    }

    /**
     * Get transient key for admin errors.
     *
     * @return string
     */
    private function get_error_transient_key() {
        return 'x402_paywall_errors_' . get_current_user_id();
    }
}
