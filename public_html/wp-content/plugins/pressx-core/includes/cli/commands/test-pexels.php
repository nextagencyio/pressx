<?php

/**
 * @file
 * WP-CLI command to test Pexels API integration.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Tests the Pexels API integration.
 *
 * @param string $query
 *   The search query to test with.
 * @param int $count
 *   The number of images to fetch for gallery test.
 *
 * @return bool
 *   TRUE if successful, FALSE otherwise.
 */
function pressx_test_pexels($query = '', $count = 4) {
  // Include the Pexels image handler.
  require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'pexels-image-handler.php';

  // If no query provided, use default test queries.
  if (empty($query)) {
    $test_queries = [
      'barista pouring latte art',
      'cozy coffee shop interior',
      'coffee beans roasting',
      'coffee shop owners',
      'espresso machine',
    ];

    WP_CLI::log("Testing Pexels Image Search Functionality");
    WP_CLI::log("======================================");
    WP_CLI::log("");

    // Test each query.
    foreach ($test_queries as $test_query) {
      WP_CLI::log("Testing query: $test_query");
      WP_CLI::log("-------------------");

      // Get image URL.
      $image_url = pressx_get_pexels_image($test_query);

      if ($image_url) {
        WP_CLI::success("Found image URL: $image_url");

        // Test image import.
        WP_CLI::log("Testing image import...");
        $image_id = pressx_import_pexels_image($image_url, $test_query);

        if ($image_id && !is_wp_error($image_id)) {
          WP_CLI::success("Successfully imported image with ID: $image_id");
          WP_CLI::log("Image URL in media library: " . wp_get_attachment_url($image_id));
        }
        else {
          WP_CLI::warning("Failed to import image");
        }
      }
      else {
        WP_CLI::warning("No image found for query: $test_query");
      }

      WP_CLI::log("");
    }

    // Test gallery functionality.
    WP_CLI::log("Testing Gallery Functionality");
    WP_CLI::log("==========================");
    WP_CLI::log("");

    $gallery_query = "modern coffee shop";
    WP_CLI::log("Testing gallery query: $gallery_query");
    WP_CLI::log("-------------------------");

    // Get gallery images.
    $gallery_images = pressx_get_pexels_gallery_images($gallery_query, $count);

    if (!empty($gallery_images)) {
      WP_CLI::success("Found " . count($gallery_images) . " images for gallery");

      // Display each image URL and test import.
      foreach ($gallery_images as $index => $image_url) {
        WP_CLI::log("Image " . ($index + 1) . ": $image_url");

        // Test image import.
        WP_CLI::log("Testing gallery image import...");
        $image_id = pressx_import_pexels_image($image_url, "Gallery image " . ($index + 1));

        if ($image_id && !is_wp_error($image_id)) {
          WP_CLI::success("Successfully imported gallery image with ID: $image_id");
          WP_CLI::log("Image URL in media library: " . wp_get_attachment_url($image_id));
        }
        else {
          WP_CLI::warning("Failed to import gallery image");
        }
      }
    }
    else {
      WP_CLI::warning("No gallery images found for query: $gallery_query");
    }
  }
  else {
    // Test with the provided query.
    WP_CLI::log("Testing query: $query");
    WP_CLI::log("-------------------");

    // Get image URL.
    $image_url = pressx_get_pexels_image($query);

    if ($image_url) {
      WP_CLI::success("Found image URL: $image_url");

      // Test image import.
      WP_CLI::log("Testing image import...");
      $image_id = pressx_import_pexels_image($image_url, $query);

      if ($image_id && !is_wp_error($image_id)) {
        WP_CLI::success("Successfully imported image with ID: $image_id");
        WP_CLI::log("Image URL in media library: " . wp_get_attachment_url($image_id));
      }
      else {
        WP_CLI::warning("Failed to import image");
      }
    }
    else {
      WP_CLI::warning("No image found for query: $query");
    }

    // Test gallery functionality.
    WP_CLI::log("");
    WP_CLI::log("Testing gallery query: $query");
    WP_CLI::log("-------------------------");

    // Get gallery images.
    $gallery_images = pressx_get_pexels_gallery_images($query, $count);

    if (!empty($gallery_images)) {
      WP_CLI::success("Found " . count($gallery_images) . " images for gallery");

      // Display each image URL and test import.
      foreach ($gallery_images as $index => $image_url) {
        WP_CLI::log("Image " . ($index + 1) . ": $image_url");

        // Test image import.
        WP_CLI::log("Testing gallery image import...");
        $image_id = pressx_import_pexels_image($image_url, "Gallery image " . ($index + 1));

        if ($image_id && !is_wp_error($image_id)) {
          WP_CLI::success("Successfully imported gallery image with ID: $image_id");
          WP_CLI::log("Image URL in media library: " . wp_get_attachment_url($image_id));
        }
        else {
          WP_CLI::warning("Failed to import gallery image");
        }
      }
    }
    else {
      WP_CLI::warning("No gallery images found for query: $query");
    }
  }

  WP_CLI::log("");
  WP_CLI::log("Test completed.");

  return TRUE;
}
