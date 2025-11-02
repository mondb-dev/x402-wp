<?php
/**
 * Basic integration test to ensure administrator settings feed the X402 client configuration.
 */

declare(strict_types=1);

// Bootstrap minimal WordPress-like environment for the client wrapper.
define('ABSPATH', __DIR__);
define('X402_PAYWALL_PLUGIN_DIR', dirname(__DIR__, 2) . '/');

// Option storage for the test environment.
$GLOBALS['x402_test_options'] = array(
    'x402_paywall_facilitator_url' => 'https://facilitator.test',
    'x402_paywall_auto_settle' => '0',
    'x402_paywall_valid_before_buffer' => '42',
    'x402_paywall_enable_evm' => '0',
    'x402_paywall_enable_spl' => '1',
);

function get_option($name, $default = false) {
    $options = $GLOBALS['x402_test_options'];
    return array_key_exists($name, $options) ? $options[$name] : $default;
}

function apply_filters($tag, $value) {
    return $value;
}

function do_action($tag, ...$args) {
    // No-op for the test environment.
}

function add_action($tag, $callback) {
    // No-op for the test environment.
}

function is_admin() {
    return false;
}

function absint($value) {
    return (int) abs((int) $value);
}

require_once dirname(__DIR__, 2) . '/includes/class-x402-paywall-x402-client.php';

$client = X402_Paywall_X402_Client::get_instance();
$config = $client->get_config();

$expected = array(
    'network' => 'mainnet',
    'api_endpoint' => 'https://facilitator.test',
    'facilitator_url' => 'https://facilitator.test',
    'auto_settle' => false,
    'valid_before_buffer' => 42,
    'enable_evm' => false,
    'enable_spl' => true,
    'timeout' => 30,
    'verify_ssl' => true,
);

$missing = array();
$wrong = array();

foreach ($expected as $key => $value) {
    if (!array_key_exists($key, $config)) {
        $missing[] = $key;
        continue;
    }

    if ($config[$key] !== $value) {
        $wrong[$key] = array('expected' => $value, 'actual' => $config[$key]);
    }
}

if ($missing || $wrong) {
    if ($missing) {
        fwrite(STDERR, 'Missing config keys: ' . implode(', ', $missing) . PHP_EOL);
    }
    if ($wrong) {
        foreach ($wrong as $key => $values) {
            fwrite(
                STDERR,
                sprintf(
                    'Value mismatch for %s. Expected %s but received %s%s',
                    $key,
                    var_export($values['expected'], true),
                    var_export($values['actual'], true),
                    PHP_EOL
                )
            );
        }
    }
    exit(1);
}

echo "X402 client configuration test passed." . PHP_EOL;
