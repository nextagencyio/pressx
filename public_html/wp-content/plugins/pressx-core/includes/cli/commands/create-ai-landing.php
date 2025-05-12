<?php

/**
 * @file
 * Script to create an AI-generated landing page using OpenRouter or Groq with Pexels image search.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Creates an AI-generated landing page.
 *
 * @param string $prompt
 *   The prompt for the AI to generate content.
 * @param bool $is_cli
 *   Whether this is being called from CLI.
 * @param bool $auto_enhance
 *   Whether to automatically enhance the prompt if needed.
 *
 * @return array|bool
 *   Array with post_id and permalink if successful, FALSE otherwise.
 */
function pressx_create_ai_landing($prompt = '', $is_cli = TRUE, $auto_enhance = TRUE) {
  if (empty($prompt)) {
    if (!$is_cli) {
      return FALSE;
    }
    else {
      if (class_exists('WP_CLI') && defined('WP_CLI') && WP_CLI) {
        WP_CLI::error('Please provide a prompt.');
      }
      return FALSE;
    }
  }

  // If auto enhance is enabled, and the prompt is very short or generic,
  // try to make it more specific for better results
  if ($auto_enhance && strlen($prompt) < 20) {
    // The prompt is quite short - attempt to enhance it
    $words = str_word_count($prompt);
    if ($words < 5) {
      // Try to expand very short prompts with common keywords
      $expanded_prompt = $prompt . " landing page with features and benefits";
      pressx_landing_log("Auto-enhancing prompt: '$prompt' → '$expanded_prompt'");
      $prompt = $expanded_prompt;
    }
  }

  // Include the image handler.
  require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'image-handler.php';

  // Include the Pexels image handler if we're using Pexels images.
  if (defined('PRESSX_USE_PEXELS_IMAGES') && PRESSX_USE_PEXELS_IMAGES) {
    require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'pexels-image-handler.php';
  }

  // Include the AI utilities.
  require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'ai-utils.php';

  // Get the API keys from wp-config.php.
  $_pressx_openrouter_api_key = defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '';
  $_pressx_groq_api_key = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';

  // Check if Pexels image search is enabled.
  $_pressx_use_pexels_images = defined('PRESSX_USE_PEXELS_IMAGES') ? PRESSX_USE_PEXELS_IMAGES : FALSE;

  if (!$_pressx_openrouter_api_key && !$_pressx_groq_api_key) {
    $error_message = "Neither OPENROUTER_API_KEY nor GROQ_API_KEY is defined in wp-config.php.\n" .
      "Please add at least one of the following to your wp-config.php file:\n" .
      "define('OPENROUTER_API_KEY', 'your-api-key-here');\n" .
      "define('GROQ_API_KEY', 'your-api-key-here');";

    if ($is_cli && class_exists('WP_CLI') && defined('WP_CLI') && WP_CLI) {
      WP_CLI::error($error_message);
    }
    return FALSE;
  }

  // Check which API to use based on configuration.
  $preferred_api = defined('PRESSX_AI_API') ? PRESSX_AI_API : 'openrouter';

  // Get the default image ID for fallback.
  $default_image_path = plugin_dir_path(dirname(dirname(__FILE__))) . 'images/card.png';
  $default_image_id = pressx_ensure_image($default_image_path);
  $default_image_url = $default_image_id ? wp_get_attachment_url($default_image_id) : '';

  // Sanitize the prompt.
  $prompt = sanitize_prompt($prompt);

  // Suggest improvements if the prompt is too generic and we're in CLI mode.
  if ($is_cli && class_exists('WP_CLI') && defined('WP_CLI') && WP_CLI) {
    suggest_prompt_improvements($prompt);
    WP_CLI::log("Generating AI landing page for: " . $prompt);
  }

  // Generate the landing page content using AI.
  try {
    // Create the system prompt.
    $system_prompt = "You are a landing page content generator specialized in creating compelling, targeted content based on user prompts.

Your task is to generate content for a landing page that SPECIFICALLY addresses the user's prompt. The content should be tailored to the topic, business, or purpose described in the prompt.

Generate exactly 6 different section types from this list: hero, side_by_side, card_group, gallery, accordion, quote, text, logo_collection.

IMPORTANT: If you include a gallery section, it MUST have EXACTLY 4 media_items. No more, no less.

IMPORTANT: The content MUST be customized to match the user's prompt. For example, if they ask for a coffee shop landing page, all headings, text, and content should be about coffee, cafes, etc.

IMPORTANT FOR CARD GROUPS: When creating a card_group section, you MUST create EXACTLY 3 CARDS.
- Each card MUST include an array of 3 DIFFERENT Lucide icons that are semantically relevant to the card's content.
- The first icon should be the most appropriate/preferred icon.
- The second and third icons are alternative suggestions.
- Icons MUST be valid Lucide icon names.
- Icons should relate to the card's theme or content.
- IMPORTANT: Each card in the group MUST have a DIFFERENT primary icon. Do not use the same icon for multiple cards in the same group.

IMPORTANT FOR IMAGES: For each section that can include images (hero, side_by_side, gallery items, etc.), include an \"image_search\" field with a specific search phrase that would find a relevant image. For example, for a coffee shop, you might use \"barista pouring latte art\" or \"cozy coffee shop interior\".

Your response MUST be only valid JSON with an array of 6 sections, each containing at minimum a '_type' field from the section types list. Each section type has specific fields based on its type:

1. hero: Must include heading, summary, image_search, and can optionally include hero_layout, link_title, link_url, link2_title, link2_url.
2. text: Must include title, body, and can optionally include text_layout.
3. side_by_side: Must include title, summary, image_search, and can optionally include layout, features, link_title, link_url.
4. card_group: Must include title, summary, cards array (each with title, body, and icons array with 3 different Lucide icon names).
5. gallery: Must include title, summary, media_items array (each with title, summary, and image_search).
6. quote: Must include quote, author, image_search.
7. logo_collection: Must include title, summary.
8. accordion: Must include title, summary, items array (each with title, body).

Your response format should be valid JSON that looks EXACTLY like this (with your content):
{
  \"sections\": [
    {
      \"_type\": \"card_group\",
      \"title\": \"Features\",
      \"summary\": \"What we offer\",
      \"cards\": [
        {
          \"title\": \"Card Title\",
          \"body\": \"Card description\",
          \"icons\": [\"preferred-icon\", \"alternative-icon-1\", \"alternative-icon-2\"]
        }
      ]
    }
    ...more sections...
  ]
}";

    // Make the AI request.
    pressx_landing_log("Making AI request...");
    $content = pressx_ai_request($prompt, $system_prompt, $is_cli, TRUE);

    // Debug the raw AI response.
    pressx_landing_log("Raw AI response: " . $content);

    // Extract the JSON part from the response by finding the first { and last }.
    $json_start = strpos($content, '{');
    $json_end = strrpos($content, '}');

    if ($json_start !== FALSE && $json_end !== FALSE) {
      $json_content = substr($content, $json_start, $json_end - $json_start + 1);
      // Parse the JSON response.
      $landing_data = json_decode($json_content, TRUE);
    }
    else {
      // Fallback to direct parsing if we can't find valid JSON markers.
      $landing_data = json_decode($content, TRUE);
    }

    // Debug the parsed data.
    pressx_landing_log("Parsed landing data: " . print_r($landing_data, TRUE));

    if (!$landing_data || !isset($landing_data['sections'])) {
      pressx_landing_error("Failed to parse AI response. Invalid JSON format.");
      return FALSE;
    }

    // Generate title if not provided by the AI
    $landing_title = isset($landing_data['title']) ? $landing_data['title'] : "AI Landing Page: " . ucfirst($prompt);

    // Process the sections to ensure they have the correct structure.
    $processed_sections = [];

    foreach ($landing_data['sections'] as $section) {
      // Ensure each section has a _type.
      if (!isset($section['_type'])) {
        continue;
      }

      // Process card groups to ensure they have exactly 3 cards with valid icons.
      if ($section['_type'] === 'card_group' && isset($section['cards'])) {
        // Limit to first 3 cards.
        $section['cards'] = array_slice($section['cards'], 0, 3);

        // If fewer than 3 cards, add placeholder cards.
        while (count($section['cards']) < 3) {
          $section['cards'][] = [
            'title' => 'Additional Card ' . (count($section['cards']) + 1),
            'body' => 'Placeholder description for additional card.',
            'icons' => ['star'],
            'icon' => 'star'
          ];
          pressx_landing_log("Added placeholder card to reach 3 items.");
        }

        // Track used icons to prevent duplicates.
        $used_icons = [];

        // Process icons for each card.
        foreach ($section['cards'] as &$card) {
          // If icons are not set, default to star.
          $icon_options = $card['icons'] ?? ['star'];

          // Validate each icon, keeping the first valid icon as primary.
          $validated_icons = [];
          foreach ($icon_options as $icon) {
            // Validate the icon.
            $validated_icon_options = validate_lucide_icon($icon);

            // If the first option is not the original icon, try the next options.
            if ($validated_icon_options[0] !== $icon) {
              // Try the other options in the order they were returned.
              for ($i = 0; $i < count($validated_icon_options); $i++) {
                // If this icon is valid and not already used, use it.
                if ($validated_icon_options[$i] !== 'star' && !in_array($validated_icon_options[$i], $used_icons)) {
                  $validated_icons[] = $validated_icon_options[$i];
                  break;
                }
              }
            } else {
              // Original icon was valid, check if it's already used.
              if (!in_array($icon, $used_icons)) {
                $validated_icons[] = $icon;
              }
            }
          }

          // If no valid icons were found, default to star.
          if (empty($validated_icons)) {
            // Try to find an unused icon from a predefined list.
            $fallback_icons = ['star', 'zap', 'award', 'check', 'heart', 'thumbs-up', 'coffee', 'gift'];
            foreach ($fallback_icons as $fallback) {
              if (!in_array($fallback, $used_icons)) {
                $validated_icons = [$fallback];
                break;
              }
            }

            // If all fallbacks are used, use a numbered variant.
            if (empty($validated_icons)) {
              $validated_icons = ['star-' . count($used_icons)];
            }
          }

          // Use the first validated icon as primary.
          $card['icon'] = $validated_icons[0];

          // Add this icon to the used icons list.
          $used_icons[] = $card['icon'];

          // If more than one icon was found, store alternatives.
          if (count($validated_icons) > 1) {
            $card['icon_alternatives'] = array_slice($validated_icons, 1);
          }
        }
      }

      // Ensure gallery sections always have exactly 4 items
      if ($section['_type'] === 'gallery') {
        if (!isset($section['media_items']) || !is_array($section['media_items'])) {
          $section['media_items'] = [];
        }

        // If fewer than 4 items, add more
        while (count($section['media_items']) < 4) {
          $section['media_items'][] = [
            'title' => 'Gallery Item ' . (count($section['media_items']) + 1),
            'summary' => 'Additional gallery item',
            'media' => $default_image_url
          ];
          pressx_landing_log("Added missing gallery item to reach 4 items.");
        }

        // If more than 4 items, trim the excess
        if (count($section['media_items']) > 4) {
          pressx_landing_log("Trimming gallery from " . count($section['media_items']) . " to 4 items.");
          $section['media_items'] = array_slice($section['media_items'], 0, 4);
        }

        // Add media URLs to all gallery items if not already set
        foreach ($section['media_items'] as &$item) {
          if (!isset($item['media'])) {
            $item['media'] = $default_image_url;
          }
        }
      }

      // Add logo IDs to logo collection.
      if ($section['_type'] === 'logo_collection') {
        $section['logos'] = [
          $default_image_id,
          $default_image_id,
          $default_image_id,
          $default_image_id,
          $default_image_id,
          $default_image_id,
        ];
      }

      // Add media URLs to carousel items if they exist.
      if ($section['_type'] === 'carousel' && isset($section['items'])) {
        foreach ($section['items'] as &$item) {
          $item['media'] = $default_image_url;
        }
      }

      // Add media URLs to card items if they're custom type.
      if ($section['_type'] === 'card_group' && isset($section['cards'])) {
        foreach ($section['cards'] as &$card) {
          if (isset($card['type']) && $card['type'] === 'custom') {
            $card['media'] = $default_image_url;
          }
        }
      }

      // Add the processed section.
      $processed_sections[] = $section;
    }

    // Process images for sections that need them if Pexels is enabled.
    if ($_pressx_use_pexels_images) {
      foreach ($processed_sections as &$section) {
        // Handle image search for hero, side_by_side, and quote sections.
        if (in_array($section['_type'], ['hero', 'side_by_side', 'quote']) && isset($section['image_search'])) {
          pressx_landing_log("Searching for image: " . $section['image_search']);
          $image_url = pressx_get_pexels_image($section['image_search']);

          if ($image_url) {
            pressx_landing_log("Found image: " . $image_url);
            // Import the image to WordPress media library.
            $image_id = pressx_import_pexels_image($image_url, $section['image_search']);

            if (!is_wp_error($image_id)) {
              $section['media'] = wp_get_attachment_url($image_id);
              pressx_landing_log("Imported image as attachment ID: " . $image_id);
            }
            else {
              // Fallback to default image.
              $section['media'] = $default_image_url;
              pressx_landing_log("Failed to import image, using default placeholder.");
            }
          }
          else {
            // Fallback to default image.
            $section['media'] = $default_image_url;
            pressx_landing_log("No image found, using default placeholder.");
          }
        } else if (in_array($section['_type'], ['hero', 'side_by_side', 'quote'])) {
          // No image search provided, use default.
          $section['media'] = $default_image_url;
        }

        // Handle gallery items.
        if ($section['_type'] === 'gallery' && isset($section['media_items']) && is_array($section['media_items'])) {
          foreach ($section['media_items'] as &$item) {
            if (isset($item['image_search'])) {
              pressx_landing_log("Searching for gallery image: " . $item['image_search']);
              $image_url = pressx_get_pexels_image($item['image_search']);

              if ($image_url) {
                pressx_landing_log("Found gallery image: " . $image_url);
                $image_id = pressx_import_pexels_image($image_url, $item['image_search']);

                if (!is_wp_error($image_id)) {
                  $item['media'] = wp_get_attachment_url($image_id);
                  pressx_landing_log("Imported gallery image as attachment ID: " . $image_id);
                }
                else {
                  // Fallback to default image.
                  $item['media'] = $default_image_url;
                  pressx_landing_log("Failed to import gallery image, using default placeholder.");
                }
              }
              else {
                // Fallback to default image.
                $item['media'] = $default_image_url;
                pressx_landing_log("No gallery image found, using default placeholder.");
              }
            } else {
              // No image search provided, use default.
              $item['media'] = $default_image_url;
            }
          }
        }
      }
    } else {
      // Use default image if Pexels search is disabled.
      foreach ($processed_sections as &$section) {
        if (in_array($section['_type'], ['hero', 'side_by_side', 'quote'])) {
          $section['media'] = $default_image_url;
        }

        // Also set default images for gallery items if Pexels is disabled
        if ($section['_type'] === 'gallery' && isset($section['media_items']) && is_array($section['media_items'])) {
          foreach ($section['media_items'] as &$item) {
            $item['media'] = $default_image_url;
          }
        }
      }
    }

    // Create a slug from the title and append a timestamp to ensure uniqueness.
    $base_slug = sanitize_title($landing_title);
    $timestamp = date('YmdHis');
    $slug = $base_slug . '-' . $timestamp;

    // Prepare the landing page data.
    $landing_args = [
      'post_title' => $landing_title,
      'post_name' => $slug,
      'post_status' => 'publish',
      'post_type' => 'landing',
    ];

    // Create a new landing page.
    $landing_id = wp_insert_post($landing_args);
    pressx_landing_log("Created landing page: {$landing_title}");

    // Set the featured image.
    if ($landing_id && $default_image_id) {
      set_post_thumbnail($landing_id, $default_image_id);
    }

    // Save the processed sections to Carbon Fields meta.
    if ($landing_id && !empty($processed_sections)) {
      // Make sure Carbon Fields is loaded.
      if (!function_exists('carbon_set_post_meta')) {
        // Try to boot Carbon Fields if it's available but not initialized.
        if (function_exists('carbon_fields_boot_app')) {
          carbon_fields_boot_app();
          // Give it a moment to initialize
          sleep(1);
        } else {
          // Try to include Carbon Fields directly
          $carbon_fields_path = ABSPATH . 'wp-content/plugins/carbon-fields/carbon-fields.php';
          if (file_exists($carbon_fields_path)) {
            include_once($carbon_fields_path);
            if (function_exists('carbon_fields_boot_app')) {
              carbon_fields_boot_app();
              // Give it a moment to initialize
              sleep(1);
            }
          }
        }
      }

      // Check if Carbon Fields is available.
      if (function_exists('carbon_set_post_meta')) {
        // Save the processed sections to Carbon Fields meta.
        carbon_set_post_meta($landing_id, 'sections', $processed_sections);

        // Verify the sections were saved.
        $saved_sections = carbon_get_post_meta($landing_id, 'sections');
        pressx_landing_log("Saved " . (is_array($saved_sections) ? count($saved_sections) : "0") . " sections to landing page.");
      }
      else {
        pressx_landing_warning("Carbon Fields not available. Sections not added.");
      }
    }

    // Log success with URL.
    $permalink = get_permalink($landing_id);
    pressx_landing_success("AI landing page created with ID: $landing_id, slug: $slug");
    pressx_landing_log("View page: $permalink");
    pressx_landing_log("Edit page: " . admin_url("post.php?post=$landing_id&action=edit"));

    return [
      'post_id' => $landing_id,
      'permalink' => $permalink,
    ];
  }
  catch (Exception $e) {
    pressx_landing_error("Error generating AI landing page: " . $e->getMessage());
    return FALSE;
  }
}

/**
 * Sanitizes a prompt.
 *
 * @param string $prompt
 *   The prompt to sanitize.
 *
 * @return string
 *   The sanitized prompt.
 */
function sanitize_prompt($prompt) {
  return pressx_sanitize_prompt($prompt);
}

/**
 * Suggests improvements for generic prompts.
 *
 * @param string $prompt
 *   The prompt to check.
 */
function suggest_prompt_improvements($prompt) {
  // Check for generic prompts and suggest improvements.
  $generic_prompts = [
    'landing page' => "Be more specific about the purpose, e.g., 'landing page for a fitness app'.",
    'website' => "Provide more details, such as 'photography portfolio for wedding photographers'.",
    'shop' => "Be more descriptive, e.g., 'boutique clothing store for sustainable fashion'.",
    'business' => "Add more context, like 'tech startup focusing on AI innovation'.",
  ];

  foreach ($generic_prompts as $keyword => $suggestion) {
    if (strpos($prompt, $keyword) !== FALSE) {
      // Only use WP_CLI if it exists (command line context).
      if (class_exists('WP_CLI')) {
        WP_CLI::log("Tip: " . $suggestion);
      }
      break;
    }
  }
}

/**
 * Validate a Lucide icon name against the icon names file.
 *
 * @param string $icon
 *   The icon name to validate.
 *
 * @return array
 *   An array of validated icon names.
 */
function validate_lucide_icon($icon) {
  // Path to the Lucide icon names file.
  $icon_file = plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'lucide-icon-names.txt';

  // If file doesn't exist, default to star.
  if (!file_exists($icon_file)) {
    pressx_landing_warning("Warning: Lucide icon names file not found at: " . $icon_file . " Using default 'star' icon.");
    return ['star'];
  }

  // Read icon names from file.
  $icon_names = array_filter(array_map('trim', file($icon_file, FILE_IGNORE_NEW_LINES)));

  // If no icons found, default to star.
  if (empty($icon_names)) {
    pressx_landing_warning("Warning: No icon names found in the file. Using default 'star' icon.");
    return ['star'];
  }

  // Normalize the input icon name
  $normalized_icon = strtolower(str_replace([' ', '_'], '-', $icon));

  // Exact matches first (case-insensitive, with normalization)
  $exact_matches = array_filter($icon_names, function($name) use ($normalized_icon) {
    return strtolower(str_replace([' ', '_'], '-', $name)) === $normalized_icon;
  });

  // If exact match found, return up to 5 matches.
  if (!empty($exact_matches)) {
    return array_slice($exact_matches, 0, 5);
  }

  // Partial matches (contains the icon name)
  $partial_matches = array_filter($icon_names, function($name) use ($normalized_icon) {
    $normalized_name = strtolower(str_replace([' ', '_'], '-', $name));
    return
      strpos($normalized_name, $normalized_icon) !== false ||
      strpos($normalized_icon, $normalized_name) !== false;
  });

  // If partial matches found, return up to 5 matches.
  if (!empty($partial_matches)) {
    return array_slice($partial_matches, 0, 5);
  }

  // If no match found, default to star.
  return ['star'];
}

/**
 * Wrapper for logging that works in both CLI and API contexts.
 *
 * @param string $message
 *   The message to log.
 */
function pressx_landing_log($message) {
  if (class_exists('WP_CLI') && defined('WP_CLI') && WP_CLI) {
    WP_CLI::log($message);
  }
  // In API context, we could log to a file or just return silently
  // error_log('PressX Landing: ' . $message);
}

/**
 * Wrapper for error logging that works in both CLI and API contexts.
 *
 * @param string $message
 *   The error message.
 * @param bool $exit
 *   Whether to exit after logging the error.
 *
 * @return bool
 *   Always returns FALSE.
 */
function pressx_landing_error($message, $exit = FALSE) {
  if (class_exists('WP_CLI') && defined('WP_CLI') && WP_CLI) {
    if ($exit) {
      WP_CLI::error($message);
    }
    else {
      WP_CLI::warning($message);
    }
  }
  // In API context, we could log to a file
  // error_log('PressX Landing Error: ' . $message);
  return FALSE;
}

/**
 * Wrapper for success logging that works in both CLI and API contexts.
 *
 * @param string $message
 *   The success message.
 */
function pressx_landing_success($message) {
  if (class_exists('WP_CLI')) {
    WP_CLI::success($message);
  }
  // In API context, we could log to a file
  // error_log('PressX Landing Success: ' . $message);
}

/**
 * Wrapper for warning logging that works in both CLI and API contexts.
 *
 * @param string $message
 *   The warning message.
 */
function pressx_landing_warning($message) {
  if (class_exists('WP_CLI')) {
    WP_CLI::warning($message);
  }
  // In API context, we could log to a file
  // error_log('PressX Landing Warning: ' . $message);
}
