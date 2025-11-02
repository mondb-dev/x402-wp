<?php
/**
 * Financial utilities for the X402 paywall plugin.
 *
 * @package X402_Paywall
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Finance helper singleton.
 */
class X402_Paywall_Finance {

    /**
     * Singleton instance.
     *
     * @var X402_Paywall_Finance|null
     */
    private static $instance = null;

    /**
     * Retrieve singleton instance.
     *
     * @return X402_Paywall_Finance
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
    private function __construct() {}

    /**
     * Convert atomic amount to decimal representation.
     *
     * @param string|int $amount   Atomic amount.
     * @param int        $decimals Number of decimals for the asset.
     *
     * @return string
     */
    public function atomic_to_decimal($amount, $decimals) {
        $amount   = preg_replace('/[^0-9]/', '', (string) $amount);
        $decimals = max(0, (int) $decimals);

        if ('' === $amount) {
            return '0';
        }

        if (0 === $decimals) {
            return ltrim($amount, '0') ?: '0';
        }

        if (function_exists('bcdiv')) {
            $divisor = bcpow('10', (string) $decimals);

            return rtrim(bcdiv($amount, $divisor, $decimals), '0.');
        }

        $length = strlen($amount);

        if ($length <= $decimals) {
            $amount = str_pad($amount, $decimals + 1, '0', STR_PAD_LEFT);
        }

        $integer = substr($amount, 0, -$decimals);
        $fraction = substr($amount, -$decimals);
        $fraction = rtrim($fraction, '0');

        $integer = $integer === '' ? '0' : ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;

        return $fraction === '' ? $integer : $integer . '.' . $fraction;
    }

    /**
     * Convert decimal amount to atomic representation.
     *
     * @param string $amount   Decimal amount.
     * @param int    $decimals Number of decimals for the asset.
     *
     * @return string
     */
    public function decimal_to_atomic($amount, $decimals) {
        $amount   = trim((string) $amount);
        $decimals = max(0, (int) $decimals);

        if ('' === $amount) {
            return '0';
        }

        if (function_exists('bcmul')) {
            $factor = bcpow('10', (string) $decimals);

            return preg_replace('/[^0-9]/', '', bcmul($amount, $factor, $decimals));
        }

        if (strpos($amount, '.') !== false) {
            list($int, $fraction) = explode('.', $amount, 2);
            $fraction = substr($fraction, 0, $decimals);
            $fraction = str_pad($fraction, $decimals, '0');

            return preg_replace('/[^0-9]/', '', $int . $fraction);
        }

        return preg_replace('/[^0-9]/', '', $amount . str_repeat('0', $decimals));
    }

    /**
     * Format amount for display.
     *
     * @param string $decimal_amount Decimal amount.
     * @param int    $decimals       Token decimals.
     *
     * @return string
     */
    public function format_amount_display($decimal_amount, $decimals) {
        $decimals = max(0, (int) $decimals);
        $value    = (float) $decimal_amount;

        $formatted = number_format_i18n($value, min($decimals, 6));

        return apply_filters('x402_formatted_amount', $formatted, $decimal_amount, $decimals);
    }

    /**
     * Calculate transaction fee.
     *
     * @param string $amount Decimal amount.
     * @param float  $rate   Fee rate (0.0 - 1.0).
     *
     * @return string
     */
    public function calculate_fee($amount, $rate) {
        $amount = (float) $amount;
        $rate   = max(0, min(1, (float) $rate));

        return number_format_i18n($amount * $rate, 6);
    }

    /**
     * Record a financial audit event.
     *
     * @param array $data Event data.
     *
     * @return bool
     */
    public function record_audit_event($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'x402_financial_audit';

        $defaults = array(
            'id'                 => wp_generate_uuid4(),
            'timestamp'          => current_time('mysql'),
            'post_id'            => 0,
            'user_id'            => 0,
            'user_address'       => '',
            'recipient_address'  => '',
            'amount'             => '0',
            'token_address'      => '',
            'network'            => '',
            'transaction_hash'   => null,
            'status'             => 'pending',
            'ip_address'         => '',
            'user_agent'         => '',
            'metadata'           => null,
        );

        $data = wp_parse_args($data, $defaults);

        return (bool) $wpdb->insert(
            $table,
            array(
                'id'                => sanitize_text_field($data['id']),
                'timestamp'         => sanitize_text_field($data['timestamp']),
                'post_id'           => absint($data['post_id']),
                'user_id'           => absint($data['user_id']),
                'user_address'      => sanitize_text_field($data['user_address']),
                'recipient_address' => sanitize_text_field($data['recipient_address']),
                'amount'            => $this->sanitize_decimal_amount($data['amount']),
                'token_address'     => sanitize_text_field($data['token_address']),
                'network'           => sanitize_text_field($data['network']),
                'transaction_hash'  => $data['transaction_hash'] ? sanitize_text_field($data['transaction_hash']) : null,
                'status'            => sanitize_key($data['status']),
                'ip_address'        => sanitize_text_field($data['ip_address']),
                'user_agent'        => sanitize_text_field($data['user_agent']),
                'metadata'          => $data['metadata'] ? wp_json_encode($data['metadata']) : null,
            ),
            array('%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Retrieve financial summary statistics.
     *
     * @param array $args Optional filters.
     *
     * @return array
     */
    public function get_financial_summary($args = array()) {
        global $wpdb;

        $defaults = array(
            'status' => 'verified',
            'limit'  => 100,
        );

        $args = wp_parse_args($args, $defaults);

        $logs_table = $wpdb->prefix . 'x402_payment_logs';

        $where  = array();
        $params = array();

        if (!empty($args['status'])) {
            $where[]  = 'payment_status = %s';
            $params[] = sanitize_key($args['status']);
        }

        $sql = "SELECT post_id, amount, network, payment_status FROM {$logs_table}";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY created_at DESC';

        if (!empty($args['limit'])) {
            $sql .= ' LIMIT %d';
            $params[] = absint($args['limit']);
        }

        $prepared = $params ? $wpdb->prepare($sql, $params) : $sql;
        $rows     = $wpdb->get_results($prepared, ARRAY_A);

        $totals = array(
            'count'             => 0,
            'amount_atomic'     => '0',
            'amount_decimal'    => '0',
            'by_network'        => array(),
            'by_status'         => array(),
        );

        $decimal_totals = '0';
        $decimals_cache = array();

        foreach ($rows as $row) {
            $post_id = (int) $row['post_id'];
            $totals['count']++;
            $totals['amount_atomic'] = $this->add_atomic($totals['amount_atomic'], $row['amount']);

            if (!isset($totals['by_status'][$row['payment_status']])) {
                $totals['by_status'][$row['payment_status']] = 0;
            }

            $totals['by_status'][$row['payment_status']]++;

            if (!isset($totals['by_network'][$row['network']])) {
                $totals['by_network'][$row['network']] = array(
                    'count'         => 0,
                    'amount_atomic' => '0',
                    'amount_decimal'=> '0',
                );
            }

            $totals['by_network'][$row['network']]['count']++;
            $totals['by_network'][$row['network']]['amount_atomic'] = $this->add_atomic(
                $totals['by_network'][$row['network']]['amount_atomic'],
                $row['amount']
            );

            if (!isset($decimals_cache[$post_id])) {
                $decimals_cache[$post_id] = (int) get_post_meta($post_id, '_x402_paywall_token_decimals', true);
            }

            $decimal_amount = $this->atomic_to_decimal($row['amount'], $decimals_cache[$post_id]);
            $decimal_totals = $this->add_decimal($decimal_totals, $decimal_amount);
            $totals['by_network'][$row['network']]['amount_decimal'] = $this->add_decimal(
                $totals['by_network'][$row['network']]['amount_decimal'],
                $decimal_amount
            );
        }

        $totals['amount_decimal'] = $decimal_totals;

        /**
         * Allow plugins/themes to filter the generated summary.
         *
         * @param array $totals Summary data.
         * @param array $args   Query arguments.
         */
        return apply_filters('x402_financial_summary', $totals, $args);
    }

    /**
     * Add two atomic numbers represented as strings.
     *
     * @param string $current Current atomic amount.
     * @param string $value   Amount to add.
     *
     * @return string
     */
    private function add_atomic($current, $value) {
        $current = preg_replace('/[^0-9]/', '', (string) $current);
        $value   = preg_replace('/[^0-9]/', '', (string) $value);

        if (function_exists('bcadd')) {
            return bcadd($current, $value, 0);
        }

        $sum = (string) ((float) $current + (float) $value);

        return preg_replace('/[^0-9]/', '', $sum);
    }

    /**
     * Add decimal strings using BCMath when available.
     *
     * @param string $current Current decimal amount.
     * @param string $value   Value to add.
     *
     * @return string
     */
    private function add_decimal($current, $value) {
        $current = (string) $current;
        $value   = (string) $value;

        if (function_exists('bcadd')) {
            return rtrim(bcadd($current, $value, 18), '0.');
        }

        $sum = (float) $current + (float) $value;

        return (string) $sum;
    }

    /**
     * Sanitize decimal amount.
     *
     * @param string $amount Decimal amount.
     *
     * @return string
     */
    private function sanitize_decimal_amount($amount) {
        $amount = (string) $amount;

        if (!preg_match('/^\d+(?:\.\d+)?$/', $amount)) {
            $amount = preg_replace('/[^0-9\.]/', '', $amount);
        }

        return $amount === '' ? '0' : $amount;
    }
}
