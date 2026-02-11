<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class StyleCollector {
    public function collect_all_styles(): array {
        $styles = [
            'theme-json-variables' => '',
            'global-styles' => '',
            'block-library' => '',
            'theme-stylesheet' => '',
            'block-specific' => '',
        ];

        if (class_exists('WP_Theme_JSON_Resolver')) {
            $theme_json = \WP_Theme_JSON_Resolver::get_merged_data();
            if ($theme_json) {
                $styles['theme-json-variables'] = $theme_json->get_stylesheet(['variables', 'presets']);
            }
        }

        if (function_exists('wp_get_global_stylesheet')) {
            $styles['global-styles'] = wp_get_global_stylesheet();
        }

        $styles['block-library'] = $this->collect_block_library_styles();
        $styles['theme-stylesheet'] = $this->collect_theme_stylesheet();

        return apply_filters('wp_vrt_collected_styles', $styles, null, null);
    }

    private function collect_block_library_styles(): string {
        if (!function_exists('wp_common_block_scripts_and_styles')) {
            return '';
        }

        wp_common_block_scripts_and_styles();
        wp_enqueue_style('wp-block-library');
        wp_enqueue_style('wp-block-library-theme');

        $handles = ['wp-block-library', 'wp-block-library-theme'];
        $content = '';

        foreach ($handles as $handle) {
            $content .= $this->get_style_handle_content($handle) . "\n";
        }

        return $content;
    }

    private function collect_theme_stylesheet(): string {
        $path = get_stylesheet_directory() . '/style.css';
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            return is_string($contents) ? $contents : '';
        }
        return '';
    }

    private function get_style_handle_content(string $handle): string {
        global $wp_styles;
        if (!$wp_styles || empty($wp_styles->registered[$handle])) {
            return '';
        }

        $style = $wp_styles->registered[$handle];
        $content = '';

        if (!empty($style->src)) {
            $path = $this->map_url_to_path($style->src);
            if ($path && file_exists($path)) {
                $file_contents = file_get_contents($path);
                if (is_string($file_contents)) {
                    $content .= $file_contents;
                }
            }
        }

        if (!empty($style->extra['after']) && is_array($style->extra['after'])) {
            $content .= "\n" . implode("\n", $style->extra['after']);
        }

        return $content;
    }

    private function map_url_to_path(string $url): ?string {
        $parsed = wp_parse_url($url);
        if (!$parsed || empty($parsed['path'])) {
            return null;
        }

        $path = wp_normalize_path($parsed['path']);
        $site_path = wp_parse_url(site_url(), PHP_URL_PATH);
        $content_path = wp_parse_url(content_url(), PHP_URL_PATH);
        $includes_path = wp_parse_url(includes_url(), PHP_URL_PATH);

        if ($includes_path && str_starts_with($path, $includes_path)) {
            return wp_normalize_path(ABSPATH . WPINC . substr($path, strlen($includes_path)));
        }

        if ($content_path && str_starts_with($path, $content_path)) {
            return wp_normalize_path(WP_CONTENT_DIR . substr($path, strlen($content_path)));
        }

        if ($site_path && str_starts_with($path, $site_path)) {
            return wp_normalize_path(ABSPATH . substr($path, strlen($site_path)));
        }

        return null;
    }
}
