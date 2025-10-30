<?php
/**
 * Public-facing functionality
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public class
 */
class X402_Paywall_Public {
    
    /**
     * Payment handler instance
     *
     * @var X402_Paywall_Payment_Handler
     */
    private $payment_handler;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->payment_handler = new X402_Paywall_Payment_Handler();
    }
    
    /**
     * Initialize public hooks
     */
    public function init() {
        add_filter('the_content', array($this, 'filter_content'), 10);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('template_redirect', array($this, 'handle_payment_request'));
    }
    
    /**
     * Enqueue public CSS and JavaScript
     */
    public function enqueue_public_assets() {
        if (is_singular(array('post', 'page'))) {
            wp_enqueue_style(
                'x402-paywall-public',
                X402_PAYWALL_PLUGIN_URL . 'assets/css/public.css',
                array(),
                X402_PAYWALL_VERSION
            );
            
            wp_enqueue_script(
                'x402-paywall-public',
                X402_PAYWALL_PLUGIN_URL . 'assets/js/public.js',
                array('jquery'),
                X402_PAYWALL_VERSION,
                true
            );
        }
    }
    
    /**
     * Filter post content to implement paywall
     *
     * @param string $content Post content
     * @return string Filtered content
     */
    public function filter_content($content) {
        if (!is_singular(array('post', 'page')) || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        global $post;
        
        // Check if paywall is enabled for this post
        $enabled = get_post_meta($post->ID, '_x402_paywall_enabled', true);
        
        if ($enabled !== '1') {
            return $content;
        }
        
        // Check if user is the author or admin (bypass paywall)
        if (current_user_can('edit_post', $post->ID)) {
            return '<div class="x402-paywall-notice x402-paywall-author-notice">' .
                   '<strong>' . esc_html__('Paywall Preview', 'x402-paywall') . ':</strong> ' .
                   esc_html__('You are viewing this content as the author/editor. Other users will see a paywall.', 'x402-paywall') .
                   '</div>' . $content;
        }
        
        // Get paywall configuration
        $paywall_config = $this->get_paywall_config($post->ID);
        
        if (!$paywall_config) {
            return $content;
        }
        
        // Check if payment has been made
        if ($this->check_payment_status($post->ID)) {
            // Payment verified, show content
            return $content;
        }
        
        // No payment, show paywall message
        return $this->render_paywall_message($post->ID, $paywall_config);
    }
    
    /**
     * Handle payment request via x402 protocol
     */
    public function handle_payment_request() {
        if (!is_singular(array('post', 'page'))) {
            return;
        }
        
        global $post;
        
        // Check if paywall is enabled
        $enabled = get_post_meta($post->ID, '_x402_paywall_enabled', true);
        
        if ($enabled !== '1') {
            return;
        }
        
        // Bypass for authors/admins
        if (current_user_can('edit_post', $post->ID)) {
            return;
        }
        
        // Get paywall configuration
        $paywall_config = $this->get_paywall_config($post->ID);
        
        if (!$paywall_config) {
            return;
        }
        
        // Check for X-Payment header
        $payment_header = $this->get_payment_header();
        
        if ($payment_header) {
            // Process payment
            $this->process_payment_request($post->ID, $paywall_config);
        } else {
            // Check if this is a direct API request for payment info
            if ($this->is_payment_info_request()) {
                $this->send_payment_required_response($post->ID, $paywall_config);
            }
        }
    }
    
    /**
     * Get payment header from request
     *
     * @return string|null Payment header value
     */
    private function get_payment_header() {
        $headers = array('HTTP_X_PAYMENT', 'X-Payment');
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }
        
        return null;
    }
    
    /**
     * Check if this is a payment info request
     *
     * @return bool
     */
    private function is_payment_info_request() {
        // Check if Accept header indicates payment protocol client
        $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
        return strpos($accept, 'application/json') !== false;
    }
    
    /**
     * Send 402 Payment Required response
     *
     * @param int $post_id Post ID
     * @param array $paywall_config Paywall configuration
     */
    private function send_payment_required_response($post_id, $paywall_config) {
        // Create payment requirements
        $requirements = $this->payment_handler->create_payment_requirements($post_id, $paywall_config);
        
        if (!$requirements) {
            return;
        }
        
        // Create response
        $response = $this->payment_handler->create_payment_required_response($requirements);
        
        // Send 402 status
        status_header(402);
        
        // Send headers
        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . $value);
        }
        
        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        exit;
    }
    
    /**
     * Process payment request
     *
     * @param int $post_id Post ID
     * @param array $paywall_config Paywall configuration
     */
    private function process_payment_request($post_id, $paywall_config) {
        // Create payment requirements
        $requirements = $this->payment_handler->create_payment_requirements($post_id, $paywall_config);
        
        if (!$requirements) {
            return;
        }
        
        // Process payment
        $result = $this->payment_handler->process_payment($requirements);
        
        if ($result['verified']) {
            // Payment verified!
            $transaction_hash = null;
            if ($result['settlement'] && isset($result['settlement']['transaction'])) {
                $transaction_hash = $result['settlement']['transaction'];
            }
            
            // Get user address from payment
            $user_address = $this->extract_user_address_from_payment();
            
            // Log successful payment
            X402_Paywall_DB::log_payment(array(
                'post_id' => $post_id,
                'user_address' => $user_address,
                'amount' => $paywall_config['amount'],
                'token_address' => $paywall_config['token_address'],
                'network' => $paywall_config['network'],
                'transaction_hash' => $transaction_hash,
                'payment_status' => 'verified',
            ));
            
            // Set cookie to remember payment for this session
            setcookie('x402_paid_' . $post_id, '1', time() + (86400 * 30), '/');
            
            // Redirect back to the post
            wp_redirect(get_permalink($post_id));
            exit;
        }
    }
    
    /**
     * Extract user address from payment header
     *
     * @return string User address
     */
    private function extract_user_address_from_payment() {
        // This would extract from the actual payment payload
        // For now, return a placeholder
        return 'unknown';
    }
    
    /**
     * Check if payment has been made for this post
     *
     * @param int $post_id Post ID
     * @return bool Payment status
     */
    private function check_payment_status($post_id) {
        // Check cookie first
        if (isset($_COOKIE['x402_paid_' . $post_id]) && $_COOKIE['x402_paid_' . $post_id] === '1') {
            return true;
        }
        
        // Could also check database if we have user address
        // For now, rely on cookie
        return false;
    }
    
    /**
     * Get paywall configuration for a post
     *
     * @param int $post_id Post ID
     * @return array|null Paywall configuration
     */
    private function get_paywall_config($post_id) {
        $network_type = get_post_meta($post_id, '_x402_paywall_network_type', true);
        $network = get_post_meta($post_id, '_x402_paywall_network', true);
        $token_address = get_post_meta($post_id, '_x402_paywall_token_address', true);
        $amount = get_post_meta($post_id, '_x402_paywall_amount', true);
        $decimals = get_post_meta($post_id, '_x402_paywall_token_decimals', true);
        
        if (!$network || !$token_address || !$amount) {
            return null;
        }
        
        // Get author's payment address
        $author_id = get_post_field('post_author', $post_id);
        $profile = X402_Paywall_DB::get_user_profile($author_id);
        
        if (!$profile) {
            return null;
        }
        
        $recipient_address = $network_type === 'spl' ? $profile->spl_address : $profile->evm_address;
        
        if (!$recipient_address) {
            return null;
        }
        
        // Convert amount to atomic units
        $decimals = $decimals ?: 6;
        $amount_atomic = bcmul($amount, bcpow('10', (string)$decimals));
        
        $config = array(
            'recipient_address' => $recipient_address,
            'amount' => $amount_atomic,
            'token_address' => $token_address,
            'network' => $network,
            'network_type' => $network_type,
        );
        
        // Add EVM-specific fields
        if ($network_type === 'evm') {
            $config['token_name'] = get_post_meta($post_id, '_x402_paywall_token_name', true);
            $config['token_version'] = get_post_meta($post_id, '_x402_paywall_token_version', true);
        }
        
        return $config;
    }
    
    /**
     * Render paywall message
     *
     * @param int $post_id Post ID
     * @param array $paywall_config Paywall configuration
     * @return string Paywall HTML
     */
    private function render_paywall_message($post_id, $paywall_config) {
        $amount = get_post_meta($post_id, '_x402_paywall_amount', true);
        $network_type = $paywall_config['network_type'];
        $network = $paywall_config['network'];
        
        // Get excerpt or first 150 characters
        global $post;
        $excerpt = has_excerpt($post->ID) ? get_the_excerpt($post->ID) : wp_trim_words($post->post_content, 30);
        
        ob_start();
        ?>
        <div class="x402-paywall-container">
            <div class="x402-paywall-preview">
                <?php echo wp_kses_post($excerpt); ?>
            </div>
            
            <div class="x402-paywall-message">
                <div class="x402-paywall-icon">🔒</div>
                <h3><?php esc_html_e('Premium Content', 'x402-paywall'); ?></h3>
                <p><?php esc_html_e('This content requires a payment to view.', 'x402-paywall'); ?></p>
                
                <div class="x402-paywall-details">
                    <div class="x402-paywall-price">
                        <span class="x402-paywall-amount"><?php echo esc_html($amount); ?></span>
                        <span class="x402-paywall-currency">USDC</span>
                    </div>
                    <div class="x402-paywall-network">
                        <?php 
                        printf(
                            esc_html__('on %s', 'x402-paywall'),
                            esc_html(ucfirst(str_replace('-', ' ', $network)))
                        );
                        ?>
                    </div>
                </div>
                
                <div class="x402-paywall-instructions">
                    <p><?php esc_html_e('To access this content:', 'x402-paywall'); ?></p>
                    <ol>
                        <li><?php esc_html_e('Use a wallet that supports the x402 payment protocol', 'x402-paywall'); ?></li>
                        <li><?php esc_html_e('Connect your wallet and authorize the payment', 'x402-paywall'); ?></li>
                        <li><?php esc_html_e('The content will be unlocked automatically', 'x402-paywall'); ?></li>
                    </ol>
                </div>
                
                <div class="x402-paywall-info">
                    <p>
                        <small>
                            <?php esc_html_e('Powered by', 'x402-paywall'); ?> 
                            <a href="https://github.com/coinbase/x402" target="_blank">x402 protocol</a>
                        </small>
                    </p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
