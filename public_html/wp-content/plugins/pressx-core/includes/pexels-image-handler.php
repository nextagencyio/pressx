<?php
/**
 * @file
 * Pexels API image search handler for generating dynamic placeholder images.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Fetches an image from Pexels based on a search query.
 *
 * @param string $query
 *   The search query to find an image for.
 *
 * @return string|null
 *   The URL of the found image, or null if none found.
 */
function pressx_get_pexels_image($query) {
  // Get the API key from WordPress options or constants.
  $api_key = defined('PEXELS_API_KEY') ? PEXELS_API_KEY : get_option('pressx_pexels_api_key');

  error_log("PressX Pexels: Searching for image with query: " . $query);
  error_log("PressX Pexels: API key available: " . (!empty($api_key) ? 'Yes' : 'No'));

  if (empty($api_key)) {
    if (class_exists('WP_CLI') && defined('WP_CLI') && WP_CLI) {
      WP_CLI::warning("Pexels API key not found. Please set PEXELS_API_KEY constant or pressx_pexels_api_key option.");
    } else {
      error_log("Pexels API key not found. Please set PEXELS_API_KEY constant or pressx_pexels_api_key option.");
    }
    return NULL;
  }

  // Prepare the API request.
  $url = 'https://api.pexels.com/v1/search?query=' . urlencode($query) . '&per_page=5&orientation=landscape';
  $args = [
    'headers' => [
      'Authorization' => $api_key,
    ],
  ];

  error_log("PressX Pexels: Making API request to: " . $url);

  // Make the API request.
  $response = wp_remote_get($url, $args);

  // Check for errors.
  if (is_wp_error($response)) {
    if (class_exists('WP_CLI') && defined('WP_CLI') && WP_CLI) {
      WP_CLI::warning("Error fetching image from Pexels: " . $response->get_error_message());
    } else {
      error_log("PressX Pexels: Error fetching image from Pexels: " . $response->get_error_message());
    }
    return NULL;
  }

  // Parse the response.
  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, TRUE);

  error_log("PressX Pexels: API response received. Status code: " . wp_remote_retrieve_response_code($response));

  // Check if we got any photos.
  if (empty($data['photos'])) {
    if (class_exists('WP_CLI') && defined('WP_CLI') && WP_CLI) {
      WP_CLI::warning("No images found on Pexels for query: $query");
    } else {
      error_log("PressX Pexels: No images found on Pexels for query: $query");
    }
    return NULL;
  }

  error_log("PressX Pexels: Found " . count($data['photos']) . " images for query: " . $query);

  // Sort photos by size to get the highest quality ones.
  usort($data['photos'], function($a, $b) {
    return ($b['width'] * $b['height']) - ($a['width'] * $a['height']);
  });

  // Get the first (largest) photo.
  $photo = $data['photos'][0];
  $image_url = $photo['src']['original'];

  error_log("PressX Pexels: Selected image URL: " . $image_url);

  return $image_url;
}

/**
 * Fetches multiple images from Pexels based on a search query.
 *
 * @param string $query
 *   The search query to find images for.
 * @param int $count
 *   The number of images to fetch.
 *
 * @return array
 *   An array of image URLs.
 */
function pressx_get_pexels_gallery_images($query, $count = 4) {
  // Get the API key from WordPress options or constants.
  $api_key = defined('PEXELS_API_KEY') ? PEXELS_API_KEY : get_option('pressx_pexels_api_key');

  if (empty($api_key)) {
    WP_CLI::warning("Pexels API key not found. Please set PEXELS_API_KEY constant or pressx_pexels_api_key option.");
    return [];
  }

  // Prepare the API request.
  $url = 'https://api.pexels.com/v1/search?query=' . urlencode($query) . '&per_page=' . intval($count);
  $args = [
    'headers' => [
      'Authorization' => $api_key,
    ],
  ];

  // Make the API request.
  $response = wp_remote_get($url, $args);

  // Check for errors.
  if (is_wp_error($response)) {
    WP_CLI::warning("Error fetching images from Pexels: " . $response->get_error_message());
    return [];
  }

  // Parse the response.
  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, TRUE);

  // Check if we got any photos.
  if (empty($data['photos'])) {
    WP_CLI::warning("No images found for query: $query");
    return [];
  }

  // Extract the URLs of the photos.
  $images = [];
  foreach ($data['photos'] as $photo) {
    $images[] = $photo['src']['large'];
  }

  return $images;
}

/**
 * Import an image from Pexels into the media library.
 *
 * @param string $image_url
 *   The URL of the image to import.
 * @param string $alt_text
 *   The alt text for the image.
 * @param string $caption
 *   The caption for the image.
 *
 * @return int|WP_Error
 *   The attachment ID or WP_Error on failure.
 */
function pressx_import_pexels_image($image_url = '', $alt_text = '', $caption = '') {
  error_log("PressX Pexels: Starting image import process");
  error_log("PressX Pexels: Image URL: " . $image_url);
  error_log("PressX Pexels: Alt text: " . $alt_text);

  // Include required files.
  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/media.php';
  require_once ABSPATH . 'wp-admin/includes/image.php';

  // If no image URL is provided, return NULL.
  if (empty($image_url)) {
    error_log("PressX Pexels: No image URL provided for import");
    return NULL;
  }

  // Get the upload directory info.
  $upload_dir = wp_upload_dir();
  if (!empty($upload_dir['error'])) {
    error_log("PressX Pexels: Upload directory error: " . $upload_dir['error']);
    return NULL;
  }

  // Clean up the filename by removing query parameters.
  $filename = basename(strtok($image_url, '?'));

  // If the URL doesn't have a file extension, try to determine it.
  if (!pathinfo($filename, PATHINFO_EXTENSION)) {
    // Default to jpg for Pexels images.
    $filename .= '.jpg';
  }

  // Generate a unique filename.
  $filename = wp_unique_filename($upload_dir['path'], $filename);
  $filepath = $upload_dir['path'] . '/' . $filename;

  error_log("PressX Pexels: Downloading image to: " . $filepath);

  // Download the image using wp_remote_get.
  $response = wp_remote_get($image_url);
  if (is_wp_error($response)) {
    error_log("PressX Pexels: Error downloading image: " . $response->get_error_message());
    return NULL;
  }

  $image_data = wp_remote_retrieve_body($response);
  if (empty($image_data)) {
    error_log("PressX Pexels: Empty image data received");
    return NULL;
  }

  // Save the image file.
  if (!file_put_contents($filepath, $image_data)) {
    error_log("PressX Pexels: Error saving image to: " . $filepath);
    return NULL;
  }

  error_log("PressX Pexels: Image saved successfully to: " . $filepath);

  // Set up the image title.
  $title = !empty($alt_text) ? $alt_text : pathinfo($filename, PATHINFO_FILENAME);
  $caption = !empty($caption) ? $caption : $title;

  // Insert the image into the media library.
  $attachment = [
    'post_mime_type' => wp_check_filetype($filepath)['type'],
    'post_title' => $title,
    'post_content' => '',
    'post_excerpt' => $caption,
    'post_status' => 'inherit',
  ];

  error_log("PressX Pexels: Importing image to media library");

  // Insert the attachment.
  $attachment_id = wp_insert_attachment($attachment, $filepath);
  if (is_wp_error($attachment_id)) {
    error_log("PressX Pexels: Error creating attachment: " . $attachment_id->get_error_message());
    @unlink($filepath);
    return NULL;
  }

  error_log("PressX Pexels: Attachment created with ID: " . $attachment_id);

  // Generate metadata for the attachment.
  $attachment_data = wp_generate_attachment_metadata($attachment_id, $filepath);
  if (is_wp_error($attachment_data)) {
    error_log("PressX Pexels: Error generating attachment metadata: " . $attachment_data->get_error_message());
    wp_delete_attachment($attachment_id, TRUE);
    @unlink($filepath);
    return NULL;
  }

  // Update the metadata.
  wp_update_attachment_metadata($attachment_id, $attachment_data);

  // Add alt text.
  if (!empty($alt_text)) {
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
  }

  error_log("PressX Pexels: Successfully imported image with ID: " . $attachment_id);
  error_log("PressX Pexels: Image URL in media library: " . wp_get_attachment_url($attachment_id));

  return $attachment_id;
}

