<?php
/**
 * Centralized hook registrations for the X402 paywall plugin.
 *
 * @package X402_Paywall
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook manager singleton.
 */
class X402_Paywall_Hooks {

    /**
     * Singleton instance.
     *
     * @var X402_Paywall_Hooks|null
     */
    private static $instance = null;

    /**
     * Retrieve singleton instance.
     *
     * @return X402_Paywall_Hooks
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
        add_filter('x402_paywall_template_classes', array($this, 'ensure_default_template_classes'), 10, 2);
        add_action('x402_paywall_message_body', array($this, 'render_default_protocol_notice'), 5, 1);
        add_action('x402_after_wallet_display', array($this, 'render_default_wallet_notice'));
        add_filter('x402_supported_networks', array($this, 'filter_supported_networks'));
        add_filter('x402_supported_tokens', array($this, 'filter_supported_tokens'));
    }

    /**
     * Guarantee baseline CSS classes for templates and expose filter.
     *
     * @param array  $classes  Current classes.
     * @param string $template Template slug.
     *
     * @return array
     */
    public function ensure_default_template_classes($classes, $template) {
        $base_classes = array(
            'x402-paywall-template',
            'x402-template-' . sanitize_html_class(str_replace('_', '-', $template)),
        );

        $merged = array_unique(array_merge($base_classes, (array) $classes));

        return array_values(array_filter($merged));
    }

    /**
     * Render a default protocol notice within the paywall message body.
     *
     * @param int $post_id Current post ID.
     */
    public function render_default_protocol_notice($post_id) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        echo '<p class="x402-paywall-notice">' .
            esc_html__(
                'Use an X402 compatible wallet to submit the required payment and unlock this content.',
                'x402-paywall'
            ) .
            '</p>';
    }

    /**
     * Render an informational message after the wallet form.
     */
    public function render_default_wallet_notice() {
        echo '<p class="description x402-wallet-help">' .
            esc_html__(
                'Wallet details are stored securely inside WordPress and used to construct payment requirements.',
                'x402-paywall'
            ) .
            '</p>';
    }

    /**
     * Provide the default list of supported networks.
     *
     * @param array $networks Existing networks supplied by other code.
     *
     * @return array
     */
    public function filter_supported_networks($networks) {
        $defaults = array(
            'ethereum-mainnet',
            'base-mainnet',
            'optimism-mainnet',
            'arbitrum-mainnet',
            'polygon-mainnet',
            'solana-mainnet',
            'solana-devnet',
        );

        return array_values(array_unique(array_merge($defaults, (array) $networks)));
    }

    /**
     * Provide default supported tokens for filters.
     *
     * @param array $tokens Existing tokens.
     *
     * @return array
     */
    public function filter_supported_tokens($tokens) {
        $defaults = array(
            'usdc' => array(
                'symbol' => 'USDC',
                'decimals' => 6,
            ),
        );

        return array_merge($defaults, (array) $tokens);
    }
}
