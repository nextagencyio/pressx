<?php

/**
 * @file
 * Script to create the get started page.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Creates the get started page.
 *
 * @param bool $force
 *   Whether to force recreation of the get started page even if it already exists.
 *
 * @return bool
 *   TRUE if successful, FALSE otherwise.
 */
function pressx_create_get_started($force = FALSE) {
  // Include the image handler.
  require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'image-handler.php';

  // Get the image ID using the helper function.
  $image_path = plugin_dir_path(dirname(dirname(__FILE__))) . 'images/card.png';
  $image_id = pressx_ensure_image($image_path);

  // Check if the page already exists.
  $existing_page = get_page_by_path('get-started');

  if ($existing_page && !$force) {
    WP_CLI::log("Get started page already exists. Skipping.");
    return TRUE;
  }

  // Prepare the page data.
  $page_args = [
    'post_title' => 'Get Started',
    'post_name' => 'get-started',
    'post_status' => 'publish',
    'post_type' => 'landing',
  ];

  // If the page exists and force is true, update it.
  if ($existing_page && $force) {
    $page_args['ID'] = $existing_page->ID;
    $page_id = wp_update_post($page_args);
    WP_CLI::log("Updated get started page.");
  }
  else {
    // Otherwise, create a new page.
    $page_id = wp_insert_post($page_args);
    WP_CLI::log("Created get started page.");
  }

  // Set the featured image if provided.
  if ($page_id && $image_id) {
    set_post_thumbnail($page_id, $image_id);
  }

  // Add sections based on the PressX design.
  $image_url = $image_id ? wp_get_attachment_url($image_id) : '';

  $sections = [
    [
      '_type' => 'hero',
      'hero_layout' => 'image_bottom',
      'heading' => 'Get Started',
      'summary' => 'Whether you\'re a designer or a developer, PressXprovides the perfect foundation to start customizing to your specific needs.',
      'media' => $image_url,
    ],
    [
      '_type' => 'side_by_side',
      'eyebrow' => 'Bootstrap UI kits',
      'layout' => 'image_right',
      'title' => 'PressXfor Designers',
      'summary' => 'Leverage a variety of Figma community templates built on Bootstrap 5 to jumpstart your design process.',
      'media' => $image_url,
      'link_title' => 'Browse Figma UI kits',
      'link_url' => '#figma-kits',
    ],
    [
      '_type' => 'side_by_side',
      'eyebrow' => 'Project template',
      'layout' => 'image_left',
      'title' => 'PressXfor Developers',
      'summary' => 'Visit our GitHub repository to download the PressXproject template and start building your site today.',
      'media' => $image_url,
      'link_title' => 'Find out more',
      'link_url' => '#github-repo',
    ],
  ];

  // Update meta values in Carbon Fields format.
  if (function_exists('carbon_set_post_meta')) {
    carbon_set_post_meta($page_id, 'sections', $sections);
    WP_CLI::log("Added Carbon Fields sections to the get started page.");
  }
  else {
    WP_CLI::warning("Carbon Fields not available. Sections not added.");
  }

  // Get the post slug.
  $post = get_post($page_id);
  $slug = $post->post_name;

  WP_CLI::success("Get started page created successfully!");
  WP_CLI::log("ID: {$page_id}");
  WP_CLI::log("Slug: {$slug}");
  WP_CLI::log("View your page at:");
  WP_CLI::log("http://pressx.ddev.site/landing/{$slug}");
  WP_CLI::log("http://pressx.ddev.site:3333/{$slug} (Next.js)");

  return TRUE;
}
