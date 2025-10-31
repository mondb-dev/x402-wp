<?php
/**
 * Wallet Display Template
 *
 * This template can be overridden by copying it to:
 * yourtheme/x402-paywall/wallet-display.php
 *
 * @package X402_Paywall
 * @var int $user_id User ID
 * @var array $wallet_data Wallet data
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$classes = X402_Paywall_Template_Loader::get_template_classes('wallet-display');
?>

<div class="<?php echo esc_attr($classes); ?>">
    <?php do_action('x402_before_wallet_display'); ?>
    
    <div class="x402-wallet-header">
        <h3><?php esc_html_e('Payment Wallets', 'x402-paywall'); ?></h3>
        <p class="description"><?php esc_html_e('Configure your wallet addresses to receive payments', 'x402-paywall'); ?></p>
    </div>
    
    <div class="x402-wallet-addresses">
        <div class="x402-wallet-field">
            <label for="x402_evm_address">
                <strong><?php esc_html_e('EVM Address (Ethereum, Base, Polygon, etc.)', 'x402-paywall'); ?></strong>
            </label>
            <input 
                type="text" 
                id="x402_evm_address" 
                name="x402_evm_address" 
                value="<?php echo esc_attr($wallet_data['evm_address'] ?? ''); ?>" 
                class="regular-text x402-address-input"
                placeholder="0x..."
            />
            <p class="description">
                <?php esc_html_e('Your EVM-compatible wallet address (starts with 0x)', 'x402-paywall'); ?>
            </p>
        </div>
        
        <div class="x402-wallet-field">
            <label for="x402_spl_address">
                <strong><?php esc_html_e('SPL Address (Solana)', 'x402-paywall'); ?></strong>
            </label>
            <input 
                type="text" 
                id="x402_spl_address" 
                name="x402_spl_address" 
                value="<?php echo esc_attr($wallet_data['spl_address'] ?? ''); ?>" 
                class="regular-text x402-address-input"
                placeholder="Base58 address..."
            />
            <p class="description">
                <?php esc_html_e('Your Solana wallet address (Base58 format)', 'x402-paywall'); ?>
            </p>
        </div>
    </div>
    
    <div class="x402-wallet-actions">
        <button type="button" class="button button-primary x402-save-wallet">
            <?php esc_html_e('Save Wallet Addresses', 'x402-paywall'); ?>
        </button>
        
        <span class="x402-wallet-status" style="display:none;"></span>
    </div>
    
    <?php do_action('x402_after_wallet_display'); ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('.x402-save-wallet').on('click', function() {
        var $button = $(this);
        var $status = $('.x402-wallet-status');
        var userId = <?php echo absint($user_id); ?>;
        
        $button.prop('disabled', true);
        $status.hide();
        
        $.ajax({
            url: '<?php echo esc_url(rest_url('x402-paywall/v1/wallet/' . $user_id)); ?>',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>');
            },
            data: {
                evm_address: $('#x402_evm_address').val(),
                spl_address: $('#x402_spl_address').val()
            },
            success: function(response) {
                $status.text('<?php esc_html_e('Wallet addresses saved successfully', 'x402-paywall'); ?>')
                    .css('color', 'green')
                    .show();
                setTimeout(function() {
                    $status.fadeOut();
                }, 3000);
            },
            error: function(xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message 
                    ? xhr.responseJSON.message 
                    : '<?php esc_html_e('Failed to save wallet addresses', 'x402-paywall'); ?>';
                $status.text(message)
                    .css('color', 'red')
                    .show();
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
});
</script>
