<?php
/**
 * Payment handler class that wraps x402-php library
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;
use X402\Exceptions\PaymentRequiredException;
use X402\Exceptions\ValidationException;
use X402\Exceptions\FacilitatorException;

/**
 * Payment handler wrapper class
 */
class X402_Paywall_Payment_Handler {
    
    /**
     * X402 payment handler instance
     *
     * @var PaymentHandler
     */
    private $handler;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->initialize_handler();
    }
    
    /**
     * Initialize the x402 payment handler
     */
    private function initialize_handler() {
        $facilitator_url = get_option('x402_paywall_facilitator_url', 'https://facilitator.x402.org');
        $auto_settle = get_option('x402_paywall_auto_settle', '1') === '1';
        $buffer_seconds = (int) get_option('x402_paywall_valid_before_buffer', '6');
        
        try {
            $facilitator = new FacilitatorClient($facilitator_url);
            $this->handler = new PaymentHandler(
                facilitator: $facilitator,
                autoSettle: $auto_settle,
                validBeforeBufferSeconds: $buffer_seconds
            );
        } catch (Exception $e) {
            error_log('X402 Paywall: Failed to initialize payment handler - ' . $e->getMessage());
            $this->handler = null;
        }
    }
    
    /**
     * Create payment requirements for a post
     *
     * @param int $post_id Post ID
     * @param array $paywall_config Paywall configuration
     * @return object|null Payment requirements
     */
    public function create_payment_requirements($post_id, $paywall_config) {
        if (!$this->handler) {
            return null;
        }
        
        try {
            $post_url = get_permalink($post_id);
            $post_title = get_the_title($post_id);
            
            $params = array(
                'payTo' => $paywall_config['recipient_address'],
                'amount' => $paywall_config['amount'],
                'resource' => $post_url,
                'description' => sprintf(__('Access to: %s', 'x402-paywall'), $post_title),
                'asset' => $paywall_config['token_address'],
                'network' => $paywall_config['network'],
                'scheme' => 'exact',
                'timeout' => 300,
                'mimeType' => 'text/html',
                'id' => 'post-' . $post_id . '-' . uniqid(),
            );
            
            // Add extra field for EVM networks (required for EIP-712)
            if ($this->is_evm_network($paywall_config['network'])) {
                $params['extra'] = array(
                    'name' => $paywall_config['token_name'] ?? 'USD Coin',
                    'version' => $paywall_config['token_version'] ?? '2',
                );
            }
            
            return $this->handler->createPaymentRequirements(
                $params['payTo'],
                $params['amount'],
                $params['resource'],
                $params['description'],
                $params['asset'],
                $params['network'],
                $params['scheme'],
                $params['timeout'],
                $params['mimeType'],
                $params['extra'] ?? null,
                $params['id']
            );
        } catch (Exception $e) {
            error_log('X402 Paywall: Failed to create payment requirements - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Process incoming payment
     *
     * @param array $requirements Payment requirements
     * @return array Result with 'verified' status and optional 'settlement' data
     */
    public function process_payment($requirements) {
        if (!$this->handler) {
            return array('verified' => false, 'settlement' => null);
        }
        
        try {
            // Convert $_SERVER to array if needed
            $server_data = $_SERVER;
            
            $result = $this->handler->processPayment($server_data, $requirements);
            return $result;
        } catch (PaymentRequiredException $e) {
            error_log('X402 Paywall: Payment required - ' . $e->getMessage());
            return array('verified' => false, 'settlement' => null);
        } catch (ValidationException $e) {
            error_log('X402 Paywall: Validation error - ' . $e->getMessage());
            return array('verified' => false, 'settlement' => null);
        } catch (FacilitatorException $e) {
            error_log('X402 Paywall: Facilitator error - ' . $e->getMessage());
            return array('verified' => false, 'settlement' => null);
        } catch (Exception $e) {
            error_log('X402 Paywall: Unexpected error - ' . $e->getMessage());
            return array('verified' => false, 'settlement' => null);
        }
    }
    
    /**
     * Create 402 Payment Required response
     *
     * @param object $requirements Payment requirements
     * @return array Response data including headers
     */
    public function create_payment_required_response($requirements) {
        if (!$this->handler) {
            return array(
                'headers' => array(),
                'body' => array('error' => 'Payment handler not initialized'),
            );
        }
        
        try {
            $response = $this->handler->createPaymentRequiredResponse($requirements);
            return array(
                'headers' => $response->getHeaders(),
                'body' => json_decode(json_encode($response), true),
            );
        } catch (Exception $e) {
            error_log('X402 Paywall: Failed to create payment required response - ' . $e->getMessage());
            return array(
                'headers' => array(),
                'body' => array('error' => 'Failed to create payment response'),
            );
        }
    }
    
    /**
     * Check if network is EVM-based
     *
     * @param string $network Network identifier
     * @return bool True if EVM network
     */
    private function is_evm_network($network) {
        $evm_networks = array(
            'ethereum-mainnet', 'ethereum-sepolia', 'ethereum-holesky',
            'base-mainnet', 'base-sepolia',
            'optimism-mainnet', 'optimism-sepolia',
            'arbitrum-mainnet', 'arbitrum-sepolia',
            'polygon-mainnet', 'polygon-amoy',
        );
        
        return in_array($network, $evm_networks, true);
    }
    
    /**
     * Validate Ethereum address
     *
     * @param string $address Address to validate
     * @return bool Valid status
     */
    public static function validate_evm_address($address) {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    }
    
    /**
     * Validate Solana address
     *
     * @param string $address Address to validate
     * @return bool Valid status
     */
    public static function validate_spl_address($address) {
        // Solana addresses are 32-44 base58 characters
        return preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address) === 1;
    }
}
