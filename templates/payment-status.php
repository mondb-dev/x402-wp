<?php
/**
 * Payment Status Template
 *
 * This template can be overridden by copying it to:
 * yourtheme/x402-paywall/payment-status.php
 *
 * @package X402_Paywall
 * @var string $status Payment status (verified, pending, failed)
 * @var array $data Status data
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$classes = X402_Paywall_Template_Loader::get_template_classes('payment-status', array('x402-status-' . $status));
?>

<div class="<?php echo esc_attr($classes); ?>">
    <?php if ($status === 'verified'): ?>
        <div class="x402-status-success">
            <div class="x402-status-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10 17L5 12L6.41 10.59L10 14.17L17.59 6.58L19 8L10 17Z" fill="#10b981"/>
                </svg>
            </div>
            <h3><?php esc_html_e('Payment Verified', 'x402-paywall'); ?></h3>
            <p><?php esc_html_e('Your payment has been successfully verified. You now have access to this content.', 'x402-paywall'); ?></p>
            
            <?php if (!empty($data['transaction_hash'])): ?>
                <div class="x402-transaction-details">
                    <strong><?php esc_html_e('Transaction:', 'x402-paywall'); ?></strong>
                    <code><?php echo esc_html(substr($data['transaction_hash'], 0, 10) . '...' . substr($data['transaction_hash'], -8)); ?></code>
                </div>
            <?php endif; ?>
        </div>
        
    <?php elseif ($status === 'pending'): ?>
        <div class="x402-status-pending">
            <div class="x402-status-icon x402-spinner">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" stroke="#f59e0b" stroke-width="2" fill="none" opacity="0.25"/>
                    <path d="M12 2 A10 10 0 0 1 22 12" stroke="#f59e0b" stroke-width="2" fill="none" stroke-linecap="round"/>
                </svg>
            </div>
            <h3><?php esc_html_e('Payment Pending', 'x402-paywall'); ?></h3>
            <p><?php esc_html_e('Your payment is being processed. Please wait...', 'x402-paywall'); ?></p>
        </div>
        
    <?php else: ?>
        <div class="x402-status-error">
            <div class="x402-status-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="#ef4444"/>
                </svg>
            </div>
            <h3><?php esc_html_e('Payment Failed', 'x402-paywall'); ?></h3>
            <p>
                <?php 
                echo esc_html($data['message'] ?? __('Payment verification failed. Please try again.', 'x402-paywall')); 
                ?>
            </p>
            
            <?php if (!empty($data['error_code'])): ?>
                <p class="x402-error-code">
                    <small><?php echo esc_html(sprintf(__('Error code: %s', 'x402-paywall'), $data['error_code'])); ?></small>
                </p>
            <?php endif; ?>
            
            <button type="button" class="button x402-retry-payment">
                <?php esc_html_e('Try Again', 'x402-paywall'); ?>
            </button>
        </div>
    <?php endif; ?>
</div>

<style>
.x402-status-icon {
    text-align: center;
    margin-bottom: 1rem;
}

.x402-spinner svg {
    animation: x402-spin 1s linear infinite;
}

@keyframes x402-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.x402-payment-status {
    text-align: center;
    padding: 2rem;
    border-radius: 8px;
    margin: 2rem 0;
}

.x402-status-success {
    background-color: #f0fdf4;
    border: 2px solid #10b981;
}

.x402-status-pending {
    background-color: #fffbeb;
    border: 2px solid #f59e0b;
}

.x402-status-error {
    background-color: #fef2f2;
    border: 2px solid #ef4444;
}

.x402-transaction-details {
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.5);
    border-radius: 4px;
}

.x402-transaction-details code {
    font-family: monospace;
    font-size: 0.9em;
}
</style>
