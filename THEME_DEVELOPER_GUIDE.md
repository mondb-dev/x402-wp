# X402 Paywall - Theme Developer Guide

## Quick Start for Theme Developers

This guide shows you how to customize the X402 Paywall plugin to match your theme's design.

---

## Template Override System

### Where to Place Custom Templates

Create a directory in your theme:
```
yourtheme/
  x402-paywall/          ← Plugin checks here first
    paywall-message.php
    wallet-display.php
    payment-status.php
```

Or alternatively:
```
yourtheme/
  x402/                  ← Plugin checks here second
    paywall-message.php
```

### Available Templates

1. **paywall-message.php** - The main paywall screen users see
2. **wallet-display.php** - Wallet management in user profile
3. **payment-status.php** - Payment verification status display

---

## Customizing the Paywall Message

### Basic Override Example

Copy `plugins/x402-paywall/templates/paywall-message.php` to `yourtheme/x402-paywall/paywall-message.php`

```php
<?php
/**
 * Custom Paywall Message Template
 * Available variables:
 * - $post_id (int): Current post ID
 * - $config (array): Paywall configuration
 * - $title (string): Post title
 * - $excerpt (string): Post excerpt
 */
?>

<div class="my-theme-paywall">
    <div class="paywall-container">
        <h2><?php esc_html_e('Premium Content', 'your-theme'); ?></h2>
        
        <div class="price-display">
            <?php
            $finance = X402_Paywall_Finance::get_instance();
            $amount = $finance->atomic_to_decimal(
                $config['amount'], 
                $config['token_decimals']
            );
            ?>
            <span class="amount"><?php echo esc_html($amount); ?></span>
            <span class="token"><?php echo esc_html($config['token_name']); ?></span>
        </div>
        
        <button class="btn btn-primary x402-paywall-connect-button" 
                data-post-id="<?php echo esc_attr($post_id); ?>">
            <?php esc_html_e('Unlock Content', 'your-theme'); ?>
        </button>
    </div>
</div>
```

---

## Using Hooks for Customization

### Add Content Before Paywall

```php
// In your theme's functions.php
add_action('x402_before_paywall_message', function($post_id, $config) {
    ?>
    <div class="theme-paywall-notice">
        <p>🔒 This is exclusive content for our supporters!</p>
    </div>
    <?php
}, 10, 2);
```

### Customize Paywall Title

```php
add_filter('x402_paywall_title', function($title, $post_id) {
    return 'Support This Content';
}, 10, 2);
```

### Add Custom CSS Classes

```php
add_filter('x402_paywall_message_classes', function($classes, $post_id) {
    $classes[] = 'my-theme-premium';
    $classes[] = 'post-type-' . get_post_type($post_id);
    return $classes;
}, 10, 2);
```

---

## Styling the Paywall

### Default CSS Classes

The plugin uses these CSS classes:

```css
/* Main containers */
.x402-paywall-message { }
.x402-locked-content { }

/* Header section */
.x402-paywall-header { }
.x402-paywall-icon { }
.x402-paywall-title { }

/* Body section */
.x402-paywall-body { }
.x402-paywall-description { }
.x402-paywall-excerpt { }
.x402-paywall-payment-info { }

/* Footer section */
.x402-paywall-footer { }
.x402-paywall-connect-button { }
.x402-paywall-security-note { }
```

### Example Theme Styling

```css
/* In your theme's style.css */

.x402-paywall-message {
    background: var(--theme-background);
    border: 2px solid var(--theme-accent);
    border-radius: 12px;
    padding: 2rem;
    margin: 2rem 0;
}

.x402-paywall-title {
    color: var(--theme-heading);
    font-family: var(--theme-font);
    font-size: 2rem;
    margin-bottom: 1rem;
}

.x402-paywall-connect-button {
    background: var(--theme-primary);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
}

.x402-paywall-connect-button:hover {
    background: var(--theme-primary-dark);
}
```

---

## Working with Web3 Integration

### The Connect Button

The `.x402-paywall-connect-button` must have `data-post-id` attribute:

```html
<button class="x402-paywall-connect-button" 
        data-post-id="<?php echo esc_attr($post_id); ?>">
    Connect & Pay
</button>
```

The plugin's JavaScript automatically handles:
- Wallet connection
- Payment transaction
- Content unlocking

### Custom JavaScript Integration

```javascript
// In your theme's JS file
jQuery(document).ready(function($) {
    // Listen for payment events
    $(document).on('x402-payment-started', function(e, postId) {
        console.log('Payment initiated for post:', postId);
        // Show loading state
    });
    
    $(document).on('x402-payment-success', function(e, postId) {
        console.log('Payment successful for post:', postId);
        // Show success message
    });
    
    $(document).on('x402-payment-error', function(e, postId, error) {
        console.log('Payment failed:', error);
        // Show error message
    });
});
```

---

## Responsive Design

### Mobile-First Example

```css
/* Mobile first */
.x402-paywall-message {
    padding: 1rem;
}

.x402-paywall-payment-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Tablet and up */
@media (min-width: 768px) {
    .x402-paywall-message {
        padding: 2rem;
    }
    
    .x402-paywall-payment-info {
        flex-direction: row;
        justify-content: space-between;
    }
}

/* Desktop */
@media (min-width: 1024px) {
    .x402-paywall-message {
        max-width: 800px;
        margin: 2rem auto;
    }
}
```

---

## Block Theme Integration

### For Full Site Editing Themes

```php
// In your theme's functions.php

// Register custom block pattern
function mytheme_register_paywall_pattern() {
    register_block_pattern(
        'mytheme/paywall-notice',
        array(
            'title'       => __('Paywall Notice', 'mytheme'),
            'description' => __('Custom paywall display', 'mytheme'),
            'content'     => '<!-- wp:group {"className":"mytheme-paywall"} -->
                <!-- Add your blocks here -->
                <!-- /wp:group -->',
        )
    );
}
add_action('init', 'mytheme_register_paywall_pattern');
```

---

## Common Customizations

### 1. Change Button Text

```php
add_filter('x402_paywall_message_html', function($html, $post_id, $config) {
    return str_replace(
        'Connect Wallet & Pay',
        'Unlock Now',
        $html
    );
}, 10, 3);
```

### 2. Hide Excerpt Preview

```php
add_filter('x402_excerpt_length', function($length, $post_id) {
    return 0; // No excerpt preview
}, 10, 2);
```

### 3. Custom Price Display

```php
add_action('x402_paywall_message_body', function($post_id) {
    $config = get_post_meta($post_id, '_x402_paywall_config', true);
    ?>
    <div class="custom-price-badge">
        <span class="label">Only</span>
        <span class="price">$<?php echo esc_html($config['amount']); ?></span>
    </div>
    <?php
}, 5, 1); // Priority 5 to run early
```

### 4. Add Social Sharing

```php
add_action('x402_after_paywall_message', function($post_id, $config) {
    ?>
    <div class="paywall-share">
        <p>Love this content? Share it:</p>
        <!-- Your social sharing buttons -->
    </div>
    <?php
}, 10, 2);
```

---

## Dark Mode Support

```css
/* Light mode (default) */
.x402-paywall-message {
    background: #ffffff;
    color: #000000;
}

/* Dark mode */
@media (prefers-color-scheme: dark) {
    .x402-paywall-message {
        background: #1a1a1a;
        color: #ffffff;
    }
    
    .x402-paywall-connect-button {
        background: #3b82f6;
    }
}

/* Or with theme toggle */
body.dark-mode .x402-paywall-message {
    background: #1a1a1a;
    color: #ffffff;
}
```

---

## Accessibility

### Best Practices

```html
<!-- Use semantic HTML -->
<div class="x402-paywall-message" role="dialog" aria-labelledby="paywall-title">
    <h2 id="paywall-title">Premium Content</h2>
    
    <!-- Ensure buttons have proper labels -->
    <button class="x402-paywall-connect-button"
            aria-label="Connect wallet and pay to access content">
        Unlock Content
    </button>
</div>
```

### Keyboard Navigation

```css
/* Focus states */
.x402-paywall-connect-button:focus {
    outline: 2px solid var(--theme-primary);
    outline-offset: 2px;
}

.x402-paywall-connect-button:focus:not(:focus-visible) {
    outline: none;
}

.x402-paywall-connect-button:focus-visible {
    outline: 2px solid var(--theme-primary);
}
```

---

## Testing Your Customization

### Preview as Non-Author

1. Log out or use incognito mode
2. Visit a paywalled post
3. Test the payment flow

### Test Responsive Design

```javascript
// Browser console
window.resizeTo(375, 667); // iPhone size
window.resizeTo(768, 1024); // iPad size
window.resizeTo(1920, 1080); // Desktop
```

---

## Troubleshooting

### Template Not Loading?

Check file path:
```
✓ yourtheme/x402-paywall/paywall-message.php
✗ yourtheme/x402paywall/paywall-message.php (missing hyphen)
✗ yourtheme/templates/paywall-message.php (wrong folder)
```

### Styles Not Applying?

Check CSS specificity:
```css
/* ✗ Too generic */
.paywall-message { }

/* ✓ Use plugin's classes */
.x402-paywall-message { }

/* ✓ Or increase specificity */
.my-theme .x402-paywall-message { }
```

### JavaScript Not Working?

Ensure jQuery is loaded:
```php
// In your theme's functions.php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('jquery');
});
```

---

## Support

- **Documentation:** See `HOOKS_REFERENCE.md` for all available hooks
- **Examples:** Check `templates/` folder for base templates
- **Issues:** GitHub repository issues section

---

## License

Theme customizations inherit the plugin's Apache-2.0 license.

---

*Happy theming! 🎨*
