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

use X402\Types\ExactPaymentPayload;
use X402\Types\PaymentPayload;

/**
 * Public class
 */
class X402_Paywall_Public {

    private const SESSION_TTL = 1800;
    private const SESSION_HEADER = 'X-Payment-Session';
    
    /**
     * Payment handler instance
     *
     * @var X402_Paywall_Payment_Handler
     */
    private $payment_handler;

    /**
     * Whether the current request has a verified payment
     *
     * @var bool
     */
    private $payment_verified = false;
    
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
        add_action('template_redirect', array($this, 'handle_payment_request'), 10);
        add_action('template_redirect', array($this, 'enforce_paywall_access'), 20);
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
        $requirements = $this->payment_handler->create_payment_requirements($post_id, $paywall_config);

        if (!$requirements) {
            return;
        }

        $result = $this->payment_handler->process_payment($requirements);

        if (!$result['verified']) {
            return;
        }

        $settlement = is_array($result['settlement']) ? $result['settlement'] : null;

        if (!$settlement || !$this->confirm_payment_response_signature($settlement)) {
            error_log('X402 Paywall: Settlement proof missing or failed signature confirmation for post ' . $post_id);
            return;
        }

        $payer_identifier = $this->extract_payer_identifier_from_result($result);

        if (!$payer_identifier) {
            error_log('X402 Paywall: Unable to determine payer identifier for post ' . $post_id);
            return;
        }

        $transaction_hash = $settlement['transaction'] ?? null;
        $normalized_proof = $this->normalize_settlement_proof($settlement);
        $session_header = $this->store_verified_session($post_id, $payer_identifier, $normalized_proof);

        X402_Paywall_DB::log_payment(array(
            'post_id' => $post_id,
            'user_address' => $payer_identifier,
            'payer_identifier' => $payer_identifier,
            'amount' => $paywall_config['amount'],
            'token_address' => $paywall_config['token_address'],
            'network' => $paywall_config['network'],
            'transaction_hash' => $transaction_hash,
            'settlement_proof' => $normalized_proof,
            'payment_status' => 'verified',
        ));

        $this->payment_verified = true;

        $response_header = $this->payment_handler->create_payment_response_header($settlement);
        header($this->payment_handler->get_payment_response_header_name() . ': ' . $response_header);

        if ($session_header) {
            header(self::SESSION_HEADER . ': ' . $session_header);
        }

        wp_safe_redirect(get_permalink($post_id));
        exit;
    }

    /**
     * Check if payment has been made for this post
     *
     * @param int $post_id Post ID
     * @return bool Payment status
     */
    private function check_payment_status($post_id) {
        $session = $this->get_active_session($post_id);

        if (!$session) {
            return false;
        }

        $payer = $session['data']['payer'] ?? '';

        if ($payer && X402_Paywall_DB::has_user_paid($post_id, $payer)) {
            $this->refresh_session_state($post_id, $session);
            $this->payment_verified = true;
            return true;
        }

        if ($payer && !empty($session['data']['proof'])) {
            $this->refresh_session_state($post_id, $session);
            $this->payment_verified = true;
            return true;
        }

        return false;
    }

    /**
     * Enforce the paywall before rendering content
     */
    public function enforce_paywall_access() {
        if (!is_singular(array('post', 'page'))) {
            return;
        }

        global $post;

        if (!$post) {
            return;
        }

        $enabled = get_post_meta($post->ID, '_x402_paywall_enabled', true);

        if ($enabled !== '1') {
            return;
        }

        if (current_user_can('edit_post', $post->ID)) {
            return;
        }

        $paywall_config = $this->get_paywall_config($post->ID);

        if (!$paywall_config) {
            return;
        }

        if ($this->payment_verified || $this->check_payment_status($post->ID)) {
            return;
        }

        if ($this->is_payment_info_request()) {
            $this->send_payment_required_response($post->ID, $paywall_config);
        }

        status_header(402);
        nocache_headers();

        $message = $this->render_paywall_message($post->ID, $paywall_config);

        wp_die($message, esc_html__('Payment Required', 'x402-paywall'), array('response' => 402));
    }

    /**
     * Confirm the facilitator response signature exists before trusting settlement data
     *
     * @param array $settlement Settlement payload
     * @return bool
     */
    private function confirm_payment_response_signature($settlement) {
        $proof = $this->get_settlement_proof_array($settlement);

        if (!$proof) {
            return false;
        }

        $signature = null;

        if (isset($proof['signature']) && is_string($proof['signature'])) {
            $signature = $proof['signature'];
        } elseif (isset($proof['signedMessage']['signature']) && is_string($proof['signedMessage']['signature'])) {
            $signature = $proof['signedMessage']['signature'];
        }

        if (!$signature || !is_string($signature) || trim($signature) === '') {
            return false;
        }

        $payload = null;

        if (isset($proof['payload'])) {
            $payload = $proof['payload'];
        } elseif (isset($proof['signedMessage']['payload'])) {
            $payload = $proof['signedMessage']['payload'];
        }

        if ($payload === null || $payload === '') {
            return false;
        }

        return true;
    }

    /**
     * Extract payer identifier from facilitator result
     *
     * @param array $result Payment handler result
     * @return string|null
     */
    private function extract_payer_identifier_from_result($result) {
        if (isset($result['settlement']['payer']) && !empty($result['settlement']['payer'])) {
            return strtolower($result['settlement']['payer']);
        }

        if (isset($result['payload']) && $result['payload'] instanceof PaymentPayload) {
            $payload = $result['payload']->payload;

            if ($payload instanceof ExactPaymentPayload && isset($payload->authorization->from)) {
                return strtolower($payload->authorization->from);
            }
        }

        return null;
    }

    /**
     * Normalize settlement proof for persistence
     *
     * @param array $settlement Settlement payload
     * @return string
     */
    private function normalize_settlement_proof($settlement) {
        $proof = $this->get_settlement_proof_array($settlement);

        $encoded = wp_json_encode($proof ? $proof : $settlement);

        if ($encoded === false) {
            $encoded = json_encode($proof ? $proof : $settlement);
        }

        return $encoded ?: '';
    }

    /**
     * Get settlement proof array from facilitator response
     *
     * @param array $settlement Settlement payload
     * @return array|null
     */
    private function get_settlement_proof_array($settlement) {
        if (isset($settlement['proof'])) {
            if (is_array($settlement['proof'])) {
                return $settlement['proof'];
            }

            if (is_string($settlement['proof'])) {
                $decoded = json_decode($settlement['proof'], true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        if (isset($settlement['settlement_proof']) && is_array($settlement['settlement_proof'])) {
            return $settlement['settlement_proof'];
        }

        return is_array($settlement) ? $settlement : null;
    }

    /**
     * Store verified session information
     *
     * @param int $post_id Post ID
     * @param string $payer_identifier Normalized payer address
     * @param string|null $normalized_proof Settlement proof JSON
     * @return string Session header value
     */
    private function store_verified_session($post_id, $payer_identifier, $normalized_proof = null) {
        $this->clear_legacy_cookie($post_id);

        $token = wp_generate_uuid4();
        $signature = $this->sign_session_token($token, $post_id, $payer_identifier);
        $transient_key = $this->build_session_transient_key($post_id, $token);

        $payload = array(
            'post_id' => $post_id,
            'payer' => strtolower($payer_identifier),
            'signature' => $signature,
            'proof' => $normalized_proof,
            'created_at' => time(),
        );

        set_transient($transient_key, $payload, self::SESSION_TTL);

        $this->set_session_cookie($post_id, $token, $signature);

        return $token . '.' . $signature;
    }

    /**
     * Refresh session expiration and cookie state
     *
     * @param int $post_id Post ID
     * @param array $session Session payload
     */
    private function refresh_session_state($post_id, $session) {
        if (!isset($session['transient_key'], $session['token'], $session['signature'])) {
            return;
        }

        set_transient($session['transient_key'], $session['data'], self::SESSION_TTL);
        $this->set_session_cookie($post_id, $session['token'], $session['signature']);
    }

    /**
     * Retrieve active session from request
     *
     * @param int $post_id Post ID
     * @return array|null
     */
    private function get_active_session($post_id) {
        $session = $this->parse_session_from_request($post_id);

        if (!$session) {
            $this->clear_legacy_cookie($post_id);
        }

        return $session;
    }

    /**
     * Parse session token from cookie or header
     *
     * @param int $post_id Post ID
     * @return array|null
     */
    private function parse_session_from_request($post_id) {
        $session_value = null;
        $cookie_name = $this->get_session_cookie_name($post_id);

        if (isset($_COOKIE[$cookie_name])) {
            $session_value = $_COOKIE[$cookie_name];
        }

        if (!$session_value && isset($_SERVER['HTTP_X_PAYMENT_SESSION'])) {
            $session_value = $_SERVER['HTTP_X_PAYMENT_SESSION'];
        }

        if (!$session_value || !is_string($session_value)) {
            return null;
        }

        $parts = explode('.', $session_value, 2);

        if (count($parts) !== 2) {
            return null;
        }

        list($token, $signature) = $parts;

        if ($token === '' || $signature === '') {
            return null;
        }

        $transient_key = $this->build_session_transient_key($post_id, $token);
        $data = get_transient($transient_key);

        if (!is_array($data) || !isset($data['signature']) || !hash_equals($data['signature'], $signature)) {
            return null;
        }

        $expected_signature = $this->sign_session_token($token, $post_id, $data['payer'] ?? '');

        if (!hash_equals($expected_signature, $signature)) {
            return null;
        }

        return array(
            'token' => $token,
            'signature' => $signature,
            'data' => $data,
            'transient_key' => $transient_key,
        );
    }

    /**
     * Sign a session token for integrity
     *
     * @param string $token Session token
     * @param int $post_id Post ID
     * @param string $payer_identifier Payer address
     * @return string
     */
    private function sign_session_token($token, $post_id, $payer_identifier) {
        $payer = strtolower((string)$payer_identifier);

        return hash_hmac('sha256', $token . '|' . $post_id . '|' . $payer, wp_salt('auth'));
    }

    /**
     * Build the transient key for storing session data
     *
     * @param int $post_id Post ID
     * @param string $token Session token
     * @return string
     */
    private function build_session_transient_key($post_id, $token) {
        return 'x402_paywall_session_' . md5($post_id . '|' . $token);
    }

    /**
     * Persist secure cookie for session token
     *
     * @param int $post_id Post ID
     * @param string $token Session token
     * @param string $signature Signature
     */
    private function set_session_cookie($post_id, $token, $signature) {
        $cookie_name = $this->get_session_cookie_name($post_id);
        $value = $token . '.' . $signature;

        $params = array(
            'expires' => time() + self::SESSION_TTL,
            'path' => defined('COOKIEPATH') ? COOKIEPATH : '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        );

        if (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN) {
            $params['domain'] = COOKIE_DOMAIN;
        }

        setcookie($cookie_name, $value, $params);
        $_COOKIE[$cookie_name] = $value;
    }

    /**
     * Get the session cookie name for a post
     *
     * @param int $post_id Post ID
     * @return string
     */
    private function get_session_cookie_name($post_id) {
        return 'x402_paywall_session_' . $post_id;
    }

    /**
     * Clear legacy cookie usage
     *
     * @param int $post_id Post ID
     */
    private function clear_legacy_cookie($post_id) {
        $legacy_name = 'x402_paid_' . $post_id;

        if (!isset($_COOKIE[$legacy_name])) {
            return;
        }

        $params = array(
            'expires' => time() - HOUR_IN_SECONDS,
            'path' => defined('COOKIEPATH') ? COOKIEPATH : '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        );

        if (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN) {
            $params['domain'] = COOKIE_DOMAIN;
        }

        setcookie($legacy_name, '', $params);

        unset($_COOKIE[$legacy_name]);
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
