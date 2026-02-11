<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class Cli {
    public static function init(): void {
        \WP_CLI::add_command('vrt snapshots', [self::class, 'generate_snapshots']);
        \WP_CLI::add_command('vrt test', [self::class, 'run_tests']);
    }

    public static function run_tests(array $args, array $assoc_args): void {
        $base_url = $assoc_args['base-url'] ?? \home_url();
        $dir = $assoc_args['dir'] ?? (defined('WP_CONTENT_DIR') ? \WP_CONTENT_DIR . '/wp-vrt-snapshots' : sys_get_temp_dir());
        $node = $assoc_args['node'] ?? 'node';

        \WP_CLI::log('Generating snapshots...');
        self::generate_snapshots([], [
            'base-url' => $base_url,
            'dir' => $dir,
            'node' => $node,
        ]);

        $plugin_dir = rtrim(WP_VRT_PATH, '/\\');
        $env = array_merge($_ENV, [
            'WP_VRT_BASE_URL' => $base_url,
            'WP_VRT_SNAPSHOT_DIR' => $dir,
            'NODE_BINARY' => $node,
        ]);

        $cmd = 'composer test';
        \WP_CLI::log('Running Pest tests...');
        $result = self::run_process($cmd, $plugin_dir, $env);

        if ($result !== 0) {
            \WP_CLI::error('Pest tests failed.');
        }
    }

    public static function generate_snapshots(array $args, array $assoc_args): void {
        $base_url = $assoc_args['base-url'] ?? \home_url();
        $dir = $assoc_args['dir'] ?? (defined('WP_CONTENT_DIR') ? \WP_CONTENT_DIR . '/wp-vrt-snapshots' : sys_get_temp_dir());
        $types = $assoc_args['types'] ?? 'blocks,patterns,templates,parts,scenarios';
        $width = isset($assoc_args['width']) ? (int) $assoc_args['width'] : 1200;
        $height = isset($assoc_args['height']) ? (int) $assoc_args['height'] : 800;
        $node = $assoc_args['node'] ?? 'node';

        $script = WP_VRT_PATH . 'assets/cli/screenshot.mjs';
        if (!file_exists($script)) {
            \WP_CLI::error('Missing Playwright script at ' . $script);
        }

        if (!is_dir($dir) && !\wp_mkdir_p($dir)) {
            \WP_CLI::error('Failed to create snapshot directory: ' . $dir);
        }

        $discovery = self::fetch_discovery($base_url);
        $base_url = $discovery['base_url'] ?? $base_url;

        $wanted = array_filter(array_map('trim', explode(',', (string) $types)));
        $items = self::collect_items($discovery['items'] ?? [], $wanted);

        if (empty($items)) {
            \WP_CLI::warning('No items found to snapshot.');
            return;
        }

        \WP_CLI::log('Saving snapshots to ' . $dir);

        foreach ($items as $item) {
            $url = self::join_url($base_url, $item['url']);
            $name = $item['snapshot'];
            $path = rtrim($dir, '/\\') . '/' . $name . '.png';

            $result = self::run_node($node, $script, [$url, $path, (string) $width, (string) $height]);
            if ($result !== 0) {
                \WP_CLI::warning('Failed: ' . $url);
                continue;
            }

            \WP_CLI::log('Saved: ' . $path);
        }
    }

    private static function fetch_discovery(string $base_url): array {
        $endpoint = rtrim($base_url, '/') . '/wp-json/wp-vrt/v1/discover';
        $response = \wp_remote_get($endpoint);
        if (\is_wp_error($response)) {
            $parsed = \wp_parse_url($endpoint);
            if (!empty($parsed['scheme']) && $parsed['scheme'] === 'https') {
                $http_endpoint = \set_url_scheme($endpoint, 'http');
                \WP_CLI::warning('HTTPS failed, retrying over HTTP: ' . $http_endpoint);
                $response = \wp_remote_get($http_endpoint);
                if (!\is_wp_error($response)) {
                    $endpoint = $http_endpoint;
                }
            }
        }

        if (\is_wp_error($response)) {
            \WP_CLI::error('Failed to fetch discovery endpoint: ' . $endpoint);
        }

        $body = \wp_remote_retrieve_body($response);
        $data = \json_decode($body, true);
        if (!is_array($data)) {
            \WP_CLI::error('Invalid JSON from discovery endpoint: ' . $endpoint);
        }

        return $data;
    }

    private static function collect_items(array $items, array $types): array {
        $collected = [];

        if (in_array('blocks', $types, true) && !empty($items['blocks'])) {
            foreach ($items['blocks'] as $block) {
                $collected[] = [
                    'url' => $block['url'],
                    'snapshot' => 'block-' . self::snapshot_slug($block['name'] ?? $block['url'] ?? 'block'),
                ];

                foreach ($block['variations'] ?? [] as $variation) {
                    $collected[] = [
                        'url' => $variation['url'],
                        'snapshot' => 'block-' . self::snapshot_slug(($block['name'] ?? 'block') . '-' . ($variation['name'] ?? 'variation')),
                    ];
                }
            }
        }

        if (in_array('patterns', $types, true) && !empty($items['patterns'])) {
            foreach ($items['patterns'] as $pattern) {
                $collected[] = [
                    'url' => $pattern['url'],
                    'snapshot' => 'pattern-' . self::snapshot_slug($pattern['name'] ?? $pattern['slug'] ?? 'pattern'),
                ];
            }
        }

        if (in_array('templates', $types, true) && !empty($items['templates'])) {
            foreach ($items['templates'] as $template) {
                $collected[] = [
                    'url' => $template['url'],
                    'snapshot' => 'template-' . self::snapshot_slug($template['slug'] ?? 'template'),
                ];
            }
        }

        if (in_array('parts', $types, true) && !empty($items['template_parts'])) {
            foreach ($items['template_parts'] as $part) {
                $collected[] = [
                    'url' => $part['url'],
                    'snapshot' => 'part-' . self::snapshot_slug($part['slug'] ?? 'part'),
                ];
            }
        }

        if (in_array('scenarios', $types, true) && !empty($items['scenarios'])) {
            foreach ($items['scenarios'] as $scenario) {
                $collected[] = [
                    'url' => $scenario['url'],
                    'snapshot' => 'scenario-' . self::snapshot_slug($scenario['slug'] ?? 'scenario'),
                ];
            }
        }

        return $collected;
    }

    private static function run_node(string $node, string $script, array $args): int {
        $command = array_merge([$node, $script], $args);
        $escaped = array_map('escapeshellarg', $command);
        $cmd = implode(' ', $escaped);

        return self::run_process($cmd, null, null);
    }

    private static function run_process(string $cmd, ?string $cwd, ?array $env): int {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = \proc_open($cmd, $descriptor, $pipes, $cwd, $env);
        if (!is_resource($process)) {
            return 1;
        }

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        return (int) \proc_close($process);
    }

    private static function join_url(string $base_url, string $path): string {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base_url = rtrim($base_url, '/');
        if (str_ends_with($base_url, '/wp-vrt') && str_starts_with($path, '/wp-vrt/')) {
            $base_url = substr($base_url, 0, -strlen('/wp-vrt'));
        }

        return rtrim($base_url, '/') . $path;
    }

    private static function snapshot_slug(string $value): string {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim($value, '-');
    }
}
