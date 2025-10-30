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

use X402\Encoding\Encoder;
use X402\Types\ExactPaymentPayload;
use X402\Types\PaymentPayload;

/**
 * Public class
 */
class X402_Paywall_Public {

    private const SESSION_TTL = 1800;
    private const SESSION_HEADER = 'X-Payment-Session';

    /**
     * Cached payment header for current request
     *
     * @var array|null
     */
    private $cached_payment_header = null;
    
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

            wp_localize_script(
                'x402-paywall-public',
                'x402PaywallData',
                array(
                    'errorTitle' => esc_html__('Payment Error', 'x402-paywall'),
                    'defaultErrorMessage' => esc_html__('We were unable to verify your payment. Please try again.', 'x402-paywall'),
                    'dismissLabel' => esc_html__('Dismiss', 'x402-paywall'),
                    'referenceLabel' => esc_html__('Support reference: %s', 'x402-paywall'),
                    'cookiePath' => defined('COOKIEPATH') ? (COOKIEPATH ?: '/') : '/',
                )
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
            $this->process_payment_request($post->ID, $paywall_config, $payment_header);
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
     * @return array|null Payment header data
     */
    private function get_payment_header() {
        if ($this->cached_payment_header !== null) {
            return $this->cached_payment_header;
        }

        $headers = array('HTTP_X_PAYMENT', 'X-Payment');

        foreach ($headers as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }

            $parsed = $this->parse_payment_header_value((string) $_SERVER[$header]);

            if ($parsed !== null) {
                $this->cached_payment_header = $parsed;
                return $this->cached_payment_header;
            }
        }

        $this->cached_payment_header = null;
        return null;
    }

    /**
     * Parse and validate the X-Payment header value
     *
     * @param string $header_value Raw header string
     * @return array|null
     */
    private function parse_payment_header_value($header_value) {
        $header_value = trim((string) $header_value);

        if ($header_value === '') {
            return null;
        }

        try {
            $payload = Encoder::decodePaymentHeader($header_value);
        } catch (\Throwable $exception) {
            error_log('X402 Paywall: Failed to decode X-Payment header - ' . $exception->getMessage());
            return null;
        }

        if (!$payload instanceof PaymentPayload || !$this->validate_payment_payload_structure($payload)) {
            error_log('X402 Paywall: Invalid payment payload structure in X-Payment header');
            return null;
        }

        $raw_payer = null;
        $normalized_payer = null;

        if ($payload->payload instanceof ExactPaymentPayload && isset($payload->payload->authorization->from)) {
            $raw_payer = $payload->payload->authorization->from;
            $normalized_payer = $this->normalize_wallet_address($raw_payer, $payload->network);
        }

        return array(
            'raw' => $header_value,
            'payload' => $payload,
            'network' => $payload->network,
            'payer' => $raw_payer,
            'normalized_payer' => $normalized_payer,
        );
    }

    /**
     * Validate decoded payment payload
     *
     * @param PaymentPayload $payload
     * @return bool
     */
    private function validate_payment_payload_structure($payload) {
        if (!$payload instanceof PaymentPayload) {
            return false;
        }

        if ($payload->scheme !== 'exact') {
            return false;
        }

        if ($this->is_svm_network($payload->network)) {
            if (!($payload->payload instanceof \X402\Types\ExactSvmPayload)) {
                return false;
            }

            $transaction = $payload->payload->transaction ?? '';
            return is_string($transaction) && trim($transaction) !== '';
        }

        if (!($payload->payload instanceof ExactPaymentPayload)) {
            return false;
        }

        $signature = $payload->payload->signature ?? '';
        $authorization = $payload->payload->authorization ?? null;

        if (!is_string($signature) || trim($signature) === '') {
            return false;
        }

        if ($authorization === null || !isset($authorization->from) || !is_string($authorization->from)) {
            return false;
        }

        return $this->normalize_wallet_address($authorization->from, $payload->network) !== null;
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
     * @param array $payment_header Parsed X-Payment header
     */
    private function process_payment_request($post_id, $paywall_config, $payment_header) {
        $requirements = $this->payment_handler->create_payment_requirements($post_id, $paywall_config);

        if (!$requirements) {
            return;
        }

        if (isset($payment_header['raw'])) {
            $_SERVER['HTTP_X_PAYMENT'] = $payment_header['raw'];
            $_SERVER['X-Payment'] = $payment_header['raw'];
        }

        $result = $this->payment_handler->process_payment($requirements);

        if (empty($result['verified'])) {
            $this->handle_failed_payment_attempt($post_id, $paywall_config, $payment_header, $result);
            return;
        }

        $settlement = is_array($result['settlement']) ? $result['settlement'] : null;

        if (!$settlement || !$this->confirm_payment_response_signature($settlement)) {
            error_log('X402 Paywall: Settlement proof missing or failed signature confirmation for post ' . $post_id);
            return;
        }

        $payer_identifier = $this->extract_user_address_from_payment($payment_header, $paywall_config['network']);

        if (!$payer_identifier) {
            $payer_identifier = $this->extract_payer_identifier_from_result($result, $paywall_config['network']);
        }

        if (!$payer_identifier) {
            error_log('X402 Paywall: Unable to determine payer identifier for post ' . $post_id);
            return;
        }

        $transaction_hash = $settlement['transaction'] ?? null;
        $normalized_proof = $this->normalize_settlement_proof($settlement);
        $session_header = $this->store_verified_session($post_id, $payer_identifier, $normalized_proof);

        $facilitator_signature = $this->extract_facilitator_signature($settlement);
        $facilitator_reference = $this->extract_facilitator_reference($settlement);

        X402_Paywall_DB::log_payment(array(
            'post_id' => $post_id,
            'user_address' => $payer_identifier,
            'normalized_address' => $payer_identifier,
            'payer_identifier' => $payer_identifier,
            'amount' => $paywall_config['amount'],
            'token_address' => $paywall_config['token_address'],
            'network' => $paywall_config['network'],
            'transaction_hash' => $transaction_hash,
            'settlement_proof' => $normalized_proof,
            'payment_status' => 'verified',
            'facilitator_signature' => $facilitator_signature,
            'facilitator_reference' => $facilitator_reference,
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
     * Handle failed payment attempt responses and logging.
     *
     * @param int   $post_id         Post identifier.
     * @param array $paywall_config  Paywall configuration for the post.
     * @param array $payment_header  Parsed X-Payment header.
     * @param array $result          Raw payment handler result.
     */
    private function handle_failed_payment_attempt($post_id, $paywall_config, $payment_header, $result) {
        $error_details = isset($result['error']) && is_array($result['error']) ? $result['error'] : array();

        $status_code = isset($error_details['status_code']) ? (int) $error_details['status_code'] : 402;
        if ($status_code < 400 || $status_code > 599) {
            $status_code = 402;
        }

        $error_code = isset($error_details['code']) && is_string($error_details['code']) ? trim($error_details['code']) : '';
        $facilitator_message = isset($error_details['facilitator_message']) && is_string($error_details['facilitator_message'])
            ? trim($error_details['facilitator_message'])
            : '';

        if ($facilitator_message !== '') {
            $facilitator_message = wp_strip_all_tags($facilitator_message);
        }

        $raw_message = isset($error_details['message']) && is_string($error_details['message'])
            ? $error_details['message']
            : __('Unable to verify the payment with the facilitator.', 'x402-paywall');

        $sanitized_message = trim(wp_strip_all_tags($raw_message));
        if ($sanitized_message === '') {
            $sanitized_message = __('Unable to verify the payment with the facilitator.', 'x402-paywall');
        }

        $payer_identifier = $this->extract_user_address_from_payment($payment_header, $paywall_config['network']);

        if (!$payer_identifier) {
            $payer_identifier = $this->extract_payer_identifier_from_result($result, $paywall_config['network']);
        }

        $facilitator_reference = null;

        if (isset($error_details['reference']) && is_string($error_details['reference'])) {
            $facilitator_reference = trim($error_details['reference']);

            if ($facilitator_reference === '') {
                $facilitator_reference = null;
            }
        }

        $log_payload = array(
            'post_id' => $post_id,
            'user_address' => $payer_identifier,
            'normalized_address' => $payer_identifier,
            'payer_identifier' => $payer_identifier,
            'amount' => $paywall_config['amount'],
            'token_address' => $paywall_config['token_address'],
            'network' => $paywall_config['network'],
            'transaction_hash' => null,
            'payment_status' => 'failed',
            'facilitator_signature' => null,
            'facilitator_reference' => $facilitator_reference,
            'failure_status_code' => $status_code,
            'facilitator_error_code' => $error_code,
            'facilitator_message' => $facilitator_message,
        );

        $encoded_error = $this->encode_error_payload_for_storage($error_details, $status_code, $facilitator_message, $sanitized_message, $error_code);

        if ($encoded_error !== null) {
            $log_payload['settlement_proof'] = $encoded_error;
        }

        $support_reference = null;

        $log_id = X402_Paywall_DB::log_payment($log_payload);

        if ($log_id) {
            $support_reference = $this->format_support_reference($log_id);
        }

        $public_message = $sanitized_message;

        if ($error_code !== '') {
            /* translators: %s: Error code returned from facilitator. */
            $public_message .= ' ' . sprintf(__('(Error code: %s)', 'x402-paywall'), $error_code);
        }

        if ($support_reference) {
            /* translators: %s: Support reference identifier. */
            $public_message .= ' ' . sprintf(__('Support reference: %s', 'x402-paywall'), $support_reference);
        }

        if ($this->should_return_json_error_response()) {
            $this->send_json_error_response($public_message, $status_code, $error_code, $support_reference, $error_details);
            return;
        }

        $this->queue_frontend_error_notice(
            array(
                'message' => $sanitized_message,
                'code' => $error_code,
                'status' => $status_code,
                'reference' => $support_reference,
            )
        );

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
    private function extract_payer_identifier_from_result($result, $network) {
        if (isset($result['settlement']['payer']) && !empty($result['settlement']['payer'])) {
            return $this->normalize_wallet_address($result['settlement']['payer'], $network);
        }

        if (isset($result['payload']) && $result['payload'] instanceof PaymentPayload) {
            $payload = $result['payload']->payload;

            if ($payload instanceof ExactPaymentPayload && isset($payload->authorization->from)) {
                return $this->normalize_wallet_address($payload->authorization->from, $network);
            }
        }

        return null;
    }

    /**
     * Extract normalized user address from payment header
     *
     * @param array $payment_header Parsed payment header
     * @param string $expected_network Expected network identifier
     * @return string|null
     */
    private function extract_user_address_from_payment($payment_header, $expected_network) {
        if (!is_array($payment_header)) {
            return null;
        }

        $network = $payment_header['network'] ?? $expected_network;

        if (isset($payment_header['normalized_payer']) && $payment_header['normalized_payer']) {
            return $payment_header['normalized_payer'];
        }

        if (isset($payment_header['payer'])) {
            return $this->normalize_wallet_address($payment_header['payer'], $network);
        }

        if (isset($payment_header['payload']) && $payment_header['payload'] instanceof PaymentPayload) {
            $payload = $payment_header['payload']->payload;

            if ($payload instanceof ExactPaymentPayload && isset($payload->authorization->from)) {
                return $this->normalize_wallet_address($payload->authorization->from, $network);
            }
        }

        return null;
    }

    /**
     * Normalize wallet address for storage and comparisons
     *
     * @param string|null $address Wallet address
     * @param string $network Network identifier
     * @return string|null
     */
    private function normalize_wallet_address($address, $network) {
        if (!is_string($address)) {
            return null;
        }

        $trimmed = trim($address);

        if ($trimmed === '') {
            return null;
        }

        if ($this->is_svm_network($network)) {
            $normalized = $this->normalize_spl_address($trimmed);

            if ($normalized === null) {
                return null;
            }

            return $normalized;
        }

        $lower = strtolower($trimmed);

        if (!X402_Paywall_Payment_Handler::validate_evm_address($lower)) {
            return null;
        }

        return $lower;
    }

    /**
     * Normalize Solana/SPL wallet address
     *
     * @param string $address Wallet address
     * @return string|null
     */
    private function normalize_spl_address($address) {
        $normalized = preg_replace('/\s+/', '', $address);

        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        if (!X402_Paywall_Payment_Handler::validate_spl_address($normalized)) {
            return null;
        }

        return $normalized;
    }

    /**
     * Determine if the network is Solana-based
     *
     * @param string $network Network identifier
     * @return bool
     */
    private function is_svm_network($network) {
        if (!is_string($network)) {
            return false;
        }

        return str_starts_with($network, 'solana-');
    }

    /**
     * Extract facilitator signature from settlement payload
     *
     * @param array $settlement Settlement payload
     * @return string|null
     */
    private function extract_facilitator_signature($settlement) {
        $proof = $this->get_settlement_proof_array($settlement);

        if (!$proof) {
            return null;
        }

        if (isset($proof['signature']) && is_string($proof['signature']) && trim($proof['signature']) !== '') {
            return trim($proof['signature']);
        }

        if (isset($proof['signedMessage']['signature']) && is_string($proof['signedMessage']['signature']) && trim($proof['signedMessage']['signature']) !== '') {
            return trim($proof['signedMessage']['signature']);
        }

        return null;
    }

    /**
     * Extract facilitator reference identifier
     *
     * @param array $settlement Settlement payload
     * @return string|null
     */
    private function extract_facilitator_reference($settlement) {
        $proof = $this->get_settlement_proof_array($settlement);

        $candidates = array();

        if (is_array($proof)) {
            $candidates[] = $proof;

            if (isset($proof['payload']) && is_array($proof['payload'])) {
                $candidates[] = $proof['payload'];
            }

            if (isset($proof['signedMessage']) && is_array($proof['signedMessage'])) {
                $candidates[] = $proof['signedMessage'];

                if (isset($proof['signedMessage']['payload']) && is_array($proof['signedMessage']['payload'])) {
                    $candidates[] = $proof['signedMessage']['payload'];
                }
            }
        }

        $keys = array('reference', 'transactionReference', 'transaction_reference', 'paymentReference', 'payment_reference', 'id', 'paymentId', 'payment_id', 'requestId', 'request_id');

        foreach ($candidates as $candidate) {
            foreach ($keys as $key) {
                if (isset($candidate[$key]) && is_string($candidate[$key])) {
                    $value = trim($candidate[$key]);

                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        $fallback_keys = array('reference', 'transaction', 'transactionHash', 'transaction_hash');

        foreach ($fallback_keys as $fallback_key) {
            if (isset($settlement[$fallback_key]) && is_string($settlement[$fallback_key])) {
                $value = trim($settlement[$fallback_key]);

                if ($value !== '') {
                    return $value;
                }
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

        $is_secure = is_ssl();

        if (function_exists('wp_is_using_https') && wp_is_using_https()) {
            $is_secure = true;
        }

        $params = array(
            'expires' => time() + self::SESSION_TTL,
            'path' => defined('COOKIEPATH') ? COOKIEPATH : '/',
            'secure' => $is_secure,
            'httponly' => true,
            'samesite' => 'Strict',
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

        $is_secure = is_ssl();

        if (function_exists('wp_is_using_https') && wp_is_using_https()) {
            $is_secure = true;
        }

        $params = array(
            'expires' => time() - HOUR_IN_SECONDS,
            'path' => defined('COOKIEPATH') ? COOKIEPATH : '/',
            'secure' => $is_secure,
            'httponly' => true,
            'samesite' => 'Strict',
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
        $amount_format = get_post_meta($post_id, '_x402_paywall_amount_format', true);

        $decimals = $decimals ? (int) $decimals : 6;

        if ($amount_format === 'atomic') {
            $amount_atomic = preg_replace('/[^0-9]/', '', (string) $amount);
        } else {
            $amount_atomic = $this->convert_decimal_to_atomic($amount, $decimals);
        }

        if (!$network || !$token_address || !$amount_atomic) {
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
        
        $config = array(
            'recipient_address' => $recipient_address,
            'amount' => $amount_atomic,
            'token_address' => $token_address,
            'network' => $network,
            'network_type' => $network_type,
            'token_decimals' => $decimals,
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
        $amount_atomic = $paywall_config['amount'];
        $network_type = $paywall_config['network_type'];
        $network = $paywall_config['network'];
        $decimals = isset($paywall_config['token_decimals']) ? (int) $paywall_config['token_decimals'] : (int) get_post_meta($post_id, '_x402_paywall_token_decimals', true);

        $amount_display = $this->format_atomic_amount($amount_atomic, $decimals);
        $token_label = $this->get_token_display_label($post_id, $paywall_config);
        
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
                        <span class="x402-paywall-amount"><?php echo esc_html($amount_display); ?></span>
                        <span class="x402-paywall-currency"><?php echo esc_html($token_label); ?></span>
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

    /**
     * Get a human-readable token label for the paywall UI.
     *
     * @param int   $post_id        Post identifier.
     * @param array $paywall_config Paywall configuration array.
     * @return string
     */
    private function get_token_display_label($post_id, $paywall_config) {
        if (!empty($paywall_config['token_name'])) {
            return $paywall_config['token_name'];
        }

        $meta_token_name = get_post_meta($post_id, '_x402_paywall_token_name', true);
        if (!empty($meta_token_name)) {
            return $meta_token_name;
        }

        $network_type = $paywall_config['network_type'] ?? '';
        $network = $paywall_config['network'] ?? '';
        $token_address = $paywall_config['token_address'] ?? '';

        $token_config = X402_Paywall_Token_Registry::get_token($network_type, $network, $token_address);
        if (is_array($token_config)) {
            if (!empty($token_config['name'])) {
                return $token_config['name'];
            }

            if (!empty($token_config['token_name'])) {
                return $token_config['token_name'];
            }
        }

        if (!empty($token_address)) {
            return $token_address;
        }

        return esc_html__('Payment Token', 'x402-paywall');
    }

    /**
     * Convert a decimal string amount to atomic units.
     *
     * @param string $amount_string Human-readable amount.
     * @param int    $decimals      Token decimals.
     * @return string|null Atomic amount or null on failure.
     */
    private function convert_decimal_to_atomic($amount_string, $decimals) {
        $amount_string = trim((string) $amount_string);

        if ($amount_string === '') {
            return null;
        }

        if (!preg_match('/^\d+(?:\.\d+)?$/', $amount_string)) {
            return null;
        }

        $parts = explode('.', $amount_string, 2);
        $integer_part = $parts[0];
        $fractional_part = isset($parts[1]) ? $parts[1] : '';

        $decimals = max(0, (int) $decimals);

        if ($fractional_part !== '' && strlen($fractional_part) > $decimals) {
            return null;
        }

        $has_integer_value = (bool) preg_match('/[1-9]/', $integer_part);
        $has_fractional_value = $fractional_part !== '' && (bool) preg_match('/[1-9]/', $fractional_part);

        if (!$has_integer_value && !$has_fractional_value) {
            return null;
        }

        $fractional_part = str_pad($fractional_part, $decimals, '0', STR_PAD_RIGHT);
        $atomic = ltrim($integer_part . $fractional_part, '0');

        if ($atomic === '') {
            $atomic = '0';
        }

        if ($atomic === '0') {
            return null;
        }

        return $atomic;
    }

    /**
     * Format an atomic token amount for human-readable display.
     *
     * @param string $amount_atomic Amount in atomic units.
     * @param int    $decimals      Token decimals.
     * @return string
     */
    private function format_atomic_amount($amount_atomic, $decimals) {
        $amount_atomic = preg_replace('/[^0-9]/', '', (string) $amount_atomic);

        if ($amount_atomic === '') {
            return '0';
        }

        $decimals = max(0, (int) $decimals);

        if ($decimals === 0) {
            return ltrim($amount_atomic, '0') !== '' ? ltrim($amount_atomic, '0') : '0';
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
     * Encode error details for persistence in the payment log.
     *
     * @param array  $error_details       Raw error payload from facilitator/library.
     * @param int    $status_code         HTTP status code for the failure.
     * @param string $facilitator_message Message returned by the facilitator, if available.
     * @param string $public_message      Sanitized message intended for user display.
     * @param string $error_code          Facilitator error code identifier.
     * @return string|null JSON encoded payload or null on failure.
     */
    private function encode_error_payload_for_storage($error_details, $status_code, $facilitator_message, $public_message, $error_code) {
        $payload = array(
            'status_code' => $status_code,
            'error_code' => $error_code,
            'message' => $facilitator_message !== '' ? $facilitator_message : $public_message,
        );

        if (!empty($error_details) && is_array($error_details)) {
            $payload['details'] = $error_details;
        }

        $encoded = wp_json_encode($payload);

        if ($encoded === false) {
            $encoded = json_encode($payload);
        }

        return is_string($encoded) ? $encoded : null;
    }

    /**
     * Format the support reference identifier for a payment log entry.
     *
     * @param int $log_id Log identifier.
     * @return string
     */
    private function format_support_reference($log_id) {
        $numeric = (int) $log_id;

        if ($numeric <= 0) {
            return (string) $log_id;
        }

        return sprintf('X402-%06d', $numeric);
    }

    /**
     * Determine whether the current request prefers a JSON error response.
     *
     * @return bool
     */
    private function should_return_json_error_response() {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos(strtolower((string) $_SERVER['HTTP_ACCEPT']), 'application/json') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Send JSON error response detailing why access was denied.
     *
     * @param string     $message           Human-readable message.
     * @param int        $status_code       HTTP status code to send.
     * @param string     $error_code        Facilitator error code, if available.
     * @param string|null $reference        Support reference identifier.
     * @param array      $error_details     Additional error details to include in response.
     */
    private function send_json_error_response($message, $status_code, $error_code, $reference, $error_details) {
        status_header($status_code);
        nocache_headers();

        $payload = array(
            'success' => false,
            'error' => array(
                'message' => $message,
                'status' => $status_code,
            ),
        );

        if ($error_code !== '') {
            $payload['error']['code'] = $error_code;
        }

        if ($reference) {
            $payload['error']['reference'] = $reference;
        }

        if (!empty($error_details) && is_array($error_details)) {
            $payload['error']['details'] = $error_details;
        }

        $charset = get_option('blog_charset');
        header('Content-Type: application/json; charset=' . ($charset ? $charset : 'utf-8'));

        $body = wp_json_encode($payload);

        if ($body === false) {
            $body = json_encode($payload);
        }

        echo $body;
        exit;
    }

    /**
     * Queue a front-end notice via a short-lived cookie for display on the next render.
     *
     * @param array $data Error payload containing message, status, code, and reference.
     */
    private function queue_frontend_error_notice($data) {
        if (headers_sent()) {
            return;
        }

        $message = isset($data['message']) ? trim((string) $data['message']) : '';

        if ($message === '') {
            return;
        }

        $payload = array('message' => $message);

        if (!empty($data['status'])) {
            $payload['status'] = (int) $data['status'];
        }

        if (!empty($data['code'])) {
            $payload['code'] = (string) $data['code'];
        }

        if (!empty($data['reference'])) {
            $payload['reference'] = (string) $data['reference'];
        }

        $encoded = wp_json_encode($payload);

        if ($encoded === false) {
            $encoded = json_encode($payload);
        }

        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $cookie_value = rawurlencode(base64_encode($encoded));

        $options = array(
            'expires' => time() + 300,
            'path' => defined('COOKIEPATH') ? (COOKIEPATH ?: '/') : '/',
            'secure' => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        );

        if (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN) {
            $options['domain'] = COOKIE_DOMAIN;
        }

        setcookie('x402_paywall_error', $cookie_value, $options);
    }
}
