<?php
/**
 * Plugin Name: WP VRT
 * Description: Virtual pages for block/theme visual regression testing.
 * Version: 0.1.0
 * Author: OpenCode
 * Requires PHP: 8.3
 * Requires at least: 6.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_VRT_PATH', plugin_dir_path(__FILE__));
define('WP_VRT_URL', plugin_dir_url(__FILE__));

if (file_exists(WP_VRT_PATH . 'vendor/autoload.php')) {
    require_once WP_VRT_PATH . 'vendor/autoload.php';
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'WpVrt\\')) {
        return;
    }

    $map = [
        'WpVrt\\VirtualPages' => 'class-virtual-pages.php',
        'WpVrt\\Renderer' => 'class-renderer.php',
        'WpVrt\\StyleCollector' => 'class-style-collector.php',
        'WpVrt\\BlockRegistry' => 'class-block-registry.php',
        'WpVrt\\SampleContent' => 'class-sample-content.php',
        'WpVrt\\DiscoveryApi' => 'class-discovery-api.php',
        'WpVrt\\PatternRegistry' => 'class-pattern-registry.php',
        'WpVrt\\TemplateRegistry' => 'class-template-registry.php',
        'WpVrt\\ScenarioRegistry' => 'class-scenario-registry.php',
        'WpVrt\\DynamicContext' => 'class-dynamic-context.php',
        'WpVrt\\AdminPage' => 'class-admin-page.php',
        'WpVrt\\Cli' => 'class-cli.php',
        'WpVrt\\RestPatternFilters' => 'class-rest-pattern-filters.php',
    ];

    if (!isset($map[$class])) {
        return;
    }

    $path = WP_VRT_PATH . 'includes/' . $map[$class];
    if (file_exists($path)) {
        require_once $path;
    }
});

register_activation_hook(__FILE__, 'wp_vrt_activate');
register_deactivation_hook(__FILE__, 'wp_vrt_deactivate');

function wp_vrt_activate(): void {
    WpVrt\VirtualPages::register_rewrite_rules();
    flush_rewrite_rules();
}

function wp_vrt_deactivate(): void {
    flush_rewrite_rules();
}

add_action('plugins_loaded', static function () {
    WpVrt\VirtualPages::init();
    WpVrt\DiscoveryApi::init();
    WpVrt\AdminPage::init();
    WpVrt\RestPatternFilters::init();

    if (defined('WP_CLI') && WP_CLI) {
        WpVrt\Cli::init();
    }
});

// Remove blocks that cannot be meaningfully previewed in isolation
add_filter('wp_vrt_block_denylist', static function (array $denylist): array {
    $social_links_variants = [
        'core/social-link-amazon',
        'core/social-link-bandcamp',
        'core/social-link-behance',
        'core/social-link-chain',
        'core/social-link-codepen',
        'core/social-link-deviantart',
        'core/social-link-dribbble',
        'core/social-link-dropbox',
        'core/social-link-etsy',
        'core/social-link-facebook',
        'core/social-link-feed',
        'core/social-link-fivehundredpx',
        'core/social-link-flickr',
        'core/social-link-foursquare',
        'core/social-link-goodreads',
        'core/social-link-google',
        'core/social-link-github',
        'core/social-link-instagram',
        'core/social-link-lastfm',
        'core/social-link-linkedin',
        'core/social-link-mail',
        'core/social-link-mastodon',
        'core/social-link-meetup',
        'core/social-link-medium',
        'core/social-link-pinterest',
        'core/social-link-pocket',
        'core/social-link-reddit',
        'core/social-link-skype',
        'core/social-link-snapchat',
        'core/social-link-soundcloud',
        'core/social-link-spotify',
        'core/social-link-tumblr',
        'core/social-link-twitch',
        'core/social-link-twitter',
        'core/social-link-vimeo',
        'core/social-link-vk',
        'core/social-link-wordpress',
        'core/social-link-yelp',
        'core/social-link-youtube',
    ];

    return array_merge($denylist, array_merge($social_links_variants, [
        'core/template-part',  // Template parts are reusable components, not meant for block preview
        'core/pattern',        // Pattern placeholder blocks are metadata, not visual content
        'core/widget-group',   // Widget groups are sidebar/widget area specific
    ]));
});
