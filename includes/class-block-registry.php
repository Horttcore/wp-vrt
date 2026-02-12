<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class BlockRegistry {
    private SampleContent $sample_content;

    public function __construct() {
        $this->sample_content = new SampleContent();
    }

    public function get_block_content(string $slug, ?string $variation): ?string {
        $block_name = $this->get_block_name_from_slug($slug);
        if (!$block_name) {
            return null;
        }

        $markup = $this->sample_content->generate_block_markup($block_name, $variation);
        return $markup !== '' ? $markup : null;
    }

    public function get_discoverable_blocks(bool $include_disabled = false): array {
        $blocks = [];
        $registry = \WP_Block_Type_Registry::get_instance();
        foreach ($registry->get_all_registered() as $block_name => $block_type) {
            if (!$this->is_supported_block($block_name)) {
                continue;
            }

            $enabled = $this->is_block_enabled($block_name, $block_type);
            if (!$enabled && !$include_disabled) {
                continue;
            }
            $blocks[$block_name] = [
                'name' => $block_name,
                'title' => $block_type->title ?? $block_name,
                'slug' => $this->block_name_to_slug($block_name),
                'variations' => $this->get_block_variations($block_name),
                'disabled' => !$enabled,
            ];
        }

        return \apply_filters('wp_vrt_discoverable_blocks', $blocks);
    }

    public function get_block_families(): array {
        $families = $this->get_default_families();
        $families = \apply_filters('wp_vrt_block_families', $families);

        $registry = \WP_Block_Type_Registry::get_instance();
        foreach ($registry->get_all_registered() as $block_name => $block_type) {
            if (!$this->is_supported_block($block_name)) {
                continue;
            }

            if (!empty($block_type->parent) && is_array($block_type->parent)) {
                foreach ($block_type->parent as $parent) {
                    $families = $this->add_family_member($families, $parent, $block_name);
                }
            }
        }

        return $this->normalize_families($families);
    }

    private function get_block_name_from_slug(string $slug): ?string {
        $registry = \WP_Block_Type_Registry::get_instance();
        foreach ($registry->get_all_registered() as $block_name => $block_type) {
            if (!$this->is_supported_block($block_name)) {
                continue;
            }
            if ($this->block_name_to_slug($block_name) === $slug) {
                return $block_name;
            }
        }

        return null;
    }

    private function block_name_to_slug(string $block_name): string {
        return str_replace('/', '-', $block_name);
    }

    private function get_block_variations(string $block_name): array {
        $variations = [];
        if (class_exists('WP_Block_Styles_Registry')) {
            $styles = \WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block($block_name);
            foreach ($styles as $style) {
                if (empty($style['name'])) {
                    continue;
                }
                $variations[] = [
                    'name' => $style['name'],
                    'label' => $style['label'] ?? $style['name'],
                ];
            }
        }

        return \apply_filters('wp_vrt_block_variations', $variations, $block_name);
    }

    private function is_supported_block(string $block_name): bool {
        $allowlist = \apply_filters('wp_vrt_block_allowlist', []);
        $denylist = \apply_filters('wp_vrt_block_denylist', []);

        if (is_array($allowlist) && !empty($allowlist)) {
            return in_array($block_name, $allowlist, true);
        }

        if (is_array($denylist) && in_array($block_name, $denylist, true)) {
            return false;
        }

        return true;
    }

    private function is_block_enabled(string $block_name, $block_type): bool {
        $enabled = true;
        $enabled = \apply_filters('wp_vrt_is_item_enabled', $enabled, 'block', $block_name, $block_type);
        $enabled = \apply_filters('wp_vrt_block_enabled', $enabled, $block_name, $block_type);
        return (bool) $enabled;
    }

    private function get_default_families(): array {
        return [
            [
                'title' => 'Columns',
                'description' => 'Columns work with Column child blocks.',
                'blocks' => ['core/columns', 'core/column'],
            ],
            [
                'title' => 'Buttons',
                'description' => 'Buttons container with Button items.',
                'blocks' => ['core/buttons', 'core/button'],
            ],
            [
                'title' => 'Gallery',
                'description' => 'Gallery uses Image items.',
                'blocks' => ['core/gallery', 'core/image'],
            ],
            [
                'title' => 'Navigation',
                'description' => 'Navigation is composed of link and submenu blocks.',
                'blocks' => ['core/navigation', 'core/navigation-link', 'core/navigation-submenu'],
            ],
        ];
    }

    private function add_family_member(array $families, string $parent, string $child): array {
        foreach ($families as $index => $family) {
            if (empty($family['blocks']) || !is_array($family['blocks'])) {
                continue;
            }

            if (in_array($parent, $family['blocks'], true)) {
                if (!in_array($child, $family['blocks'], true)) {
                    $families[$index]['blocks'][] = $child;
                }
                return $families;
            }
        }

        $families[] = [
            'title' => $parent,
            'description' => 'Auto-grouped blocks for ' . $parent . '.',
            'blocks' => [$parent, $child],
        ];

        return $families;
    }

    private function normalize_families(array $families): array {
        $normalized = [];
        foreach ($families as $family) {
            if (empty($family['blocks']) || !is_array($family['blocks'])) {
                continue;
            }

            $blocks = array_values(array_unique(array_filter($family['blocks'], 'is_string')));
            if (empty($blocks)) {
                continue;
            }

            $normalized[] = [
                'title' => (string) ($family['title'] ?? 'Block Family'),
                'description' => (string) ($family['description'] ?? ''),
                'blocks' => $blocks,
            ];
        }

        return $normalized;
    }
}
