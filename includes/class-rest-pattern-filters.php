<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class RestPatternFilters {
    public static function init(): void {
        add_filter('rest_post_dispatch', [self::class, 'filter_block_patterns'], 10, 3);
    }

    public static function filter_block_patterns($response, $server, $request) {
        if (!$response instanceof \WP_REST_Response || !$request instanceof \WP_REST_Request) {
            return $response;
        }

        if (!self::should_filter_request($request)) {
            return $response;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            return $response;
        }

        if (!array_is_list($data)) {
            return $response;
        }

        $filtered = array_values(array_filter($data, [self::class, 'pattern_is_allowed']));
        $response->set_data($filtered);

        return $response;
    }

    private static function should_filter_request(\WP_REST_Request $request): bool {
        $route = $request->get_route();
        if (!is_string($route) || !str_starts_with($route, '/wp/v2/block-patterns')) {
            return false;
        }

        $context = $request->get_param('context');
        return is_string($context) && $context === 'edit';
    }

    private static function pattern_is_allowed($pattern): bool {
        if (!is_array($pattern)) {
            return true;
        }

        $categories = $pattern['categories'] ?? [];
        if (!is_array($categories)) {
            return true;
        }

        return !in_array(PatternRegistry::VRT_CATEGORY, $categories, true);
    }
}
