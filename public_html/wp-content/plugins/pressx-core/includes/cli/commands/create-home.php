<?php

/**
 * @file
 * Script to create the home page.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Creates the home page.
 *
 * @param bool $force
 *   Whether to force recreation of the home page even if it already exists.
 *
 * @return bool
 *   TRUE if successful, FALSE otherwise.
 */
function pressx_create_home($force = FALSE) {
  // Include the image handler.
  require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'image-handler.php';

  // Get the image ID using the helper function.
  $image_path = plugin_dir_path(dirname(dirname(__FILE__))) . 'images/card.png';
  $image_id = pressx_ensure_image($image_path);

  // First, import all the technology logos.
  $logo_paths = [
    'wordpress' => plugin_dir_path(dirname(dirname(__FILE__))) . 'images/wordpress.png',
    'nextjs' => plugin_dir_path(dirname(dirname(__FILE__))) . 'images/nextjs.png',
    'storybook' => plugin_dir_path(dirname(dirname(__FILE__))) . 'images/storybook.png',
    'tailwind' => plugin_dir_path(dirname(dirname(__FILE__))) . 'images/tailwind.png',
    'shadcn' => plugin_dir_path(dirname(dirname(__FILE__))) . 'images/shadcn.png',
    'graphql' => plugin_dir_path(dirname(dirname(__FILE__))) . 'images/graphql.png',
    'react' => plugin_dir_path(dirname(dirname(__FILE__))) . 'images/react.png',
  ];

  $logo_ids = [];

  // Import each logo into the media library using the helper function.
  foreach ($logo_paths as $name => $logo_path) {
    if (file_exists($logo_path)) {
      // Use the pressx_ensure_image function with a custom title.
      $logo_id = pressx_ensure_image($logo_path, sanitize_file_name($name));
      if ($logo_id) {
        $logo_ids[] = $logo_id;
      }
    }
  }

  // Check if the page already exists.
  $existing_page = get_page_by_path('home');

  if ($existing_page && !$force) {
    WP_CLI::log("Home page already exists. Skipping.");
    return TRUE;
  }

  // Prepare the page data.
  $page_args = [
    'post_title' => 'PressX - AI-Powered Lightning Fast Development',
    'post_name' => 'home',
    'post_status' => 'publish',
    'post_type' => 'landing',
  ];

  // If the page exists and force is true, update it.
  if ($existing_page && $force) {
    $page_args['ID'] = $existing_page->ID;
    $page_id = wp_update_post($page_args);
    WP_CLI::log("Updated home page.");
  }
  else {
    // Otherwise, create a new page.
    $page_id = wp_insert_post($page_args);
    WP_CLI::log("Created home page.");
  }

  // Set the featured image if provided.
  if ($page_id && $image_id) {
    set_post_thumbnail($page_id, $image_id);
  }

  // Add sections to match the design.
  $image_url = $image_id ? wp_get_attachment_url($image_id) : '';

  $sections = [
    [
      '_type' => 'hero',
      'hero_layout' => 'image_top',
      'heading' => '**Empower** Your Web Development with **PressX**',
      'summary' => 'Experience the next-gen platform that seamlessly integrates WordPress\'s powerful CMS with innovative front-end technologies. Elevate your projects with unmatched flexibility and performance.',
      'media' => $image_url,
      'link_title' => 'Start',
      'link_url' => '#get-started',
      'link2_title' => 'Explore',
      'link2_url' => '#features',
    ],
    [
      '_type' => 'side_by_side',
      'eyebrow' => '',
      'layout' => 'image_right',
      'title' => 'Unmatched Advantages of PressX',
      'summary' => 'Elevate Your Web Development with PressX\'s Cutting Edge Features',
      'media' => $image_url,
      'features' => [
        [
          '_type' => 'stat',
          'title' => 'Decoupled Architecture',
          'summary' => 'Harness the power of headless CMS to separate the front and back-end for optimal flexibility.',
          'icon' => 'network',
        ],
        [
          '_type' => 'stat',
          'title' => 'AI Development Tools',
          'summary' => 'Leverage cutting-edge AI assistants and smart code generators to accelerate your development.',
          'icon' => 'bot',
        ],
      ],
    ],
    [
      '_type' => 'card_group',
      'title' => 'Discover the Future of Web Development with PressX\'s Innovative Features',
      'cards' => [
        [
          'type' => 'stat',
          'heading' => 'Unlock the Power of Modern Web Solutions Tailored for You',
          'body' => 'Experience seamless integration and flexibility with our Decoupled Architecture.',
          'icon' => 'code',
        ],
        [
          'type' => 'stat',
          'heading' => 'Experience seamless integration and flexibility with our Decoupled Architecture',
          'body' => 'Leverage the power of React and Node.js to dramatically improve your website\'s efficiency and speed.',
          'icon' => 'git-branch',
        ],
        [
          'type' => 'stat',
          'heading' => 'Achieve Blazing Fast Performance That Keeps Your Users Engaged and Satisfied',
          'body' => 'Deliver lightning-quick load times and smooth interactions that keep your audience coming back.',
          'icon' => 'zap',
        ],
      ],
    ],
    [
      '_type' => 'logo_collection',
      'title' => 'Trusted Open Source Technology',
      'logos' => $logo_ids,
    ],
    [
      '_type' => 'side_by_side',
      'eyebrow' => '',
      'layout' => 'image_left',
      'title' => 'Elevate Your Web Development Skills with PressX',
      'summary' => 'Join the PressX ecosystem to unlock new possibilities in web development. Our platform combines the best of WordPress\'s robust back-end with cutting-edge front-end technologies, enabling you to create powerful, scalable, and innovative web solutions.',
      'media' => $image_url,
    ],
    [
      '_type' => 'text',
      'title' => 'Accelerate Your Web Development Journey',
      'body' => 'Start building faster, more efficient websites today. Explore PressX\'s capabilities and see how it can transform your development process.',
      'text_layout' => 'buttons-right',
      'link_title' => 'Get Started',
      'link_url' => '/get-started',
      'link2_title' => 'Learn More',
      'link2_url' => '/learn-more',
    ],
    [
      '_type' => 'newsletter',
      'title' => 'Stay Informed with PressX',
      'summary' => 'Get the latest product updates, feature releases, and optimization tips delivered straight to your inbox.',
    ],
    [
      '_type' => 'side_by_side',
      'layout' => 'image_right',
      'title' => 'Discover the Essential Features That Make PressX Stand Out in Web Development',
      'summary' => 'PressX offers a powerful blend of flexibility and performance, enabling seamless integration and unparalleled user experience.',
      'media' => $image_url,
      'features' => [
        [
          '_type' => 'stat',
          'title' => 'Key Features',
          'summary' => 'Explore our innovative tools designed to enhance your web development journey, from AI-assisted coding to seamless React integration.',
        ],
        [
          '_type' => 'stat',
          'title' => 'Accelerate Your Projects',
          'summary' => 'Leverage PressX\'s powerful features to reduce development time and deliver high-performance websites faster than ever.',
        ],
      ],
    ],
  ];

  // Update meta values in Carbon Fields format.
  if (function_exists('carbon_set_post_meta')) {
    carbon_set_post_meta($page_id, 'sections', $sections);
    WP_CLI::log("Added Carbon Fields sections to the home page.");
  }
  else {
    WP_CLI::warning("Carbon Fields not available. Sections not added.");
  }

  // Set this page as the homepage.
  update_option('show_on_front', 'page');
  update_option('page_on_front', $page_id);
  WP_CLI::log("Set home page as front page.");

  // Get the post slug.
  $post = get_post($page_id);
  $slug = $post->post_name;

  WP_CLI::success("Home page created successfully!");
  WP_CLI::log("ID: {$page_id}");
  WP_CLI::log("Slug: {$slug}");
  WP_CLI::log("Set as WordPress homepage: ✅");
  WP_CLI::log("View your page at:");
  WP_CLI::log("http://pressx.ddev.site/");
  WP_CLI::log("http://pressx.ddev.site:3333/ (Next.js)");

  return TRUE;
}
