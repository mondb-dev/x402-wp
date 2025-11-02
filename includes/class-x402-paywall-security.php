<?php
/**
 * Security helpers for the X402 paywall plugin.
 *
 * @package X402_Paywall
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security utility singleton.
 */
class X402_Paywall_Security {

    /**
     * Singleton instance.
     *
     * @var X402_Paywall_Security|null
     */
    private static $instance = null;

    /**
     * Webhook timestamp tolerance (seconds).
     *
     * @var int
     */
    private $webhook_tolerance = 300;

    /**
     * Cached nonce action used for REST requests.
     *
     * @var string
     */
    private $rest_nonce_action = 'wp_rest';

    /**
     * Retrieve singleton instance.
     *
     * @return X402_Paywall_Security
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
        add_filter('rest_pre_serve_request', array($this, 'inject_security_headers'), 10, 4);
        add_filter('rest_url', array($this, 'enforce_https_rest_url'), 10, 2);
    }

    /**
     * Add security headers to REST responses.
     *
     * @param bool            $served  Whether the request has already been served.
     * @param WP_HTTP_Response $result  Result object.
     * @param WP_REST_Request  $request Current request.
     * @param WP_REST_Server   $server  Server instance.
     *
     * @return bool
     */
    public function inject_security_headers($served, $result, $request, $server) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: no-referrer-when-downgrade');
        }

        return $served;
    }

    /**
     * Force HTTPS REST URLs when the site supports it.
     *
     * @param string $url  REST URL.
     * @param string $path Requested path.
     *
     * @return string
     */
    public function enforce_https_rest_url($url, $path) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        if (is_ssl()) {
            return set_url_scheme($url, 'https');
        }

        return $url;
    }

    /**
     * Verify nonce supplied for REST requests.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool
     */
    public function verify_rest_request_nonce($request) {
        $nonce = $request->get_header('X-WP-Nonce');

        if (empty($nonce)) {
            $nonce = $request->get_param('_wpnonce');
        }

        if (empty($nonce)) {
            return false;
        }

        return (bool) wp_verify_nonce($nonce, $this->rest_nonce_action);
    }

    /**
     * Sanitize a set of headers.
     *
     * @param array $headers Raw headers.
     *
     * @return array
     */
    public function sanitize_headers($headers) {
        $sanitized = array();

        foreach ((array) $headers as $key => $value) {
            $normalized_key = sanitize_key($key);

            if (is_array($value)) {
                $sanitized[$normalized_key] = array_map('sanitize_text_field', $value);
            } else {
                $sanitized[$normalized_key] = sanitize_text_field((string) $value);
            }
        }

        return $sanitized;
    }

    /**
     * Verify webhook signature.
     *
     * @param string      $payload   Raw request payload.
     * @param string|null $signature Provided signature.
     * @param int|null    $timestamp Provided timestamp (unix epoch).
     *
     * @return bool
     */
    public function verify_webhook_signature($payload, $signature, $timestamp = null) {
        $secret = get_option('x402_paywall_webhook_secret');

        if (empty($secret)) {
            // If no secret is configured we consider the webhook public.
            return true;
        }

        if (!is_string($signature) || '' === trim($signature)) {
            return false;
        }

        if (null !== $timestamp) {
            $timestamp = absint($timestamp);

            if (0 === $timestamp || abs(time() - $timestamp) > $this->webhook_tolerance) {
                return false;
            }
        }

        $payload_to_sign = $payload;

        if (null !== $timestamp) {
            $payload_to_sign = $timestamp . '.' . $payload;
        }

        $expected_signature = hash_hmac('sha256', $payload_to_sign, $secret);

        return hash_equals($expected_signature, $signature);
    }
}
