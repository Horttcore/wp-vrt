# WP VRT

Virtual pages for block/theme visual regression testing in WordPress.

## Usage

- Block example: `/wp-vrt/block/core-paragraph`
- Block variation: `/wp-vrt/block/core-button/outline`
- Pattern: `/wp-vrt/pattern/{pattern-slug}`
- Template: `/wp-vrt/template/{template-slug}`
- Template part: `/wp-vrt/template-part/{part-slug}`
- Scenario: `/wp-vrt/scenario/{scenario-slug}`

## Discovery Endpoint

`/wp-json/wp-vrt/v1/discover`

Returns a manifest of blocks, patterns, templates, template parts, and scenarios with URLs.

## Pest Visual Regression

Install dev dependencies:

```bash
composer install
```

Install Playwright for screenshots:

```bash
npm install -D playwright
```

## Admin UI Build

Install dependencies and build the admin UI bundle:

```bash
npm install
npm run build
```

For development:

```bash
npm run start
```

Run tests:

```bash
WP_VRT_BASE_URL="http://bedrock.test" composer test
```

You can override the Node binary:

```bash
NODE_BINARY="/usr/local/bin/node" composer test
```

To store screenshot PNGs in `wp-content/wp-vrt-snapshots`, set:

```bash
WP_VRT_SNAPSHOT_DIR="/path/to/wp-content/wp-vrt-snapshots" composer test
```

## Scenarios (theme/plugin)

Register custom scenarios from your theme or another plugin.

```php
add_filter('wp_vrt_register_scenarios', function ($scenarios) {
    $scenarios['button-states'] = [
        'title' => 'Button - All States',
        'description' => 'Default and outline buttons',
        'content' => '
            <!-- wp:buttons -->
            <div class="wp-block-buttons">
                <!-- wp:button -->
                <div class="wp-block-button"><a class="wp-block-button__link">Default</a></div>
                <!-- /wp:button -->

                <!-- wp:button {"className":"is-style-outline"} -->
                <div class="wp-block-button is-style-outline"><a class="wp-block-button__link">Outline</a></div>
                <!-- /wp:button -->
            </div>
            <!-- /wp:buttons -->
        ',
    ];

    return $scenarios;
});
```

You can also use a callable for `content` if you need custom logic.

## Default Scenario Coverage

WP VRT does not ship any built-in scenarios. Scenarios are expected to be registered by your theme or other plugins.

| Scenario | Coverage | Notes |
| --- | --- | --- |
| (none) | N/A | Register scenarios via `wp_vrt_register_scenarios` |

## Block Discovery Filters

By default, WP VRT includes all registered blocks. You can narrow it down:

```php
// Only include these blocks
add_filter('wp_vrt_block_allowlist', function () {
    return ['core/paragraph', 'core/heading', 'acf/hero'];
});

// Or exclude specific blocks
add_filter('wp_vrt_block_denylist', function () {
    return ['core/legacy-widget', 'plugin/experimental-block'];
});
```

## Enable/Disable Exposed Items

You can disable items from being exposed by default. Disabled items are hidden unless you enable the flag.

```php
add_filter('wp_vrt_is_item_enabled', function ($enabled, $type, $id, $item) {
    if ($type === 'block' && $id === 'core/paragraph') {
        return false;
    }
    return $enabled;
}, 10, 4);

add_filter('wp_vrt_template_part_enabled', function ($enabled, $template) {
    return $template->slug !== 'header';
}, 10, 2);
```

Show disabled items:
- Admin UI: toggle on `Tools → WP VRT`
- Discovery: `/wp-json/wp-vrt/v1/discover?include_disabled=1`

To toggle items from the UI, use the disable/enable icon on each row.

## Block Families

The admin UI groups blocks that are commonly used together. You can add or override families:

```php
add_filter('wp_vrt_block_families', function ($families) {
    $families[] = [
        'title' => 'Accordion',
        'description' => 'Accordion with item blocks.',
        'blocks' => ['my/accordion', 'my/accordion-item'],
    ];
    return $families;
});
```

## Block Sample Content

Dynamic blocks render from attributes only. Static blocks still require inner markup for rendering on the frontend, so WP VRT provides minimal HTML for core blocks. You can override per block:

```php
add_filter('wp_vrt_block_content', function ($content, $block_name) {
    if ($block_name === 'core/paragraph') {
        return [[
            'attrs' => ['content' => 'Custom paragraph text'],
            'content' => '<p>Custom paragraph text</p>',
        ]];
    }
    return $content;
}, 10, 2);
```

## WP-CLI Snapshots

Generate PNG snapshots into `wp-content/wp-vrt-snapshots`:

```bash
wp vrt snapshots --types=blocks --width=1200 --height=800
```

Options:
- `--types=blocks,patterns,templates,parts,scenarios`
- `--base-url=https://example.test`
- `--dir=/path/to/wp-content/wp-vrt-snapshots`
- `--width=1200`
- `--height=800`
- `--node=/path/to/node`

## WP-CLI Test

Generate snapshots and run Pest in one command:

```bash
wp vrt test --base-url="http://bedrock.test"
```

## Dynamic Content

Patterns and templates with dynamic blocks (query, post content, etc.) are excluded by default.
Opt in using:

```php
add_filter('wp_vrt_allow_dynamic_content', function ($allow, $type, $slug, $content) {
    return true;
}, 10, 4);
```

To enable dynamic rendering globally (and provide a post context), use:

```php
add_filter('wp_vrt_enable_dynamic_rendering', function () {
    return true;
});
```

You can also provide a specific post ID for context:

```php
add_filter('wp_vrt_dynamic_post_id', function () {
    return 123;
});
```

If no posts exist, WP VRT creates a temporary draft and deletes it after render.
