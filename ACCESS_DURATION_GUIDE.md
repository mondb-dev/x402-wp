# Access Duration Implementation Guide

## Overview
The X402 Paywall plugin now supports configurable access duration per content. Authors can specify how long users can access content after payment, from 1 day to permanent (lifetime) access.

## Changes Made

### 1. Database Schema
Added `expires_at` column to `wp_x402_payment_logs` table:
```sql
ALTER TABLE wp_x402_payment_logs 
ADD COLUMN expires_at datetime DEFAULT NULL AFTER facilitator_message,
ADD INDEX idx_expires (expires_at);
```

- `NULL` = permanent access (no expiry)
- Non-NULL = specific expiration datetime

### 2. Admin UI (Meta Box)
Added "Access Duration" dropdown in post editor:
- Location: X402 Paywall Configuration meta box
- Default: `1_year` (current behavior maintained)
- Options:
  - 1 Day
  - 1 Week
  - 1 Month
  - 3 Months
  - 6 Months
  - **1 Year (Default)**
  - Permanent (Lifetime)

Stored in post meta: `_x402_paywall_access_duration`

### 3. Payment Logging
Updated `X402_Paywall_DB::log_payment()` to accept `expires_at` field.

### 4. Access Verification
Updated `X402_Paywall_DB::has_user_paid()` to check expiry:
```sql
AND (expires_at IS NULL OR expires_at > NOW())
```

### 5. Helper Function
Added `X402_Paywall_DB::calculate_access_expiry($post_id)`:
- Reads `_x402_paywall_access_duration` from post meta
- Returns MySQL datetime string or NULL for permanent
- Defaults to 1 year if not set

## Usage for Developers

### When Logging Payments
```php
// Calculate expiry based on post configuration
$expires_at = X402_Paywall_DB::calculate_access_expiry($post_id);

// Log payment with expiry
X402_Paywall_DB::log_payment(array(
    'post_id' => $post_id,
    'user_address' => $user_wallet,
    'amount' => $amount,
    'token_address' => $token,
    'network' => $network,
    'transaction_hash' => $tx_hash,
    'payment_status' => 'verified',
    'expires_at' => $expires_at, // NEW: Can be NULL for permanent
));
```

### Hook into Payment Verification
```php
// Add filter to include expiry in payment logging
add_filter('x402_payment_verification_result', function($result, $requirements) {
    if (!empty($result['verified']) && isset($requirements->postId)) {
        $post_id = $requirements->postId;
        $expires_at = X402_Paywall_DB::calculate_access_expiry($post_id);
        
        // Include expiry in result for logging
        $result['expires_at'] = $expires_at;
    }
    return $result;
}, 10, 2);
```

### Checking Access with Expiry
```php
// Old way (still works):
$has_access = X402_Paywall_DB::has_user_paid($post_id, $wallet_address);

// New behavior automatically checks expiry:
// SELECT ... WHERE payment_status = 'verified' 
//   AND (expires_at IS NULL OR expires_at > NOW())
```

### Custom Access Duration Logic
```php
// Override access duration for specific posts
add_filter('x402_paywall_access_duration', function($duration, $post_id) {
    // Give premium members permanent access
    if (has_term('premium', 'category', $post_id)) {
        return 'permanent';
    }
    return $duration;
}, 10, 2);
```

## Migration for Existing Payments

All existing payments have `expires_at = NULL`, which means:
- ✅ **Permanent access granted** (backward compatible)
- No retroactive expiry applied
- Existing users retain their access

To apply expiry to existing payments:
```php
// Example: Apply 1-year expiry to old payments
global $wpdb;
$table = $wpdb->prefix . 'x402_payment_logs';

$wpdb->query("
    UPDATE {$table} 
    SET expires_at = DATE_ADD(created_at, INTERVAL 1 YEAR)
    WHERE expires_at IS NULL 
    AND payment_status = 'verified'
    AND created_at > '2024-01-01'
");
```

## Testing

### Test Access Expiry
```php
// 1. Create post with 1-day access
// 2. Make payment
// 3. Verify expires_at in database:
global $wpdb;
$expires = $wpdb->get_var($wpdb->prepare(
    "SELECT expires_at FROM {$wpdb->prefix}x402_payment_logs 
     WHERE post_id = %d AND payment_status = 'verified' 
     ORDER BY created_at DESC LIMIT 1",
    $post_id
));
// Should be: tomorrow's date

// 4. Manually set expiry to past:
$wpdb->update(
    $wpdb->prefix . 'x402_payment_logs',
    array('expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))),
    array('post_id' => $post_id)
);

// 5. Verify access is now denied:
$has_access = X402_Paywall_DB::has_user_paid($post_id, $wallet);
// Should return: false
```

### Test Permanent Access
```php
// 1. Create post with "Permanent" duration
// 2. Verify expires_at is NULL after payment:
$expires = $wpdb->get_var("SELECT expires_at FROM ... LIMIT 1");
// Should be: NULL

// 3. User retains access forever
```

## Frontend Display

To show expiry information to users after payment, update the payment success template:

```php
// In templates/payment-status.php or custom template
if (!empty($expires_at)) {
    $expiry_date = date_i18n(get_option('date_format'), strtotime($expires_at));
    echo '<p class="x402-access-expiry">';
    printf(
        __('Access granted until %s', 'x402-paywall'),
        '<strong>' . esc_html($expiry_date) . '</strong>'
    );
    echo '</p>';
} else {
    echo '<p class="x402-access-permanent">';
    _e('Permanent access granted!', 'x402-paywall');
    echo '</p>';
}
```

## Admin Display

Show expiry in payment logs:

```php
// In admin payment logs table
$expires_display = $log->expires_at 
    ? date_i18n(get_option('date_format'), strtotime($log->expires_at))
    : __('Never', 'x402-paywall');

echo '<td>' . esc_html($expires_display) . '</td>';
```

## Cron Job for Cleanup

Add cleanup of expired payment records (optional):

```php
// In includes/class-x402-paywall-cron.php
public function cleanup_expired_payments() {
    global $wpdb;
    $table = $wpdb->prefix . 'x402_payment_logs';
    
    // Archive or delete expired payments older than 90 days
    $wpdb->query("
        DELETE FROM {$table}
        WHERE expires_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        AND payment_status = 'verified'
    ");
}
```

## Future Enhancements

### 1. Renewal System
```php
// Allow users to renew expired access
function x402_renew_access($post_id, $old_payment_id) {
    // Create new payment log extending from old expiry
    $old_expires = get_payment_expiry($old_payment_id);
    $new_expires = date('Y-m-d H:i:s', strtotime($old_expires . ' +1 year'));
    
    return X402_Paywall_DB::log_payment(array(
        'expires_at' => $new_expires,
        // ... other fields
    ));
}
```

### 2. Tiered Pricing
```php
// Different prices for different durations
add_filter('x402_payment_amount', function($amount, $post_id) {
    $duration = get_post_meta($post_id, '_x402_paywall_access_duration', true);
    
    $multipliers = array(
        '1_day' => 0.1,
        '1_week' => 0.25,
        '1_month' => 0.5,
        '1_year' => 1.0,
        'permanent' => 2.0,
    );
    
    return $amount * ($multipliers[$duration] ?? 1.0);
}, 10, 2);
```

### 3. Expiry Notifications
```php
// Email users before access expires
add_action('x402_daily_cleanup', 'x402_check_expiring_access');

function x402_check_expiring_access() {
    global $wpdb;
    $table = $wpdb->prefix . 'x402_payment_logs';
    
    // Find payments expiring in 3 days
    $expiring = $wpdb->get_results("
        SELECT * FROM {$table}
        WHERE expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
        AND payment_status = 'verified'
    ");
    
    foreach ($expiring as $payment) {
        // Send notification email
        x402_send_expiry_notice($payment);
    }
}
```

## Configuration Summary

| Duration | Database Value | Expiry Calculation | Use Case |
|----------|---------------|-------------------|----------|
| 1 Day | `1_day` | `NOW() + 1 day` | Daily news, event tickets |
| 1 Week | `1_week` | `NOW() + 1 week` | Weekly content, trial access |
| 1 Month | `1_month` | `NOW() + 1 month` | Monthly subscriptions |
| 3 Months | `3_months` | `NOW() + 3 months` | Quarterly access |
| 6 Months | `6_months` | `NOW() + 6 months` | Semi-annual access |
| **1 Year (Default)** | `1_year` | `NOW() + 1 year` | Annual subscriptions |
| Permanent | `permanent` | `NULL` (no expiry) | One-time purchases, lifetime access |

## Backward Compatibility

✅ **All existing behavior preserved:**
- Default is 1 year (current behavior)
- NULL expires_at = permanent access
- Existing payments remain valid
- No breaking changes to API

✅ **Database migration handled:**
- New column added via `dbDelta()` in activator
- Existing rows get `NULL` (permanent)
- No data loss

✅ **Cookie behavior unchanged:**
- Cookies still expire after 1 year client-side
- Database check remains authoritative
- Expiry now checked on every access

## Support

For questions or issues:
1. Check payment logs: `wp_x402_payment_logs` table
2. Verify post meta: `_x402_paywall_access_duration`
3. Test expiry calculation: `X402_Paywall_DB::calculate_access_expiry($post_id)`
4. Enable debug mode: `define('WP_DEBUG', true);`
