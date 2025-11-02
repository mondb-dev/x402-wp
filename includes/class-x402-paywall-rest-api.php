<?php
/**
 * REST API integrations for the X402 paywall plugin.
 *
 * @package X402_Paywall
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API controller singleton.
 */
class X402_Paywall_REST_API {

    /**
     * Namespace for REST routes.
     */
    private const REST_NAMESPACE = 'x402-paywall/v1';

    /**
     * Singleton instance.
     *
     * @var X402_Paywall_REST_API|null
     */
    private static $instance = null;

    /**
     * Security helper reference.
     *
     * @var X402_Paywall_Security
     */
    private $security;

    /**
     * Finance helper reference.
     *
     * @var X402_Paywall_Finance
     */
    private $finance;

    /**
     * Retrieve singleton instance.
     *
     * @return X402_Paywall_REST_API
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->security = X402_Paywall_Security::get_instance();
        $this->finance  = X402_Paywall_Finance::get_instance();

        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/verify-payment',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'handle_verify_payment'),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/payment-requirements/(?P<post_id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'handle_payment_requirements'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'post_id' => array(
                        'description' => __('Post identifier.', 'x402-paywall'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/payment-status/(?P<post_id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'handle_payment_status'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'address' => array(
                        'description' => __('Wallet address used for the payment.', 'x402-paywall'),
                        'type'        => 'string',
                        'required'    => true,
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/transactions',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'handle_transactions'),
                'permission_callback' => array($this, 'can_view_transactions'),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/wallet/(?P<user_id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'handle_get_wallet'),
                    'permission_callback' => array($this, 'can_manage_wallet'),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'handle_update_wallet'),
                    'permission_callback' => array($this, 'can_manage_wallet'),
                    'args'                => array(
                        'evm_address' => array(
                            'type'        => 'string',
                            'required'    => false,
                            'description' => __('EVM compatible wallet address.', 'x402-paywall'),
                        ),
                        'spl_address' => array(
                            'type'        => 'string',
                            'required'    => false,
                            'description' => __('Solana wallet address.', 'x402-paywall'),
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/financial-summary',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'handle_financial_summary'),
                'permission_callback' => array($this, 'can_manage_finance'),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/webhook',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'handle_webhook'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Handle verify payment requests.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_verify_payment(WP_REST_Request $request) {
        $requirements = $request->get_param('requirements');
        $payment      = $request->get_param('payment_header');

        if (!is_array($requirements)) {
            return new WP_Error('x402_invalid_requirements', __('Payment requirements are missing or invalid.', 'x402-paywall'), array('status' => 400));
        }

        $protocol = X402_Paywall_Protocol::get_instance();

        $previous_payment = $_SERVER['HTTP_X_PAYMENT'] ?? null;
        $previous_payment_alt = $_SERVER['X-Payment'] ?? null;

        if (is_string($payment) && '' !== trim($payment)) {
            $_SERVER['HTTP_X_PAYMENT'] = $payment;
            $_SERVER['X-Payment']      = $payment;
        }

        $requirements_object = json_decode(wp_json_encode($requirements));

        if (!is_object($requirements_object)) {
            return new WP_Error('x402_invalid_requirements', __('Could not normalize payment requirements.', 'x402-paywall'), array('status' => 400));
        }

        $result = $protocol->process_payment($requirements_object);

        if (null === $previous_payment) {
            unset($_SERVER['HTTP_X_PAYMENT']);
        } else {
            $_SERVER['HTTP_X_PAYMENT'] = $previous_payment;
        }

        if (null === $previous_payment_alt) {
            unset($_SERVER['X-Payment']);
        } else {
            $_SERVER['X-Payment'] = $previous_payment_alt;
        }

        return rest_ensure_response($result);
    }

    /**
     * Retrieve payment requirements for a post.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_payment_requirements(WP_REST_Request $request) {
        $post_id = (int) $request['post_id'];

        if (!$this->is_paywall_enabled($post_id)) {
            return new WP_Error('x402_paywall_disabled', __('Paywall not enabled for this post.', 'x402-paywall'), array('status' => 404));
        }

        $config = $this->get_paywall_config($post_id);

        if (!$config) {
            return new WP_Error('x402_invalid_configuration', __('Unable to determine paywall configuration.', 'x402-paywall'), array('status' => 400));
        }

        $protocol     = X402_Paywall_Protocol::get_instance();
        $requirements = $protocol->create_payment_requirements($post_id, $config);

        if (!$requirements) {
            return new WP_Error('x402_requirement_error', __('Failed to generate payment requirements.', 'x402-paywall'), array('status' => 500));
        }

        return rest_ensure_response($this->prepare_requirements_response($requirements));
    }

    /**
     * Determine payment status for a user/address.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_payment_status(WP_REST_Request $request) {
        $post_id = (int) $request['post_id'];
        $address = sanitize_text_field($request->get_param('address'));

        if ('' === $address) {
            return new WP_Error('x402_invalid_address', __('Wallet address is required.', 'x402-paywall'), array('status' => 400));
        }

        $has_paid = X402_Paywall_DB::has_user_paid($post_id, $address);

        return rest_ensure_response(
            array(
                'post_id'  => $post_id,
                'address'  => $address,
                'verified' => $has_paid,
            )
        );
    }

    /**
     * List recent transactions.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response
     */
    public function handle_transactions(WP_REST_Request $request) {
        global $wpdb;

        $table = $wpdb->prefix . 'x402_payment_logs';
        $per_page = (int) $request->get_param('per_page');

        if ($per_page <= 0) {
            $per_page = 50;
        }

        $limit = min(200, max(1, $per_page));

        $sql = "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $limit), ARRAY_A);

        foreach ($rows as &$row) {
            $row['id']          = (int) $row['id'];
            $row['post_id']     = (int) $row['post_id'];
            $row['created_at']  = mysql_to_rfc3339($row['created_at']);
            $decimals = (int) get_post_meta($row['post_id'], '_x402_paywall_token_decimals', true);

            if ($decimals <= 0) {
                $decimals = 6;
            }

            $row['amount_readable'] = $this->finance->atomic_to_decimal($row['amount'], $decimals);
        }

        return rest_ensure_response($rows);
    }

    /**
     * Get wallet addresses.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response
     */
    public function handle_get_wallet(WP_REST_Request $request) {
        $user_id = (int) $request['user_id'];

        $profile = X402_Paywall_DB::get_user_profile($user_id);

        return rest_ensure_response(
            array(
                'user_id'     => $user_id,
                'evm_address' => $profile ? $profile->evm_address : null,
                'spl_address' => $profile ? $profile->spl_address : null,
            )
        );
    }

    /**
     * Update wallet addresses.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_update_wallet(WP_REST_Request $request) {
        if (!$this->security->verify_rest_request_nonce($request)) {
            return new WP_Error('x402_invalid_nonce', __('Security nonce is missing or invalid.', 'x402-paywall'), array('status' => 403));
        }

        $user_id = (int) $request['user_id'];

        $evm_address = sanitize_text_field($request->get_param('evm_address'));
        $spl_address = sanitize_text_field($request->get_param('spl_address'));

        if ($evm_address && !X402_Paywall_Payment_Handler::validate_evm_address($evm_address)) {
            return new WP_Error('x402_invalid_evm', __('Invalid EVM address format.', 'x402-paywall'), array('status' => 400));
        }

        if ($spl_address && !X402_Paywall_Payment_Handler::validate_spl_address($spl_address)) {
            return new WP_Error('x402_invalid_spl', __('Invalid Solana address format.', 'x402-paywall'), array('status' => 400));
        }

        X402_Paywall_DB::save_user_profile(
            $user_id,
            $evm_address ? $evm_address : null,
            $spl_address ? $spl_address : null
        );

        do_action('x402_wallet_updated', $user_id, array(
            'evm_address' => $evm_address,
            'spl_address' => $spl_address,
        ));

        return rest_ensure_response(
            array(
                'success' => true,
                'user_id' => $user_id,
            )
        );
    }

    /**
     * Return financial summary data.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response
     */
    public function handle_financial_summary(WP_REST_Request $request) {
        $summary = $this->finance->get_financial_summary(
            array(
                'status' => $request->get_param('status'),
                'limit'  => $request->get_param('limit'),
            )
        );

        return rest_ensure_response($summary);
    }

    /**
     * Handle webhook callbacks.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_webhook(WP_REST_Request $request) {
        $signature = $request->get_header('X-X402-Signature');
        $timestamp = $request->get_header('X-X402-Timestamp');
        $body      = $request->get_body();

        if (!$this->security->verify_webhook_signature($body, $signature, $timestamp ? (int) $timestamp : null)) {
            return new WP_Error('x402_invalid_signature', __('Webhook signature verification failed.', 'x402-paywall'), array('status' => 403));
        }

        $payload = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('x402_invalid_payload', __('Unable to decode webhook payload.', 'x402-paywall'), array('status' => 400));
        }

        do_action('x402_webhook_received', $payload, $request);

        return rest_ensure_response(array('received' => true));
    }

    /**
     * Capability check for viewing transactions.
     *
     * @return bool
     */
    public function can_view_transactions() {
        return current_user_can('edit_posts');
    }

    /**
     * Capability check for wallet operations.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return bool
     */
    public function can_manage_wallet(WP_REST_Request $request) {
        $user_id = (int) $request['user_id'];

        if (get_current_user_id() === $user_id) {
            return current_user_can('publish_posts');
        }

        return current_user_can('edit_user', $user_id);
    }

    /**
     * Capability check for financial summary.
     *
     * @return bool
     */
    public function can_manage_finance() {
        return current_user_can('manage_options');
    }

    /**
     * Check whether paywall is enabled for post.
     *
     * @param int $post_id Post identifier.
     *
     * @return bool
     */
    private function is_paywall_enabled($post_id) {
        return '1' === get_post_meta($post_id, '_x402_paywall_enabled', true);
    }

    /**
     * Retrieve paywall configuration for a post.
     *
     * @param int $post_id Post identifier.
     *
     * @return array|null
     */
    private function get_paywall_config($post_id) {
        $network_type = get_post_meta($post_id, '_x402_paywall_network_type', true);
        $network      = get_post_meta($post_id, '_x402_paywall_network', true);
        $token        = get_post_meta($post_id, '_x402_paywall_token_address', true);
        $amount       = get_post_meta($post_id, '_x402_paywall_amount', true);
        $decimals     = (int) get_post_meta($post_id, '_x402_paywall_token_decimals', true);
        $amount_format = get_post_meta($post_id, '_x402_paywall_amount_format', true);

        if (!$network_type || !$network || !$token || !$amount) {
            return null;
        }

        if ('atomic' !== $amount_format) {
            $amount = $this->finance->decimal_to_atomic($amount, $decimals ?: 6);
        }

        $author_id = (int) get_post_field('post_author', $post_id);
        $profile   = X402_Paywall_DB::get_user_profile($author_id);

        if (!$profile) {
            return null;
        }

        $recipient = 'spl' === $network_type ? $profile->spl_address : $profile->evm_address;

        if (!$recipient) {
            return null;
        }

        $config = array(
            'recipient_address' => $recipient,
            'amount'            => $amount,
            'token_address'     => $token,
            'network'           => $network,
            'network_type'      => $network_type,
            'token_decimals'    => $decimals ?: 6,
        );

        if ('evm' === $network_type) {
            $config['token_name']    = get_post_meta($post_id, '_x402_paywall_token_name', true);
            $config['token_version'] = get_post_meta($post_id, '_x402_paywall_token_version', true);
        }

        return $config;
    }

    /**
     * Prepare requirements for REST response.
     *
     * @param object $requirements Requirements object returned by protocol handler.
     *
     * @return array
     */
    private function prepare_requirements_response($requirements) {
        $data = json_decode(wp_json_encode($requirements), true);

        return is_array($data) ? $data : array();
    }
}
