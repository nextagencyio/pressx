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

    if ($preferred_api === 'groq' && !empty($_pressx_groq_api_key)) {
      $ai_response = pressx_generate_with_groq($system_prompt, '', $_pressx_groq_api_key);
    }
    elseif (!empty($_pressx_openrouter_api_key)) {
      $ai_response = pressx_generate_with_openrouter($system_prompt, '', $_pressx_openrouter_api_key);
    }

    if (!empty($ai_response)) {
      // Parse the JSON response.
      $section_data = json_decode($ai_response, TRUE);

      if (is_array($section_data) && isset($section_data['_type']) && $section_data['_type'] === $section_type) {
        // Process images if needed.
        if (isset($section_data['image_search']) && !empty($section_data['image_search'])) {
          $image_url = '';

          // Try to get an image from Pexels if enabled.
          if (defined('PRESSX_USE_PEXELS_IMAGES') && PRESSX_USE_PEXELS_IMAGES && function_exists('pressx_get_pexels_image')) {
            $image_id = pressx_get_pexels_image($section_data['image_search']);
            if ($image_id) {
              $image_url = wp_get_attachment_url($image_id);
            }
          }

          // Fall back to default image if needed.
          if (empty($image_url)) {
            $image_url = $default_image_url;
          }

          $section_data['media'] = $image_url;
        }

        // Process gallery items if this is a gallery section.
        if ($section_type === 'gallery' && isset($section_data['media_items']) && is_array($section_data['media_items'])) {
          foreach ($section_data['media_items'] as $key => $item) {
            if (isset($item['image_search']) && !empty($item['image_search'])) {
              $image_url = '';

              // Try to get an image from Pexels if enabled.
              if (defined('PRESSX_USE_PEXELS_IMAGES') && PRESSX_USE_PEXELS_IMAGES && function_exists('pressx_get_pexels_image')) {
                $image_id = pressx_get_pexels_image($item['image_search']);
                if ($image_id) {
                  $image_url = wp_get_attachment_url($image_id);
                }
              }

              // Fall back to default image if needed.
              if (empty($image_url)) {
                $image_url = $default_image_url;
              }

              $section_data['media_items'][$key]['media'] = $image_url;
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
