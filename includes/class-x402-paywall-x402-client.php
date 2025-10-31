<?php
/**
 * X402 Library Client Wrapper
 * Integrates mondb-dev/x402-php with WordPress
 *
 * @package X402_Paywall
 */

if (!defined('ABSPATH')) {
    exit;
}

class X402_Paywall_X402_Client {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * X402 Client instance
     *
     * @var mixed
     */
    private $client = null;
    
    /**
     * Library available flag
     *
     * @var bool
     */
    private $library_available = false;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize
     */
    private function __construct() {
        $this->load_library();
        $this->init_client();
    }
    
    /**
     * Load X402 library
     */
    private function load_library() {
        $autoload_path = X402_PAYWALL_PLUGIN_DIR . 'vendor/autoload.php';
        
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
            
            // Check for various possible class names from x402-php
            $this->library_available = class_exists('\X402\Client') || 
                                      class_exists('\X402\X402') ||
                                      class_exists('X402\PaymentClient') ||
                                      class_exists('X402');
        }
        
        // Show admin notice if library not available
        if (!$this->library_available && is_admin()) {
            add_action('admin_notices', array($this, 'library_missing_notice'));
        }
    }
    
    /**
     * Initialize X402 client
     */
    private function init_client() {
        if (!$this->library_available) {
            return;
        }
        
        try {
            $config = array(
                'network' => get_option('x402_default_network', 'mainnet'),
                'api_endpoint' => get_option('x402_api_endpoint', ''),
                'timeout' => 30,
                'verify_ssl' => true,
            );
            
            $config = apply_filters('x402_client_config', $config);
            
            // Try to instantiate based on available classes
            if (class_exists('\X402\Client')) {
                $this->client = new \X402\Client($config);
            } elseif (class_exists('\X402\X402')) {
                $this->client = new \X402\X402($config);
            } elseif (class_exists('X402\PaymentClient')) {
                $this->client = new \X402\PaymentClient($config);
            } elseif (class_exists('X402')) {
                $this->client = new \X402($config);
            }
            
            do_action('x402_client_initialized', $this->client);
            
        } catch (\Exception $e) {
            error_log('X402 Client Initialization Error: ' . $e->getMessage());
            if (is_admin()) {
                add_action('admin_notices', function() use ($e) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>X402 Paywall:</strong> Failed to initialize X402 client: ';
                    echo esc_html($e->getMessage());
                    echo '</p></div>';
                });
            }
        }
    }
    
    /**
     * Check if library is available
     *
     * @return bool
     */
    public function is_available() {
        return $this->library_available && $this->client !== null;
    }
    
    /**
     * Get X402 client
     *
     * @return mixed|null
     */
    public function get_client() {
        return $this->client;
    }
    
    /**
     * Create payment request
     *
     * @param array $data Payment data
     * @return array|WP_Error
     */
    public function create_payment_request($data) {
        if (!$this->is_available()) {
            return new WP_Error('x402_unavailable', 'X402 library not available. Run: composer install');
        }
        
        try {
            $request_data = array(
                'amount' => floatval($data['amount']),
                'currency' => sanitize_text_field($data['currency']),
                'recipient' => sanitize_text_field($data['recipient']),
                'network' => sanitize_text_field($data['network']),
                'metadata' => array(
                    'post_id' => isset($data['post_id']) ? intval($data['post_id']) : 0,
                    'site_url' => get_site_url(),
                    'timestamp' => current_time('timestamp'),
                ),
            );
            
            if (isset($data['callback_url'])) {
                $request_data['callback_url'] = esc_url_raw($data['callback_url']);
            }
            
            // Try different method names based on what the library provides
            $payment_request = null;
            if (method_exists($this->client, 'createPaymentRequest')) {
                $payment_request = $this->client->createPaymentRequest($request_data);
            } elseif (method_exists($this->client, 'create_payment_request')) {
                $payment_request = $this->client->create_payment_request($request_data);
            } elseif (method_exists($this->client, 'createPayment')) {
                $payment_request = $this->client->createPayment($request_data);
            } elseif (method_exists($this->client, 'create')) {
                $payment_request = $this->client->create($request_data);
            }
            
            if (!$payment_request) {
                return new WP_Error('x402_method_not_found', 'Payment request method not found in X402 library');
            }
            
            // Convert to array for WordPress compatibility
            $result = $this->payment_request_to_array($payment_request);
            
            return apply_filters('x402_payment_request_created', $result, $data);
            
        } catch (\Exception $e) {
            error_log('X402 Payment Request Error: ' . $e->getMessage());
            return new WP_Error('x402_error', $e->getMessage());
        }
    }
    
    /**
     * Verify payment
     *
     * @param array $data Payment verification data
     * @return array|WP_Error
     */
    public function verify_payment($data) {
        if (!$this->is_available()) {
            return new WP_Error('x402_unavailable', 'X402 library not available. Run: composer install');
        }
        
        try {
            $verification_data = array(
                'transaction_hash' => sanitize_text_field($data['transaction_hash']),
                'network' => sanitize_text_field($data['network']),
                'expected_amount' => floatval($data['expected_amount']),
                'expected_recipient' => sanitize_text_field($data['expected_recipient']),
            );
            
            if (isset($data['token'])) {
                $verification_data['token'] = sanitize_text_field($data['token']);
            }
            
            // Try different verification method names
            $verification = null;
            if (method_exists($this->client, 'verifyPayment')) {
                $verification = $this->client->verifyPayment($verification_data);
            } elseif (method_exists($this->client, 'verify_payment')) {
                $verification = $this->client->verify_payment($verification_data);
            } elseif (method_exists($this->client, 'verify')) {
                $verification = $this->client->verify($verification_data);
            }
            
            if ($verification === null) {
                return new WP_Error('x402_method_not_found', 'Payment verification method not found in X402 library');
            }
            
            // Convert verification result to array
            $result = $this->verification_to_array($verification);
            
            return apply_filters('x402_payment_verified', $result, $data);
            
        } catch (\Exception $e) {
            error_log('X402 Payment Verification Error: ' . $e->getMessage());
            return new WP_Error('x402_verification_failed', $e->getMessage());
        }
    }
    
    /**
     * Verify X402 signature
     *
     * @param string $message Message that was signed
     * @param string $signature Signature to verify
     * @param string $public_key Public key/address
     * @return bool|WP_Error
     */
    public function verify_signature($message, $signature, $public_key) {
        if (!$this->is_available()) {
            return new WP_Error('x402_unavailable', 'X402 library not available');
        }
        
        try {
            // Try to get signer
            $signer = null;
            if (method_exists($this->client, 'getSigner')) {
                $signer = $this->client->getSigner();
            } elseif (method_exists($this->client, 'get_signer')) {
                $signer = $this->client->get_signer();
            } elseif (property_exists($this->client, 'signer')) {
                $signer = $this->client->signer;
            }
            
            if ($signer && method_exists($signer, 'verify')) {
                $is_valid = $signer->verify($message, $signature, $public_key);
            } elseif (method_exists($this->client, 'verifySignature')) {
                $is_valid = $this->client->verifySignature($message, $signature, $public_key);
            } elseif (method_exists($this->client, 'verify_signature')) {
                $is_valid = $this->client->verify_signature($message, $signature, $public_key);
            } else {
                return new WP_Error('x402_method_not_found', 'Signature verification not supported by X402 library');
            }
            
            return apply_filters('x402_signature_verification', $is_valid, $message, $signature, $public_key);
            
        } catch (\Exception $e) {
            error_log('X402 Signature Verification Error: ' . $e->getMessage());
            return new WP_Error('x402_signature_error', $e->getMessage());
        }
    }
    
    /**
     * Convert payment request object to array
     *
     * @param mixed $payment_request Payment request object
     * @return array
     */
    private function payment_request_to_array($payment_request) {
        $result = array();
        
        // Try to extract data from object
        if (is_object($payment_request)) {
            // Try getter methods
            $methods = array(
                'payment_id' => array('getId', 'get_id', 'id'),
                'amount' => array('getAmount', 'get_amount', 'amount'),
                'currency' => array('getCurrency', 'get_currency', 'currency'),
                'recipient' => array('getRecipient', 'get_recipient', 'recipient'),
                'network' => array('getNetwork', 'get_network', 'network'),
                'qr_code' => array('getQRCode', 'get_qr_code', 'getQrCode', 'qr_code'),
                'deeplink' => array('getDeeplink', 'get_deeplink', 'deeplink'),
                'expires_at' => array('getExpiresAt', 'get_expires_at', 'expires_at'),
                'metadata' => array('getMetadata', 'get_metadata', 'metadata'),
            );
            
            foreach ($methods as $key => $method_names) {
                foreach ($method_names as $method) {
                    if (method_exists($payment_request, $method)) {
                        $result[$key] = $payment_request->$method();
                        break;
                    } elseif (property_exists($payment_request, $method)) {
                        $result[$key] = $payment_request->$method;
                        break;
                    }
                }
            }
            
            // Try to convert to array if method exists
            if (method_exists($payment_request, 'toArray')) {
                $result = array_merge($result, $payment_request->toArray());
            } elseif (method_exists($payment_request, 'to_array')) {
                $result = array_merge($result, $payment_request->to_array());
            }
        } elseif (is_array($payment_request)) {
            $result = $payment_request;
        }
        
        return $result;
    }
    
    /**
     * Convert verification object to array
     *
     * @param mixed $verification Verification result
     * @return array
     */
    private function verification_to_array($verification) {
        $result = array(
            'verified' => false,
            'confirmations' => 0,
            'block_number' => null,
            'timestamp' => null,
            'from' => null,
            'to' => null,
            'amount' => null,
        );
        
        if (is_bool($verification)) {
            $result['verified'] = $verification;
            return $result;
        }
        
        if (is_object($verification)) {
            // Try getter methods
            $methods = array(
                'verified' => array('isVerified', 'is_verified', 'verified', 'getVerified', 'get_verified'),
                'confirmations' => array('getConfirmations', 'get_confirmations', 'confirmations'),
                'block_number' => array('getBlockNumber', 'get_block_number', 'block_number'),
                'timestamp' => array('getTimestamp', 'get_timestamp', 'timestamp'),
                'from' => array('getFromAddress', 'get_from_address', 'getFrom', 'from'),
                'to' => array('getToAddress', 'get_to_address', 'getTo', 'to'),
                'amount' => array('getAmount', 'get_amount', 'amount'),
            );
            
            foreach ($methods as $key => $method_names) {
                foreach ($method_names as $method) {
                    if (method_exists($verification, $method)) {
                        $result[$key] = $verification->$method();
                        break;
                    } elseif (property_exists($verification, $method)) {
                        $result[$key] = $verification->$method;
                        break;
                    }
                }
            }
            
            // Try to convert to array
            if (method_exists($verification, 'toArray')) {
                $result = array_merge($result, $verification->toArray());
            } elseif (method_exists($verification, 'to_array')) {
                $result = array_merge($result, $verification->to_array());
            }
        } elseif (is_array($verification)) {
            $result = array_merge($result, $verification);
        }
        
        return $result;
    }
    
    /**
     * Get transaction details
     *
     * @param string $tx_hash Transaction hash
     * @param string $network Network
     * @return array|WP_Error
     */
    public function get_transaction($tx_hash, $network) {
        if (!$this->is_available()) {
            return new WP_Error('x402_unavailable', 'X402 library not available');
        }
        
        try {
            $transaction = null;
            if (method_exists($this->client, 'getTransaction')) {
                $transaction = $this->client->getTransaction($tx_hash, $network);
            } elseif (method_exists($this->client, 'get_transaction')) {
                $transaction = $this->client->get_transaction($tx_hash, $network);
            }
            
            if (!$transaction) {
                return new WP_Error('x402_method_not_found', 'Transaction lookup not supported');
            }
            
            return $this->transaction_to_array($transaction);
            
        } catch (\Exception $e) {
            error_log('X402 Get Transaction Error: ' . $e->getMessage());
            return new WP_Error('x402_transaction_error', $e->getMessage());
        }
    }
    
    /**
     * Convert transaction object to array
     *
     * @param mixed $transaction Transaction object
     * @return array
     */
    private function transaction_to_array($transaction) {
        $result = array();
        
        if (is_object($transaction)) {
            $methods = array(
                'hash' => array('getHash', 'get_hash', 'hash'),
                'from' => array('getFrom', 'get_from', 'from'),
                'to' => array('getTo', 'get_to', 'to'),
                'amount' => array('getAmount', 'get_amount', 'amount'),
                'confirmations' => array('getConfirmations', 'get_confirmations', 'confirmations'),
                'block_number' => array('getBlockNumber', 'get_block_number', 'block_number'),
                'timestamp' => array('getTimestamp', 'get_timestamp', 'timestamp'),
                'status' => array('getStatus', 'get_status', 'status'),
            );
            
            foreach ($methods as $key => $method_names) {
                foreach ($method_names as $method) {
                    if (method_exists($transaction, $method)) {
                        $result[$key] = $transaction->$method();
                        break;
                    } elseif (property_exists($transaction, $method)) {
                        $result[$key] = $transaction->$method;
                        break;
                    }
                }
            }
            
            if (method_exists($transaction, 'toArray')) {
                $result = array_merge($result, $transaction->toArray());
            }
        } elseif (is_array($transaction)) {
            $result = $transaction;
        }
        
        return $result;
    }
    
    /**
     * Get supported networks from X402 library
     *
     * @return array
     */
    public function get_supported_networks() {
        if (!$this->is_available()) {
            return array();
        }
        
        try {
            if (method_exists($this->client, 'getSupportedNetworks')) {
                return $this->client->getSupportedNetworks();
            } elseif (method_exists($this->client, 'get_supported_networks')) {
                return $this->client->get_supported_networks();
            }
        } catch (\Exception $e) {
            error_log('X402 Get Networks Error: ' . $e->getMessage());
        }
        
        return array();
    }
    
    /**
     * Get supported tokens for network
     *
     * @param string $network Network identifier
     * @return array
     */
    public function get_supported_tokens($network) {
        if (!$this->is_available()) {
            return array();
        }
        
        try {
            if (method_exists($this->client, 'getSupportedTokens')) {
                return $this->client->getSupportedTokens($network);
            } elseif (method_exists($this->client, 'get_supported_tokens')) {
                return $this->client->get_supported_tokens($network);
            }
        } catch (\Exception $e) {
            error_log('X402 Get Tokens Error: ' . $e->getMessage());
        }
        
        return array();
    }
    
    /**
     * Admin notice for missing library
     */
    public function library_missing_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e('X402 Paywall:', 'x402-paywall'); ?></strong> 
                <?php esc_html_e('X402 PHP library not found. Please run:', 'x402-paywall'); ?>
            </p>
            <pre style="background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace;">cd <?php echo esc_html(X402_PAYWALL_PLUGIN_DIR); ?>
composer install</pre>
            <p>
                <?php esc_html_e('Or download from:', 'x402-paywall'); ?> 
                <a href="https://github.com/mondb-dev/x402-php" target="_blank">
                    https://github.com/mondb-dev/x402-php
                </a>
            </p>
            <p>
                <em><?php esc_html_e('Plugin will use fallback implementation until library is installed.', 'x402-paywall'); ?></em>
            </p>
        </div>
        <?php
    }
}
