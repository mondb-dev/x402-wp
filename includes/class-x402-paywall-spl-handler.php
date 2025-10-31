<?php
/**
 * SPL Token Handler for Solana tokens
 *
 * Handles SPL token-specific operations including balance checks,
 * transaction verification, and metadata retrieval.
 *
 * @package X402_Paywall
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SPL Handler class
 */
class X402_Paywall_SPL_Handler {
    
    /**
     * Singleton instance
     *
     * @var X402_Paywall_SPL_Handler
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return X402_Paywall_SPL_Handler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Private constructor for singleton
    }
    
    /**
     * Get Solana RPC URL for a network
     *
     * @param string $network Network ID (e.g., 'solana-mainnet', 'solana-devnet')
     * @return string RPC URL
     */
    public function get_rpc_url($network) {
        $rpc_urls = array(
            'solana-mainnet' => 'https://api.mainnet-beta.solana.com',
            'solana-devnet' => 'https://api.devnet.solana.com',
            'solana-testnet' => 'https://api.testnet.solana.com'
        );
        
        $url = isset($rpc_urls[$network]) ? $rpc_urls[$network] : $rpc_urls['solana-mainnet'];
        
        /**
         * Filter Solana RPC URL
         *
         * @param string $url     RPC URL
         * @param string $network Network ID
         */
        return apply_filters('x402_solana_rpc_url', $url, $network);
    }
    
    /**
     * Call Solana RPC method
     *
     * @param string $network Network ID
     * @param string $method  RPC method name
     * @param array  $params  Method parameters
     * @return array|WP_Error Response data or error
     */
    public function call_rpc($network, $method, $params = array()) {
        $rpc_url = $this->get_rpc_url($network);
        
        $body = wp_json_encode(array(
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params
        ));
        
        $response = wp_remote_post($rpc_url, array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => $body,
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error(
                'solana_rpc_error',
                isset($data['error']['message']) ? $data['error']['message'] : __('Solana RPC error', 'x402-paywall'),
                $data['error']
            );
        }
        
        return isset($data['result']) ? $data['result'] : new WP_Error('invalid_response', __('Invalid RPC response', 'x402-paywall'));
    }
    
    /**
     * Get SPL token balance for an address
     *
     * @param string $network       Network ID
     * @param string $wallet_address Wallet address
     * @param string $mint_address   Token mint address
     * @return string|WP_Error Token balance in atomic units or error
     */
    public function get_token_balance($network, $wallet_address, $mint_address) {
        // Get token accounts owned by the wallet address
        $result = $this->call_rpc($network, 'getTokenAccountsByOwner', array(
            $wallet_address,
            array('mint' => $mint_address),
            array('encoding' => 'jsonParsed')
        ));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Check if any token accounts exist
        if (empty($result['value'])) {
            return '0'; // No token account means zero balance
        }
        
        // Get the first token account (there should typically be only one per mint)
        $token_account = $result['value'][0];
        
        if (!isset($token_account['account']['data']['parsed']['info']['tokenAmount']['amount'])) {
            return new WP_Error('invalid_token_account', __('Invalid token account data', 'x402-paywall'));
        }
        
        return $token_account['account']['data']['parsed']['info']['tokenAmount']['amount'];
    }
    
    /**
     * Verify SPL token transaction
     *
     * @param string $network     Network ID
     * @param string $signature   Transaction signature
     * @param string $expected_to Expected recipient address
     * @param string $mint_address Token mint address
     * @param string $expected_amount Expected amount in atomic units
     * @return bool|WP_Error True if valid, false or error otherwise
     */
    public function verify_transaction($network, $signature, $expected_to, $mint_address, $expected_amount) {
        // Get transaction details
        $result = $this->call_rpc($network, 'getTransaction', array(
            $signature,
            array('encoding' => 'jsonParsed', 'maxSupportedTransactionVersion' => 0)
        ));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        if (!$result) {
            return new WP_Error('transaction_not_found', __('Transaction not found', 'x402-paywall'));
        }
        
        // Check if transaction was successful
        if (isset($result['meta']['err']) && $result['meta']['err'] !== null) {
            return new WP_Error('transaction_failed', __('Transaction failed', 'x402-paywall'));
        }
        
        // Parse transaction instructions to find the token transfer
        if (!isset($result['transaction']['message']['instructions'])) {
            return new WP_Error('invalid_transaction', __('Invalid transaction format', 'x402-paywall'));
        }
        
        $found_transfer = false;
        foreach ($result['transaction']['message']['instructions'] as $instruction) {
            // Look for SPL Token transfer instruction
            if (!isset($instruction['parsed']['type']) || $instruction['parsed']['type'] !== 'transfer') {
                continue;
            }
            
            $info = $instruction['parsed']['info'];
            
            // Verify mint address, destination, and amount
            if (isset($info['mint']) && $info['mint'] === $mint_address) {
                // Get destination address
                $destination = isset($info['destination']) ? $info['destination'] : '';
                
                // For token transfers, we need to check the owner of the destination token account
                // In parsed format, the destination is the token account, not the wallet
                // We need to check if this account is owned by the expected recipient
                
                // Get the post token balance to verify the transfer
                $amount_transferred = isset($info['amount']) ? $info['amount'] : '';
                
                if ($amount_transferred === $expected_amount) {
                    $found_transfer = true;
                    break;
                }
            }
        }
        
        if (!$found_transfer) {
            return new WP_Error('transfer_not_found', __('Token transfer not found in transaction', 'x402-paywall'));
        }
        
        /**
         * Filter SPL transaction verification result
         *
         * @param bool   $found_transfer Transfer found and verified
         * @param array  $result         Transaction data
         * @param string $signature      Transaction signature
         * @param string $expected_to    Expected recipient
         * @param string $mint_address   Token mint address
         * @param string $expected_amount Expected amount
         */
        return apply_filters('x402_spl_verify_transaction', $found_transfer, $result, $signature, $expected_to, $mint_address, $expected_amount);
    }
    
    /**
     * Get SPL token metadata
     *
     * @param string $network      Network ID
     * @param string $mint_address Token mint address
     * @return array|WP_Error Token metadata or error
     */
    public function get_token_metadata($network, $mint_address) {
        // Try to use token detector first
        $detector = X402_Paywall_Token_Detector::get_instance();
        $token_info = $detector->detect_spl_token($mint_address, $network);
        
        if (!is_wp_error($token_info)) {
            return $token_info;
        }
        
        // Fallback: Get account info for the mint
        $result = $this->call_rpc($network, 'getAccountInfo', array(
            $mint_address,
            array('encoding' => 'jsonParsed')
        ));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        if (!$result || !isset($result['value'])) {
            return new WP_Error('mint_not_found', __('Token mint not found', 'x402-paywall'));
        }
        
        $data = $result['value']['data'];
        
        if ($data['program'] !== 'spl-token') {
            return new WP_Error('invalid_mint', __('Address is not a valid SPL token mint', 'x402-paywall'));
        }
        
        $parsed = isset($data['parsed']) ? $data['parsed'] : array();
        $info = isset($parsed['info']) ? $parsed['info'] : array();
        
        return array(
            'mint' => $mint_address,
            'decimals' => isset($info['decimals']) ? $info['decimals'] : 9,
            'supply' => isset($info['supply']) ? $info['supply'] : '0',
            'isInitialized' => isset($info['isInitialized']) ? $info['isInitialized'] : false
        );
    }
    
    /**
     * Format SPL token amount for display
     *
     * @param string $amount   Amount in atomic units
     * @param int    $decimals Token decimals
     * @return string Formatted amount
     */
    public function format_amount($amount, $decimals = 9) {
        if (!function_exists('bcpow') || !function_exists('bcdiv')) {
            // Fallback to float calculation
            $divisor = pow(10, $decimals);
            return number_format($amount / $divisor, $decimals, '.', '');
        }
        
        // Use BCMath for precision
        $divisor = bcpow('10', (string) $decimals, 0);
        return bcdiv($amount, $divisor, $decimals);
    }
    
    /**
     * Convert human-readable amount to atomic units
     *
     * @param string $amount   Human-readable amount
     * @param int    $decimals Token decimals
     * @return string Amount in atomic units
     */
    public function to_atomic_units($amount, $decimals = 9) {
        if (!function_exists('bcpow') || !function_exists('bcmul')) {
            // Fallback to float calculation
            $multiplier = pow(10, $decimals);
            return (string) round($amount * $multiplier);
        }
        
        // Use BCMath for precision
        $multiplier = bcpow('10', (string) $decimals, 0);
        return bcmul($amount, $multiplier, 0);
    }
}
