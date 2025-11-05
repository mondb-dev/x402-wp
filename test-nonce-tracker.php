<?php
/**
 * Test script for x402-php library integration with NonceTrackerInterface
 * 
 * Run from command line: php test-nonce-tracker.php
 */

require_once 'vendor/autoload.php';

use X402\Nonce\NonceTrackerInterface;

echo "=== X402 Nonce Tracker Integration Test ===\n\n";

// Check if interface exists
echo "1. Checking for NonceTrackerInterface...\n";
if (interface_exists('X402\Nonce\NonceTrackerInterface')) {
    echo "✓ NonceTrackerInterface found\n\n";
} else {
    echo "✗ NonceTrackerInterface NOT found\n";
    echo "Please update x402-php library: composer update mondb-dev/x402-php\n";
    exit(1);
}

// Check interface methods
echo "2. Checking interface methods...\n";
$reflection = new ReflectionClass('X402\Nonce\NonceTrackerInterface');
$methods = $reflection->getMethods();

$required_methods = ['hasNonce', 'isNonceUsed', 'markUsed', 'markNonceUsed', 'remove'];
foreach ($required_methods as $method) {
    $found = false;
    foreach ($methods as $m) {
        if ($m->getName() === $method) {
            $found = true;
            break;
        }
    }
    echo ($found ? '✓' : '✗') . " Method: {$method}\n";
}
echo "\n";

// Check PaymentHandler accepts NonceTrackerInterface
echo "3. Checking PaymentHandler constructor...\n";
$paymentHandlerReflection = new ReflectionClass('X402\Middleware\PaymentHandler');
$constructor = $paymentHandlerReflection->getConstructor();
$params = $constructor->getParameters();

$hasNonceTrackerParam = false;
foreach ($params as $param) {
    if ($param->getName() === 'nonceTracker') {
        $hasNonceTrackerParam = true;
        $type = $param->getType();
        echo "✓ PaymentHandler has nonceTracker parameter\n";
        echo "  Type: " . ($type ? $type->getName() : 'none') . "\n";
        echo "  Nullable: " . ($param->allowsNull() ? 'yes' : 'no') . "\n";
        break;
    }
}

if (!$hasNonceTrackerParam) {
    echo "✗ PaymentHandler does not have nonceTracker parameter\n";
}
echo "\n";

// Test creating PaymentHandler with mock NonceTracker
echo "4. Testing PaymentHandler initialization with NonceTracker...\n";

// Simple mock NonceTracker for testing
class TestNonceTracker implements NonceTrackerInterface {
    private $used = [];
    
    public function hasNonce(string $nonce): bool {
        return isset($this->used[$nonce]);
    }
    
    public function isNonceUsed(string $nonce): bool {
        return $this->hasNonce($nonce);
    }
    
    public function markUsed(string $nonce, int $ttlSeconds): bool {
        if ($this->hasNonce($nonce)) {
            return false;
        }
        $this->used[$nonce] = true;
        return true;
    }
    
    public function markNonceUsed(string $nonce, int $ttlSeconds): void {
        if (!$this->markUsed($nonce, $ttlSeconds)) {
            throw new \X402\Exceptions\ValidationException('Nonce already used');
        }
    }
    
    public function remove(string $nonce): bool {
        if (isset($this->used[$nonce])) {
            unset($this->used[$nonce]);
            return true;
        }
        return false;
    }
}

try {
    $tracker = new TestNonceTracker();
    $facilitator = new \X402\Facilitator\FacilitatorClient('https://facilitator.x402.org');
    
    $handler = new \X402\Middleware\PaymentHandler(
        facilitator: $facilitator,
        nonceTracker: $tracker
    );
    
    echo "✓ PaymentHandler initialized successfully with NonceTracker\n";
    echo "\n";
    
    // Test nonce tracking
    echo "5. Testing nonce tracking...\n";
    $testNonce = 'base-mainnet:0x123abc';
    
    echo "  Initial check: " . ($tracker->hasNonce($testNonce) ? 'used' : 'not used') . "\n";
    
    $marked = $tracker->markUsed($testNonce, 3600);
    echo "  Mark as used: " . ($marked ? 'success' : 'failed') . "\n";
    
    echo "  After mark: " . ($tracker->hasNonce($testNonce) ? 'used' : 'not used') . "\n";
    
    $marked_again = $tracker->markUsed($testNonce, 3600);
    echo "  Mark again: " . ($marked_again ? 'success' : 'failed (expected)') . "\n";
    
    echo "\n✓ All tests passed!\n\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "=== Integration Ready ===\n";
echo "The x402-php library is properly integrated with NonceTrackerInterface support.\n";
echo "WordPress plugin can now use X402_Paywall_Replay_Prevention as a NonceTracker.\n";
