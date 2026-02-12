<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class DiscoveryApi {
    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route('wp-vrt/v1', '/discover', [
            'methods' => 'GET',
            'callback' => [self::class, 'handle_discover'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle_discover(): \WP_REST_Response {
        $include_disabled = isset($_GET['include_disabled']) && $_GET['include_disabled'] !== '0';

        $blocks = (new BlockRegistry())->get_discoverable_blocks($include_disabled);
        $items = [
            'blocks' => [],
            'patterns' => [],
            'template_parts' => [],
            'templates' => [],
            'scenarios' => [],
        ];

        foreach ($blocks as $block) {
            $block_item = [
                'name' => $block['name'],
                'title' => $block['title'],
                'url' => '/wp-vrt/block/' . $block['slug'],
                'variations' => [],
                'disabled' => $block['disabled'] ?? false,
            ];

            foreach ($block['variations'] as $variation) {
                $block_item['variations'][] = [
                    'name' => $variation['name'],
                    'label' => $variation['label'],
                    'url' => '/wp-vrt/block/' . $block['slug'] . '/' . $variation['name'],
                ];
            }

            $items['blocks'][] = $block_item;
        }

        $patterns = (new PatternRegistry())->get_discoverable_patterns($include_disabled);
        foreach ($patterns as $pattern) {
            $items['patterns'][] = [
                'name' => $pattern['name'],
                'title' => $pattern['title'],
                'url' => '/wp-vrt/pattern/' . $pattern['slug'],
                'categories' => $pattern['categories'],
                'disabled' => $pattern['disabled'] ?? false,
                'is_dynamic' => $pattern['is_dynamic'] ?? false,
            ];
        }

        $templates = (new TemplateRegistry())->get_discoverable_templates($include_disabled);
        foreach ($templates as $template) {
            $items['templates'][] = [
                'slug' => $template['slug'],
                'title' => $template['title'],
                'url' => '/wp-vrt/template/' . $template['slug'],
                'disabled' => $template['disabled'] ?? false,
                'is_dynamic' => $template['is_dynamic'] ?? false,
            ];
        }

        $template_parts = (new TemplateRegistry())->get_discoverable_template_parts($include_disabled);
        foreach ($template_parts as $part) {
            $items['template_parts'][] = [
                'slug' => $part['slug'],
                'title' => $part['title'],
                'area' => $part['area'],
                'url' => '/wp-vrt/template-part/' . $part['slug'],
                'disabled' => $part['disabled'] ?? false,
                'is_dynamic' => $part['is_dynamic'] ?? false,
            ];
        }

        $scenarios = (new ScenarioRegistry())->get_discoverable_scenarios($include_disabled);
        foreach ($scenarios as $scenario) {
            $items['scenarios'][] = [
                'slug' => $scenario['slug'],
                'title' => $scenario['title'],
                'description' => $scenario['description'],
                'url' => '/wp-vrt/scenario/' . $scenario['slug'],
                'disabled' => $scenario['disabled'] ?? false,
            ];
        }

        $response = [
            'base_url' => home_url('/wp-vrt'),
            'timestamp' => gmdate('c'),
            'items' => $items,
        ];

        return new \WP_REST_Response($response, 200);
    }
}
