<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class PatternRegistry {
    public const VRT_CATEGORY = 'visual-regression-testing';

    public function get_pattern_content(string $slug): ?string {
        $pattern = $this->get_pattern_by_slug($slug);
        if (!$pattern || empty($pattern['content'])) {
            return null;
        }

        return (string) $pattern['content'];
    }

    public function get_discoverable_patterns(bool $include_disabled = false): array {
        $patterns = [];
        if (!class_exists('WP_Block_Patterns_Registry')) {
            return $patterns;
        }

        $registry = \WP_Block_Patterns_Registry::get_instance();
        foreach ($registry->get_all_registered() as $pattern) {
            if (empty($pattern['name'])) {
                continue;
            }
            $content = (string) ($pattern['content'] ?? '');
            $enabled = $this->is_pattern_enabled($pattern);
            if (!$enabled && !$include_disabled) {
                continue;
            }
            $patterns[$pattern['name']] = [
                'name' => $pattern['name'],
                'title' => $pattern['title'] ?? $pattern['name'],
                'slug' => $this->pattern_name_to_slug($pattern['name']),
                'categories' => $pattern['categories'] ?? [],
                'is_dynamic' => $content !== '' ? $this->is_dynamic_content($content) : false,
                'disabled' => !$enabled,
            ];
        }

        foreach ($this->get_stored_patterns() as $pattern) {
            if (empty($pattern['name'])) {
                continue;
            }
            if (isset($patterns[$pattern['name']])) {
                continue;
            }
            $content = (string) ($pattern['content'] ?? '');
            $enabled = $this->is_pattern_enabled($pattern);
            if (!$enabled && !$include_disabled) {
                continue;
            }
            $patterns[$pattern['name']] = [
                'name' => $pattern['name'],
                'title' => $pattern['title'] ?? $pattern['name'],
                'slug' => $this->pattern_name_to_slug($pattern['name']),
                'categories' => $pattern['categories'] ?? [],
                'is_dynamic' => $content !== '' ? $this->is_dynamic_content($content) : false,
                'disabled' => !$enabled,
            ];
        }

        return \apply_filters('wp_vrt_discoverable_patterns', array_values($patterns));
    }

    private function get_pattern_by_slug(string $slug): ?array {
        if (!class_exists('WP_Block_Patterns_Registry')) {
            return null;
        }

        $registry = \WP_Block_Patterns_Registry::get_instance();
        foreach ($registry->get_all_registered() as $pattern) {
            if (empty($pattern['name'])) {
                continue;
            }
            if ($this->pattern_name_to_slug($pattern['name']) === $slug) {
                return $pattern;
            }
        }

        foreach ($this->get_stored_patterns() as $pattern) {
            if (empty($pattern['name'])) {
                continue;
            }
            if ($this->pattern_name_to_slug($pattern['name']) === $slug) {
                return $pattern;
            }
        }

        return null;
    }

    private function get_stored_patterns(): array {
        $post_types = [];
        if (\post_type_exists('wp_block_pattern')) {
            $post_types[] = 'wp_block_pattern';
        }
        if (\post_type_exists('wp_block')) {
            $post_types[] = 'wp_block';
        }

        if (empty($post_types)) {
            return [];
        }

        $posts = \get_posts([
            'post_type' => $post_types,
            'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $patterns = [];
        foreach ($posts as $post) {
            $post_name = $post->post_name;
            if (!is_string($post_name) || $post_name === '') {
                $post_name = 'pattern-' . $post->ID;
            }

            $categories = \wp_get_object_terms($post->ID, 'wp_pattern_category', ['fields' => 'slugs']);
            if (\is_wp_error($categories)) {
                $categories = [];
            }

            $should_include = $post->post_type === 'wp_block_pattern' || !empty($categories);
            if (!$should_include) {
                continue;
            }

            $patterns[] = [
                'name' => 'user/' . $post_name,
                'title' => $post->post_title !== '' ? $post->post_title : $post_name,
                'content' => $post->post_content,
                'categories' => is_array($categories) ? $categories : [],
            ];
        }

        return $patterns;
    }

    private function pattern_name_to_slug(string $name): string {
        return str_replace('/', '-', $name);
    }

    private function is_dynamic_content(string $content): bool {
        if ($content === '') {
            return false;
        }

        $dynamic_blocks = [
            'core/query',
            'core/post-content',
            'core/post-title',
            'core/post-date',
            'core/post-author',
            'core/post-terms',
            'core/post-excerpt',
            'core/post-featured-image',
            'core/comments',
            'core/comments-title',
            'core/comment-template',
            'core/latest-posts',
            'core/archives',
            'core/calendar',
        ];

        return DynamicContext::content_has_dynamic_blocks($content);
    }

    private function is_pattern_enabled(array $pattern): bool {
        $enabled = true;
        $enabled = \apply_filters('wp_vrt_is_item_enabled', $enabled, 'pattern', $pattern['name'] ?? '', $pattern);
        $enabled = \apply_filters('wp_vrt_pattern_enabled', $enabled, $pattern);
        return (bool) $enabled;
    }
}
