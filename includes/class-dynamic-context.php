<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class DynamicContext {
    private ?\WP_Query $previous_query = null;
    private ?\WP_Query $previous_the_query = null;
    private ?\WP_Post $previous_post = null;
    private ?int $temporary_post_id = null;

    public function setup(): void {
        $post_id = \apply_filters('wp_vrt_dynamic_post_id', null);

        if ($post_id) {
            $post = \get_post((int) $post_id);
        } else {
            $posts = \get_posts([
                'numberposts' => 1,
                'post_status' => 'publish',
                'post_type' => 'any',
            ]);
            $post = $posts[0] ?? null;
        }

        if (!$post instanceof \WP_Post) {
            $post = $this->create_temporary_post();
        }

        if (!$post instanceof \WP_Post) {
            return;
        }

        $this->previous_query = $GLOBALS['wp_query'] ?? null;
        $this->previous_the_query = $GLOBALS['wp_the_query'] ?? null;
        $this->previous_post = $GLOBALS['post'] ?? null;

        $query = new \WP_Query([
            'p' => $post->ID,
            'post_type' => $post->post_type,
            'post_status' => 'any',
        ]);

        $GLOBALS['wp_query'] = $query;
        $GLOBALS['wp_the_query'] = $query;
        $GLOBALS['post'] = $post;

        if ($query->have_posts()) {
            $query->the_post();
        }
    }

    public function reset(): void {
        if ($this->previous_query !== null) {
            $GLOBALS['wp_query'] = $this->previous_query;
        }

        if ($this->previous_the_query !== null) {
            $GLOBALS['wp_the_query'] = $this->previous_the_query;
        }

        if ($this->previous_post !== null) {
            $GLOBALS['post'] = $this->previous_post;
        }

        \wp_reset_postdata();

        if ($this->temporary_post_id) {
            \wp_delete_post($this->temporary_post_id, true);
            $this->temporary_post_id = null;
        }
    }

    public static function content_has_dynamic_blocks(string $content): bool {
        if ($content === '') {
            return false;
        }

        $blocks = \parse_blocks($content);
        return self::contains_dynamic_block($blocks);
    }

    public static function is_dynamic_block_name(string $block_name): bool {
        return self::is_dynamic_block($block_name);
    }

    private static function contains_dynamic_block(array $blocks): bool {
        foreach ($blocks as $block) {
            if (!empty($block['blockName']) && self::is_dynamic_block((string) $block['blockName'])) {
                return true;
            }
            if (!empty($block['innerBlocks'])) {
                if (self::contains_dynamic_block($block['innerBlocks'])) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function is_dynamic_block(string $block_name): bool {
        if ($block_name === '') {
            return false;
        }

        if (class_exists('WP_Block_Type_Registry')) {
            $registry = \WP_Block_Type_Registry::get_instance();
            $block_type = $registry->get_registered($block_name);
            if ($block_type) {
                $dynamic = !empty($block_type->render_callback);
                return (bool) \apply_filters('wp_vrt_is_dynamic_block', $dynamic, $block_name, $block_type);
            }
        }

        return (bool) \apply_filters('wp_vrt_is_dynamic_block', false, $block_name, null);
    }

    private function create_temporary_post(): ?\WP_Post {
        $args = \apply_filters('wp_vrt_dynamic_fallback_post', [
            'post_title' => 'WP VRT Sample Post',
            'post_content' => 'This is sample content for dynamic template rendering.',
            'post_status' => 'draft',
            'post_type' => 'post',
        ]);

        if (!is_array($args)) {
            return null;
        }

        $post_id = \wp_insert_post($args, true);
        if (\is_wp_error($post_id)) {
            return null;
        }

        $this->temporary_post_id = (int) $post_id;
        return \get_post($post_id);
    }
}
