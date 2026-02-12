<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateRegistry {
    public function get_template_content(string $slug): ?string {
        $template = $this->get_template_by_slug($slug, 'wp_template');
        if (!$template || empty($template->content)) {
            return null;
        }

        return (string) $template->content;
    }

    public function get_template_part_content(string $slug): ?string {
        $template = $this->get_template_by_slug($slug, 'wp_template_part');
        if (!$template || empty($template->content)) {
            return null;
        }

        return (string) $template->content;
    }

    public function get_discoverable_templates(bool $include_disabled = false): array {
        $items = [];
        foreach (\get_block_templates([], 'wp_template') as $template) {
            $enabled = $this->is_template_enabled($template, 'template');
            if (!$enabled && !$include_disabled) {
                continue;
            }
            $items[] = [
                'slug' => $template->slug,
                'title' => $template->title ?? $template->slug,
                'is_dynamic' => !empty($template->content) ? $this->is_dynamic_content((string) $template->content) : false,
                'disabled' => !$enabled,
            ];
        }

        return \apply_filters('wp_vrt_discoverable_templates', $items);
    }

    public function get_discoverable_template_parts(bool $include_disabled = false): array {
        $items = [];
        foreach (\get_block_templates([], 'wp_template_part') as $template) {
            $enabled = $this->is_template_enabled($template, 'template-part');
            if (!$enabled && !$include_disabled) {
                continue;
            }
            $items[] = [
                'slug' => $template->slug,
                'title' => $template->title ?? $template->slug,
                'area' => $template->area ?? null,
                'is_dynamic' => !empty($template->content) ? $this->is_dynamic_content((string) $template->content) : false,
                'disabled' => !$enabled,
            ];
        }

        return \apply_filters('wp_vrt_discoverable_template_parts', $items);
    }

    private function get_template_by_slug(string $slug, string $type): ?\WP_Block_Template {
        if ($slug === '') {
            return null;
        }

        foreach (\get_block_templates([], $type) as $template) {
            if ($template->slug === $slug) {
                return $template;
            }
        }

        return null;
    }

    private function is_template_enabled(\WP_Block_Template $template, string $type): bool {
        $enabled = true;
        $enabled = \apply_filters('wp_vrt_is_item_enabled', $enabled, $type, $template->slug, $template);
        $enabled = $type === 'template'
            ? \apply_filters('wp_vrt_template_enabled', $enabled, $template)
            : \apply_filters('wp_vrt_template_part_enabled', $enabled, $template);
        return (bool) $enabled;
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
}
