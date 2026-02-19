<?php

namespace WpVrt;

if (!defined('ABSPATH')) {
    exit;
}

class SampleContent {
    public function generate_block_markup(string $block_name, ?string $variation): string {
        $example_markup = $this->get_block_example_markup($block_name, $variation);
        if ($example_markup !== null) {
            return $example_markup;
        }

        $blocks = $this->get_block_content($block_name, $variation);
        if (empty($blocks)) {
            return '';
        }

        $markup = '';
        foreach ($blocks as $idx => $block) {
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

    private function get_block_example_markup(string $block_name, ?string $variation): ?string {
        if (!class_exists('WP_Block_Type_Registry')) {
            return null;
        }

        $block_type = \WP_Block_Type_Registry::get_instance()->get_registered($block_name);
        if ($block_type && !empty($block_type->example) && is_array($block_type->example)) {
            if (function_exists('error_log')) {
                error_log('[WPVRTDEBUG] block_type example path taken (registry) for ' . $block_name);
            }
            if (function_exists('error_log')) {
                error_log('[WPVRTDEBUG] block_type example path taken for ' . $block_name);
            }
        }
        if (!$block_type || empty($block_type->example) || !is_array($block_type->example)) {
            return null;
        }

        $example = $block_type->example;
        $has_inner = !empty($example['innerBlocks']) || !empty($example['innerHTML']) || !empty($example['innerContent']);
        if (!$has_inner) {
            return null;
        }

        $attrs = $example['attributes'] ?? [];
        if (!is_array($attrs)) {
            $attrs = [];
        }
        if ($variation) {
            $attrs['className'] = $this->merge_class_name($attrs['className'] ?? '', 'is-style-' . $variation);
        }

        $block = [
            'blockName' => $block_name,
            'attrs' => $attrs,
            'innerBlocks' => $this->normalize_example_blocks($example['innerBlocks'] ?? []),
            'innerHTML' => is_string($example['innerHTML'] ?? null) ? $example['innerHTML'] : '',
            'innerContent' => is_array($example['innerContent'] ?? null) ? $example['innerContent'] : [],
        ];

        return \serialize_block($block) . "\n";
    }

    private function normalize_example_blocks($inner_blocks): array {
        if (!is_array($inner_blocks)) {
            return [];
        }

        $normalized = [];
        foreach ($inner_blocks as $inner_block) {
            if (!is_array($inner_block)) {
                continue;
            }

            $block_name = $inner_block['blockName'] ?? $inner_block['name'] ?? '';
            if (!is_string($block_name) || $block_name === '') {
                continue;
            }

            $attrs = $inner_block['attrs'] ?? $inner_block['attributes'] ?? [];
            if (!is_array($attrs)) {
                $attrs = [];
            }

            $normalized[] = [
                'blockName' => $block_name,
                'attrs' => $attrs,
                'innerBlocks' => $this->normalize_example_blocks($inner_block['innerBlocks'] ?? []),
                'innerHTML' => is_string($inner_block['innerHTML'] ?? null) ? $inner_block['innerHTML'] : '',
                'innerContent' => is_array($inner_block['innerContent'] ?? null) ? $inner_block['innerContent'] : [],
            ];
        }

        return $normalized;
    }

    private function get_block_content(string $block_name, ?string $variation): array {
        $custom = \apply_filters('wp_vrt_block_content', null, $block_name, $variation);
        if (is_array($custom)) {
            return $custom;
        }

        // Try the explicit sample first to see if we have content for this block
        $explicit_sample = $this->get_explicit_block_sample($block_name, $variation);
        if ($explicit_sample !== null) {
            return $explicit_sample;
        }

        // If no explicit sample, check if it's dynamic
        if (DynamicContext::is_dynamic_block_name($block_name)) {
            return [[
                'attrs' => \apply_filters('wp_vrt_block_attributes', [], $block_name, $variation),
            ]];
        }

        // Fallback
        $fallback = [
            [
                'content' => '<p>Sample content for block: ' . htmlspecialchars($block_name, ENT_QUOTES, 'UTF-8') . '</p>',
            ],
        ];
        
        return $fallback;
    }

    private function get_explicit_block_sample(string $block_name, ?string $variation): ?array {
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

            case 'core/code':
                return [
                    [
                        'content' => '<pre class="wp-block-code"><code>const greeting = "Hello, world!";\nconsole.log(greeting);</code></pre>',
                    ],
                ];

            case 'core/preformatted':
                return [
                    [
                        'content' => '<pre class="wp-block-preformatted">Line one of preformatted text\nLine two keeps spacing</pre>',
                    ],
                ];

            case 'core/verse':
                return [
                    [
                        'content' => '<pre class="wp-block-verse">Roses are red\nViolets are blue\nVerse block sample\nJust for you</pre>',
                    ],
                ];

            case 'core/table':
                return [
                    [
                        'content' => '<figure class="wp-block-table"><table><thead><tr><th>Plan</th><th>Monthly</th></tr></thead><tbody><tr><td>Starter</td><td>$9</td></tr><tr><td>Pro</td><td>$29</td></tr></tbody></table><figcaption>Sample pricing table</figcaption></figure>',
                    ],
                ];

            case 'core/file':
                return [
                    [
                        'content' => '<div class="wp-block-file"><a href="https://via.placeholder.com/600x800.pdf">Sample PDF</a><a class="wp-block-file__button" href="https://via.placeholder.com/600x800.pdf">Download</a></div>',
                    ],
                ];

            case 'core/media-text':
                return [
                    [
                        'content' => '<div class="wp-block-media-text"><figure class="wp-block-media-text__media"><img src="https://via.placeholder.com/640x480/94a3b8/ffffff?text=Media" alt="Media text sample" /></figure><div class="wp-block-media-text__content"><h3>Media + Text</h3><p>This block shows an image alongside supporting copy.</p></div></div>',
                    ],
                ];

            case 'core/search':
                return [
                    [
                        'content' => '<div class="wp-block-search"><form role="search" class="wp-block-search__inside-wrapper"><label class="wp-block-search__label">Search</label><input type="search" class="wp-block-search__input" value="" placeholder="Search"/><button type="submit" class="wp-block-search__button">Search</button></form></div>',
                    ],
                ];

            case 'core/social-links':
                return [
                    [
                        'content' => '<ul class="wp-block-social-links">
<li class="wp-social-link wp-social-link-amazon"><a href="#" aria-label="Amazon"></a></li>
<li class="wp-social-link wp-social-link-bandcamp"><a href="#" aria-label="Bandcamp"></a></li>
<li class="wp-social-link wp-social-link-behance"><a href="#" aria-label="Behance"></a></li>
<li class="wp-social-link wp-social-link-chain"><a href="#" aria-label="Link"></a></li>
<li class="wp-social-link wp-social-link-codepen"><a href="#" aria-label="CodePen"></a></li>
<li class="wp-social-link wp-social-link-deviantart"><a href="#" aria-label="DeviantArt"></a></li>
<li class="wp-social-link wp-social-link-dribbble"><a href="#" aria-label="Dribbble"></a></li>
<li class="wp-social-link wp-social-link-dropbox"><a href="#" aria-label="Dropbox"></a></li>
<li class="wp-social-link wp-social-link-etsy"><a href="#" aria-label="Etsy"></a></li>
<li class="wp-social-link wp-social-link-facebook"><a href="#" aria-label="Facebook"></a></li>
<li class="wp-social-link wp-social-link-feed"><a href="#" aria-label="Feed"></a></li>
<li class="wp-social-link wp-social-link-fivehundredpx"><a href="#" aria-label="500px"></a></li>
<li class="wp-social-link wp-social-link-flickr"><a href="#" aria-label="Flickr"></a></li>
<li class="wp-social-link wp-social-link-foursquare"><a href="#" aria-label="Foursquare"></a></li>
<li class="wp-social-link wp-social-link-goodreads"><a href="#" aria-label="Goodreads"></a></li>
<li class="wp-social-link wp-social-link-google"><a href="#" aria-label="Google"></a></li>
<li class="wp-social-link wp-social-link-github"><a href="#" aria-label="GitHub"></a></li>
<li class="wp-social-link wp-social-link-instagram"><a href="#" aria-label="Instagram"></a></li>
<li class="wp-social-link wp-social-link-lastfm"><a href="#" aria-label="Last.fm"></a></li>
<li class="wp-social-link wp-social-link-linkedin"><a href="#" aria-label="LinkedIn"></a></li>
<li class="wp-social-link wp-social-link-mail"><a href="#" aria-label="Email"></a></li>
<li class="wp-social-link wp-social-link-mastodon"><a href="#" aria-label="Mastodon"></a></li>
<li class="wp-social-link wp-social-link-meetup"><a href="#" aria-label="Meetup"></a></li>
<li class="wp-social-link wp-social-link-medium"><a href="#" aria-label="Medium"></a></li>
<li class="wp-social-link wp-social-link-pinterest"><a href="#" aria-label="Pinterest"></a></li>
<li class="wp-social-link wp-social-link-pocket"><a href="#" aria-label="Pocket"></a></li>
<li class="wp-social-link wp-social-link-reddit"><a href="#" aria-label="Reddit"></a></li>
<li class="wp-social-link wp-social-link-skype"><a href="#" aria-label="Skype"></a></li>
<li class="wp-social-link wp-social-link-snapchat"><a href="#" aria-label="Snapchat"></a></li>
<li class="wp-social-link wp-social-link-soundcloud"><a href="#" aria-label="SoundCloud"></a></li>
<li class="wp-social-link wp-social-link-spotify"><a href="#" aria-label="Spotify"></a></li>
<li class="wp-social-link wp-social-link-tumblr"><a href="#" aria-label="Tumblr"></a></li>
<li class="wp-social-link wp-social-link-twitch"><a href="#" aria-label="Twitch"></a></li>
<li class="wp-social-link wp-social-link-twitter"><a href="#" aria-label="Twitter"></a></li>
<li class="wp-social-link wp-social-link-vimeo"><a href="#" aria-label="Vimeo"></a></li>
<li class="wp-social-link wp-social-link-vk"><a href="#" aria-label="VK"></a></li>
<li class="wp-social-link wp-social-link-wordpress"><a href="#" aria-label="WordPress"></a></li>
<li class="wp-social-link wp-social-link-yelp"><a href="#" aria-label="Yelp"></a></li>
<li class="wp-social-link wp-social-link-youtube"><a href="#" aria-label="YouTube"></a></li>
</ul>',
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
                // Debug header to aid offline capture: indicate this response uses server-side sample.
                if (function_exists('header')) {
                    @header('X-WPVRT-Debug: core-cover-server-sample');
                }
                // Debug hook: indicate when server-side sample for cover is used
                if (function_exists('error_log')) {
                    error_log('[WPVRT] core/cover sample content rendered (server-side)');
                }
                return [
                    [
                        'attrs' => [
                            'url' => 'https://via.placeholder.com/1200x600/34495e/ffffff?text=Cover+Background',
                            'dimRatio' => 50,
                        ],
                        'content' => '<div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="https://via.placeholder.com/1200x600/34495e/ffffff?text=Cover+Background" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><h2>Cover Block Title</h2><p>Sample content inside cover block.</p></div></div><!-- WPVRT_SAMPLE_CORE_COVER -->',
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

            // Post blocks with mock content
            case 'core/post-title':
                return [
                    [
                        'attrs' => ['level' => 2],
                        'content' => '<h2 class="wp-block-post-title">Sample Post Title</h2>',
                    ],
                ];

            case 'core/post-content':
                return [
                    [
                        'content' => '<div class="wp-block-post-content"><p>This is the main post content. It demonstrates how the post-content block renders the full content of a post or page. This block automatically pulls the post content from the WordPress database when used on an actual post.</p></div>',
                    ],
                ];

            case 'core/post-excerpt':
                return [
                    [
                        'content' => '<p class="wp-block-post-excerpt">This is a sample post excerpt that summarizes the main content of the post. Excerpts are useful for preview pages and archives.</p>',
                    ],
                ];

            case 'core/post-featured-image':
                return [
                    [
                        'attrs' => ['align' => 'center'],
                        'content' => '<figure class="wp-block-post-featured-image"><img src="https://via.placeholder.com/800x400" alt="Featured image" /></figure>',
                    ],
                ];

            case 'core/post-date':
                return [
                    [
                        'content' => '<time class="wp-block-post-date" datetime="2026-02-19">February 19, 2026</time>',
                    ],
                ];

            case 'core/post-author':
                return [
                    [
                        'content' => '<div class="wp-block-post-author"><div class="wp-block-post-author__avatar"><img alt="Sample Author" src="https://via.placeholder.com/48x48" width="48" height="48" class="avatar avatar-48 photo"></div><div class="wp-block-post-author__content"><p class="wp-block-post-author__name">Sample Author</p></div></div>',
                    ],
                ];

            case 'core/post-author-name':
                return [
                    [
                        'content' => '<span class="wp-block-post-author-name">Sample Author</span>',
                    ],
                ];

            case 'core/post-author-biography':
                return [
                    [
                        'content' => '<p class="wp-block-post-author-biography">Sample author biography. This is where the author can share information about themselves and their background.</p>',
                    ],
                ];

            case 'core/post-terms':
                return [
                    [
                        'content' => '<div class="wp-block-post-terms"><a href="#">Sample Category</a>, <a href="#">Another Category</a></div>',
                    ],
                ];

            case 'core/post-time-to-read':
                return [
                    [
                        'content' => '<span class="wp-block-post-time-to-read">3 min read</span>',
                    ],
                ];

            case 'core/post-comments-count':
                return [
                    [
                        'content' => '<span class="wp-block-post-comments-count">5 comments</span>',
                    ],
                ];

            case 'core/post-comments-form':
                return [
                    [
                        'content' => '<div class="wp-block-post-comments-form"><p><strong>Leave a reply</strong></p><form><p><label>Name *</label><input type="text" required></p><p><label>Email *</label><input type="email" required></p><p><label>Comment *</label><textarea rows="4" required></textarea></p><p><button type="submit">Post Comment</button></p></form></div>',
                    ],
                ];

            case 'core/post-comments-link':
                return [
                    [
                        'content' => '<a href="#" class="wp-block-post-comments-link">View Comments (5)</a>',
                    ],
                ];

            case 'core/post-navigation-link':
                return [
                    [
                        'content' => '<div class="wp-block-post-navigation-link"><a href="#">← Next Post</a></div>',
                    ],
                ];

            // Comment blocks with mock content
            case 'core/post-comments':
                return [
                    [
                        'content' => '<div class="wp-block-post-comments"><h3>Comments (3)</h3><ol class="commentlist"><li class="comment"><article class="comment-body"><p class="comment-text">This is the first sample comment demonstrating how comments appear in the post.</p><footer class="comment-meta"><p class="comment-author">John Doe</p><p class="comment-date">February 19, 2026</p></footer></article></li><li class="comment"><article class="comment-body"><p class="comment-text">Another sample comment showing multiple comment threads.</p><footer class="comment-meta"><p class="comment-author">Jane Smith</p><p class="comment-date">February 19, 2026</p></footer></article></li><li class="comment"><article class="comment-body"><p class="comment-text">A third comment to demonstrate the comment list structure.</p><footer class="comment-meta"><p class="comment-author">Mike Johnson</p><p class="comment-date">February 19, 2026</p></footer></article></li></ol></div>',
                    ],
                ];

            case 'core/comment-author-name':
                return [
                    [
                        'content' => '<span class="wp-block-comment-author-name">John Doe</span>',
                    ],
                ];

            case 'core/comment-author-avatar':
                return [
                    [
                        'content' => '<div class="wp-block-comment-author-avatar"><img alt="Sample Commenter" src="https://via.placeholder.com/32x32" width="32" height="32" class="avatar avatar-32 photo"></div>',
                    ],
                ];

            case 'core/comment-content':
                return [
                    [
                        'content' => '<p class="wp-block-comment-content">This is a sample comment demonstrating how comment content appears in the comment block.</p>',
                    ],
                ];

            case 'core/comment-date':
                return [
                    [
                        'content' => '<time class="wp-block-comment-date" datetime="2026-02-19">February 19, 2026</time>',
                    ],
                ];

            case 'core/comment-edit-link':
                return [
                    [
                        'content' => '<a href="#" class="wp-block-comment-edit-link">Edit</a>',
                    ],
                ];

            case 'core/comment-reply-link':
                return [
                    [
                        'content' => '<a href="#" class="wp-block-comment-reply-link">Reply</a>',
                    ],
                ];

            case 'core/comments-title':
                return [
                    [
                        'content' => '<h3 class="wp-block-comments-title">Discussion (3 comments)</h3>',
                    ],
                ];

            // Dynamic blocks with fallback mock content
            case 'core/query':
                return [
                    [
                        'attrs' => ['queryId' => 1],
                        'content' => '<div class="wp-block-query"><div class="wp-block-post-template"><article><h2 class="wp-block-post-title"><a href="#">Sample Post 1</a></h2><time>February 19, 2026</time><p>Sample post excerpt from the latest posts query...</p></article><article><h2 class="wp-block-post-title"><a href="#">Sample Post 2</a></h2><time>February 18, 2026</time><p>Another sample post excerpt demonstrating the query loop...</p></article></div></div>',
                    ],
                ];

            case 'core/query-pagination':
                return [
                    [
                        'content' => '<div class="wp-block-query-pagination"><a href="#" class="wp-block-query-pagination-previous">← Previous</a><span aria-current="page" class="wp-block-query-pagination-numbers">1</span><a href="#">2</a><a href="#">3</a><a href="#" class="wp-block-query-pagination-next">Next →</a></div>',
                    ],
                ];

            case 'core/query-title':
                return [
                    [
                        'content' => '<h1 class="wp-block-query-title">Search Results for "sample"</h1>',
                    ],
                ];

            case 'core/query-no-results':
                return [
                    [
                        'content' => '<p class="wp-block-query-no-results">Sorry, but nothing matched your search terms. Please try again with some different keywords.</p>',
                    ],
                ];

            case 'core/query-total':
                return [
                    [
                        'content' => '<p class="wp-block-query-total">Showing 2 results</p>',
                    ],
                ];

            case 'core/read-more':
                return [
                    [
                        'content' => '<a href="#" class="wp-block-read-more">Continue reading →</a>',
                    ],
                ];

            case 'core/latest-posts':
                return [
                    [
                        'content' => '<ul class="wp-block-latest-posts"><li><a href="#">Sample Post 1</a> <time>February 19, 2026</time></li><li><a href="#">Sample Post 2</a> <time>February 18, 2026</time></li><li><a href="#">Sample Post 3</a> <time>February 17, 2026</time></li></ul>',
                    ],
                ];

            case 'core/latest-comments':
                return [
                    [
                        'content' => '<ol class="wp-block-latest-comments"><li class="wp-block-latest-comments__comment"><div class="wp-block-latest-comments__comment-metadata">John Doe on <a href="#">Sample Post</a></div><p class="wp-block-latest-comments__comment-excerpt">Sample comment text...</p></li><li class="wp-block-latest-comments__comment"><div class="wp-block-latest-comments__comment-metadata">Jane Smith on <a href="#">Another Post</a></div><p class="wp-block-latest-comments__comment-excerpt">Another sample comment...</p></li></ol>',
                    ],
                ];

            case 'core/rss':
                return [
                    [
                        'content' => '<div class="wp-block-rss"><ul><li><a href="#">Sample Feed Item 1</a></li><li><a href="#">Sample Feed Item 2</a></li><li><a href="#">Sample Feed Item 3</a></li></ul></div>',
                    ],
                ];

            case 'core/search':
                return [
                    [
                        'content' => '<div class="wp-block-search"><form role="search" class="wp-block-search__inside-wrapper"><input type="search" class="wp-block-search__input" placeholder="Search..."/><button type="submit" class="wp-block-search__button">Search</button></form></div>',
                    ],
                ];

            case 'core/site-logo':
                return [
                    [
                        'content' => '<img class="wp-block-site-logo" src="https://via.placeholder.com/100x50" alt="Site Logo" />',
                    ],
                ];

            case 'core/site-tagline':
                return [
                    [
                        'content' => '<p class="wp-block-site-tagline">Your website tagline or motto goes here</p>',
                    ],
                ];

            case 'core/tag-cloud':
                return [
                    [
                        'content' => '<div class="wp-block-tag-cloud"><a href="#">Design</a> <a href="#">Development</a> <a href="#">WordPress</a> <a href="#">Gutenberg</a> <a href="#">Blocks</a></div>',
                    ],
                ];

            case 'core/archives':
                return [
                    [
                        'content' => '<ul class="wp-block-archives"><li><a href="#">February 2026</a></li><li><a href="#">January 2026</a></li><li><a href="#">December 2025</a></li></ul>',
                    ],
                ];

            case 'core/categories':
                return [
                    [
                        'content' => '<ul class="wp-block-categories"><li><a href="#">News</a></li><li><a href="#">Updates</a></li><li><a href="#">Resources</a></li></ul>',
                    ],
                ];

            case 'core/page-list':
                return [
                    [
                        'content' => '<ul class="wp-block-page-list"><li><a href="#">Home</a></li><li><a href="#">About</a></li><li><a href="#">Services</a></li><li><a href="#">Contact</a></li></ul>',
                    ],
                ];

            case 'core/calendar':
                return [
                    [
                        'content' => '<table class="wp-block-calendar"><caption>February 2026</caption><thead><tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr></thead><tbody><tr><td>1</td><td>2</td><td>3</td><td>4</td><td>5</td><td>6</td><td>7</td></tr><tr><td>8</td><td>9</td><td>10</td><td>11</td><td>12</td><td>13</td><td>14</td></tr><tr><td>15</td><td>16</td><td>17</td><td>18</td><td><strong>19</strong></td><td>20</td><td>21</td></tr></tbody></table>',
                    ],
                ];

            case 'core/avatar':
                return [
                    [
                        'content' => '<img alt="Sample User Avatar" src="https://via.placeholder.com/64x64" width="64" height="64" class="avatar avatar-64 photo" />',
                    ],
                ];

            case 'core/breadcrumbs':
                return [
                    [
                        'content' => '<nav class="wp-block-breadcrumbs"><a href="#">Home</a> / <a href="#">Blog</a> / <span aria-current="page">Sample Post</span></nav>',
                    ],
                ];

            case 'core/loginout':
                return [
                    [
                        'content' => '<a href="#" class="wp-block-loginout">Log in</a>',
                    ],
                ];

            case 'core/term-count':
                return [
                    [
                        'content' => '<span class="wp-block-term-count">12</span>',
                    ],
                ];

            case 'core/term-description':
                return [
                    [
                        'content' => '<p class="wp-block-term-description">This is a sample term description providing information about the category or tag.</p>',
                    ],
                ];

            case 'core/term-name':
                return [
                    [
                        'content' => '<span class="wp-block-term-name">Sample Term</span>',
                    ],
                ];

            case 'core/term-template':
                return [
                    [
                        'content' => '<div class="wp-block-term-template"><div class="wp-block-term-item"><h2 class="wp-block-term-name"><a href="#">Sample Category 1</a></h2><p>Description of the category</p></div><div class="wp-block-term-item"><h2 class="wp-block-term-name"><a href="#">Sample Category 2</a></h2><p>Another category description</p></div></div>',
                    ],
                ];

            case 'core/post-comment':
                return [
                    [
                        'content' => '<li class="comment"><article class="comment-body"><p class="comment-text">This is a sample comment block demonstrating how individual comments appear.</p><footer class="comment-meta"><p class="comment-author">Comment Author</p><p class="comment-date">February 19, 2026</p></footer></article></li>',
                    ],
                ];

            case 'core/footnotes':
                return [
                    [
                        'content' => '<section class="wp-block-footnotes"><ol><li id="fn-1">This is a sample footnote demonstrating how footnotes appear at the bottom of content. <a href="#">Back to content</a></li><li id="fn-2">Another sample footnote showing multiple notes. <a href="#">Back to content</a></li></ol></section>',
                    ],
                ];

            case 'core/navigation':
                return [
                    [
                        'content' => '<nav class="wp-block-navigation"><ul><li class="wp-block-navigation-item"><a href="#">Home</a></li><li class="wp-block-navigation-item"><a href="#">About</a></li><li class="wp-block-navigation-item"><a href="#">Services</a></li><li class="wp-block-navigation-item"><a href="#">Contact</a></li></ul></nav>',
                    ],
                ];

            case 'core/table-of-contents':
                return [
                    [
                        'content' => '<div class="wp-block-table-of-contents"><ul><li><a href="#">Introduction</a></li><li><a href="#">Main Content</a><ul><li><a href="#">Section 1</a></li><li><a href="#">Section 2</a></li></ul></li><li><a href="#">Conclusion</a></li></ul></div>',
                    ],
                ];

            case 'core/details':
                return [
                    [
                        'content' => '<details class="wp-block-details"><summary>Click to expand</summary><p>This is the hidden content that appears when you click the summary above.</p></details>',
                    ],
                ];

            case 'core/accordion':
                return [
                    [
                        'content' => '<div class="wp-block-accordion"><details class="wp-block-details"><summary>Item 1</summary><p>Content for the first accordion item.</p></details><details class="wp-block-details"><summary>Item 2</summary><p>Content for the second accordion item.</p></details><details class="wp-block-details"><summary>Item 3</summary><p>Content for the third accordion item.</p></details></div>',
                    ],
                ];

            case 'core/embed':
                return [
                    [
                        'attrs' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                        'content' => '<figure class="wp-block-embed"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="500" height="281"></iframe></figure>',
                    ],
                ];

            case 'core/freeform':
                return [
                    [
                        'content' => '<div class="wp-block-freeform"><!-- wp:paragraph --><p>This is a classic/freeform block that allows HTML content.</p><!-- /wp:paragraph --></div>',
                    ],
                ];

            case 'core/html':
                return [
                    [
                        'content' => '<div class="wp-block-html"><pre>&lt;div class="custom-html"&gt;\n  &lt;p&gt;Custom HTML content example&lt;/p&gt;\n&lt;/div&gt;</pre></div>',
                    ],
                ];

            case 'core/shortcode':
                return [
                    [
                        'content' => '<div class="wp-block-shortcode">[sample_shortcode attr="value"]</div>',
                    ],
                ];

            case 'core/math':
                return [
                    [
                        'content' => '<div class="wp-block-math"><div class="wp-block-math__content">E = mc²</div></div>',
                    ],
                ];

            case 'core/more':
                return [
                    [
                        'content' => '<hr class="wp-block-more" /><!-- more -->',
                    ],
                ];

            case 'core/text-columns':
                return [
                    [
                        'content' => '<div class="wp-block-text-columns"><div class="wp-block-column"><p>Column one content goes here. This is sample text in the first column.</p></div><div class="wp-block-column"><p>Column two content goes here. This is sample text in the second column.</p></div></div>',
                    ],
                ];

            case 'core/legacy-widget':
                return [
                    [
                        'content' => '<div class="wp-block-legacy-widget"><div class="widget"><div class="widget-content">Sample widget content</div></div></div>',
                    ],
                ];

            case 'core/terms-query':
                return [
                    [
                        'content' => '<div class="wp-block-terms-query"><div class="wp-block-term-template"><div class="wp-block-term-item"><h3><a href="#">Sample Category 1</a></h3><p class="wp-block-term-description">Category description</p></div><div class="wp-block-term-item"><h3><a href="#">Sample Category 2</a></h3><p class="wp-block-term-description">Another category description</p></div></div></div>',
                    ],
                ];

        }

        // Fallback: provide a simple sample content for any block that doesn't have explicit sample content.
        // This ensures block previews render something for static blocks that don't rely on DB data.
        $fallback = [
            [
                'content' => '<p>Sample content for block: ' . htmlspecialchars($block_name, ENT_QUOTES, 'UTF-8') . '</p>',
            ],
        ];
        
        return $fallback;
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
