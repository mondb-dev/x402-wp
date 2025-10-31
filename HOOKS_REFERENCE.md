# X402 Paywall - Developer Documentation

## Hooks & Extensibility Guide

The X402 Paywall plugin is designed to be fully extensible through WordPress hooks (actions and filters). This document details all available hooks for customization.

## Table of Contents

1. [Payment Workflow Hooks](#payment-workflow-hooks)
2. [Template Hooks](#template-hooks)
3. [Content Filter Hooks](#content-filter-hooks)
4. [Admin Hooks](#admin-hooks)
5. [Extensibility Hooks](#extensibility-hooks)
6. [Template Override System](#template-override-system)
7. [REST API Hooks](#rest-api-hooks)

---

## Payment Workflow Hooks

### Actions

#### `x402_before_payment_verification`
Fires before payment verification starts.

```php
add_action('x402_before_payment_verification', function($post_id, $payment_data) {
    // Log payment attempt
    error_log("Payment verification starting for post {$post_id}");
}, 10, 2);
```

#### `x402_after_payment_verification`
Fires after successful payment verification.

```php
add_action('x402_after_payment_verification', function($post_id, $payment_result) {
    // Send notification email
    wp_mail(get_option('admin_email'), 'Payment Received', "Payment verified for post {$post_id}");
}, 10, 2);
```

#### `x402_payment_verification_failed`
Fires when payment verification fails.

```php
add_action('x402_payment_verification_failed', function($post_id, $error) {
    // Log failure
    error_log("Payment failed for post {$post_id}: " . $error->get_error_message());
}, 10, 2);
```

#### `x402_payment_logged`
Fires when payment is logged to database.

```php
add_action('x402_payment_logged', function($payment_log) {
    // Trigger webhook or external API
    wp_remote_post('https://your-webhook.com/payment', [
        'body' => json_encode($payment_log)
    ]);
}, 10, 1);
```

### Filters

#### `x402_payment_requirements`
Filter payment requirements before sending to client.

```php
add_filter('x402_payment_requirements', function($requirements, $post_id) {
    // Add custom metadata
    $requirements['metadata']['custom_field'] = 'value';
    return $requirements;
}, 10, 2);
```

#### `x402_payment_verification_result`
Filter payment verification result.

```php
add_filter('x402_payment_verification_result', function($result, $post_id) {
    // Add custom verification logic
    if ($result['verified']) {
        // Grant additional access
    }
    return $result;
}, 10, 2);
```

---

## Template Hooks

### Actions

#### `x402_before_paywall_message`
Fires before paywall message is displayed.

```php
add_action('x402_before_paywall_message', function($post_id, $config) {
    echo '<div class="my-custom-notice">Premium content ahead!</div>';
}, 10, 2);
```

#### `x402_paywall_message_header`
Fires in the header section of paywall message.

```php
add_action('x402_paywall_message_header', function($post_id) {
    echo '<div class="custom-badge">Exclusive Content</div>';
}, 10, 1);
```

#### `x402_before_wallet_display`
Fires before wallet display in user profile.

```php
add_action('x402_before_wallet_display', function() {
    echo '<p>Manage your payment wallet addresses below:</p>';
});
```

### Filters

#### `x402_paywall_message_html`
Filter the entire paywall message HTML.

```php
add_filter('x402_paywall_message_html', function($html, $post_id, $config) {
    // Completely customize the paywall message
    return '<div class="custom-paywall">' . $html . '</div>';
}, 10, 3);
```

#### `x402_paywall_message_classes`
Filter CSS classes for paywall message.

```php
add_filter('x402_paywall_message_classes', function($classes, $post_id) {
    $classes[] = 'premium-post-' . get_post_type($post_id);
    return $classes;
}, 10, 2);
```

---

## Content Filter Hooks

### Filters

#### `x402_show_paywall`
Filter whether to show paywall for specific post.

```php
add_filter('x402_show_paywall', function($show_paywall, $post_id, $post) {
    // Hide paywall for specific categories
    if (has_category('free-access', $post_id)) {
        return false;
    }
    return $show_paywall;
}, 10, 3);
```

#### `x402_excerpt_length`
Filter excerpt length for paywalled content.

```php
add_filter('x402_excerpt_length', function($length, $post_id) {
    // Show longer excerpt for premium posts
    return 100;
}, 10, 2);
```

#### `x402_user_can_bypass`
Filter whether user can bypass paywall.

```php
add_filter('x402_user_can_bypass', function($can_bypass, $post_id, $user_id) {
    // Allow subscribers to bypass
    $user = get_userdata($user_id);
    if (in_array('subscriber', $user->roles)) {
        return true;
    }
    return $can_bypass;
}, 10, 3);
```

---

## Admin Hooks

### Actions

#### `x402_settings_saved`
Fires when paywall settings are saved.

```php
add_action('x402_settings_saved', function($post_id, $settings) {
    // Clear cache after settings update
    wp_cache_delete('paywall_config_' . $post_id);
}, 10, 2);
```

#### `x402_profile_updated`
Fires when user wallet addresses are updated.

```php
add_action('x402_profile_updated', function($user_id, $addresses) {
    // Send confirmation email
    $user = get_userdata($user_id);
    wp_mail($user->user_email, 'Wallet Updated', 'Your wallet addresses have been updated.');
}, 10, 2);
```

### Filters

#### `x402_admin_settings_fields`
Filter admin settings fields.

```php
add_filter('x402_admin_settings_fields', function($fields) {
    $fields['custom_setting'] = [
        'type' => 'text',
        'label' => 'Custom Setting',
        'description' => 'A custom setting field'
    ];
    return $fields;
}, 10, 1);
```

---

## Extensibility Hooks

### Filters

#### `x402_supported_networks`
Filter supported blockchain networks.

```php
add_filter('x402_supported_networks', function($networks) {
    $networks['avalanche-mainnet'] = 'Avalanche Mainnet';
    return $networks;
}, 10, 1);
```

#### `x402_supported_tokens`
Filter supported tokens for a network.

```php
add_filter('x402_supported_tokens', function($tokens, $network) {
    if ($network === 'ethereum-mainnet') {
        $tokens[] = [
            'address' => '0x...',
            'symbol' => 'CUSTOM',
            'name' => 'Custom Token',
            'decimals' => 18
        ];
    }
    return $tokens;
}, 10, 2);
```

#### `x402_transaction_fee`
Filter transaction fee calculation.

```php
add_filter('x402_transaction_fee', function($fee, $amount, $network) {
    // Add 2.5% fee for certain networks
    if ($network === 'ethereum-mainnet') {
        $finance = X402_Paywall_Finance::get_instance();
        return $finance->calculate_fee($amount, '2.5');
    }
    return $fee;
}, 10, 3);
```

#### `x402_facilitator_url`
Filter facilitator URL.

```php
add_filter('x402_facilitator_url', function($url) {
    // Use custom facilitator
    return 'https://my-custom-facilitator.com';
}, 10, 1);
```

---

## Template Override System

### Theme Template Hierarchy

The plugin checks for template overrides in this order:

1. `yourtheme/x402-paywall/template-name.php`
2. `yourtheme/x402/template-name.php`
3. `plugin/templates/template-name.php`

### Creating Custom Templates

```php
// In your theme: yourtheme/x402-paywall/paywall-message.php
<?php
// Your custom paywall message template
?>
<div class="my-custom-paywall">
    <h2><?php echo esc_html($title); ?></h2>
    <p>Amount: <?php echo esc_html($config['amount']); ?></p>
    <!-- Your custom HTML -->
</div>
```

### Available Template Files

- `paywall-message.php` - Main paywall message display
- `wallet-display.php` - User wallet management
- `payment-status.php` - Payment status display
- `payment-form.php` - Payment form (if you create it)
- `transaction-list.php` - Transaction history (if you create it)

---

## REST API Hooks

### Actions

#### `x402_payment_verified_api`
Fires when payment is verified via API.

```php
add_action('x402_payment_verified_api', function($post_id, $result) {
    // Trigger custom action
    do_action('my_plugin_payment_received', $post_id);
}, 10, 2);
```

#### `x402_webhook_received`
Fires when webhook is received.

```php
add_action('x402_webhook_received', function($data, $request) {
    // Process webhook data
    error_log('Webhook received: ' . json_encode($data));
}, 10, 2);
```

#### `x402_wallet_updated_api`
Fires when wallet is updated via API.

```php
add_action('x402_wallet_updated_api', function($user_id, $evm_address, $spl_address) {
    // Custom processing
    update_user_meta($user_id, 'wallet_last_updated', time());
}, 10, 3);
```

---

## Custom Integration Examples

### Example 1: Add Discount for Members

```php
add_filter('x402_payment_requirements', function($requirements, $post_id) {
    if (is_user_logged_in() && current_user_can('subscriber')) {
        $finance = X402_Paywall_Finance::get_instance();
        // Apply 20% discount
        $discounted = $finance->multiply($requirements['amount'], '0.8');
        $requirements['amount'] = $discounted;
        $requirements['description'] .= ' (20% member discount applied)';
    }
    return $requirements;
}, 10, 2);
```

### Example 2: Custom Payment Notification

```php
add_action('x402_after_payment_verification', function($post_id, $payment_result) {
    $post = get_post($post_id);
    $author_id = $post->post_author;
    $author = get_userdata($author_id);
    
    // Email author
    wp_mail(
        $author->user_email,
        'Payment Received',
        "Someone paid for your post: {$post->post_title}"
    );
}, 10, 2);
```

### Example 3: Integration with Membership Plugin

```php
add_filter('x402_user_can_bypass', function($can_bypass, $post_id, $user_id) {
    // Check custom membership status
    if (function_exists('my_membership_is_active')) {
        if (my_membership_is_active($user_id)) {
            return true;
        }
    }
    return $can_bypass;
}, 10, 3);
```

---

## Security Best Practices

When extending the plugin:

1. **Always sanitize input**: Use `sanitize_text_field()`, `absint()`, etc.
2. **Escape output**: Use `esc_html()`, `esc_attr()`, `esc_url()`
3. **Verify nonces**: Use `wp_verify_nonce()` for form submissions
4. **Check capabilities**: Use `current_user_can()` before sensitive operations
5. **Use prepared statements**: When querying database directly

---

## Support

For more information:
- GitHub: https://github.com/mondb-dev/x402-wp
- X402 Protocol: https://x402.org
- Documentation: See plugin README.md
