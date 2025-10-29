<?php
/**
 * User profile payment addresses management
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Profile management class
 */
class X402_Paywall_Profile {
    
    /**
     * Initialize profile hooks
     */
    public function init() {
        // Only show for users who can create paywalls (author and above)
        add_action('show_user_profile', array($this, 'render_profile_fields'));
        add_action('edit_user_profile', array($this, 'render_profile_fields'));
        
        add_action('personal_options_update', array($this, 'save_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_profile_fields'));
    }
    
    /**
     * Check if user can manage paywalls
     *
     * @param int $user_id User ID
     * @return bool Can manage paywalls
     */
    private function can_manage_paywalls($user_id) {
        $user = get_userdata($user_id);
        return $user && (
            $user->has_cap('publish_posts') || 
            $user->has_cap('manage_options')
        );
    }
    
    /**
     * Render profile fields
     *
     * @param WP_User $user User object
     */
    public function render_profile_fields($user) {
        if (!$this->can_manage_paywalls($user->ID)) {
            return;
        }
        
        $profile = X402_Paywall_DB::get_user_profile($user->ID);
        $evm_address = $profile ? $profile->evm_address : '';
        $spl_address = $profile ? $profile->spl_address : '';
        
        wp_nonce_field('x402_paywall_profile_update', 'x402_paywall_profile_nonce');
        ?>
        
        <h2 id="x402-paywall-profile"><?php esc_html_e('X402 Paywall Payment Addresses', 'x402-paywall'); ?></h2>
        <p class="description">
            <?php esc_html_e('Configure your wallet addresses to receive payments for paywalled content.', 'x402-paywall'); ?>
        </p>
        
        <table class="form-table">
            <tr>
                <th><label for="x402_evm_address"><?php esc_html_e('EVM Address (Ethereum, Base, etc.)', 'x402-paywall'); ?></label></th>
                <td>
                    <input 
                        type="text" 
                        name="x402_evm_address" 
                        id="x402_evm_address" 
                        value="<?php echo esc_attr($evm_address); ?>" 
                        class="regular-text" 
                        placeholder="0x..."
                    />
                    <p class="description">
                        <?php esc_html_e('Your Ethereum-compatible wallet address (starts with 0x). Used for EVM networks like Ethereum, Base, Optimism, Arbitrum, and Polygon.', 'x402-paywall'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th><label for="x402_spl_address"><?php esc_html_e('Solana Address (SPL)', 'x402-paywall'); ?></label></th>
                <td>
                    <input 
                        type="text" 
                        name="x402_spl_address" 
                        id="x402_spl_address" 
                        value="<?php echo esc_attr($spl_address); ?>" 
                        class="regular-text" 
                        placeholder="Your Solana address..."
                    />
                    <p class="description">
                        <?php esc_html_e('Your Solana wallet address. Used for Solana network payments.', 'x402-paywall'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <style>
            #x402-paywall-profile {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #c3c4c7;
            }
        </style>
        <?php
    }
    
    /**
     * Save profile fields
     *
     * @param int $user_id User ID
     */
    public function save_profile_fields($user_id) {
        // Check if user can manage paywalls
        if (!$this->can_manage_paywalls($user_id)) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['x402_paywall_profile_nonce']) || 
            !wp_verify_nonce($_POST['x402_paywall_profile_nonce'], 'x402_paywall_profile_update')) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        // Get and sanitize addresses
        $evm_address = isset($_POST['x402_evm_address']) ? sanitize_text_field($_POST['x402_evm_address']) : '';
        $spl_address = isset($_POST['x402_spl_address']) ? sanitize_text_field($_POST['x402_spl_address']) : '';
        
        // Validate addresses
        $errors = array();
        
        if (!empty($evm_address) && !X402_Paywall_Payment_Handler::validate_evm_address($evm_address)) {
            $errors[] = __('Invalid EVM address format. Must start with 0x followed by 40 hexadecimal characters.', 'x402-paywall');
        }
        
        if (!empty($spl_address) && !X402_Paywall_Payment_Handler::validate_spl_address($spl_address)) {
            $errors[] = __('Invalid Solana address format.', 'x402-paywall');
        }
        
        // Show errors if any
        if (!empty($errors)) {
            foreach ($errors as $error) {
                add_action('user_profile_update_errors', function($errors_obj) use ($error) {
                    $errors_obj->add('x402_paywall_address_error', $error);
                });
            }
            return;
        }
        
        // Save to database
        X402_Paywall_DB::save_user_profile(
            $user_id,
            !empty($evm_address) ? $evm_address : null,
            !empty($spl_address) ? $spl_address : null
        );
    }
}
