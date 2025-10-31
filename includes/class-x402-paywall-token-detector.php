<?php
/**
 * Token Detector - Auto-detect token details from Contract Address
 *
 * Automatically fetches token information from blockchain and token registries
 * when users paste a contract address (CA). Supports both ERC20 and SPL tokens.
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * X402 Token Detector Class
 */
class X402_Paywall_Token_Detector {
    
    /**
     * Singleton instance
     *
     * @var X402_Paywall_Token_Detector
     */
    private static $instance = null;
    
    /**
     * Cache group
     *
     * @var string
     */
    const CACHE_GROUP = 'x402_token_detector';
    
    /**
     * Cache duration (1 hour)
     *
     * @var int
     */
    const CACHE_DURATION = HOUR_IN_SECONDS;
    
    /**
     * Get singleton instance
     *
     * @return X402_Paywall_Token_Detector
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
        // Setup cache on init
        add_action('init', array($this, 'setup_cache'));
    }
    
    /**
     * Setup cache groups
     */
    public function setup_cache() {
        wp_cache_add_non_persistent_groups(array(self::CACHE_GROUP));
    }
    
    /**
     * Detect token from contract address
     *
     * @param string $contract_address Token contract/mint address
     * @param string $network Network (ethereum, solana-mainnet, auto)
     * @return array|WP_Error Token details or error
     */
    public function detect_token($contract_address, $network = 'auto') {
        // Sanitize input
        $contract_address = sanitize_text_field(trim($contract_address));
        
        if (empty($contract_address)) {
            return new WP_Error('empty_address', __('Contract address is required', 'x402-paywall'));
        }
        
        // Check cache first
        $cache_key = md5($contract_address . $network);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (false !== $cached) {
            return $cached;
        }
        
        // Auto-detect network if needed
        if ($network === 'auto') {
            $network = $this->detect_network($contract_address);
            if ($network === 'unknown') {
                return new WP_Error('unknown_network', __('Could not determine network from address format', 'x402-paywall'));
            }
        }
        
        // Validate address format
        if (!$this->validate_address($contract_address, $network)) {
            return new WP_Error('invalid_address', __('Invalid contract address format for selected network', 'x402-paywall'));
        }
        
        // Detect based on network
        $token_data = null;
        if (strpos($network, 'solana') !== false) {
            $token_data = $this->detect_spl_token($contract_address, $network);
        } else {
            // EVM networks
            $token_data = $this->detect_erc20_token($contract_address, $network);
        }
        
        if (is_wp_error($token_data)) {
            return $token_data;
        }
        
        // Cache result
        wp_cache_set($cache_key, $token_data, self::CACHE_GROUP, self::CACHE_DURATION);
        
        // Fire action for logging/tracking
        do_action('x402_token_detected', $token_data, $contract_address, $network);
        
        return $token_data;
    }
    
    /**
     * Detect SPL token details from Solana blockchain
     *
     * @param string $mint_address Token mint address
     * @param string $network Solana network
     * @return array|WP_Error Token details
     */
    private function detect_spl_token($mint_address, $network) {
        $rpc_url = $this->get_solana_rpc_url($network);
        
        // Get token metadata from chain
        $metadata = $this->fetch_spl_metadata($mint_address, $rpc_url);
        
        if (is_wp_error($metadata)) {
            return $metadata;
        }
        
        // Try to get additional metadata from token registry APIs
        $registry_data = $this->fetch_token_registry_data($mint_address, $network);
        
        return array(
            'name' => $registry_data['name'] ?? 'Unknown Token',
            'symbol' => $registry_data['symbol'] ?? 'UNKNOWN',
            'decimals' => $metadata['decimals'] ?? 9,
            'mint_address' => $mint_address,
            'contract_address' => $mint_address, // Alias for compatibility
            'network' => $network,
            'type' => 'spl-token',
            'icon' => $registry_data['logoURI'] ?? '',
            'verified' => $registry_data['verified'] ?? false,
            'coingecko_id' => $registry_data['coingecko_id'] ?? null,
        );
    }
    
    /**
     * Detect ERC20 token details from EVM blockchain
     *
     * @param string $contract_address Token contract address
     * @param string $network EVM network
     * @return array|WP_Error Token details
     */
    private function detect_erc20_token($contract_address, $network) {
        $rpc_url = $this->get_evm_rpc_url($network);
        
        // Get token details from contract
        $metadata = $this->fetch_erc20_metadata($contract_address, $rpc_url);
        
        if (is_wp_error($metadata)) {
            return $metadata;
        }
        
        // Try to get additional metadata from registries
        $registry_data = $this->fetch_token_registry_data($contract_address, $network);
        
        return array(
            'name' => $registry_data['name'] ?? $metadata['name'] ?? 'Unknown Token',
            'symbol' => $registry_data['symbol'] ?? $metadata['symbol'] ?? 'UNKNOWN',
            'decimals' => $metadata['decimals'] ?? 18,
            'contract_address' => $contract_address,
            'mint_address' => $contract_address, // Alias for compatibility
            'network' => $network,
            'type' => 'erc20',
            'icon' => $registry_data['logoURI'] ?? '',
            'verified' => $registry_data['verified'] ?? false,
            'coingecko_id' => $registry_data['coingecko_id'] ?? null,
        );
    }
    
    /**
     * Fetch SPL token metadata from Solana
     *
     * @param string $mint_address Mint address
     * @param string $rpc_url RPC endpoint
     * @return array|WP_Error Metadata
     */
    private function fetch_spl_metadata($mint_address, $rpc_url) {
        // Get mint account info
        $request = array(
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getAccountInfo',
            'params' => array(
                $mint_address,
                array('encoding' => 'jsonParsed'),
            ),
        );
        
        $response = wp_remote_post($rpc_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($request),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('rpc_error', __('Failed to connect to Solana RPC', 'x402-paywall'));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['result']['value']) || $body['result']['value'] === null) {
            return new WP_Error('token_not_found', __('Token not found on blockchain', 'x402-paywall'));
        }
        
        $parsed = $body['result']['value']['data']['parsed'] ?? null;
        
        if (!$parsed || $parsed['type'] !== 'mint') {
            return new WP_Error('invalid_mint', __('Not a valid SPL token mint', 'x402-paywall'));
        }
        
        return array(
            'decimals' => $parsed['info']['decimals'] ?? 9,
            'supply' => $parsed['info']['supply'] ?? '0',
            'isInitialized' => $parsed['info']['isInitialized'] ?? false,
        );
    }
    
    /**
     * Fetch ERC20 token metadata from EVM blockchain
     *
     * @param string $contract_address Contract address
     * @param string $rpc_url RPC endpoint
     * @return array|WP_Error Metadata
     */
    private function fetch_erc20_metadata($contract_address, $rpc_url) {
        $metadata = array();
        
        // Call name() function
        $name = $this->call_erc20_function($contract_address, 'name', $rpc_url);
        if (!is_wp_error($name)) {
            $metadata['name'] = $name;
        }
        
        // Call symbol() function
        $symbol = $this->call_erc20_function($contract_address, 'symbol', $rpc_url);
        if (!is_wp_error($symbol)) {
            $metadata['symbol'] = $symbol;
        }
        
        // Call decimals() function
        $decimals = $this->call_erc20_function($contract_address, 'decimals', $rpc_url);
        if (!is_wp_error($decimals)) {
            $metadata['decimals'] = hexdec($decimals);
        }
        
        if (empty($metadata)) {
            return new WP_Error('no_metadata', __('Could not fetch token metadata from contract', 'x402-paywall'));
        }
        
        return $metadata;
    }
    
    /**
     * Call ERC20 contract function
     *
     * @param string $contract_address Contract address
     * @param string $function Function name (name, symbol, decimals)
     * @param string $rpc_url RPC endpoint
     * @return string|WP_Error Result
     */
    private function call_erc20_function($contract_address, $function, $rpc_url) {
        $function_signatures = array(
            'name' => '0x06fdde03',
            'symbol' => '0x95d89b41',
            'decimals' => '0x313ce567',
        );
        
        $request = array(
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'eth_call',
            'params' => array(
                array(
                    'to' => $contract_address,
                    'data' => $function_signatures[$function],
                ),
                'latest',
            ),
        );
        
        $response = wp_remote_post($rpc_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($request),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $result = $body['result'] ?? null;
        
        if (!$result || $result === '0x') {
            return new WP_Error('call_failed', sprintf(__('Failed to call %s()', 'x402-paywall'), $function));
        }
        
        // Decode result based on function
        if ($function === 'decimals') {
            return $result;
        } else {
            // Decode string result
            return $this->decode_string_result($result);
        }
    }
    
    /**
     * Decode string result from contract call
     *
     * @param string $hex Hex string
     * @return string Decoded string
     */
    private function decode_string_result($hex) {
        $hex = str_replace('0x', '', $hex);
        
        // Skip the first 64 characters (offset pointer)
        // Next 64 characters contain the length
        $length = hexdec(substr($hex, 64, 64));
        
        // Get the actual data
        $data = substr($hex, 128, $length * 2);
        
        return hex2bin($data);
    }
    
    /**
     * Fetch token data from registry APIs
     *
     * @param string $address Token address
     * @param string $network Network
     * @return array Registry data
     */
    private function fetch_token_registry_data($address, $network = 'solana-mainnet') {
        // Try Jupiter token list (for Solana)
        if (strpos($network, 'solana') !== false) {
            $jupiter_data = $this->fetch_jupiter_token($address);
            if (!is_wp_error($jupiter_data)) {
                return $jupiter_data;
            }
        }
        
        // Allow filtering to add custom registry lookups
        return apply_filters('x402_token_registry_data', array(), $address, $network);
    }
    
    /**
     * Fetch token from Jupiter token list (Solana)
     *
     * @param string $mint_address Mint address
     * @return array|WP_Error Token data
     */
    private function fetch_jupiter_token($mint_address) {
        $api_url = 'https://token.jup.ag/strict';
        
        $response = wp_remote_get($api_url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $tokens = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!is_array($tokens)) {
            return new WP_Error('invalid_response', __('Invalid response from Jupiter API', 'x402-paywall'));
        }
        
        foreach ($tokens as $token) {
            if (isset($token['address']) && $token['address'] === $mint_address) {
                return array(
                    'name' => $token['name'] ?? '',
                    'symbol' => $token['symbol'] ?? '',
                    'decimals' => $token['decimals'] ?? 9,
                    'logoURI' => $token['logoURI'] ?? '',
                    'verified' => true,
                );
            }
        }
        
        return new WP_Error('not_in_registry', __('Token not found in Jupiter registry', 'x402-paywall'));
    }
    
    /**
     * Detect network from address format
     *
     * @param string $address Token address
     * @return string Network identifier
     */
    private function detect_network($address) {
        // Solana addresses are base58 encoded, 32-44 chars
        if (preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address)) {
            return 'solana-mainnet';
        }
        
        // Ethereum addresses are 0x prefixed hex, 42 chars
        if (preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            return 'ethereum-mainnet';
        }
        
        return 'unknown';
    }
    
    /**
     * Validate address format
     *
     * @param string $address Address to validate
     * @param string $network Network
     * @return bool Valid or not
     */
    private function validate_address($address, $network) {
        if (strpos($network, 'solana') !== false) {
            // Solana base58 check
            return preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address) === 1;
        } else {
            // EVM address check
            return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
        }
    }
    
    /**
     * Get Solana RPC URL for network
     *
     * @param string $network Network identifier
     * @return string RPC URL
     */
    private function get_solana_rpc_url($network) {
        $urls = apply_filters('x402_solana_rpc_urls', array(
            'solana-mainnet' => 'https://api.mainnet-beta.solana.com',
            'solana-devnet' => 'https://api.devnet.solana.com',
            'solana-testnet' => 'https://api.testnet.solana.com',
        ));
        
        return $urls[$network] ?? $urls['solana-mainnet'];
    }
    
    /**
     * Get EVM RPC URL for network
     *
     * @param string $network Network identifier
     * @return string RPC URL
     */
    private function get_evm_rpc_url($network) {
        $urls = apply_filters('x402_evm_rpc_urls', array(
            'ethereum-mainnet' => 'https://eth.llamarpc.com',
            'ethereum-sepolia' => 'https://rpc.sepolia.org',
            'polygon-mainnet' => 'https://polygon-rpc.com',
            'base-mainnet' => 'https://mainnet.base.org',
            'arbitrum-mainnet' => 'https://arb1.arbitrum.io/rpc',
            'optimism-mainnet' => 'https://mainnet.optimism.io',
        ));
        
        return $urls[$network] ?? $urls['ethereum-mainnet'];
    }
}
