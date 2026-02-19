<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class AdminPage {
    private static string $hook_suffix = '';
    private const OPTION_SHOW_DISABLED = 'wp_vrt_show_disabled';
    private const OPTION_DISABLED_ITEMS = 'wp_vrt_disabled_items';

    public static function init(): void {
        \add_action('admin_menu', [self::class, 'register_page']);
        \add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        \add_action('admin_post_wp_vrt_toggle_disabled', [self::class, 'handle_toggle_disabled']);
        \add_action('admin_post_wp_vrt_toggle_item', [self::class, 'handle_toggle_item']);
        \add_action('admin_post_wp_vrt_toggle_all', [self::class, 'handle_toggle_all']);
        \add_action('admin_post_wp_vrt_toggle_group', [self::class, 'handle_toggle_group']);
        \add_action('wp_ajax_wp_vrt_toggle_item', [self::class, 'handle_toggle_item_ajax']);
        \add_action('wp_ajax_wp_vrt_toggle_all', [self::class, 'handle_toggle_all_ajax']);
        \add_action('wp_ajax_wp_vrt_toggle_group', [self::class, 'handle_toggle_group_ajax']);
        \add_filter('wp_vrt_is_item_enabled', [self::class, 'filter_item_enabled'], 10, 4);
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
        \wp_enqueue_style('dashicons');

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

    public static function handle_toggle_disabled(): void {
        if (!\current_user_can('manage_options')) {
            \wp_die('Unauthorized', '', ['response' => 403]);
        }

        \check_admin_referer('wp_vrt_toggle_disabled');

        $show_disabled = isset($_GET['show_disabled']) && $_GET['show_disabled'] !== '0';
        \update_option(self::OPTION_SHOW_DISABLED, $show_disabled ? '1' : '0');

        $redirect = \menu_page_url('wp-vrt', false);
        \wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_toggle_item(): void {
        if (!\current_user_can('manage_options')) {
            \wp_die('Unauthorized', '', ['response' => 403]);
        }

        \check_admin_referer('wp_vrt_toggle_item');

        $type = isset($_GET['type']) ? (string) $_GET['type'] : '';
        $id = isset($_GET['id']) ? (string) $_GET['id'] : '';
        $disable = isset($_GET['disable']) && $_GET['disable'] === '1';

        self::update_disabled_item($type, $id, $disable);

        $redirect = \menu_page_url('wp-vrt', false);
        \wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_toggle_all(): void {
        if (!\current_user_can('manage_options')) {
            \wp_die('Unauthorized', '', ['response' => 403]);
        }

        \check_admin_referer('wp_vrt_toggle_all');

        $type = isset($_GET['type']) ? (string) $_GET['type'] : '';
        $disable = isset($_GET['disable']) && $_GET['disable'] === '1';

        self::update_disabled_all($type, $disable);

        $redirect = \menu_page_url('wp-vrt', false);
        \wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_toggle_item_ajax(): void {
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        \check_ajax_referer('wp_vrt_toggle_item');

        $type = isset($_POST['type']) ? (string) $_POST['type'] : '';
        $id = isset($_POST['id']) ? (string) $_POST['id'] : '';
        $disable = isset($_POST['disable']) && $_POST['disable'] === '1';

        if ($type === '' || $id === '') {
            \wp_send_json_error(['message' => 'Invalid item'], 400);
        }

        self::update_disabled_item($type, $id, $disable);
        \wp_send_json_success(['disabled' => $disable]);
    }

    public static function handle_toggle_all_ajax(): void {
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        \check_ajax_referer('wp_vrt_toggle_all');

        $type = isset($_POST['type']) ? (string) $_POST['type'] : '';
        $disable = isset($_POST['disable']) && $_POST['disable'] === '1';

        if ($type === '') {
            \wp_send_json_error(['message' => 'Invalid type'], 400);
        }

        self::update_disabled_all($type, $disable);
        \wp_send_json_success(['disabled' => $disable]);
    }

    public static function handle_toggle_group(): void {
        if (!\current_user_can('manage_options')) {
            \wp_die('Unauthorized', '', ['response' => 403]);
        }

        \check_admin_referer('wp_vrt_toggle_all');

        $type = isset($_POST['type']) ? (string) $_POST['type'] : '';
        $disable = isset($_POST['disable']) && $_POST['disable'] === '1';
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];

        self::update_disabled_group($type, $ids, $disable);

        $redirect = \menu_page_url('wp-vrt', false);
        \wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_toggle_group_ajax(): void {
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        \check_ajax_referer('wp_vrt_toggle_all');

        $type = isset($_POST['type']) ? (string) $_POST['type'] : '';
        $disable = isset($_POST['disable']) && $_POST['disable'] === '1';
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];

        if ($type === '' || empty($ids)) {
            \wp_send_json_error(['message' => 'Invalid group'], 400);
        }

        self::update_disabled_group($type, $ids, $disable);
        \wp_send_json_success(['disabled' => $disable]);
    }

    public static function filter_item_enabled(bool $enabled, string $type, string $id, $item): bool {
        $disabled = self::get_disabled_items();
        if (!empty($disabled[$type]) && !empty($disabled[$type][$id])) {
            return false;
        }

        return $enabled;
    }

    public static function render_page(): void {
        echo '<div class="wrap wp-vrt-admin">';
        echo '<div id="wp-vrt-app"></div>';
        echo '</div>';
    }

    private static function get_inline_styles(): string {
        return '
.wp-vrt-admin { max-width: 1200px; }
.wp-vrt-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin: 12px 0 20px; }
.wp-vrt-header-main { display: grid; gap: 4px; }
.wp-vrt-title { margin: 0; font-size: 20px; font-weight: 600; }
.wp-vrt-subtitle { margin: 0; color: #50575e; }
.wp-vrt-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
.wp-vrt-tabs .components-tab-panel__tabs { margin-bottom: 12px; }
.wp-vrt-tab-label { display: inline-flex; align-items: center; gap: 6px; }
.wp-vrt-tab-count { display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 6px; border-radius: 999px; background: #f0f0f1; color: #50575e; font-size: 11px; font-weight: 600; }
.wp-vrt-tabs .components-tab-panel__tabs-item:not(.is-active) .wp-vrt-tab-count { background: #e5e7eb; }
.wp-vrt-tabs .components-tab-panel__tabs-item.is-active .wp-vrt-tab-count { background: #1d2327; color: #fff; }
.wp-vrt-tabs .components-tab-panel__tabs-item { font-weight: 600; }
.wp-vrt-tab-panel { margin-bottom: 20px; }
.wp-vrt-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin: 0 0 12px; }
.wp-vrt-search { max-width: 320px; }
.wp-vrt-filter { display: inline-flex; flex-wrap: wrap; gap: 6px; }
.wp-vrt-chip { padding: 4px 10px; border-radius: 999px; border: 1px solid #dcdcde; background: #fff; color: #1d2327; font-size: 12px; cursor: pointer; }
.wp-vrt-chip.is-active { background: #1d2327; color: #fff; border-color: #1d2327; }
.wp-vrt-chip:hover { border-color: #8c8f94; }
.wp-vrt-bulk { display: inline-flex; gap: 6px; flex-wrap: wrap; }
.wp-vrt-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
.wp-vrt-groups { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 16px; margin-bottom: 16px; }
.wp-vrt-group { padding: 12px; border: 1px solid #dcdcde; border-radius: 6px; background: #fff; }
.wp-vrt-group-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
.wp-vrt-group-meta { display: inline-flex; align-items: center; gap: 8px; }
.wp-vrt-group-actions { display: inline-flex; gap: 6px; }
div.wp-vrt-bulk-toggle { margin: 0; }
.wp-vrt-bulk-toggle .components-toggle-control__label { font-weight: 500; color: #1d2327; }
.wp-vrt-section { padding: 12px; border: 1px solid #dcdcde; border-radius: 6px; background: #fff; }
.wp-vrt-section-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
.wp-vrt-section-desc { margin: 4px 0 0; color: #646970; }
.wp-vrt-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 28px; height: 24px; padding: 0 8px; border-radius: 999px; background: #f0f0f1; color: #50575e; font-size: 12px; font-weight: 600; }
.wp-vrt-list { display: grid; gap: 8px; }
.wp-vrt-list-grid { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
.wp-vrt-block .wp-vrt-list-grid { grid-template-columns: 1fr; }
.wp-vrt-row { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 12px; padding: 12px; border: 1px solid #dcdcde; border-radius: 6px; background: #fff; transition: background-color 120ms ease, border-color 120ms ease; }
.wp-vrt-row.is-disabled { opacity: 0.6; }
.wp-vrt-row:hover { border-color: #8c8f94; background: #f6f7f7; }
.wp-vrt-row-main { display: grid; gap: 4px; min-width: 0; }
.wp-vrt-row-label { font-weight: 500; color: #1d2327; display: flex; align-items: center; gap: 8px; }
.wp-vrt-row-label .components-base-control { margin: 0; }
.wp-vrt-toggle { display: inline-flex; align-items: center; margin-top: -1px; }
.wp-vrt-toggle .components-toggle-control__label { display: none; }
.wp-vrt-row-link { display: inline-flex; align-items: center; gap: 6px; color: #1d2327; text-decoration: none; }
.wp-vrt-row-link:hover { text-decoration: underline; }
.wp-vrt-row-link-icon { font-size: 14px; width: 14px; height: 14px; }
.wp-vrt-row-actions { display: inline-flex; align-items: center; gap: 8px; }
.wp-vrt-row-action { min-width: auto; }
.wp-vrt-row-toggle { min-width: 140px; }
.wp-vrt-tag { margin-left: 0; padding: 2px 6px; border-radius: 999px; background: #f0f0f1; color: #50575e; font-size: 11px; font-weight: 600; }
.wp-vrt-tag-disabled { background: #f6d6d6; color: #8a2424; }
.wp-vrt-tag-vrt { background: #dbeafe; color: #1d4ed8; }
.wp-vrt-empty { margin: 0; color: #646970; }
@media (max-width: 960px) {
  .wp-vrt-row { grid-template-columns: 1fr; }
  .wp-vrt-row-actions { justify-content: flex-start; }
}
@media (max-width: 782px) {
  .wp-vrt-header { flex-direction: column; }
  .wp-vrt-actions { justify-content: flex-start; }
  .wp-vrt-search { max-width: 100%; }
}
';
    }

    private static function get_page_data(): array {
        $show_disabled = \get_option(self::OPTION_SHOW_DISABLED, '0') === '1';

        $block_registry = new BlockRegistry();
        $blocks = $block_registry->get_discoverable_blocks($show_disabled);
        $block_families = $block_registry->get_block_families();
        $block_categories = $block_registry->get_block_categories();
        $patterns = (new PatternRegistry())->get_discoverable_patterns($show_disabled);
        $templates = (new TemplateRegistry())->get_discoverable_templates($show_disabled);
        $parts = (new TemplateRegistry())->get_discoverable_template_parts($show_disabled);
        $scenarios = (new ScenarioRegistry())->get_discoverable_scenarios($show_disabled);

        $base_url = \home_url('/wp-vrt');
        $discovery_url = \home_url('/wp-json/wp-vrt/v1/discover');

        $block_items = [];
        foreach ($blocks as $block) {
            $block_items[] = [
                'label' => $block['title'],
                'url' => $base_url . '/block/' . $block['slug'],
                'isDisabled' => (bool) ($block['disabled'] ?? false),
                'toggleUrl' => self::get_toggle_item_url('block', $block['name'], !empty($block['disabled'] ?? false)),
            ];

            foreach ($block['variations'] as $variation) {
                $block_items[] = [
                    'label' => $block['title'] . ' - ' . $variation['label'],
                    'url' => $base_url . '/block/' . $block['slug'] . '/' . $variation['name'],
                    'isDisabled' => (bool) ($block['disabled'] ?? false),
                    'toggleUrl' => self::get_toggle_item_url('block', $block['name'], !empty($block['disabled'] ?? false)),
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
                    'isDisabled' => (bool) ($blocks[$block_name]['disabled'] ?? false),
                    'toggleUrl' => self::get_toggle_item_url('block', $blocks[$block_name]['name'], !empty($blocks[$block_name]['disabled'] ?? false)),
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
        $vrt_pattern_items = [];
        $vrt_pattern_count = 0;
        foreach ($patterns as $pattern) {
            $is_vrt = self::pattern_is_vrt($pattern);
            if ($is_vrt) {
                $vrt_pattern_count++;
            }
            $item = [
                'label' => $pattern['title'],
                'url' => $base_url . '/pattern/' . $pattern['slug'],
                'isDynamic' => (bool) ($pattern['is_dynamic'] ?? false),
                'isDisabled' => (bool) ($pattern['disabled'] ?? false),
                'isVrt' => $is_vrt,
                'toggleType' => 'pattern',
                'toggleUrl' => self::get_toggle_item_url('pattern', $pattern['name'], !empty($pattern['disabled'] ?? false)),
            ];
            if ($is_vrt) {
                $vrt_pattern_items[] = $item;
            } else {
                $pattern_items[] = $item;
            }
        }

        $template_items = [];
        foreach ($templates as $template) {
            $template_items[] = [
                'label' => $template['title'],
                'url' => $base_url . '/template/' . $template['slug'],
                'isDynamic' => (bool) ($template['is_dynamic'] ?? false),
                'isDisabled' => (bool) ($template['disabled'] ?? false),
                'toggleType' => 'template',
                'toggleUrl' => self::get_toggle_item_url('template', $template['slug'], !empty($template['disabled'] ?? false)),
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
                'isDisabled' => (bool) ($part['disabled'] ?? false),
                'toggleType' => 'template-part',
                'toggleUrl' => self::get_toggle_item_url('template-part', $part['slug'], !empty($part['disabled'] ?? false)),
            ];
        }

        $scenario_items = [];
        foreach ($scenarios as $scenario) {
            $scenario_items[] = [
                'label' => $scenario['title'],
                'url' => $base_url . '/scenario/' . $scenario['slug'],
                'isDisabled' => (bool) ($scenario['disabled'] ?? false),
                'toggleType' => 'scenario',
                'toggleUrl' => self::get_toggle_item_url('scenario', $scenario['slug'], !empty($scenario['disabled'] ?? false)),
            ];
        }

        foreach ($vrt_pattern_items as $item) {
            $scenario_items[] = $item;
        }

        $show_disabled_url = \add_query_arg(
            [
                'action' => 'wp_vrt_toggle_disabled',
                'show_disabled' => $show_disabled ? '0' : '1',
                '_wpnonce' => \wp_create_nonce('wp_vrt_toggle_disabled'),
            ],
            \admin_url('admin-post.php')
        );

        return [
            'baseUrl' => $base_url,
            'discoveryUrl' => $discovery_url,
            'showDisabled' => $show_disabled,
            'showDisabledUrl' => $show_disabled_url,
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'toggleNonce' => \wp_create_nonce('wp_vrt_toggle_item'),
            'toggleAllNonce' => \wp_create_nonce('wp_vrt_toggle_all'),
            'stats' => [
                'Blocks' => count($blocks),
                'Patterns' => count($pattern_items),
                'Templates' => count($templates),
                'Template Parts' => count($parts),
                'Scenarios' => count($scenario_items),
            ],
                'sections' => [
                    [
                        'title' => 'Blocks',
                        'description' => 'All registered blocks including variations.',
                        'type' => 'block',
                        'bulkDisableUrl' => self::get_toggle_all_url('block', true),
                        'bulkEnableUrl' => self::get_toggle_all_url('block', false),
                        'groups' => $family_items,
                        'categories' => self::map_block_categories($block_categories, $blocks, $base_url),
                        'items' => $block_items,
                    ],
                [
                    'title' => 'Patterns',
                    'description' => 'Theme and core patterns.',
                    'type' => 'pattern',
                    'bulkDisableUrl' => self::get_toggle_all_url('pattern', true),
                    'bulkEnableUrl' => self::get_toggle_all_url('pattern', false),
                    'categories' => self::map_pattern_categories($patterns, $base_url),
                    'items' => $pattern_items,
                ],
                [
                    'title' => 'Templates',
                    'description' => 'Site templates for block themes.',
                    'type' => 'template',
                    'bulkDisableUrl' => self::get_toggle_all_url('template', true),
                    'bulkEnableUrl' => self::get_toggle_all_url('template', false),
                    'items' => $template_items,
                ],
                [
                    'title' => 'Template Parts',
                    'description' => 'Reusable template fragments.',
                    'type' => 'template-part',
                    'bulkDisableUrl' => self::get_toggle_all_url('template-part', true),
                    'bulkEnableUrl' => self::get_toggle_all_url('template-part', false),
                    'categories' => self::map_template_part_categories($parts, $base_url),
                    'items' => $part_items,
                ],
                [
                    'title' => 'Scenarios',
                    'description' => 'Custom scenarios registered by themes/plugins.',
                    'type' => 'scenario',
                    'bulkDisableUrl' => self::get_toggle_all_url('scenario', true),
                    'bulkEnableUrl' => self::get_toggle_all_url('scenario', false),
                    'items' => $scenario_items,
                ],
            ],
        ];
    }

    private static function get_toggle_item_url(string $type, string $id, bool $is_disabled): string {
        return \add_query_arg(
            [
                'action' => 'wp_vrt_toggle_item',
                'type' => $type,
                'id' => $id,
                'disable' => $is_disabled ? '0' : '1',
                '_wpnonce' => \wp_create_nonce('wp_vrt_toggle_item'),
            ],
            \admin_url('admin-post.php')
        );
    }

    private static function get_disabled_items(): array {
        $disabled = \get_option(self::OPTION_DISABLED_ITEMS, []);
        return is_array($disabled) ? $disabled : [];
    }

    private static function update_disabled_item(string $type, string $id, bool $disable): void {
        if ($type === '' || $id === '') {
            return;
        }

        $disabled = self::get_disabled_items();
        if (!isset($disabled[$type]) || !is_array($disabled[$type])) {
            $disabled[$type] = [];
        }

        if ($disable) {
            $disabled[$type][$id] = true;
        } else {
            unset($disabled[$type][$id]);
        }

        \update_option(self::OPTION_DISABLED_ITEMS, $disabled);
    }

    private static function update_disabled_all(string $type, bool $disable): void {
        if ($type === '') {
            return;
        }

        $disabled = self::get_disabled_items();
        $items = self::get_type_items($type);

        if ($type === 'pattern-vrt') {
            $storage_type = 'pattern';
            if (!isset($disabled[$storage_type]) || !is_array($disabled[$storage_type])) {
                $disabled[$storage_type] = [];
            }

            if ($disable) {
                foreach ($items as $id) {
                    $disabled[$storage_type][$id] = true;
                }
            } else {
                foreach ($items as $id) {
                    unset($disabled[$storage_type][$id]);
                }
                if (empty($disabled[$storage_type])) {
                    unset($disabled[$storage_type]);
                }
            }

            \update_option(self::OPTION_DISABLED_ITEMS, $disabled);
            return;
        }

        if ($disable) {
            $disabled[$type] = [];
            foreach ($items as $id) {
                $disabled[$type][$id] = true;
            }
        } else {
            unset($disabled[$type]);
        }

        \update_option(self::OPTION_DISABLED_ITEMS, $disabled);
    }

    private static function update_disabled_group(string $type, array $ids, bool $disable): void {
        if ($type === '' || empty($ids)) {
            return;
        }

        $disabled = self::get_disabled_items();
        if (!isset($disabled[$type]) || !is_array($disabled[$type])) {
            $disabled[$type] = [];
        }

        foreach ($ids as $id) {
            $id = is_string($id) ? $id : '';
            if ($id === '') {
                continue;
            }
            if ($disable) {
                $disabled[$type][$id] = true;
            } else {
                unset($disabled[$type][$id]);
            }
        }

        if (empty($disabled[$type])) {
            unset($disabled[$type]);
        }

        \update_option(self::OPTION_DISABLED_ITEMS, $disabled);
    }

    private static function get_type_items(string $type): array {
        if ($type === 'block') {
            $blocks = (new BlockRegistry())->get_discoverable_blocks(true);
            return array_keys($blocks);
        }

        if ($type === 'pattern') {
            $patterns = (new PatternRegistry())->get_discoverable_patterns(true);
            return array_map(static fn ($pattern) => (string) $pattern['name'], $patterns);
        }

        if ($type === 'pattern-vrt') {
            $patterns = (new PatternRegistry())->get_discoverable_patterns(true);
            $vrt_patterns = array_filter($patterns, [self::class, 'pattern_is_vrt']);
            return array_map(static fn ($pattern) => (string) $pattern['name'], $vrt_patterns);
        }

        if ($type === 'template') {
            $templates = (new TemplateRegistry())->get_discoverable_templates(true);
            return array_map(static fn ($template) => (string) $template['slug'], $templates);
        }

        if ($type === 'template-part') {
            $parts = (new TemplateRegistry())->get_discoverable_template_parts(true);
            return array_map(static fn ($part) => (string) $part['slug'], $parts);
        }

        if ($type === 'scenario') {
            $scenarios = (new ScenarioRegistry())->get_discoverable_scenarios(true);
            return array_map(static fn ($scenario) => (string) $scenario['slug'], $scenarios);
        }

        return [];
    }

    private static function get_toggle_all_url(string $type, bool $disable): string {
        return \add_query_arg(
            [
                'action' => 'wp_vrt_toggle_all',
                'type' => $type,
                'disable' => $disable ? '1' : '0',
                '_wpnonce' => \wp_create_nonce('wp_vrt_toggle_all'),
            ],
            \admin_url('admin-post.php')
        );
    }

    private static function map_block_categories(array $categories, array $blocks, string $base_url): array {
        $items = [];
        foreach ($categories as $category) {
            if (empty($category['blocks']) || !is_array($category['blocks'])) {
                continue;
            }

            $category_items = [];
            foreach ($category['blocks'] as $block_name) {
                if (!isset($blocks[$block_name])) {
                    continue;
                }

                $block = $blocks[$block_name];
                $category_items[] = [
                    'label' => $block['title'],
                    'url' => $base_url . '/block/' . $block['slug'],
                    'isDisabled' => (bool) ($block['disabled'] ?? false),
                    'toggleUrl' => self::get_toggle_item_url('block', $block['name'], !empty($block['disabled'] ?? false)),
                ];
            }

            if (empty($category_items)) {
                continue;
            }

            $items[] = [
                'title' => (string) ($category['title'] ?? 'Blocks'),
                'description' => (string) ($category['description'] ?? ''),
                'slug' => (string) ($category['slug'] ?? ''),
                'type' => 'block',
                'items' => $category_items,
            ];
        }

        return $items;
    }

    private static function map_pattern_categories(array $patterns, string $base_url): array {
        $groups = [];
        foreach ($patterns as $pattern) {
            if (self::pattern_is_vrt($pattern)) {
                continue;
            }
            $categories = $pattern['categories'] ?? [];
            if (!is_array($categories) || empty($categories)) {
                $categories = ['uncategorized'];
            }

            foreach ($categories as $category) {
                $category = is_string($category) && $category !== '' ? $category : 'uncategorized';
                if (!isset($groups[$category])) {
                    $groups[$category] = [
                        'title' => ucwords(str_replace(['-', '_'], ' ', $category)),
                        'description' => '',
                        'slug' => $category,
                        'type' => 'pattern',
                        'items' => [],
                    ];
                }

            $groups[$category]['items'][] = [
                'label' => $pattern['title'],
                'url' => $base_url . '/pattern/' . $pattern['slug'],
                'isDynamic' => (bool) ($pattern['is_dynamic'] ?? false),
                'isDisabled' => (bool) ($pattern['disabled'] ?? false),
                'isVrt' => self::pattern_is_vrt($pattern),
                'toggleType' => 'pattern',
                'toggleUrl' => self::get_toggle_item_url('pattern', $pattern['name'], !empty($pattern['disabled'] ?? false)),
            ];
            }
        }

        $sorted = array_values($groups);
        usort($sorted, static fn ($a, $b) => strcasecmp((string) $a['title'], (string) $b['title']));
        return $sorted;
    }

    private static function map_template_part_categories(array $parts, string $base_url): array {
        $groups = [];
        foreach ($parts as $part) {
            $area = $part['area'] ?? 'uncategorized';
            $area = is_string($area) && $area !== '' ? $area : 'uncategorized';
            if (!isset($groups[$area])) {
                $groups[$area] = [
                    'title' => ucwords(str_replace(['-', '_'], ' ', $area)),
                    'description' => '',
                    'slug' => $area,
                    'type' => 'template-part',
                    'items' => [],
                ];
            }

            $label = $part['title'];
            if (!empty($part['area'])) {
                $label .= ' (' . $part['area'] . ')';
            }

            $groups[$area]['items'][] = [
                'label' => $label,
                'url' => $base_url . '/template-part/' . $part['slug'],
                'isDynamic' => (bool) ($part['is_dynamic'] ?? false),
                'isDisabled' => (bool) ($part['disabled'] ?? false),
                'toggleUrl' => self::get_toggle_item_url('template-part', $part['slug'], !empty($part['disabled'] ?? false)),
            ];
        }

        $sorted = array_values($groups);
        usort($sorted, static fn ($a, $b) => strcasecmp((string) $a['title'], (string) $b['title']));
        return $sorted;
    }

    private static function pattern_is_vrt(array $pattern): bool {
        $categories = $pattern['categories'] ?? [];
        if (!is_array($categories)) {
            return false;
        }

        return in_array(PatternRegistry::VRT_CATEGORY, $categories, true);
    }
}
