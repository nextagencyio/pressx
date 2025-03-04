<?php

/**
 * @file
 * PressX Core.
 *
 * Core functionality for PressX headless WordPress setup.
 *
 * Plugin Name: PressX Core
 * Plugin URI: https://github.com/your-username/pressx
 * Description: Core functionality for PressX headless WordPress setup.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/your-username
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: pressx-core.
 */

/**
 * @file
 * Core functionality for PressX headless WordPress setup.
 */

if (!defined('ABSPATH')) {
  exit;
}

use Carbon_Fields\Container;
use Carbon_Fields\Field;
use Carbon_Fields\Carbon_Fields;

// Include GraphQL configuration.
require_once plugin_dir_path(__FILE__) . 'includes/graphql.php';

// Include the REST API implementation.
require_once plugin_dir_path(__FILE__) . 'includes/rest-api.php';

// Include AI utilities.
require_once plugin_dir_path(__FILE__) . 'includes/ai-utils.php';

// Include WP-CLI commands if WP-CLI is available.
if (defined('WP_CLI') && WP_CLI) {
  require_once plugin_dir_path(__FILE__) . 'includes/cli/wp-cli.php';
}

// Initialize Carbon Fields.
add_action('after_setup_theme', function () {
  require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/vendor/autoload.php';

  // Define Carbon Fields URL.
  if (!defined('Carbon_Fields\\URL')) {
    define('Carbon_Fields\\URL', plugins_url('vendor/carbon-fields', __FILE__));
  }

  // Boot Carbon Fields.
  Carbon_Fields::boot();

  // Configure image sizes.
  add_filter('intermediate_image_sizes_advanced', function ($sizes) {
    // Remove default WordPress image sizes.
    unset($sizes['thumbnail']);
    unset($sizes['medium']);
    unset($sizes['medium_large']);
    unset($sizes['large']);
    unset($sizes['1536x1536']);
    unset($sizes['2048x2048']);

    // Add our custom scaled sizes.
    add_image_size('large', 1280, 9999, FALSE);
    add_image_size('medium', 960, 9999, FALSE);
    add_image_size('thumbnail', 320, 9999, FALSE);

    return $sizes;
  });

  // Disable default WordPress image sizes.
  add_filter('big_image_size_threshold', '__return_false');

  // Set excerpt length to 25 words.
  add_filter('excerpt_length', function () {
    return 25;
  });

  // Customize the excerpt "more" text.
  add_filter('excerpt_more', function () {
    return '...';
  });
});

// Register Carbon Fields assets.
add_action('admin_enqueue_scripts', function () {
  $vendor_url = plugins_url('vendor/carbon-fields', __FILE__);
  wp_enqueue_style('carbon-fields-core', $vendor_url . '/build/classic/core.min.css');
  wp_enqueue_style('carbon-fields-metaboxes', $vendor_url . '/build/classic/metaboxes.min.css');
  wp_enqueue_script('carbon-fields-vendor', $vendor_url . '/build/classic/vendor.min.js', ['jquery'], null, true);
  wp_enqueue_script('carbon-fields-core', $vendor_url . '/build/classic/core.min.js', ['carbon-fields-vendor'], null, true);
  wp_enqueue_script('carbon-fields-metaboxes', $vendor_url . '/build/classic/metaboxes.min.js', ['carbon-fields-core'], null, true);
});

// Register GraphQL support for default post type.
add_action('init', function () {
  // Add GraphQL support for default post type.
  add_post_type_support('post', 'graphql-show-in-graphql');

  // Remove post formats support.
  remove_post_type_support('post', 'post-formats');

  register_graphql_field('Post', 'show_in_graphql', ['type' => 'Boolean']);
  register_graphql_field('Post', 'graphql_single_name', ['type' => 'String']);
  register_graphql_field('Post', 'graphql_plural_name', ['type' => 'String']);

  register_post_type('landing', [
    'labels' => [
      'name' => 'Landing Pages',
      'singular_name' => 'Landing Page',
    ],
    'public' => TRUE,
    'show_in_graphql' => TRUE,
    'graphql_single_name' => 'landing',
    'graphql_plural_name' => 'landings',
    'supports' => ['title'],
    'has_archive' => TRUE,
    'show_in_rest' => TRUE,
    'menu_icon' => 'dashicons-welcome-add-page',
    'menu_position' => 3,
    'rewrite' => [
      'slug' => 'landing',
    ],
    'show_in_nav_menus' => TRUE,
    'publicly_queryable' => TRUE,
  ]);
});

// Remove format meta box from post editor.
add_action('admin_init', function() {
  remove_meta_box('formatdiv', 'post', 'side');
});

// Initialize Carbon Fields.
add_action('carbon_fields_loaded', function () {
  Container::make('post_meta', 'Landing Page Sections')
    ->where('post_type', '=', 'landing')
    ->add_fields([
      Field::make('complex', 'sections')
        ->set_layout('tabbed-vertical')
        ->setup_labels([
          'plural_name' => 'Sections',
          'singular_name' => 'Section',
        ])
        ->add_fields('hero', [
          Field::make('select', 'hero_layout')
            ->set_options([
              'image_top' => 'Image Top',
              'image_bottom' => 'Image Bottom',
              'image_bottom_split' => 'Image Bottom Split',
            ])
            ->set_default_value('image_top')
            ->set_required(TRUE)
            ->set_help_text('Select the layout for this hero section.'),
          Field::make('text', 'heading')
            ->set_help_text('The main heading for the hero section. Use **text** for bold text.'),
          Field::make('rich_text', 'summary')
            ->set_help_text('Provide the teaser summary for the hero.'),
          Field::make('image', 'media')
            ->set_value_type('url')
            ->set_help_text('Featured media item for the hero.'),
          Field::make('text', 'link_title')
            ->set_help_text('The text for the primary call-to-action link.'),
          Field::make('text', 'link_url')
            ->set_help_text('The URL for the primary call-to-action link.'),
          Field::make('text', 'link2_title')
            ->set_help_text('The text for the secondary optional link.'),
          Field::make('text', 'link2_url')
            ->set_help_text('The URL for the secondary optional link.'),
        ])
        ->add_fields('text', [
          Field::make('text', 'eyebrow')
            ->set_help_text('Optional eyebrow text above the title.'),
          Field::make('text', 'title')
            ->set_help_text('The main title for the text section.'),
          Field::make('rich_text', 'body')
            ->set_help_text('The main content for the text section.'),
          Field::make('text', 'link_title')
            ->set_help_text('The text for the primary call-to-action link.'),
          Field::make('text', 'link_url')
            ->set_help_text('The URL for the primary call-to-action link.'),
          Field::make('text', 'link2_title')
            ->set_help_text('The text for the secondary optional link.'),
          Field::make('text', 'link2_url')
            ->set_help_text('The URL for the secondary optional link.'),
          Field::make('select', 'text_layout')
            ->set_options([
              'default' => 'Default',
              'centered' => 'Centered',
              'buttons-right' => 'Buttons Right',
            ])
            ->set_default_value('default')
            ->set_help_text('Select the layout for this text section.'),
        ])
        ->add_fields('accordion', [
          Field::make('text', 'title')
            ->set_help_text('The title for the accordion section.'),
          Field::make('complex', 'items')
            ->set_layout('tabbed-horizontal')
            ->setup_labels([
              'plural_name' => 'Items',
              'singular_name' => 'Item',
            ])
            ->add_fields([
              Field::make('text', 'title')
                ->set_required(TRUE)
                ->set_help_text('The title for this accordion item.'),
              Field::make('rich_text', 'body')
                ->set_required(TRUE)
                ->set_help_text('The content for this accordion item.'),
              Field::make('text', 'link_title')
                ->set_help_text('Optional link text for this item.'),
              Field::make('text', 'link_url')
                ->set_help_text('Optional link URL for this item.'),
            ]),
        ])
        ->add_fields('card_group', [
          Field::make('text', 'title')
            ->set_help_text('The title for the card group section.'),
          Field::make('complex', 'cards')
            ->set_layout('tabbed-horizontal')
            ->setup_labels([
              'plural_name' => 'Cards',
              'singular_name' => 'Card',
            ])
            ->add_fields([
              Field::make('select', 'type')
                ->set_options([
                  'stat' => 'Stat Card',
                  'custom' => 'Custom Card',
                ])
                ->set_required(TRUE)
                ->set_help_text('Select the type of card.'),
              Field::make('image', 'media')
                ->set_value_type('url')
                ->set_help_text('Optional media for the card.'),
              Field::make('text', 'media_link')
                ->set_help_text('Optional link for the media (custom cards only).'),
              Field::make('text', 'heading')
                ->set_required(TRUE)
                ->set_help_text('The heading for the card.'),
              Field::make('text', 'heading_url')
                ->set_help_text('Optional URL for the heading (custom cards only).'),
              Field::make('text', 'body')
                ->set_help_text('The body text for stat cards.'),
              Field::make('text', 'summary_text')
                ->set_help_text('The summary text for custom cards.'),
              Field::make('complex', 'tags')
                ->set_layout('tabbed-vertical')
                ->add_fields([
                  Field::make('text', 'tag')
                    ->set_required(TRUE)
                    ->set_help_text('A tag for the card.'),
                ])
                ->set_help_text('Tags for custom cards.'),
              Field::make('text', 'icon')
                ->set_help_text('Optional icon name for stat cards (e.g., "rocket", "zap", "star").'),
              Field::make('text', 'link_title')
                ->set_help_text('Optional link text for the card.'),
              Field::make('text', 'link_url')
                ->set_help_text('Optional link URL for the card.'),
            ]),
        ])
        ->add_fields('logo_collection', [
          Field::make('text', 'title')
            ->set_help_text('The title for the logo collection section.'),
          Field::make('media_gallery', 'logos')
            ->set_type(['image'])
            ->set_help_text('Add collection of logos.')
        ])
        ->add_fields('carousel', [
          Field::make('text', 'title')
            ->set_help_text('The title for the carousel section.'),
          Field::make('complex', 'items')
            ->set_layout('tabbed-horizontal')
            ->setup_labels([
              'plural_name' => 'Items',
              'singular_name' => 'Item',
            ])
            ->add_fields([
              Field::make('image', 'media')
                ->set_value_type('url')
                ->set_required(TRUE)
                ->set_help_text('The image for this item.'),
              Field::make('text', 'title')
                ->set_required(TRUE)
                ->set_help_text('The title for this item.'),
              Field::make('text', 'summary')
                ->set_help_text('The summary text for this item.'),
            ]),
        ])
        ->add_fields('embed', [
          Field::make('text', 'title')
            ->set_help_text('The title for the embed section.'),
          Field::make('rich_text', 'script')
            ->set_required(TRUE)
            ->set_help_text('Paste the third-party script embed here (e.g., YouTube, Twitter, etc.).'),
          Field::make('text', 'caption')
            ->set_help_text('Optional caption text for the embedded content.'),
          Field::make('text', 'max_width')
            ->set_help_text('Optional maximum width for the embed (e.g., 800px, 100%).'),
        ])
        ->add_fields('media', [
          Field::make('text', 'title')
            ->set_help_text('Optional title for the media section.'),
          Field::make('image', 'media')
            ->set_value_type('url')
            ->set_required(TRUE)
            ->set_help_text('The media to display in this section.'),
        ])
        ->add_fields('gallery', [
          Field::make('text', 'title')
            ->set_help_text('The title for the gallery section.'),
          Field::make('rich_text', 'summary')
            ->set_help_text('Optional summary text for the gallery.'),
          Field::make('complex', 'media_items')
            ->set_layout('tabbed-horizontal')
            ->setup_labels([
              'plural_name' => 'Media Items',
              'singular_name' => 'Media Item',
            ])
            ->add_fields([
              Field::make('image', 'media')
                ->set_value_type('url')
                ->set_required(TRUE)
                ->set_help_text('Image for the gallery.'),
              Field::make('text', 'alt')
                ->set_help_text('Alt text for the image.')
            ])
        ])
        ->add_fields('newsletter', [
          Field::make('text', 'title')
            ->set_required(TRUE)
            ->set_help_text('The title for the newsletter section.')
            ->set_default_value('Sign up for our newsletter.'),
          Field::make('rich_text', 'summary')
            ->set_required(TRUE)
            ->set_help_text('The summary text for the newsletter section.')
            ->set_default_value('Stay up to date with our latest news and updates.'),
        ])
        ->add_fields('recent_posts', [
          Field::make('text', 'title')
            ->set_help_text('Optional title for the recent posts section.')
            ->set_default_value(''),
          Field::make('text', 'post_count')
            ->set_attribute('type', 'number')
            ->set_default_value(6)
            ->set_help_text('Number of recent posts to display.'),
        ])
        ->add_fields('pricing', [
          Field::make('text', 'eyebrow')
            ->set_help_text('The eyebrow text above the title.')
            ->set_default_value(''),
          Field::make('text', 'title')
            ->set_required(TRUE)
            ->set_help_text('The main title for the pricing section.')
            ->set_default_value('Compare Our Options'),
          Field::make('text', 'summary')
            ->set_help_text('The summary text below the title.')
            ->set_default_value(''),
          Field::make('text', 'includes_label')
            ->set_required(TRUE)
            ->set_help_text('The label for the features list.')
            ->set_default_value('Includes'),
          Field::make('complex', 'cards')
            ->set_required(TRUE)
            ->set_min(1)
            ->set_layout('tabbed-horizontal')
            ->setup_labels([
              'plural_name' => 'Pricing Cards',
              'singular_name' => 'Pricing Card',
            ])
            ->add_fields([
              Field::make('text', 'eyebrow')
                ->set_required(TRUE)
                ->set_help_text('The eyebrow text for this pricing card.'),
              Field::make('text', 'title')
                ->set_required(TRUE)
                ->set_help_text('The title/price for this pricing card.'),
              Field::make('text', 'monthly_label')
                ->set_help_text('Optional monthly label (e.g., "/month").'),
              Field::make('complex', 'features')
                ->set_required(TRUE)
                ->set_min(1)
                ->set_layout('tabbed-vertical')
                ->setup_labels([
                  'plural_name' => 'Features',
                  'singular_name' => 'Feature',
                ])
                ->add_fields([
                  Field::make('text', 'text')
                    ->set_required(TRUE)
                    ->set_help_text('The feature text.'),
                ]),
              Field::make('text', 'cta_text')
                ->set_required(TRUE)
                ->set_help_text('The call-to-action button text.'),
              Field::make('text', 'cta_link')
                ->set_required(TRUE)
                ->set_help_text('The call-to-action button link.'),
            ]),
        ])
        ->add_fields('quote', [
          Field::make('text', 'quote')
            ->set_required(TRUE)
            ->set_help_text('The quote text.'),
          Field::make('text', 'author')
            ->set_required(TRUE)
            ->set_help_text('The name of the person being quoted.'),
          Field::make('text', 'job_title')
            ->set_help_text('Optional job title of the person.'),
          Field::make('image', 'media')
            ->set_value_type('url')
            ->set_help_text('Optional photo of the person being quoted.'),
        ])
        ->add_fields('side_by_side', [
          Field::make('text', 'eyebrow')
            ->set_help_text('Optional eyebrow text above the title.'),
          Field::make('select', 'layout')
            ->set_options([
              'image_left' => 'Image Left',
              'image_right' => 'Image Right',
            ])
            ->set_default_value('image_left')
            ->set_help_text('Choose the layout for this section.'),
          Field::make('text', 'title')
            ->set_required(TRUE)
            ->set_help_text('The main title for this section.'),
          Field::make('rich_text', 'summary')
            ->set_help_text('Optional summary text for this section.'),
          Field::make('text', 'link_title')
            ->set_help_text('Optional link text.'),
          Field::make('text', 'link_url')
            ->set_help_text('Optional link URL.'),
          Field::make('image', 'media')
            ->set_value_type('url')
            ->set_required(TRUE)
            ->set_help_text('The image to display in this section.'),
          Field::make('complex', 'features')
            ->set_layout('tabbed-vertical')
            ->setup_labels([
              'plural_name' => 'Features',
              'singular_name' => 'Feature',
            ])
            ->add_fields('bullet', [
              Field::make('text', 'text')
                ->set_required(TRUE)
                ->set_help_text('The feature text.'),
              Field::make('text', 'icon')
                ->set_help_text('Optional icon name (e.g., "check", "star", "rocket"). Defaults to "check" if not specified.'),
            ])
            ->add_fields('stat', [
              Field::make('text', 'title')
                ->set_required(TRUE)
                ->set_help_text('The stat title/heading.'),
              Field::make('text', 'summary')
                ->set_required(TRUE)
                ->set_help_text('The stat summary/value.'),
              Field::make('text', 'icon')
                ->set_help_text('Optional icon name (e.g., "users", "chart", "trending-up").'),
              Field::make('image', 'custom_icon')
                ->set_value_type('url')
                ->set_help_text('Optional custom icon image. Takes precedence over icon name if both are specified.'),
            ]),
        ]),
    ]);
});

// Helper function to consistently resolve media fields
function resolve_media_field($media_url) {
  if (empty($media_url)) {
    return NULL;
  }

  $attachment_id = attachment_url_to_postid($media_url);
  if (!$attachment_id) {
    // If we can't get an attachment ID but have a URL, return just the URL
    return [
      'sourceUrl' => $media_url
    ];
  }

  $metadata = wp_get_attachment_metadata($attachment_id);
  return [
    'sourceUrl' => wp_get_attachment_url($attachment_id),
    'width' => (int) ($metadata['width'] ?? 0),
    'height' => (int) ($metadata['height'] ?? 0),
    'mimeType' => get_post_mime_type($attachment_id)
  ];
}

// Register GraphQL fields.
add_action('graphql_register_types', function () {
  // Add homepage settings field
  register_graphql_field('RootQuery', 'homepageSettings', [
    'type' => 'HomepageSettings',
    'description' => 'Get WordPress homepage settings',
    'resolve' => function() {
      return [
        'showOnFront' => get_option('show_on_front'),
        'pageOnFront' => get_option('page_on_front'),
      ];
    }
  ]);

  register_graphql_object_type('HomepageSettings', [
    'fields' => [
      'showOnFront' => ['type' => 'String'],
      'pageOnFront' => ['type' => 'Int'],
    ],
  ]);

  // Register CarouselItem type
  register_graphql_object_type('CarouselItem', [
    'fields' => [
      'media' => [
        'type' => 'Media',
        'description' => 'Media for the carousel item',
      ],
      'title' => [
        'type' => 'String',
        'description' => 'Title of the carousel item',
      ],
      'summary' => [
        'type' => 'String',
        'description' => 'Summary text for the carousel item',
      ],
    ],
  ]);

  register_graphql_object_type('GalleryMediaItem', [
    'fields' => [
      'media' => ['type' => 'Media'],
      'alt' => ['type' => 'String'],
    ],
  ]);

  register_graphql_field('Landing', 'sections', [
    'type' => ['list_of' => 'LandingSection'],
    'description' => 'Sections of the landing page.',
    'resolve' => function ($post) {
      $sections = carbon_get_post_meta($post->ID, 'sections');
      return array_map(function ($section) {
        $link = [
          'url' => $section['link_url'] ?? '',
          'title' => $section['link_title'] ?? '',
        ];
        $link2 = [
          'url' => $section['link2_url'] ?? '',
          'title' => $section['link2_title'] ?? '',
        ];

        // Convert markdown bold to HTML
        $heading = $section['heading'] ?? '';
        $heading = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $heading);

        // Process accordion items if this is an accordion section
        $accordion_items = [];
        if ($section['_type'] === 'accordion' && !empty($section['items'])) {
          $accordion_items = array_map(function ($item) {
            return [
              'title' => $item['title'] ?? '',
              'body' => [
                'value' => $item['body'] ?? '',
              ],
              'link' => !empty($item['link_url']) ? [
                'url' => $item['link_url'],
                'title' => $item['link_title'] ?? '',
              ] : NULL,
            ];
          }, $section['items']);
        }

        // Process carousel items if this is a carousel section
        $carousel_items = [];
        if ($section['_type'] === 'carousel' && !empty($section['items'])) {
          $carousel_items = array_map(function ($item) {
            return [
              'media' => resolve_media_field($item['media']),
              'title' => $item['title'] ?? '',
              'summary' => $item['summary'] ?? '',
            ];
          }, $section['items']);
        }

        // Process cards if this is a card group section
        $cards = [];
        if ($section['_type'] === 'card_group' && !empty($section['cards'])) {
          $cards = array_map(function ($card) {
            // Process tags
            $tags = [];
            if (!empty($card['tags'])) {
              $tags = array_map(function ($tag_item) {
                return $tag_item['tag'] ?? '';
              }, $card['tags']);
            }

            return [
              'type' => $card['type'] ?? 'custom',
              'media' => resolve_media_field($card['media']),
              'mediaLink' => $card['media_link'] ?? '',
              'heading' => [
                'title' => $card['heading'] ?? '',
                'url' => $card['type'] === 'custom' ? ($card['heading_url'] ?? '') : NULL,
              ],
              'body' => $card['body'] ?? '',
              'summaryText' => $card['summary_text'] ?? '',
              'tags' => $tags,
              'icon' => $card['icon'] ?? '',
              'link' => !empty($card['link_url']) ? [
                'url' => $card['link_url'],
                'title' => $card['link_title'] ?? '',
              ] : NULL,
            ];
          }, $section['cards']);
        }

        // Process gallery media items if this is a gallery section
        $gallery_media_items = [];
        if ($section['_type'] === 'gallery' && !empty($section['media_items'])) {
          $gallery_media_items = array_map(function ($item) {
            return [
              'media' => resolve_media_field($item['media']),
              'alt' => $item['alt'] ?? '',
            ];
          }, $section['media_items']);
        }

        $type = $section['_type'] ?? 'hero';

        $base = [
          'type' => $type,
          'title' => $section['title'] ?? '',
        ];

        switch ($type) {
          case 'logo_collection':
            return array_merge($base, [
              'type' => 'logo_collection', // Explicitly set the type
              'logos' => array_map(function ($logo_id) {
                $attachment = get_post($logo_id);
                $media = resolve_media_field(wp_get_attachment_url($logo_id));
                $media['alt'] = get_post_meta($logo_id, '_wp_attachment_image_alt', true) ?: $attachment->post_title;
                return $media;
              }, $section['logos'] ?: []),
            ]);

          case 'hero':
            return array_merge($base, [
              'heroLayout' => $section['hero_layout'] ?? 'image_top',
              'heading' => $heading,
              'summary' => $section['summary'] ?? '',
              'media' => resolve_media_field($section['media']),
              'link' => $link,
              'link2' => $link2,
            ]);

          case 'accordion':
            return array_merge($base, [
              'accordionItems' => $accordion_items,
            ]);

          case 'carousel':
            return array_merge($base, [
              'carouselItems' => $carousel_items,
            ]);

          case 'card_group':
            return array_merge($base, [
              'cards' => $cards,
            ]);

          case 'embed':
            return array_merge($base, [
              'script' => $section['script'] ?? '',
              'caption' => $section['caption'] ?? '',
              'maxWidth' => $section['max_width'] ?? '',
            ]);

          case 'media':
            return array_merge($base, [
              'media' => resolve_media_field($section['media'])
            ]);

          case 'gallery':
            return array_merge($base, [
              'summary' => $section['summary'] ?? NULL,
              'mediaItems' => !empty($section['media_items']) ? array_map(function ($item) {
                return [
                  'media' => resolve_media_field($item['media']),
                  'alt' => $item['alt'] ?? NULL,
                ];
              }, $section['media_items']) : [],
            ]);

          case 'newsletter':
            return array_merge($base, [
              'summary' => $section['summary'] ?? NULL,
            ]);

          case 'recent_posts':
            return array_merge($base, [
              'title' => $section['title'] ?? 'Recent Posts',
            ]);

          case 'pricing':
            return array_merge($base, [
              'eyebrow' => $section['eyebrow'] ?? '',
              'summary' => $section['summary'] ?? '',
              'includesLabel' => $section['includes_label'] ?? '',
              'pricingCards' => !empty($section['cards']) ? array_map(function ($card) {
                return [
                  'eyebrow' => $card['eyebrow'] ?? '',
                  'title' => $card['title'] ?? '',
                  'monthlyLabel' => $card['monthly_label'] ?? '',
                  'features' => !empty($card['features']) ? array_map(function ($feature) {
                    // Handle both string features and object features with a 'text' property.
                    if (is_array($feature) && isset($feature['text'])) {
                      return $feature['text'];
                    }
                    elseif (is_string($feature)) {
                      return $feature;
                    }
                    return '';
                  }, $card['features']) : [],
                  'ctaText' => $card['cta_text'] ?? '',
                  'ctaLink' => $card['cta_link'] ?? '',
                ];
              }, $section['cards']) : [],
            ]);

          case 'quote':
            return array_merge($base, [
              'quote' => $section['quote'] ?? '',
              'author' => $section['author'] ?? '',
              'jobTitle' => $section['job_title'] ?? NULL,
              'media' => resolve_media_field($section['media'] ?? NULL),
            ]);

          case 'side_by_side':
            return array_merge($base, [
              'eyebrow' => $section['eyebrow'] ?? '',
              'layout' => $section['layout'] ?? 'image_left',
              'title' => $section['title'] ?? '',
              'summary' => $section['summary'] ?? '',
              'link' => !empty($section['link_url']) ? [
                'url' => $section['link_url'],
                'title' => $section['link_title'] ?? '',
              ] : NULL,
              'media' => resolve_media_field($section['media']),
              'features' => !empty($section['features']) ? array_map(function ($feature) {
                if ($feature['_type'] === 'bullet') {
                  return [
                    'type' => 'bullet',
                    'text' => $feature['text'] ?? '',
                    'icon' => $feature['icon'] ?? 'check',
                  ];
                } else if ($feature['_type'] === 'stat') {
                  return [
                    'type' => 'stat',
                    'title' => $feature['title'] ?? '',
                    'summary' => $feature['summary'] ?? '',
                    'icon' => $feature['icon'] ?? '',
                    'customIcon' => isset($feature['custom_icon']) ? resolve_media_field($feature['custom_icon']) : NULL,
                  ];
                }
                return NULL;
              }, $section['features']) : [],
            ]);

          case 'text':
            return array_merge($base, [
              'eyebrow' => $section['eyebrow'] ?? '',
              'title' => $section['title'] ?? '',
              'body' => $section['body'] ?? '',
              'textLayout' => $section['text_layout'] ?? 'default',
              'link' => !empty($section['link_url']) ? [
                'url' => $section['link_url'],
                'title' => $section['link_title'] ?? '',
              ] : NULL,
              'link2' => !empty($section['link2_url']) ? [
                'url' => $section['link2_url'],
                'title' => $section['link2_title'] ?? '',
              ] : NULL,
            ]);

          default:
            return $base;
        }
      }, $sections ?: []);
    },
  ]);

  register_graphql_object_type('AccordionItem', [
    'fields' => [
      'title' => ['type' => 'String'],
      'body' => ['type' => 'AccordionBody'],
      'link' => ['type' => 'Link'],
    ],
  ]);

  register_graphql_object_type('AccordionBody', [
    'fields' => [
      'value' => ['type' => 'String'],
    ],
  ]);

  register_graphql_object_type('Link', [
    'fields' => [
      'url' => ['type' => 'String'],
      'title' => ['type' => 'String'],
    ],
  ]);

  register_graphql_object_type('ImageSize', [
    'fields' => [
      'name' => ['type' => 'String'],
      'sourceUrl' => ['type' => 'String'],
      'width' => ['type' => 'Int'],
      'height' => ['type' => 'Int'],
    ],
  ]);

  register_graphql_object_type('Media', [
    'fields' => [
      'sourceUrl' => ['type' => 'String'],
      'width' => ['type' => 'Int'],
      'height' => ['type' => 'Int'],
      'alt' => ['type' => 'String'],
    ],
  ]);

  register_graphql_object_type('Card', [
    'fields' => [
      'type' => ['type' => 'String'],
      'media' => ['type' => 'Media'],
      'mediaLink' => ['type' => 'String'],
      'heading' => ['type' => 'CardHeading'],
      'body' => ['type' => 'String'],
      'summaryText' => ['type' => 'String'],
      'tags' => ['type' => ['list_of' => 'String']],
      'icon' => ['type' => 'String'],
      'link' => ['type' => 'Link'],
    ],
  ]);

  register_graphql_object_type('CardHeading', [
    'fields' => [
      'title' => ['type' => ['non_null' => 'String']],
      'url' => ['type' => 'String'],
    ],
  ]);

  register_graphql_object_type('PricingCard', [
    'fields' => [
      'eyebrow' => ['type' => 'String'],
      'title' => ['type' => 'String'],
      'monthlyLabel' => ['type' => 'String'],
      'features' => ['type' => ['list_of' => 'String']],
      'ctaText' => ['type' => 'String'],
      'ctaLink' => ['type' => 'String'],
    ],
  ]);

  // Register GraphQL types for recent posts
  register_graphql_object_type('FeaturedImageNode', [
    'fields' => [
      'sourceUrl' => ['type' => 'String'],
      'altText' => ['type' => 'String'],
    ],
  ]);

  register_graphql_object_type('FeaturedImage', [
    'fields' => [
      'node' => ['type' => 'FeaturedImageNode'],
    ],
  ]);

  register_graphql_object_type('PostTag', [
    'fields' => [
      'name' => ['type' => 'String'],
      'slug' => ['type' => 'String'],
    ],
  ]);

  register_graphql_object_type('PostTags', [
    'fields' => [
      'nodes' => ['type' => ['list_of' => 'PostTag']],
    ],
  ]);

  register_graphql_object_type('RecentPost', [
    'fields' => [
      'databaseId' => ['type' => 'Int'],
      'title' => ['type' => 'String'],
      'slug' => ['type' => 'String'],
      'excerpt' => ['type' => 'String'],
      'date' => ['type' => 'String'],
      'featuredImage' => [
        'type' => 'FeaturedImage',
        'description' => 'Featured image of the post',
      ],
      'tags' => [
        'type' => 'PostTags',
        'description' => 'Tags associated with the post',
      ],
    ],
  ]);

  // Update the LandingSection type to include recent posts
  register_graphql_field('LandingSection', 'recentPosts', [
    'type' => ['list_of' => 'RecentPost'],
    'description' => 'Recent posts for recent posts section',
    'resolve' => function($section) {
      if ($section['type'] !== 'recent_posts') {
        return null;
      }

      $post_count = isset($section['post_count']) ? intval($section['post_count']) : 6;

      $args = [
        'post_type' => 'post',
        'posts_per_page' => $post_count,
        'orderby' => 'date',
        'order' => 'DESC',
        'post_status' => 'publish',
      ];

      $recent_posts = get_posts($args);

      return array_map(function($post) {
        // Get featured image data
        $featured_image = null;
        if (has_post_thumbnail($post->ID)) {
          $image_id = get_post_thumbnail_id($post->ID);
          $image_url = wp_get_attachment_image_url($image_id, 'full');
          $alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);

          $featured_image = [
            'node' => [
              'sourceUrl' => $image_url,
              'altText' => $alt_text,
            ]
          ];
        }

        // Get post tags
        $tags = wp_get_post_tags($post->ID, ['fields' => 'all']);
        $tag_nodes = array_map(function($tag) {
          return [
            'name' => $tag->name,
            'slug' => $tag->slug,
          ];
        }, $tags);

        return [
          'databaseId' => $post->ID,
          'title' => get_the_title($post),
          'slug' => $post->post_name,
          'excerpt' => get_the_excerpt($post),
          'date' => get_the_date('c', $post),
          'featuredImage' => $featured_image,
          'tags' => [
            'nodes' => $tag_nodes,
          ],
        ];
      }, $recent_posts);
    }
  ]);

  // Update the LandingSection type
  register_graphql_object_type('LandingSection', [
    'fields' => [
      'type' => ['type' => 'String'],
      'title' => ['type' => 'String'],
      // Hero fields
      'heroLayout' => ['type' => 'String'],
      'heading' => ['type' => 'String'],
      'summary' => ['type' => 'String'],
      'media' => ['type' => 'Media'],
      'link' => ['type' => 'Link'],
      'link2' => ['type' => 'Link'],
      // Accordion fields
      'accordionItems' => [
        'type' => ['list_of' => 'AccordionItem'],
        'description' => 'Items for accordion section',
      ],
      // Carousel fields
      'carouselItems' => [
        'type' => ['list_of' => 'CarouselItem'],
        'description' => 'Items for carousel section',
      ],
      // Card group fields
      'cards' => [
        'type' => ['list_of' => 'Card'],
        'description' => 'Cards for card group section',
      ],
      // Embed fields
      'script' => ['type' => 'String'],
      'caption' => ['type' => 'String'],
      'maxWidth' => ['type' => 'String'],
      // Gallery fields
      'mediaItems' => [
        'type' => ['list_of' => 'GalleryMediaItem'],
        'description' => 'Media items for gallery section',
      ],
      // Logo collection fields
      'logos' => [
        'type' => ['list_of' => 'Media'],
        'description' => 'Logos for logo collection section',
      ],
      // Recent posts fields
      'recentPosts' => [
        'type' => ['list_of' => 'RecentPost'],
        'description' => 'Recent posts for recent posts section',
        'resolve' => function($section) {
          if ($section['type'] !== 'recent_posts') {
            return null;
          }

          $post_count = isset($section['post_count']) ? intval($section['post_count']) : 6;

          $args = [
            'post_type' => 'post',
            'posts_per_page' => $post_count,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish',
          ];

          $recent_posts = get_posts($args);

          return array_map(function($post) {
            // Get featured image data
            $featured_image = null;
            if (has_post_thumbnail($post->ID)) {
              $image_id = get_post_thumbnail_id($post->ID);
              $image_url = wp_get_attachment_image_url($image_id, 'full');
              $alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);

              $featured_image = [
                'node' => [
                  'sourceUrl' => $image_url,
                  'altText' => $alt_text,
                ]
              ];
            }

            // Get post tags
            $tags = wp_get_post_tags($post->ID, ['fields' => 'all']);
            $tag_nodes = array_map(function($tag) {
              return [
                'name' => $tag->name,
                'slug' => $tag->slug,
              ];
            }, $tags);

            return [
              'databaseId' => $post->ID,
              'title' => get_the_title($post),
              'slug' => $post->post_name,
              'excerpt' => get_the_excerpt($post),
              'date' => get_the_date('c', $post),
              'featuredImage' => $featured_image,
              'tags' => [
                'nodes' => $tag_nodes,
              ],
            ];
          }, $recent_posts);
        }
      ],
      // Newsletter fields
      'summary' => ['type' => 'String'],
      // Pricing fields
      'eyebrow' => ['type' => 'String'],
      'includesLabel' => ['type' => 'String'],
      'pricingCards' => [
        'type' => ['list_of' => 'PricingCard'],
        'description' => 'Cards for pricing section',
      ],
      // Quote fields
      'quote' => ['type' => 'String'],
      'author' => ['type' => 'String'],
      'jobTitle' => ['type' => 'String'],
      // Sidebyside fields
      'layout' => ['type' => 'String'],
      'features' => [
        'type' => ['list_of' => 'FeatureItem'],
        'description' => 'Features for sidebyside section',
      ],
      // Text fields
      'body' => ['type' => 'String'],
      'textLayout' => ['type' => 'String'],
    ],
  ]);

  register_graphql_object_type('FeatureItem', [
    'fields' => [
      'type' => ['type' => 'String'],
      'text' => ['type' => 'String'],
      'icon' => ['type' => 'String'],
      'title' => ['type' => 'String'],
      'summary' => ['type' => 'String'],
      'customIcon' => ['type' => 'Media'],
    ],
  ]);

  register_graphql_field('LandingSection', 'features', [
    'type' => ['list_of' => 'FeatureItem'],
    'description' => 'Features for sidebyside section',
    'resolve' => function($section) {
      if (!isset($section['features']) || !is_array($section['features'])) {
        return [];
      }

      return array_map(function($feature) {
        if ($feature['_type'] === 'bullet') {
          return [
            'type' => 'bullet',
            'text' => $feature['text'] ?? '',
            'icon' => $feature['icon'] ?? 'check',
          ];
        } else if ($feature['_type'] === 'stat') {
          return [
            'type' => 'stat',
            'title' => $feature['title'] ?? '',
            'summary' => $feature['summary'] ?? '',
            'icon' => $feature['icon'] ?? '',
            'customIcon' => isset($feature['custom_icon']) ? resolve_media_field($feature['custom_icon']) : NULL,
          ];
        }
        return NULL;
      }, $section['features']);
    },
  ]);
});

// Register navigation menus.
add_action('init', function () {
  register_nav_menus([
    'primary' => 'Primary Navigation',
    'footer' => 'Footer Navigation',
  ]);
});

// Add GraphQL resolvers for menus
add_filter('graphql_RootQuery_fields', function($fields) {
  $fields['menu'] = [
    'type' => 'Menu',
    'description' => 'A WordPress navigation menu',
    'args' => [
      'id' => [
        'type' => ['non_null' => 'ID'],
        'description' => 'ID of the menu',
      ],
      'idType' => [
        'type' => 'MenuNodeIdTypeEnum',
        'description' => 'Type of ID being used to identify the menu',
      ],
    ],
    'resolve' => function($source, $args) {
      $menu_id = null;

      if (isset($args['idType']) && $args['idType'] === 'NAME') {
        $menu = wp_get_nav_menu_object($args['id']);
        $menu_id = $menu ? $menu->term_id : null;
      } else {
        $menu_id = absint($args['id']);
      }

      if (!$menu_id) {
        return null;
      }

      $menu_items = wp_get_nav_menu_items($menu_id);
      if (!$menu_items) {
        return null;
      }

      // Build hierarchical menu items
      $menu_items_by_parent = [];
      foreach ($menu_items as $menu_item) {
        $menu_items_by_parent[$menu_item->menu_item_parent][] = [
          'id' => (string) $menu_item->ID,
          'title' => $menu_item->title,
          'url' => str_replace(home_url(), '', $menu_item->url),
          'parent' => $menu_item->menu_item_parent,
        ];
      }

      // Function to build tree
      $build_tree = function($parent) use (&$build_tree, $menu_items_by_parent) {
        $items = $menu_items_by_parent[$parent] ?? [];
        foreach ($items as &$item) {
          if (isset($menu_items_by_parent[$item['id']])) {
            $item['children'] = [
              'nodes' => $build_tree($item['id'])
            ];
          }
        }
        return $items;
      };

      return [
        'id' => (string) $menu_id,
        'name' => wp_get_nav_menu_name($menu_id),
        'items' => $build_tree('0'),
      ];
    }
  ];

  return $fields;
});

// Add GraphQL types for menus
add_action('graphql_register_types', function() {
  register_graphql_enum_type('MenuNodeIdTypeEnum', [
    'description' => 'Identifies the type of ID being used to identify the menu',
    'values' => [
      'DATABASE_ID' => [
        'value' => 'DATABASE_ID',
        'description' => 'Identifies a menu node by its database ID',
      ],
      'NAME' => [
        'value' => 'NAME',
        'description' => 'Identifies a menu node by its name',
      ],
    ],
  ]);

  register_graphql_object_type('Menu', [
    'fields' => [
      'id' => ['type' => 'ID'],
      'name' => ['type' => 'String'],
      'items' => ['type' => ['list_of' => 'MenuItem']],
    ],
  ]);

  register_graphql_object_type('MenuItem', [
    'fields' => [
      'id' => ['type' => 'ID'],
      'title' => ['type' => 'String'],
      'url' => ['type' => 'String'],
      'children' => ['type' => 'MenuItemChildren'],
    ],
  ]);

  register_graphql_object_type('MenuItemChildren', [
    'fields' => [
      'nodes' => ['type' => ['list_of' => 'MenuItem']],
    ],
  ]);
});

// Modify preview links for Next.js.
add_filter('preview_post_link', function ($preview_link, $post) {
  if (!$post) {
    return $preview_link;
  }

  $frontend_url = defined('PRESSX_FRONTEND_URL') ? PRESSX_FRONTEND_URL : 'http://localhost:3333';
  $preview_secret = defined('WORDPRESS_PREVIEW_SECRET') ? WORDPRESS_PREVIEW_SECRET : 'pressx_preview_secret';

  return sprintf(
    '%s/api/preview?secret=%s&id=%d',
    untrailingslashit($frontend_url),
    $preview_secret,
    $post->ID
  );
}, 10, 2);

// Modify permalink for posts and landing pages.
add_filter('post_type_link', function ($post_link, $post) {
  if (!$post) {
    return $post_link;
  }

  $frontend_url = defined('PRESSX_FRONTEND_URL') ? PRESSX_FRONTEND_URL : 'http://localhost:3333';

  switch ($post->post_type) {
    case 'landing':
      return sprintf(
        '%s/%s',
        untrailingslashit($frontend_url),
        $post->post_name
      );
    case 'post':
      return sprintf(
        '%s/post/%s',
        untrailingslashit($frontend_url),
        $post->post_name
      );
    default:
      return $post_link;
  }
}, 10, 2);

// Modify view post link for posts and landing pages.
add_filter('post_link', function ($url, $post) {
  if (!$post) {
    return $url;
  }

  $frontend_url = defined('PRESSX_FRONTEND_URL') ? PRESSX_FRONTEND_URL : 'http://localhost:3333';

  switch ($post->post_type) {
    case 'landing':
      return sprintf(
        '%s/%s',
        untrailingslashit($frontend_url),
        $post->post_name
      );
    case 'post':
      return sprintf(
        '%s/post/%s',
        untrailingslashit($frontend_url),
        $post->post_name
      );
    default:
      return $url;
  }
}, 10, 2);

// Override get_sample_permalink for posts and landing pages in admin.
add_filter('get_sample_permalink', function ($permalink, $post_id, $title, $name, $post) {
  if (!$post) {
    return $permalink;
  }

  $frontend_url = defined('PRESSX_FRONTEND_URL') ? PRESSX_FRONTEND_URL : 'http://localhost:3333';
  $post_name = $name ? $name : $post->post_name;

  switch ($post->post_type) {
    case 'landing':
      return [
        sprintf('%s/%%pagename%%', untrailingslashit($frontend_url)),
        $post_name,
      ];
    case 'post':
      return [
        sprintf('%s/post/%%pagename%%', untrailingslashit($frontend_url)),
        $post_name,
      ];
    default:
      return $permalink;
  }
}, 10, 5);
