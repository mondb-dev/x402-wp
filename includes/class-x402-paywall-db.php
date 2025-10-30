<?php
/**
 * Database helper class
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database operations class
 */
class X402_Paywall_DB {
    
    /**
     * Get user payment profile
     *
     * @param int $user_id WordPress user ID
     * @return object|null User profile data
     */
    public static function get_user_profile($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_user_profiles';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Save or update user payment profile
     *
     * @param int $user_id WordPress user ID
     * @param string|null $evm_address EVM address
     * @param string|null $spl_address SPL address
     * @return bool Success status
     */
    public static function save_user_profile($user_id, $evm_address = null, $spl_address = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_user_profiles';
        
        $existing = self::get_user_profile($user_id);
        
        $data = array(
            'user_id' => $user_id,
            'evm_address' => $evm_address,
            'spl_address' => $spl_address,
        );
        
        if ($existing) {
            return $wpdb->update(
                $table_name,
                $data,
                array('user_id' => $user_id),
                array('%d', '%s', '%s'),
                array('%d')
            ) !== false;
        } else {
            return $wpdb->insert(
                $table_name,
                $data,
                array('%d', '%s', '%s')
            ) !== false;
        }
    }
    
    /**
     * Log a payment attempt
     *
     * @param array $payment_data Payment data
     * @return int|false Insert ID or false on failure
     */
    public static function log_payment($payment_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_payment_logs';
        
        $raw_address = isset($payment_data['user_address']) ? trim((string) $payment_data['user_address']) : null;
        $normalized_address = isset($payment_data['normalized_address']) ? trim((string) $payment_data['normalized_address']) : null;

        if ($normalized_address === null || $normalized_address === '') {
            $normalized_address = self::normalize_address_for_lookup($raw_address);
        }

        if ($raw_address === null || $raw_address === '') {
            $raw_address = $normalized_address;
        }

        $payer_identifier = isset($payment_data['payer_identifier']) ? trim((string) $payment_data['payer_identifier']) : null;

        if ($payer_identifier === null || $payer_identifier === '') {
            $payer_identifier = $normalized_address;
        }

        $data = array(
            'post_id' => (int) $payment_data['post_id'],
            'user_address' => $raw_address,
            'normalized_address' => $normalized_address,
            'amount' => $payment_data['amount'],
            'token_address' => $payment_data['token_address'],
            'network' => $payment_data['network'],
            'transaction_hash' => $payment_data['transaction_hash'] ?? null,
            'payer_identifier' => $payer_identifier,
            'settlement_proof' => $payment_data['settlement_proof'] ?? null,
            'payment_status' => $payment_data['payment_status'] ?? 'pending',
            'facilitator_signature' => $payment_data['facilitator_signature'] ?? null,
            'facilitator_reference' => $payment_data['facilitator_reference'] ?? null,
        );

        $result = $wpdb->insert(
            $table_name,
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update payment status
     *
     * @param int $payment_id Payment log ID
     * @param string $status New status
     * @param string|null $transaction_hash Transaction hash
     * @return bool Success status
     */
    public static function update_payment_status($payment_id, $status, $transaction_hash = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_payment_logs';
        
        $data = array('payment_status' => $status);
        if ($transaction_hash !== null) {
            $data['transaction_hash'] = $transaction_hash;
        }
        
        return $wpdb->update(
            $table_name,
            $data,
            array('id' => $payment_id),
            array('%s', '%s'),
            array('%d')
        ) !== false;
    }
    
    /**
     * Get payment logs for a post
     *
     * @param int $post_id Post ID
     * @param int $limit Maximum number of records
     * @return array Payment logs
     */
    public static function get_payment_logs($post_id, $limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_payment_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d ORDER BY created_at DESC LIMIT %d",
            $post_id,
            $limit
        ));
    }
    
    /**
     * Check if user has paid for post
     *
     * @param int $post_id Post ID
     * @param string $user_address User's wallet address
     * @return bool Whether payment exists and is successful
     */
    public static function has_user_paid($post_id, $user_address) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'x402_payment_logs';
        
        $normalized_address = self::normalize_address_for_lookup($user_address);

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
            WHERE post_id = %d
            AND (normalized_address = %s OR payer_identifier = %s OR user_address = %s)
            AND payment_status = 'verified'",
            $post_id,
            $normalized_address,
            $normalized_address,
            $normalized_address
        ));

        return $result > 0;
    }

    /**
     * Normalize wallet address for consistent lookups
     *
     * @param string|null $address Wallet address
     * @return string
     */
    private static function normalize_address_for_lookup($address) {
        if (!is_string($address)) {
            return '';
        }

        $trimmed = trim($address);

        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, '0x')) {
            return strtolower($trimmed);
        }

        return $trimmed;
    }
}
