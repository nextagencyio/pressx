<?php

/**
 * @file
 * AI Section Generator for PressX.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Generates a section using AI based on a topic and section type.
 *
 * @param string $section_type
 *   The type of section to generate.
 * @param string $topic
 *   The topic or description for the section.
 *
 * @return array
 *   The generated section data or empty array if generation failed.
 */
function pressx_generate_ai_section($section_type, $topic) {
  // Include the AI utilities.
  require_once plugin_dir_path(__FILE__) . 'ai-utils.php';

  // Include the image handler.
  require_once plugin_dir_path(__FILE__) . 'image-handler.php';

  // Include the Pexels image handler if we're using Pexels images.
  if (defined('PRESSX_USE_PEXELS_IMAGES') && PRESSX_USE_PEXELS_IMAGES) {
    require_once plugin_dir_path(__FILE__) . 'pexels-image-handler.php';
    error_log("PressX AI Section: Loaded Pexels image handler.");
  }
  else {
    error_log("PressX AI Section: Pexels integration is not enabled.");
  }

  // Get the API keys from wp-config.php.
  $_pressx_openrouter_api_key = defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '';
  $_pressx_groq_api_key = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';

  // Check which API to use based on configuration.
  $preferred_api = defined('PRESSX_AI_API') ? PRESSX_AI_API : 'openrouter';

  // Get the default image ID for fallback.
  $default_image_path = plugin_dir_path(dirname(__FILE__)) . 'images/card.png';
  $default_image_id = pressx_ensure_image($default_image_path);
  $default_image_url = $default_image_id ? wp_get_attachment_url($default_image_id) : '';

  error_log("PressX AI Section: Starting section generation for type: " . $section_type);
  error_log("PressX AI Section: Topic: " . $topic);
  error_log("PressX AI Section: Default image URL: " . $default_image_url);

  // Sanitize the prompt.
  $sanitized_topic = pressx_sanitize_prompt($topic);

  // Create the system prompt for generating a single section.
  $system_prompt = "You are a website section content generator specialized in creating compelling, targeted content based on user prompts.

Your task is to generate content for a single $section_type section that SPECIFICALLY addresses the user's topic: \"$sanitized_topic\".

IMPORTANT: The content MUST be customized to match the user's topic. For example, if they ask for content about coffee, all headings, text, and content should be about coffee, cafes, etc.

IMPORTANT FOR CARD GROUPS: When creating a card_group section, you MUST create EXACTLY 3 CARDS.
- Each card MUST include an array of 3 DIFFERENT Lucide icons that are semantically relevant to the card's content.
- The first icon should be the most appropriate/preferred icon.
- The second and third icons are alternative suggestions.
- Icons MUST be valid Lucide icon names.
- Icons should relate to the card's theme or content.
- IMPORTANT: Each card in the group MUST have a DIFFERENT primary icon. Do not use the same icon for multiple cards in the same group.

IMPORTANT FOR IMAGES: For sections that can include images (hero, side_by_side, gallery items, etc.), include an \"image_search\" field with a specific search phrase that would find a relevant image. For example, for a coffee shop, you might use \"barista pouring latte art\" or \"cozy coffee shop interior\".

Your response MUST be only valid JSON with a single section object containing at minimum a '_type' field matching \"$section_type\". Each section type has specific fields based on its type:

1. hero: Must include heading, summary, image_search, and can optionally include hero_layout, link_title, link_url, link2_title, link2_url.
2. text: Must include title, body, and can optionally include text_layout.
3. side_by_side: Must include title, summary, image_search, and can optionally include layout, features, link_title, link_url.
4. card_group: Must include title, summary, cards array (each with title, body, and icons array with 3 different Lucide icon names).
5. gallery: Must include title, summary, media_items array (EXACTLY 4 items, each with title, summary, and image_search).
6. quote: Must include quote, author, image_search.
7. accordion: Must include title, summary, items array (each with title, body).

Your response format should be valid JSON that looks EXACTLY like this (with your content):
{
  \"_type\": \"$section_type\",
  ... other fields appropriate for this section type ...
}";

  try {
    // Generate the section content using AI.
    $ai_response = '';

    error_log("PressX AI Section: Attempting to generate content with " . $preferred_api);

    if ($preferred_api === 'groq' && !empty($_pressx_groq_api_key)) {
      $ai_response = pressx_generate_with_groq($system_prompt, '', $_pressx_groq_api_key);
      error_log("PressX AI Section: Generated content using Groq API");
    }
    elseif (!empty($_pressx_openrouter_api_key)) {
      $ai_response = pressx_generate_with_openrouter($system_prompt, '', $_pressx_openrouter_api_key);
      error_log("PressX AI Section: Generated content using OpenRouter API");
    }

    if (!empty($ai_response)) {
      error_log("PressX AI Section: Raw AI response: " . $ai_response);

      // Parse the JSON response.
      $section_data = json_decode($ai_response, TRUE);
      error_log("PressX AI Section: Parsed section data: " . print_r($section_data, TRUE));

      if (is_array($section_data) && isset($section_data['_type']) && $section_data['_type'] === $section_type) {
        error_log("PressX AI Section: Valid section data received for type: " . $section_type);

        // Process images if needed.
        $image_search = '';

        // Check for image search field based on section type.
        switch ($section_type) {
          case 'hero':
          case 'side_by_side':
          case 'quote':
            $image_search = $section_data['image_search'] ?? '';
            error_log("PressX AI Section: Image search query for {$section_type}: " . $image_search);
            error_log("PressX AI Section: Full section data for debugging: " . print_r($section_data, TRUE));
            break;

          case 'gallery':
            // Gallery items are processed separately.
            error_log("PressX AI Section: Gallery section detected, will process items separately.");
            error_log("PressX AI Section: Gallery items: " . print_r($section_data['media_items'] ?? [], TRUE));
            break;

          default:
            // For any other section type, check root level.
            $image_search = $section_data['image_search'] ?? '';
            error_log("PressX AI Section: Image search query for {$section_type}: " . $image_search);
        }

        // Process the main section image if we have a search query.
        if (!empty($image_search)) {
          $image_url = '';

          error_log("PressX AI Section: Processing image for section type: " . $section_type);
          error_log("PressX AI Section: Image search query: " . $image_search);

          // Try to get an image from Pexels if enabled.
          if (defined('PRESSX_USE_PEXELS_IMAGES') && PRESSX_USE_PEXELS_IMAGES === TRUE && function_exists('pressx_get_pexels_image')) {
            error_log("PressX AI Section: Pexels integration is enabled and function exists.");
            error_log("PressX AI Section: PRESSX_USE_PEXELS_IMAGES value: " . var_export(PRESSX_USE_PEXELS_IMAGES, TRUE));

            // Get the image URL from Pexels.
            $pexels_url = pressx_get_pexels_image($image_search);

            error_log("PressX AI Section: Pexels URL result: " . ($pexels_url ? $pexels_url : 'None'));

            if ($pexels_url) {
              // Import the image to WordPress media library.
              if (function_exists('pressx_import_pexels_image')) {
                error_log("PressX AI Section: Attempting to import Pexels image to media library");
                error_log("PressX AI Section: Image URL to import: " . $pexels_url);

                $image_id = pressx_import_pexels_image($pexels_url, $image_search);
                error_log("PressX AI Section: Import result - Image ID: " . ($image_id ? $image_id : 'Failed'));

                if (!is_wp_error($image_id) && $image_id) {
                  $image_url = wp_get_attachment_url($image_id);
                  error_log("PressX AI Section: Successfully imported image with ID: " . $image_id);
                  error_log("PressX AI Section: Image URL in media library: " . $image_url);
                }
                else {
                  $error_message = is_wp_error($image_id) ? $image_id->get_error_message() : 'Unknown error';
                  error_log("PressX AI Section: Failed to import image. Error: " . $error_message);
                  error_log("PressX AI Section: Error details: " . print_r(is_wp_error($image_id) ? $image_id : [], TRUE));
                }
              }
              else {
                error_log("PressX AI Section: pressx_import_pexels_image function not found");
                error_log("PressX AI Section: Available functions: " . print_r(get_defined_functions()['user'], TRUE));
              }
            }
            else {
              error_log("PressX AI Section: No image URL returned from Pexels");
              error_log("PressX AI Section: Search query used: " . $image_search);
            }
          }
          else {
            error_log("PressX AI Section: Pexels integration check failed");
            error_log("PressX AI Section: PRESSX_USE_PEXELS_IMAGES defined: " . (defined('PRESSX_USE_PEXELS_IMAGES') ? 'Yes' : 'No'));
            error_log("PressX AI Section: PRESSX_USE_PEXELS_IMAGES value: " . (defined('PRESSX_USE_PEXELS_IMAGES') ? var_export(PRESSX_USE_PEXELS_IMAGES, TRUE) : 'Not defined'));
            error_log("PressX AI Section: pressx_get_pexels_image function exists: " . (function_exists('pressx_get_pexels_image') ? 'Yes' : 'No'));
            error_log("PressX AI Section: Include path: " . get_include_path());
          }

          // Fall back to default image if needed.
          if (empty($image_url)) {
            $image_url = $default_image_url;
            error_log("PressX AI Section: Using default image: " . $default_image_url);
            error_log("PressX AI Section: Reason for fallback: No valid image URL obtained from Pexels");
          }

          // Set the image URL based on section type.
          switch ($section_type) {
            case 'hero':
            case 'side_by_side':
            case 'quote':
              $section_data['media'] = $image_url;
              error_log("PressX AI Section: Set media URL for {$section_type}: " . $image_url);
              error_log("PressX AI Section: Updated section data: " . print_r($section_data, TRUE));
              break;

            default:
              // For any other section type that needs an image.
              if (isset($section_data['image_search'])) {
                $section_data['media'] = $image_url;
                error_log("PressX AI Section: Set media URL for {$section_type}: " . $image_url);
                error_log("PressX AI Section: Updated section data: " . print_r($section_data, TRUE));
              }
          }
        }
        else {
          // No image search provided, use default image for sections that require it.
          switch ($section_type) {
            case 'hero':
            case 'side_by_side':
            case 'quote':
              $section_data['media'] = $default_image_url;
              break;
          }
        }

        // Process gallery items if this is a gallery section.
        if ($section_type === 'gallery' && isset($section_data['media_items']) && is_array($section_data['media_items'])) {
          // Initialize media_items array if not set.
          if (!isset($section_data['media_items'])) {
            $section_data['media_items'] = [];
          }

          // Ensure we have exactly 4 items.
          while (count($section_data['media_items']) < 4) {
            $section_data['media_items'][] = [
              'title' => 'Gallery Item ' . (count($section_data['media_items']) + 1),
              'summary' => 'Additional gallery item.',
              'media' => $default_image_url,
            ];
          }

          // If more than 4 items, trim the excess.
          if (count($section_data['media_items']) > 4) {
            $section_data['media_items'] = array_slice($section_data['media_items'], 0, 4);
          }

          foreach ($section_data['media_items'] as $key => $item) {
            if (isset($item['image_search']) && !empty($item['image_search'])) {
              $image_url = '';

              // Try to get an image from Pexels if enabled.
              if (defined('PRESSX_USE_PEXELS_IMAGES') && PRESSX_USE_PEXELS_IMAGES === TRUE && function_exists('pressx_get_pexels_image')) {
                // Get the image URL from Pexels.
                $pexels_url = pressx_get_pexels_image($item['image_search']);

                if ($pexels_url) {
                  // Import the image to WordPress media library.
                  if (function_exists('pressx_import_pexels_image')) {
                    $image_id = pressx_import_pexels_image($pexels_url, $item['image_search']);

                    if (!is_wp_error($image_id) && $image_id) {
                      $image_url = wp_get_attachment_url($image_id);
                    }
                  }
                }

                // Fall back to default image if needed.
                if (empty($image_url)) {
                  $image_url = $default_image_url;
                }

                $section_data['media_items'][$key]['media'] = $image_url;
              }
            }
            else {
              // No image search provided, use default.
              $section_data['media_items'][$key]['media'] = $default_image_url;
            }
          }
        }

        return $section_data;
      }
    }
  }
  catch (Exception $e) {
    error_log('AI section generation failed: ' . $e->getMessage());
  }

  return [];
}
