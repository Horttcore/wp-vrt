<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class ScenarioRegistry {
    public function get_discoverable_scenarios(): array {
        $scenarios = $this->get_scenarios();
        $items = [];

        foreach ($scenarios as $slug => $scenario) {
            $items[] = [
                'slug' => $slug,
                'title' => $scenario['title'] ?? $slug,
                'description' => $scenario['description'] ?? null,
            ];
        }

        return apply_filters('wp_vrt_discoverable_scenarios', $items);
    }

    public function get_scenario_content(string $slug): ?string {
        $scenarios = $this->get_scenarios();
        if (!isset($scenarios[$slug])) {
            return null;
        }

        $content = $scenarios[$slug]['content'] ?? null;
        if ($content === null) {
            return null;
        }

        return is_callable($content) ? (string) call_user_func($content) : (string) $content;
    }

    private function get_scenarios(): array {
        $scenarios = apply_filters('wp_vrt_register_scenarios', []);
        if (!is_array($scenarios)) {
            return [];
        }

        foreach ($scenarios as $slug => $scenario) {
            if (!is_array($scenario) || empty($scenario['content'])) {
                unset($scenarios[$slug]);
            }
        }

        return $scenarios;
    }
}
