<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class VirtualPages {
    public const QUERY_TYPE = 'wp_vrt_type';
    public const QUERY_SLUG = 'wp_vrt_slug';
    public const QUERY_VARIATION = 'wp_vrt_variation';

    public static function init(): void {
        add_action('init', [self::class, 'register_rewrite_rules']);
        add_action('template_redirect', [self::class, 'handle_request'], 1);
        add_filter('query_vars', [self::class, 'register_query_vars']);
    }

    public static function register_query_vars(array $vars): array {
        $vars[] = self::QUERY_TYPE;
        $vars[] = self::QUERY_SLUG;
        $vars[] = self::QUERY_VARIATION;
        return $vars;
    }

    public static function register_rewrite_rules(): void {
        add_rewrite_tag('%' . self::QUERY_TYPE . '%', '([^&]+)');
        add_rewrite_tag('%' . self::QUERY_SLUG . '%', '([^&]+)');
        add_rewrite_tag('%' . self::QUERY_VARIATION . '%', '([^&]+)');

        add_rewrite_rule(
            '^wp-vrt/([^/]+)/([^/]+)/?([^/]*)/?$',
            'index.php?' . self::QUERY_TYPE . '=$matches[1]&' . self::QUERY_SLUG . '=$matches[2]&' . self::QUERY_VARIATION . '=$matches[3]',
            'top'
        );
    }

    public static function handle_request(): void {
        $type = get_query_var(self::QUERY_TYPE);
        $slug = get_query_var(self::QUERY_SLUG);
        $variation = get_query_var(self::QUERY_VARIATION);

        if (!$type) {
            $parsed = self::parse_request_uri();
            if (!$parsed) {
                return;
            }
            $type = $parsed['type'];
            $slug = $parsed['slug'];
            $variation = $parsed['variation'];
        }

        $variation = is_string($variation) && $variation !== '' ? $variation : null;

        $allowed_types = ['block', 'pattern', 'template', 'template-part', 'scenario'];
        if (!in_array($type, $allowed_types, true)) {
            wp_die('Invalid VRT type', 'Invalid Request', ['response' => 400]);
        }

        $content = self::load_content($type, (string) $slug, $variation);
        if ($content === null) {
            wp_die('VRT item not found', 'Not Found', ['response' => 404]);
        }
        
        // DEBUG: Log what content is loaded
        if (function_exists('error_log')) {
            error_log('[WPVRT_DEBUG] Content for ' . $type . '/' . $slug . ': ' . strlen($content ?? '') . ' bytes');
            if (strlen($content ?? '') < 500) {
                error_log('[WPVRT_DEBUG] Content: ' . var_export($content, true));
            }
        }

        $dynamic_context = null;
        if (DynamicContext::content_has_dynamic_blocks($content)) {
            $dynamic_context = new DynamicContext();
            $dynamic_context->setup();
        }

        try {
            $renderer = new Renderer();
            $html = $renderer->render($type, (string) $slug, $variation, $content);
        } finally {
            if ($dynamic_context) {
                $dynamic_context->reset();
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    private static function parse_request_uri(): ?array {
        if (empty($_SERVER['REQUEST_URI'])) {
            return null;
        }

        $path = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }

        $path = trim($path, '/');
        if (!str_starts_with($path, 'wp-vrt/')) {
            return null;
        }

        $parts = explode('/', $path);
        if (count($parts) < 3) {
            return null;
        }

        return [
            'type' => $parts[1] ?? '',
            'slug' => $parts[2] ?? '',
            'variation' => $parts[3] ?? null,
        ];
    }

    private static function load_content(string $type, string $slug, ?string $variation): ?string {
        if ($slug === '') {
            return null;
        }

        if ($type === 'block') {
            $registry = new BlockRegistry();
            return $registry->get_block_content($slug, $variation);
        }

        if ($type === 'pattern') {
            $registry = new PatternRegistry();
            return $registry->get_pattern_content($slug);
        }

        if ($type === 'template') {
            $registry = new TemplateRegistry();
            return $registry->get_template_content($slug);
        }

        if ($type === 'template-part') {
            $registry = new TemplateRegistry();
            return $registry->get_template_part_content($slug);
        }

        if ($type === 'scenario') {
            $registry = new ScenarioRegistry();
            return $registry->get_scenario_content($slug);
        }

        return null;
    }
}
