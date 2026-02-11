<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class Renderer {
    public function render(string $type, string $slug, ?string $variation, string $content): string {
        $styles = (new StyleCollector())->collect_all_styles();
        $rendered_content = do_blocks($content);
        $head_output = $this->capture_wp_head();
        $footer_output = $this->capture_wp_footer();

        $language = get_bloginfo('language');
        $charset = get_bloginfo('charset');
        $title = sprintf('WP VRT: %s - %s', $type, $slug);

        $html = "<!DOCTYPE html>\n";
        $html .= sprintf('<html lang="%s" class="wp-vrt-html">', esc_attr($language));
        $html .= "\n<head>\n";
        $html .= sprintf('<meta charset="%s">', esc_attr($charset)) . "\n";
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        $html .= sprintf('<title>%s</title>', esc_html($title)) . "\n";
        $html .= sprintf('<meta name="wp-vrt-type" content="%s">', esc_attr($type)) . "\n";
        $html .= sprintf('<meta name="wp-vrt-slug" content="%s">', esc_attr($slug)) . "\n";
        if ($variation) {
            $html .= sprintf('<meta name="wp-vrt-variation" content="%s">', esc_attr($variation)) . "\n";
        }

        if (!empty($styles['theme-json-variables'])) {
            $html .= "<style id=\"wp-vrt-theme-json-variables\">\n";
            $html .= $styles['theme-json-variables'] . "\n</style>\n";
        }
        if (!empty($styles['global-styles'])) {
            $html .= "<style id=\"wp-vrt-global-styles\">\n";
            $html .= $styles['global-styles'] . "\n</style>\n";
        }
        if (!empty($styles['block-library'])) {
            $html .= "<style id=\"wp-vrt-block-library\">\n";
            $html .= $styles['block-library'] . "\n</style>\n";
        }
        if (!empty($styles['theme-stylesheet'])) {
            $html .= "<style id=\"wp-vrt-theme-stylesheet\">\n";
            $html .= $styles['theme-stylesheet'] . "\n</style>\n";
        }
        if (!empty($styles['block-specific'])) {
            $html .= "<style id=\"wp-vrt-block-specific\">\n";
            $html .= $styles['block-specific'] . "\n</style>\n";
        }

        if ($head_output !== '') {
            $html .= $head_output . "\n";
        }

        $html .= "</head>\n";

        $body_classes = [
            'wp-vrt-body',
            'wp-vrt-' . sanitize_html_class($type),
        ];
        $html .= sprintf('<body class="%s" data-wp-vrt-slug="%s">', esc_attr(implode(' ', $body_classes)), esc_attr($slug));
        $html .= "\n<div class=\"wp-site-blocks\">\n";
        $html .= $rendered_content . "\n";
        $html .= "</div>\n";

        if ($footer_output !== '') {
            $html .= $footer_output . "\n";
        }

        $html .= "</body>\n</html>";

        return apply_filters('wp_vrt_html_output', $html, $type, $slug, $variation);
    }

    private function capture_wp_head(): string {
        if (function_exists('wp_enqueue_registered_block_scripts_and_styles')) {
            wp_enqueue_registered_block_scripts_and_styles();
        }

        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('wp-embed');
            wp_enqueue_script('wp-block-navigation');
            wp_enqueue_script('wp-block-interactivity');
        }

        ob_start();
        do_action('wp_head');
        return (string) ob_get_clean();
    }

    private function capture_wp_footer(): string {
        ob_start();
        do_action('wp_footer');
        return (string) ob_get_clean();
    }
}
