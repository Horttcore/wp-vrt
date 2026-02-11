<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class AdminPage {
    private static string $hook_suffix = '';

    public static function init(): void {
        \add_action('admin_menu', [self::class, 'register_page']);
        \add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function register_page(): void {
        self::$hook_suffix = (string) \add_management_page(
            'WP VRT',
            'WP VRT',
            'manage_options',
            'wp-vrt',
            [self::class, 'render_page']
        );
    }

    public static function enqueue_assets(string $hook): void {
        if ($hook !== self::$hook_suffix) {
            return;
        }

        \wp_enqueue_style('wp-components');
        \wp_enqueue_style('wp-block-editor');

        $asset_path = WP_VRT_PATH . 'build/admin-page.asset.php';
        $asset = [
            'dependencies' => ['wp-element', 'wp-components', 'wp-views'],
            'version' => '0.1.0',
        ];
        if (file_exists($asset_path)) {
            $asset = require $asset_path;
        }

        \wp_enqueue_script(
            'wp-vrt-admin',
            WP_VRT_URL . 'build/admin-page.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        \wp_add_inline_style('wp-components', self::get_inline_styles());

        $data = self::get_page_data();
        \wp_add_inline_script(
            'wp-vrt-admin',
            'window.wpVrtAdminData = ' . \wp_json_encode($data) . ';',
            'before'
        );
    }

    public static function render_page(): void {
        echo '<div class="wrap wp-vrt-admin">';
        echo '<div id="wp-vrt-app"></div>';
        echo '</div>';
    }

    private static function get_inline_styles(): string {
        return '
.wp-vrt-admin { max-width: 1240px; }
.wp-vrt-header { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin: 24px 0 18px; padding: 20px 24px; border-radius: 14px; background: linear-gradient(120deg, #f1f5f9 0%, #f8fafc 100%); border: 1px solid #e2e8f0; }
.wp-vrt-title { margin: 0; font-size: 28px; letter-spacing: -0.02em; }
.wp-vrt-subtitle { margin: 6px 0 0; color: #4b5563; }
.wp-vrt-actions { display: flex; gap: 12px; flex-wrap: wrap; }
.wp-vrt-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin: 0 0 24px; }
.wp-vrt-stat { border-radius: 12px; box-shadow: 0 1px 0 rgba(0,0,0,0.04); }
.wp-vrt-stat-value { font-size: 24px; font-weight: 600; margin-bottom: 4px; }
.wp-vrt-stat-label { color: #4b5563; }
.wp-vrt-tabs .components-tab-panel__tabs { margin-bottom: 12px; }
.wp-vrt-tabs .components-tab-panel__tabs-item { font-weight: 600; }
.wp-vrt-tab-panel { margin-bottom: 18px; }
.wp-vrt-search { max-width: 360px; margin: 0 0 12px; }
.wp-vrt-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; }
.wp-vrt-groups { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-bottom: 18px; }
.wp-vrt-group { border-radius: 14px; overflow: hidden; }
.wp-vrt-group-header { display: flex; align-items: center; justify-content: space-between; }
.wp-vrt-section { border-radius: 14px; overflow: hidden; box-shadow: 0 1px 0 rgba(0,0,0,0.04); }
.wp-vrt-section-header { display: flex; align-items: center; justify-content: space-between; }
.wp-vrt-section-desc { margin: 6px 0 0; color: #6b7280; }
.wp-vrt-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; padding: 2px 8px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 12px; font-weight: 600; }
.wp-vrt-list { display: grid; gap: 10px; }
.wp-vrt-list-grid { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
.wp-vrt-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; transition: border-color 120ms ease, box-shadow 120ms ease, transform 120ms ease; }
.wp-vrt-row:hover { border-color: #111827; box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08); transform: translateY(-1px); }
.wp-vrt-row-label { font-weight: 600; color: #111827; display: inline-flex; align-items: center; gap: 8px; }
.wp-vrt-row-action { min-width: auto; }
.wp-vrt-tag { margin-left: 8px; padding: 2px 6px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 600; }
.wp-vrt-empty { margin: 0; color: #6b7280; }
@media (max-width: 782px) {
  .wp-vrt-header { flex-direction: column; align-items: flex-start; }
}
';
    }

    private static function get_page_data(): array {
        $block_registry = new BlockRegistry();
        $blocks = $block_registry->get_discoverable_blocks();
        $block_families = $block_registry->get_block_families();
        $patterns = (new PatternRegistry())->get_discoverable_patterns();
        $templates = (new TemplateRegistry())->get_discoverable_templates();
        $parts = (new TemplateRegistry())->get_discoverable_template_parts();
        $scenarios = (new ScenarioRegistry())->get_discoverable_scenarios();

        $base_url = \home_url('/wp-vrt');
        $discovery_url = \home_url('/wp-json/wp-vrt/v1/discover');

        $block_items = [];
        foreach ($blocks as $block) {
            $block_items[] = [
                'label' => $block['title'],
                'url' => $base_url . '/block/' . $block['slug'],
            ];

            foreach ($block['variations'] as $variation) {
                $block_items[] = [
                    'label' => $block['title'] . ' - ' . $variation['label'],
                    'url' => $base_url . '/block/' . $block['slug'] . '/' . $variation['name'],
                ];
            }
        }

        $family_items = [];
        foreach ($block_families as $family) {
            $items = [];
            foreach ($family['blocks'] as $block_name) {
                if (!isset($blocks[$block_name])) {
                    continue;
                }
                $items[] = [
                    'label' => $blocks[$block_name]['title'],
                    'url' => $base_url . '/block/' . $blocks[$block_name]['slug'],
                ];
            }

            if (empty($items)) {
                continue;
            }

            $family_items[] = [
                'title' => $family['title'],
                'description' => $family['description'],
                'items' => $items,
            ];
        }

        $pattern_items = [];
        foreach ($patterns as $pattern) {
            $pattern_items[] = [
                'label' => $pattern['title'],
                'url' => $base_url . '/pattern/' . $pattern['slug'],
                'isDynamic' => (bool) ($pattern['is_dynamic'] ?? false),
            ];
        }

        $template_items = [];
        foreach ($templates as $template) {
            $template_items[] = [
                'label' => $template['title'],
                'url' => $base_url . '/template/' . $template['slug'],
                'isDynamic' => (bool) ($template['is_dynamic'] ?? false),
            ];
        }

        $part_items = [];
        foreach ($parts as $part) {
            $label = $part['title'];
            if (!empty($part['area'])) {
                $label .= ' (' . $part['area'] . ')';
            }
            $part_items[] = [
                'label' => $label,
                'url' => $base_url . '/template-part/' . $part['slug'],
                'isDynamic' => (bool) ($part['is_dynamic'] ?? false),
            ];
        }

        $scenario_items = [];
        foreach ($scenarios as $scenario) {
            $scenario_items[] = [
                'label' => $scenario['title'],
                'url' => $base_url . '/scenario/' . $scenario['slug'],
            ];
        }

        return [
            'baseUrl' => $base_url,
            'discoveryUrl' => $discovery_url,
            'stats' => [
                'Blocks' => count($blocks),
                'Patterns' => count($patterns),
                'Templates' => count($templates),
                'Template Parts' => count($parts),
                'Scenarios' => count($scenarios),
            ],
            'sections' => [
                [
                    'title' => 'Blocks',
                    'description' => 'All registered blocks including variations.',
                    'groups' => $family_items,
                    'items' => $block_items,
                ],
                [
                    'title' => 'Patterns',
                    'description' => 'Theme and core patterns.',
                    'items' => $pattern_items,
                ],
                [
                    'title' => 'Templates',
                    'description' => 'Site templates for block themes.',
                    'items' => $template_items,
                ],
                [
                    'title' => 'Template Parts',
                    'description' => 'Reusable template fragments.',
                    'items' => $part_items,
                ],
                [
                    'title' => 'Scenarios',
                    'description' => 'Custom scenarios registered by themes/plugins.',
                    'items' => $scenario_items,
                ],
            ],
        ];
    }
}
