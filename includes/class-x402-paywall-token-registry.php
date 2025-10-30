<?php
/**
 * Token registry for supported networks
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides token configuration metadata for supported networks.
 */
class X402_Paywall_Token_Registry {
    /**
     * Get all supported token configurations.
     *
     * @return array
     */
    public static function get_all() {
        return array(
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
    }

    /**
     * Get the token configuration for a specific network/token pair.
     *
     * @param string $network_type Network type (e.g. evm, spl).
     * @param string $network Network identifier.
     * @param string $token_address Token contract/mint address.
     * @return array|null
     */
    public static function get_token($network_type, $network, $token_address) {
        $tokens = self::get_all();

        if (isset($tokens[$network_type][$network]['tokens'][$token_address])) {
            return $tokens[$network_type][$network]['tokens'][$token_address];
        }

        return null;
    }
}
