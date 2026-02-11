<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class SampleContent {
    public function generate_block_markup(string $block_name, ?string $variation): string {
        $blocks = $this->get_block_content($block_name, $variation);
        if (empty($blocks)) {
            return '';
        }

        $markup = '';
        foreach ($blocks as $block) {
            $attrs = $block['attrs'] ?? [];
            if ($variation) {
                $attrs['className'] = $this->merge_class_name($attrs['className'] ?? '', 'is-style-' . $variation);
            }
            $attrs_json = empty($attrs) ? '' : \wp_json_encode($attrs);
            $content = $block['content'] ?? '';

            $markup .= '<!-- wp:' . $block_name;
            if ($attrs_json) {
                $markup .= ' ' . $attrs_json;
            }
            $markup .= " -->\n";
            $markup .= $content . "\n";
            $markup .= '<!-- /wp:' . $block_name . " -->\n\n";
        }

        return $markup;
    }

    private function get_block_content(string $block_name, ?string $variation): array {
        $custom = \apply_filters('wp_vrt_block_content', null, $block_name, $variation);
        if (is_array($custom)) {
            return $custom;
        }

        if (DynamicContext::is_dynamic_block_name($block_name)) {
            return [[
                'attrs' => \apply_filters('wp_vrt_block_attributes', [], $block_name, $variation),
            ]];
        }

        switch ($block_name) {
            case 'core/paragraph':
                return [
                    [
                        'attrs' => [
                            'content' => 'This is a short paragraph.',
                        ],
                        'content' => '<p>This is a short paragraph.</p>',
                    ],
                    [
                        'attrs' => [
                            'content' => 'This is a medium-length paragraph that demonstrates text wrapping, line height, and overall typography styles applied by the theme. It should contain enough content to span multiple lines on most viewport sizes.',
                        ],
                        'content' => '<p>This is a medium-length paragraph that demonstrates text wrapping, line height, and overall typography styles applied by the theme. It should contain enough content to span multiple lines on most viewport sizes.</p>',
                    ],
                    [
                        'attrs' => ['align' => 'center'],
                        'content' => '<p class="has-text-align-center">This is a centered paragraph.</p>',
                    ],
                ];

            case 'core/heading':
                $headings = [];
                for ($level = 1; $level <= 6; $level++) {
                    $headings[] = [
                        'attrs' => [
                            'level' => $level,
                            'content' => 'Heading Level ' . $level,
                        ],
                        'content' => '<h' . $level . '>Heading Level ' . $level . '</h' . $level . '>',
                    ];
                }
                return $headings;

            case 'core/list':
                return [
                    [
                        'attrs' => ['ordered' => false],
                        'content' => '<ul><li>First unordered list item</li><li>Second item with more text</li><li>Third item</li></ul>',
                    ],
                    [
                        'attrs' => ['ordered' => true],
                        'content' => '<ol><li>First ordered list item</li><li>Second item</li><li>Third item</li></ol>',
                    ],
                ];

            case 'core/quote':
                return [
                    [
                        'attrs' => [
                            'value' => 'This is a sample quote demonstrating the blockquote styling.',
                            'citation' => 'Citation Source',
                        ],
                        'content' => '<blockquote class="wp-block-quote"><p>This is a sample quote demonstrating the blockquote styling.</p><cite>Citation Source</cite></blockquote>',
                    ],
                ];

            case 'core/pullquote':
                return [
                    [
                        'attrs' => [
                            'value' => 'This is a pull quote example to test styling.',
                            'citation' => 'Citation Source',
                        ],
                        'content' => '<figure class="wp-block-pullquote"><blockquote><p>This is a pull quote example to test styling.</p><cite>Citation Source</cite></blockquote></figure>',
                    ],
                ];

            case 'core/button':
                return [
                    [
                        'attrs' => [
                            'text' => 'Default Button',
                        ],
                        'content' => '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Default Button</a></div>',
                    ],
                ];

            case 'core/buttons':
                return [
                    [
                        'content' => '<div class="wp-block-buttons"><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Primary</a></div><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#">Outline</a></div></div>',
                    ],
                ];

            case 'core/separator':
                return [
                    [
                        'content' => '<hr class="wp-block-separator" />',
                    ],
                ];

            case 'core/spacer':
                return [
                    [
                        'attrs' => ['height' => '48px'],
                        'content' => '<div style="height:48px" aria-hidden="true" class="wp-block-spacer"></div>',
                    ],
                ];

            case 'core/group':
                return [
                    [
                        'content' => '<div class="wp-block-group"><p>Group block with inner content.</p></div>',
                    ],
                ];

            case 'core/columns':
                return [
                    [
                        'content' => '<div class="wp-block-columns">' .
                            '<div class="wp-block-column"><p>Column one content.</p></div>' .
                            '<div class="wp-block-column"><p>Column two content.</p></div>' .
                        '</div>',
                    ],
                ];

            case 'core/column':
                return [
                    [
                        'content' => '<div class="wp-block-column"><p>Standalone column content.</p></div>',
                    ],
                ];

            case 'core/row':
                return [
                    [
                        'content' => '<div class="wp-block-row">' .
                            '<div class="wp-block-group"><p>Row item one.</p></div>' .
                            '<div class="wp-block-group"><p>Row item two.</p></div>' .
                        '</div>',
                    ],
                ];

            case 'core/stack':
                return [
                    [
                        'content' => '<div class="wp-block-stack">' .
                            '<div class="wp-block-group"><p>Stack item one.</p></div>' .
                            '<div class="wp-block-group"><p>Stack item two.</p></div>' .
                        '</div>',
                    ],
                ];

            case 'core/image':
                return [
                    [
                        'attrs' => [
                            'url' => 'https://via.placeholder.com/800x600/cccccc/666666?text=Sample+Image',
                            'alt' => 'Sample image for visual testing',
                            'caption' => 'This is a sample image caption',
                        ],
                        'content' => '<figure class="wp-block-image"><img src="https://via.placeholder.com/800x600/cccccc/666666?text=Sample+Image" alt="Sample image for visual testing" /><figcaption>This is a sample image caption</figcaption></figure>',
                    ],
                ];

            case 'core/gallery':
                return [
                    [
                        'content' => '<figure class="wp-block-gallery"><figure class="wp-block-image"><img src="https://via.placeholder.com/400x300/3498db/ffffff?text=Image+1" alt="Gallery image 1" /></figure><figure class="wp-block-image"><img src="https://via.placeholder.com/400x300/e74c3c/ffffff?text=Image+2" alt="Gallery image 2" /></figure><figure class="wp-block-image"><img src="https://via.placeholder.com/400x300/2ecc71/ffffff?text=Image+3" alt="Gallery image 3" /></figure></figure>',
                    ],
                ];

            case 'core/cover':
                return [
                    [
                        'attrs' => [
                            'url' => 'https://via.placeholder.com/1200x600/34495e/ffffff?text=Cover+Background',
                            'dimRatio' => 50,
                        ],
                        'content' => '<div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="https://via.placeholder.com/1200x600/34495e/ffffff?text=Cover+Background" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><h2>Cover Block Title</h2><p>Sample content inside cover block.</p></div></div>',
                    ],
                ];

            case 'core/audio':
                return [
                    [
                        'attrs' => [
                            'src' => 'https://via.placeholder.com/audio.mp3',
                        ],
                        'content' => '<figure class="wp-block-audio"><audio controls src="https://via.placeholder.com/audio.mp3"></audio></figure>',
                    ],
                ];

            case 'core/video':
                return [
                    [
                        'attrs' => [
                            'src' => 'https://via.placeholder.com/video.mp4',
                        ],
                        'content' => '<figure class="wp-block-video"><video controls src="https://via.placeholder.com/video.mp4"></video></figure>',
                    ],
                ];
        }

        return [
            [
                'content' => '',
            ],
        ];
    }

    private function merge_class_name(string $existing, string $add): string {
        $existing = trim($existing);
        if ($existing === '') {
            return $add;
        }

        if (str_contains($existing, $add)) {
            return $existing;
        }

        return $existing . ' ' . $add;
    }
}
