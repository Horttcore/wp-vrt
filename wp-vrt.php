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

    if (defined('WP_CLI') && WP_CLI) {
        WpVrt\Cli::init();
    }
});
