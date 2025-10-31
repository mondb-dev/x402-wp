<?php
/**
 * Paywall Message Template
 *
 * This template can be overridden by copying it to:
 * yourtheme/x402-paywall/paywall-message.php
 * or
 * yourtheme/x402/paywall-message.php
 *
 * @package X402_Paywall
 * @var int $post_id Post ID
 * @var array $config Paywall configuration
 * @var string $title Post title
 * @var string $excerpt Post excerpt
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$classes = X402_Paywall_Template_Loader::get_template_classes('paywall-message', array('x402-locked-content'));
?>

<div class="<?php echo esc_attr($classes); ?>">
    <?php do_action('x402_before_paywall_message', $post_id, $config); ?>
    
    <div class="x402-paywall-header">
        <?php do_action('x402_paywall_message_header', $post_id); ?>
        
        <div class="x402-paywall-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C10.0222 2 8.08879 2.58649 6.4443 3.6853C4.79981 4.78412 3.51809 6.3459 2.76121 8.17317C2.00433 10.0004 1.8063 12.0111 2.19215 13.9509C2.578 15.8907 3.53041 17.6725 4.92894 19.0711C6.32746 20.4696 8.10929 21.422 10.0491 21.8079C11.9889 22.1937 13.9996 21.9957 15.8268 21.2388C17.6541 20.4819 19.2159 19.2002 20.3147 17.5557C21.4135 15.9112 22 13.9778 22 12C22 9.34784 20.9464 6.8043 19.0711 4.92893C17.1957 3.05357 14.6522 2 12 2ZM12 20C10.4178 20 8.87104 19.5308 7.55544 18.6518C6.23985 17.7727 5.21447 16.5233 4.60897 15.0615C4.00347 13.5997 3.84504 11.9911 4.15372 10.4393C4.4624 8.88743 5.22433 7.46197 6.34315 6.34315C7.46197 5.22433 8.88743 4.4624 10.4393 4.15372C11.9911 3.84504 13.5997 4.00346 15.0615 4.60896C16.5233 5.21447 17.7727 6.23984 18.6518 7.55544C19.5308 8.87103 20 10.4177 20 12C20 14.1217 19.1571 16.1566 17.6569 17.6569C16.1566 19.1571 14.1217 20 12 20Z" fill="currentColor"/>
                <path d="M12 7C11.7348 7 11.4804 7.10536 11.2929 7.29289C11.1054 7.48043 11 7.73478 11 8V12C11 12.2652 11.1054 12.5196 11.2929 12.7071C11.4804 12.8946 11.7348 13 12 13C12.2652 13 12.5196 12.8946 12.7071 12.7071C12.8946 12.5196 13 12.2652 13 12V8C13 7.73478 12.8946 7.48043 12.7071 7.29289C12.5196 7.10536 12.2652 7 12 7ZM12 15C11.7033 15 11.4133 15.088 11.1666 15.2528C10.92 15.4176 10.7277 15.6519 10.6142 15.926C10.5006 16.2001 10.4709 16.5017 10.5288 16.7926C10.5867 17.0836 10.7296 17.3509 10.9393 17.5607C11.1491 17.7704 11.4164 17.9133 11.7074 17.9712C11.9983 18.0291 12.2999 17.9994 12.574 17.8858C12.8481 17.7723 13.0824 17.58 13.2472 17.3334C13.412 17.0867 13.5 16.7967 13.5 16.5C13.5 16.1022 13.342 15.7206 13.0607 15.4393C12.7794 15.158 12.3978 15 12 15Z" fill="currentColor"/>
            </svg>
        </div>
        
        <h3 class="x402-paywall-title">
            <?php echo esc_html(apply_filters('x402_paywall_title', __('Premium Content', 'x402-paywall'), $post_id)); ?>
        </h3>
    </div>
    
    <div class="x402-paywall-body">
        <?php do_action('x402_paywall_message_body', $post_id); ?>
        
        <div class="x402-paywall-description">
            <p><?php echo esc_html(apply_filters('x402_paywall_description', __('This content is protected. Make a payment to access it.', 'x402-paywall'), $post_id)); ?></p>
        </div>
        
        <?php if (!empty($excerpt)): ?>
            <div class="x402-paywall-excerpt">
                <h4><?php esc_html_e('Preview', 'x402-paywall'); ?></h4>
                <?php echo wp_kses_post(wpautop($excerpt)); ?>
            </div>
        <?php endif; ?>
        
        <div class="x402-paywall-payment-info">
            <div class="x402-payment-amount">
                <span class="x402-label"><?php esc_html_e('Amount:', 'x402-paywall'); ?></span>
                <span class="x402-value">
                    <?php 
                    $finance = X402_Paywall_Finance::get_instance();
                    $decimals = absint($config['token_decimals'] ?? 6);
                    $amount_display = $finance->atomic_to_decimal($config['amount'], $decimals);
                    echo esc_html($finance->format_amount_display($amount_display, $decimals));
                    ?>
                    <?php echo esc_html($config['token_name'] ?? 'tokens'); ?>
                </span>
            </div>
            
            <div class="x402-payment-network">
                <span class="x402-label"><?php esc_html_e('Network:', 'x402-paywall'); ?></span>
                <span class="x402-value"><?php echo esc_html(ucwords(str_replace('-', ' ', $config['network']))); ?></span>
            </div>
        </div>
        
        <div class="x402-paywall-instructions">
            <p><?php esc_html_e('To access this content:', 'x402-paywall'); ?></p>
            <ol>
                <li><?php esc_html_e('Connect your Web3 wallet', 'x402-paywall'); ?></li>
                <li><?php esc_html_e('Approve the payment transaction', 'x402-paywall'); ?></li>
                <li><?php esc_html_e('Content will be unlocked immediately after verification', 'x402-paywall'); ?></li>
            </ol>
        </div>
    </div>
    
    <div class="x402-paywall-footer">
        <?php do_action('x402_paywall_message_footer', $post_id); ?>
        
        <button type="button" class="x402-paywall-connect-button" data-post-id="<?php echo esc_attr($post_id); ?>">
            <?php esc_html_e('Connect Wallet & Pay', 'x402-paywall'); ?>
        </button>
        
        <p class="x402-paywall-security-note">
            <small><?php esc_html_e('Payments are processed securely using the X402 protocol', 'x402-paywall'); ?></small>
        </p>
    </div>
    
    <?php do_action('x402_after_paywall_message', $post_id, $config); ?>
</div>
