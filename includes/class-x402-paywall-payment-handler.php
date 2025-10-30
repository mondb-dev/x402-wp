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

            $amount_atomic = $this->normalize_atomic_amount(
                $paywall_config['amount'] ?? '',
                $paywall_config['token_decimals'] ?? 0
            );

            if ($amount_atomic === null) {
                error_log('X402 Paywall: Invalid payment amount configuration for post ' . $post_id);
                return null;
            }

            $params = array(
                'payTo' => $paywall_config['recipient_address'],
                'amount' => $amount_atomic,
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
            return $this->create_error_result(
                __('Payment handler not initialized', 'x402-paywall'),
                'handler_not_initialized',
                500
            );
        }

        try {
            // Convert $_SERVER to array if needed
            $server_data = $_SERVER;

            $result = $this->handler->processPayment($server_data, $requirements);

            if (!is_array($result)) {
                return $this->create_error_result(
                    __('Unexpected payment handler response', 'x402-paywall'),
                    'invalid_handler_response',
                    500
                );
            }

            if (!empty($result['verified'])) {
                return $result;
            }

            if (!isset($result['error'])) {
                return array_merge(
                    array(
                        'verified' => false,
                        'payload' => $result['payload'] ?? null,
                        'settlement' => $result['settlement'] ?? null,
                    ),
                    array('error' => null)
                );
            }

            return $result;
        } catch (PaymentRequiredException $e) {
            error_log('X402 Paywall: Payment required - ' . $e->getMessage());

            $status_code = $this->extract_status_code_from_exception($e, 402);
            $facilitator_message = $this->extract_facilitator_message_from_exception($e);

            return $this->create_error_result(
                $e->getMessage(),
                $e->invalidReason ?? 'payment_required',
                $status_code,
                $facilitator_message
            );
        } catch (ValidationException $e) {
            error_log('X402 Paywall: Validation error - ' . $e->getMessage());

            return $this->create_error_result(
                $e->getMessage(),
                'validation_error',
                400
            );
        } catch (FacilitatorException $e) {
            error_log('X402 Paywall: Facilitator error - ' . $e->getMessage());

            $status_code = $this->extract_status_code_from_exception($e, 502);

            return $this->create_error_result(
                $e->getMessage(),
                'facilitator_error',
                $status_code,
                $e->getMessage()
            );
        } catch (Exception $e) {
            error_log('X402 Paywall: Unexpected error - ' . $e->getMessage());

            return $this->create_error_result(
                $e->getMessage(),
                'unexpected_error',
                500
            );
        }
    }

    /**
     * Create a standardized error result for payment processing failures.
     *
     * @param string $message Error message for logging/user feedback.
     * @param string|null $code Internal error code identifier.
     * @param int $status_code HTTP status code.
     * @param string|null $facilitator_message Optional facilitator specific message.
     * @return array
     */
    private function create_error_result($message, $code = null, $status_code = 500, $facilitator_message = null) {
        $error = array(
            'message' => $message,
            'code' => $code,
            'status_code' => $status_code,
        );

        if ($facilitator_message !== null && $facilitator_message !== '') {
            $error['facilitator_message'] = $facilitator_message;
        }

        return array(
            'verified' => false,
            'payload' => null,
            'settlement' => null,
            'error' => $error,
        );
    }

    /**
     * Extract HTTP status code from nested exceptions.
     *
     * @param \Throwable $exception Exception chain to inspect.
     * @param int $default Default status code when none found.
     * @return int
     */
    private function extract_status_code_from_exception($exception, $default = 500) {
        $current = $exception;

        while ($current) {
            if ($current instanceof \GuzzleHttp\Exception\RequestException && $current->hasResponse()) {
                return (int) $current->getResponse()->getStatusCode();
            }

            $current = $current->getPrevious();
        }

        return $default;
    }

    /**
     * Extract facilitator message from nested exceptions when available.
     *
     * @param \Throwable $exception Exception chain to inspect.
     * @return string|null
     */
    private function extract_facilitator_message_from_exception($exception) {
        $current = $exception;

        while ($current) {
            if ($current instanceof FacilitatorException) {
                return $current->getMessage();
            }

            $current = $current->getPrevious();
        }

        return null;
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
     * Normalize the configured amount into atomic units.
     *
     * @param string $amount   Configured amount (atomic or decimal string).
     * @param int    $decimals Token decimals for conversion.
     * @return string|null Normalized atomic amount or null when invalid.
     */
    private function normalize_atomic_amount($amount, $decimals) {
        $amount_string = trim((string) $amount);

        if ($amount_string === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $amount_string)) {
            return $this->sanitize_atomic_string($amount_string);
        }

        if (!preg_match('/^\d+(?:\.\d+)?$/', $amount_string)) {
            return null;
        }

        $decimals = max(0, (int) $decimals);
        $parts = explode('.', $amount_string, 2);
        $integer_part = $parts[0];
        $fractional_part = isset($parts[1]) ? $parts[1] : '';

        if ($fractional_part !== '' && strlen($fractional_part) > $decimals) {
            return null;
        }

        $multiplier = '1' . str_repeat('0', $decimals);

        if (function_exists('bcmul')) {
            $result = bcmul($amount_string, $multiplier, 0);
            $normalized = $this->sanitize_atomic_string($result);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        if (class_exists('\\Decimal\\Decimal')) {
            try {
                $decimal_value = new \Decimal\Decimal($amount_string);
                $multiplier_value = new \Decimal\Decimal($multiplier);
                $result_decimal = $decimal_value->mul($multiplier_value);
                $result_string = $result_decimal->toString();

                if (stripos($result_string, 'e') === false) {
                    $normalized = $this->sanitize_atomic_string($result_string);

                    if ($normalized !== null) {
                        return $normalized;
                    }
                }
            } catch (\Throwable $exception) {
                // Fall back to manual conversion below
            }
        }

        return $this->manual_decimal_to_atomic($integer_part, $fractional_part, $decimals);
    }

    /**
     * Convert decimal amount to atomic units without external extensions.
     *
     * @param string $integer_part    Integer portion of the amount.
     * @param string $fractional_part Fractional portion of the amount.
     * @param int    $decimals        Token decimals for conversion.
     * @return string|null Atomic amount or null when invalid.
     */
    private function manual_decimal_to_atomic($integer_part, $fractional_part, $decimals) {
        $fractional_part = str_pad($fractional_part, $decimals, '0', STR_PAD_RIGHT);
        $atomic = $integer_part . $fractional_part;

        return $this->sanitize_atomic_string($atomic);
    }

    /**
     * Sanitize atomic amount strings ensuring they represent a positive integer.
     *
     * @param string $value Potential atomic amount string.
     * @return string|null Sanitized atomic amount or null when invalid.
     */
    private function sanitize_atomic_string($value) {
        $digits_only = preg_replace('/[^0-9]/', '', (string) $value);

        if ($digits_only === '' || !preg_match('/[1-9]/', $digits_only)) {
            return null;
        }

        $normalized = ltrim($digits_only, '0');

        if ($normalized === '') {
            return null;
        }

        return $normalized;
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
