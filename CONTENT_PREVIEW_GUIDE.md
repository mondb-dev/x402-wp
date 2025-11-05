# Content Preview Guide

## Overview

The X402 Paywall plugin includes a sophisticated content preview system that allows content creators to show a teaser of their paywalled content. This improves user experience and can increase conversion rates by giving potential buyers a glimpse of what they'll receive.

## Key Features

### 1. Flexible Preview Options

Authors can choose from 5 preview modes:

- **No Preview (0)**: Show paywall immediately with no content teaser
- **Short Preview (100 words)**: Brief teaser, ideal for articles
- **Medium Preview (250 words)**: Moderate teaser for longer content
- **Long Preview (500 words)**: Extensive teaser for in-depth articles
- **Custom (<!--more--> tag)**: Full author control using WordPress More tag

### 2. Smart HTML Preservation

The preview system intelligently preserves HTML structure:

- **Maintains Valid HTML**: Properly closes all tags at cutoff point
- **Preserves Block Structure**: Keeps paragraphs, headings, lists intact
- **Attribute Preservation**: All HTML attributes (classes, IDs) are maintained

### 3. Rich Media Support

Videos, images, and embeds are treated as complete blocks:

- **Complete Embeds Only**: Never cuts embeds in half
- **Supported Media**:
  - YouTube/Vimeo video embeds (via `<iframe>`)
  - Native HTML5 `<video>` and `<audio>` tags
  - Images (`<img>` tags)
  - Generic iframes
  - `<embed>` and `<object>` tags
  - WordPress figure blocks

### 4. WordPress Integration

- **Shortcode Processing**: All shortcodes are processed before preview extraction
- **oEmbed Support**: YouTube, Vimeo, and other embeds work correctly
- **Content Filters**: Runs through `the_content` filter for full compatibility
- **Theme Compatibility**: Works with any WordPress theme

## Configuration

### Per-Post Settings

When editing a post or page with X402 paywall enabled:

1. Scroll to the **X402 Paywall Settings** meta box
2. Locate the **Preview Length** dropdown
3. Select your desired preview option:
   - **No Preview**: Immediate paywall, no teaser
   - **Short (100 words)**: Quick introduction
   - **Medium (250 words)**: Balanced preview
   - **Long (500 words)**: Comprehensive teaser
   - **Custom (<!--more--> tag)**: Manual control

### Using the WordPress More Tag

For maximum control, choose "Custom (<!--more--> tag)" and add the tag in your content editor:

**Block Editor (Gutenberg)**:
1. Click the `+` button to add a block
2. Search for "More"
3. Insert the "More" block where preview should end

**Classic Editor**:
1. Position cursor where preview should end
2. Click the "Insert More Tag" button in the toolbar
3. Or manually type `<!--more-->`

**Example**:
```html
<p>This is the introduction to my article. It will be visible to everyone.</p>

<p>This paragraph contains a YouTube video:</p>
[youtube https://www.youtube.com/watch?v=dQw4w9WgXcQ]

<!--more-->

<p>This content is behind the paywall. Only paying users can see it.</p>
```

## Technical Implementation

### Word-Based Preview Extraction

When using word count limits (100/250/500), the plugin:

1. **Processes Content**: Runs content through `the_content` filter to process shortcodes and embeds
2. **Parses HTML**: Uses DOMDocument to parse HTML structure
3. **Walks DOM Tree**: Recursively walks through nodes
4. **Counts Words**: Counts only text content, not HTML tags
5. **Preserves Media**: Includes complete media elements without counting their words
6. **Closes Tags**: Ensures all HTML tags are properly closed
7. **Adds Ellipsis**: Appends "..." when content is truncated

### More Tag Preview

When using `<!--more-->` tag:

1. **Splits Content**: Uses PHP `explode()` to split on `<!--more-->`
2. **Takes First Part**: Uses only content before the tag
3. **Processes Content**: Runs through `the_content` filter
4. **Fallback**: If no more tag found, shows no preview

### Media Element Handling

The plugin treats these elements as complete blocks:

```php
array('iframe', 'img', 'video', 'audio', 'figure', 'embed', 'object')
```

These are never cut in half or counted toward word limit.

## CSS Styling

### Default Styles

The plugin includes responsive CSS for preview display:

```css
.x402-paywall-preview {
    position: relative;
    margin-bottom: 40px;
    padding-bottom: 60px;
}

.x402-paywall-preview::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 120px;
    background: linear-gradient(to bottom, rgba(255, 255, 255, 0), rgba(255, 255, 255, 1));
    pointer-events: none;
}
```

### Customizing Preview Styles

To customize preview appearance, add CSS to your theme:

**In your theme's `style.css` or custom CSS:**

```css
/* Change fade gradient color */
.x402-paywall-preview::after {
    background: linear-gradient(to bottom, rgba(248, 248, 248, 0), rgba(248, 248, 248, 1));
}

/* Adjust fade height */
.x402-paywall-preview::after {
    height: 150px;
}

/* Add border or shadow */
.x402-paywall-preview {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

/* Style the ellipsis */
.x402-preview-more {
    font-size: 1.5em;
    color: #999;
    text-align: center;
}
```

### Responsive Video Embeds

The plugin automatically handles responsive video sizing:

```css
.x402-paywall-preview iframe[src*="youtube"],
.x402-paywall-preview iframe[src*="vimeo"],
.x402-paywall-preview iframe[src*="dailymotion"] {
    aspect-ratio: 16 / 9;
    width: 100%;
    height: auto;
}
```

## Best Practices

### 1. Choose Appropriate Preview Length

- **Short Articles (500-1000 words)**: Use 100-word preview
- **Medium Articles (1000-2000 words)**: Use 250-word preview
- **Long Articles (2000+ words)**: Use 500-word preview or custom more tag
- **Video Content**: Use custom more tag to show intro + embed

### 2. Strategic More Tag Placement

Place the `<!--more-->` tag:
- After your hook/introduction
- After teasing your main points
- **Before** the paywall call-to-action
- After any free preview video/image

### 3. Include Rich Media in Preview

Consider including in your preview:
- Introductory video explaining the content
- Key images or infographics
- Author introduction video
- Problem statement visualization

### 4. Test Your Preview

Always preview your content:
1. Save your post as draft
2. Log out or use incognito mode
3. Visit the post URL
4. Verify preview looks good
5. Check that media displays correctly
6. Ensure gradient fade works properly

### 5. Balance Preview and Paywall

- Don't give away too much in the preview
- Give enough to show value
- Use preview to build curiosity
- End preview at a cliffhanger when possible

## Code Reference

### Database Schema

Preview length is stored as post meta:

```sql
SELECT meta_value 
FROM wp_postmeta 
WHERE post_id = 123 
AND meta_key = '_x402_paywall_preview_length';
```

**Valid Values**: `'0'`, `'100'`, `'250'`, `'500'`, `'-1'`

### Programmatic Access

**Get preview setting:**
```php
$preview_length = get_post_meta($post_id, '_x402_paywall_preview_length', true);
// Returns: '0', '100', '250', '500', '-1', or empty string (defaults to '0')
```

**Set preview setting:**
```php
update_post_meta($post_id, '_x402_paywall_preview_length', '250');
```

### Filters and Hooks

**Filter preview HTML before display:**
```php
add_filter('x402_paywall_preview_html', function($preview_html, $post_id, $preview_length) {
    // Modify preview HTML
    return $preview_html;
}, 10, 3);
```

**Filter preview word count:**
```php
add_filter('x402_paywall_preview_word_count', function($word_count, $post_id) {
    // Modify word count based on post type, category, etc.
    if (has_category('premium', $post_id)) {
        return 500; // Longer preview for premium category
    }
    return $word_count;
}, 10, 2);
```

## Troubleshooting

### Preview Not Showing

**Check these items:**
1. Ensure preview length is not set to "No Preview"
2. Verify content has enough words for the selected preview length
3. Check that paywall is enabled for the post
4. Confirm you're viewing as a non-paying user
5. Clear browser cache and WordPress transients

### Media Not Displaying

**Possible causes:**
1. **Invalid Embed URL**: Verify YouTube/Vimeo URL is correct
2. **Shortcode Issue**: Check shortcode is properly formatted
3. **Theme Conflict**: Disable other plugins temporarily to test
4. **oEmbed Disabled**: Ensure `wp_oembed_get()` is enabled

**Debug steps:**
```php
// Add to functions.php temporarily
add_filter('x402_paywall_preview_html', function($html) {
    error_log('Preview HTML: ' . $html);
    return $html;
});
```

### HTML Breaking

If HTML appears broken after cutoff:

1. **Check Source HTML**: Ensure original content has valid HTML
2. **Increase Word Count**: Try next preview length up
3. **Use More Tag**: Switch to custom more tag for manual control
4. **Check DOM Parsing**: Look for PHP errors in debug.log

### Gradient Not Visible

If fade gradient isn't showing:

1. **Background Color**: Ensure gradient matches your theme's background
2. **Z-Index Conflicts**: Check for theme CSS overriding `z-index`
3. **Custom Styles**: Add `!important` to override theme styles
4. **Browser Support**: Test in different browsers

## Performance Considerations

### Caching

The preview extraction process runs on every page load for non-paying users:

- **Server-side caching recommended**: Use WordPress object caching
- **CDN caching**: Can cache entire page for anonymous users
- **Transient caching**: Not implemented by default to ensure fresh content

### Optimization Tips

1. **Keep Content Clean**: Remove unnecessary HTML/whitespace
2. **Optimize Embeds**: Use lazy loading for videos
3. **Limit Media**: Don't include too many videos in preview
4. **Use CDN**: Serve images/videos from CDN

### Object Caching

For high-traffic sites, add custom caching:

```php
add_filter('x402_paywall_render_preview', function($preview, $post_id, $content) {
    $cache_key = 'x402_preview_' . $post_id;
    $cached = wp_cache_get($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    // Preview extraction happens here
    // ...
    
    wp_cache_set($cache_key, $preview, '', 3600); // 1 hour
    return $preview;
}, 10, 3);
```

## Migration from Previous Versions

If upgrading from a version without preview support:

1. **All existing paywalls default to "No Preview"**
2. **No database migration required** (new post meta only)
3. **Backward compatible**: Old paywalls work unchanged
4. **Opt-in feature**: Authors must explicitly enable preview

## See Also

- [Access Duration Guide](ACCESS_DURATION_GUIDE.md) - Configuring time-based access
- [Theme Developer Guide](THEME_DEVELOPER_GUIDE.md) - Customizing paywall templates
- [Hooks Reference](HOOKS_REFERENCE.md) - Available filters and actions
- [Token Detection Guide](TOKEN_DETECTION_GUIDE.md) - Payment token setup
