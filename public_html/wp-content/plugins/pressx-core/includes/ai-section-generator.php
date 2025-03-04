<?php

/**
 * @file
 * AI Section Generator for PressX.
 *
 * This file has been refactored to follow a structure and style similar to rest-api.php.
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
 * @return array|WP_Error
 *   The generated section data or WP_Error if generation failed.
 */
function pressx_generate_ai_section($section_type, $topic) {
  // Load AI dependencies.
  pressx_load_ai_dependencies();

  // Log the start of section generation.
  error_log("PressX AI Section: Starting section generation for type: " . $section_type . ".");
  error_log("PressX AI Section: Topic: " . $topic . ".");

  // Sanitize the prompt.
  $sanitized_topic = pressx_sanitize_prompt($topic);

  // Get the system prompt for AI generation.
  $system_prompt = pressx_get_ai_section_system_prompt($section_type, $sanitized_topic);

  try {
    // Generate the section content using AI.
    $ai_response = pressx_generate_ai_content($system_prompt);

    if (empty($ai_response)) {
      return new WP_Error('ai_generation_failed', 'Failed to generate AI content.');
    }

    // Parse and validate the AI response.
    $section_data = pressx_parse_ai_section_response($ai_response, $section_type);

    if (is_wp_error($section_data)) {
      return $section_data;
    }

    // Process images for the section.
    $section_data = pressx_process_section_images($section_data, $section_type);

    return $section_data;
  }
  catch (Exception $e) {
    error_log('AI section generation failed: ' . $e->getMessage() . '.');
    return new WP_Error('ai_generation_exception', $e->getMessage());
  }
}

/**
 * Loads all required dependencies for AI section generation.
 */
function pressx_load_ai_dependencies() {
  // Include the AI utilities.
  require_once plugin_dir_path(__FILE__) . 'ai-utils.php';

  // Include the image handler.
  require_once plugin_dir_path(__FILE__) . 'image-handler.php';

  // Include the Pexels image handler if we're using Pexels images.
  if (defined('PRESSX_USE_PEXELS_IMAGES') && PRESSX_USE_PEXELS_IMAGES === TRUE) {
    require_once plugin_dir_path(__FILE__) . 'pexels-image-handler.php';
    error_log("PressX AI Section: Loaded Pexels image handler.");
  }
  else {
    error_log("PressX AI Section: Pexels integration is not enabled.");
  }
}

/**
 * Generates the system prompt for AI section generation.
 *
 * @param string $section_type
 *   The type of section to generate.
 * @param string $sanitized_topic
 *   The sanitized topic for the section.
 *
 * @return string
 *   The system prompt for AI generation.
 */
function pressx_get_ai_section_system_prompt($section_type, $sanitized_topic) {
  $system_prompt = "You are an AI assistant that generates content for website sections. Your task is to create content for a \"$section_type\" section based on the user's prompt.\n\n";
  $system_prompt .= "You will generate content that is tailored to the user's prompt: \"$sanitized_topic\". The content MUST be specifically about this topic and should be relevant, engaging, and appropriate for a professional website.\n\n";
  $system_prompt .= "IMPORTANT: ALL content must be SPECIFICALLY about the topic \"$sanitized_topic\". This includes:\n";
  $system_prompt .= "- ALL headings, titles, and eyebrow text.\n";
  $system_prompt .= "- ALL descriptions, summaries, and body text.\n";
  $system_prompt .= "- ALL features, bullet points, and list items.\n";
  $system_prompt .= "- ALL call-to-action text.\n\n";
  $system_prompt .= "For hero sections, ensure the heading, summary, and all text directly relates to \"$sanitized_topic\".\n\n";
  $system_prompt .= "For pricing sections, you must create EXACTLY two pricing cards: one for a basic/free tier and one for a premium/paid tier. Each card must have:\n";
  $system_prompt .= "- An eyebrow that relates to \"$sanitized_topic\".\n";
  $system_prompt .= "- A title that relates to \"$sanitized_topic\".\n";
  $system_prompt .= "- A features array with 3-5 items that are SPECIFIC to \"$sanitized_topic\".\n";
  $system_prompt .= "- Call-to-action buttons with text relevant to \"$sanitized_topic\".\n\n";
  $system_prompt .= "The first card should be labeled as \"Basic Plan\" and the second as \"Premium Plan\", but all content within them must be customized for \"$sanitized_topic\".\n\n";
  $system_prompt .= "IMPORTANT FOR PRICING SECTIONS: Each feature in the features array must be an object with a 'text' property, like this: { \"text\": \"Feature description related to $sanitized_topic\" }. Do not use simple strings for features.\n\n";

  // If this is a pricing section, include a sample structure.
  if ( $section_type === 'pricing' ) {
    $pricing_section = pressx_get_pricing_section_structure();

    if ( $pricing_section ) {
      $system_prompt .= "Here's an example of a pricing section structure:\n\n";
      $system_prompt .= json_encode( $pricing_section, JSON_PRETTY_PRINT );
      $system_prompt .= "\n\n";
    }
  }

  $system_prompt .= "IMPORTANT FOR IMAGES: For sections that can include images (hero, side_by_side, gallery items, etc.), include an \"image_search\" field with a specific search phrase that would find a relevant image. For example, for a coffee shop, you might use \"barista pouring latte art\" or \"cozy coffee shop interior\".\n\n";
  $system_prompt .= "Your response MUST be only valid JSON with a single section object containing at minimum a '_type' field matching \"$section_type\". Each section type has specific fields based on its type:\n\n";
  $system_prompt .= "1. hero: Must include heading, summary, image_search, and can optionally include hero_layout, link_title, link_url, link2_title, link2_url.\n";
  $system_prompt .= "2. text: Must include title, body, and can optionally include text_layout.\n";
  $system_prompt .= "3. side_by_side: Must include title, summary, image_search, and can optionally include layout, features, link_title, link_url.\n";
  $system_prompt .= "4. card_group: Must include title, summary, cards array (each with title, body, and icons array with 3 different Lucide icon names).\n";
  $system_prompt .= "5. gallery: Must include title, summary, media_items array (EXACTLY 4 items, each with title, summary, and image_search).\n";
  $system_prompt .= "6. quote: Must include quote, author, image_search.\n";
  $system_prompt .= "7. accordion: Must include title, summary, items array (each with title, body).\n";
  $system_prompt .= "8. pricing: Must include eyebrow, title, summary, includes_label, and cards array (each with eyebrow, title, monthly_label, features array, cta_text, and cta_link).\n\n";
  $system_prompt .= "Your response format should be valid JSON that looks EXACTLY like this (with your content):\n";
  $system_prompt .= "{\n";
  $system_prompt .= "  \"_type\": \"$section_type\",\n";
  $system_prompt .= "  ... other fields based on section type ...\n";
  $system_prompt .= "}";

  return $system_prompt;
}

/**
 * Generates AI content using the configured API.
 *
 * @param string $system_prompt
 *   The system prompt for AI generation.
 *
 * @return string
 *   The AI-generated content.
 */
function pressx_generate_ai_content($system_prompt) {
  // Get the API keys from wp-config.php.
  $_pressx_openrouter_api_key = defined( 'OPENROUTER_API_KEY' ) ? OPENROUTER_API_KEY : '';
  $_pressx_groq_api_key = defined( 'GROQ_API_KEY' ) ? GROQ_API_KEY : '';

  // Check which API to use based on configuration.
  $preferred_api = defined( 'PRESSX_AI_API' ) ? PRESSX_AI_API : 'openrouter';

  error_log( "PressX AI Section: Attempting to generate content with " . $preferred_api . "." );

  $ai_response = '';

  if ( $preferred_api === 'groq' && ! empty( $_pressx_groq_api_key ) ) {
    $ai_response = pressx_generate_with_groq( $system_prompt, '', $_pressx_groq_api_key );
    error_log( "PressX AI Section: Generated content using Groq API." );
  }
  elseif ( ! empty( $_pressx_openrouter_api_key ) ) {
    $ai_response = pressx_generate_with_openrouter( $system_prompt, '', $_pressx_openrouter_api_key );
    error_log( "PressX AI Section: Generated content using OpenRouter API." );
  }

  if ( ! empty( $ai_response ) ) {
    error_log( "PressX AI Section: Raw AI response: " . $ai_response . "." );
  }

  return $ai_response;
}

/**
 * Parses and validates the AI response for a section.
 *
 * @param string $ai_response
 *   The raw AI response.
 * @param string $section_type
 *   The type of section being generated.
 *
 * @return array|WP_Error
 *   The parsed section data or WP_Error if parsing failed.
 */
function pressx_parse_ai_section_response($ai_response, $section_type) {
  // Clean up the response if it contains markdown code blocks
  if (strpos($ai_response, '```json') !== FALSE) {
    $ai_response = preg_replace('/```json\s*/', '', $ai_response);
    $ai_response = preg_replace('/\s*```\s*$/', '', $ai_response);
  }

  // Parse the JSON response.
  $section_data = json_decode($ai_response, TRUE);
  error_log("PressX AI Section: Parsed section data: " . print_r($section_data, TRUE) . ".");

  if (!is_array($section_data) || !isset($section_data['_type']) || $section_data['_type'] !== $section_type) {
    return new WP_Error('invalid_response', 'The AI response is not a valid section of the requested type.');
  }

  error_log("PressX AI Section: Valid section data received for type: " . $section_type . ".");

  // Validate the section data based on its type.
  return pressx_validate_section_data($section_data, $section_type);
}

/**
 * Validates section data based on its type.
 *
 * @param array  $section_data
 *   The section data to validate.
 * @param string $section_type
 *   The type of section.
 *
 * @return array|WP_Error
 *   The validated section data or WP_Error if validation failed.
 */
function pressx_validate_section_data(array $section_data, $section_type) {
  switch ( $section_type ) {
    case 'hero':
      // Validate required fields for hero section.
      if ( ! isset( $section_data['heading'] ) || ! isset( $section_data['summary'] ) ) {
        return new WP_Error( 'invalid_hero', 'The hero section is missing required fields.' );
      }
      break;

    case 'text':
      // Validate required fields for text section.
      if ( ! isset( $section_data['title'] ) || ! isset( $section_data['body'] ) ) {
        return new WP_Error( 'invalid_text', 'The text section is missing required fields.' );
      }
      break;

    case 'side_by_side':
      // Validate required fields for side_by_side section.
      if ( ! isset( $section_data['title'] ) || ! isset( $section_data['summary'] ) ) {
        return new WP_Error( 'invalid_side_by_side', 'The side by side section is missing required fields.' );
      }
      break;

    case 'card_group':
      // Validate required fields for card_group section.
      if ( ! isset( $section_data['title'] ) || ! isset( $section_data['summary'] ) ||
           ! isset( $section_data['cards'] ) || ! is_array( $section_data['cards'] ) ) {
        return new WP_Error( 'invalid_card_group', 'The card group section is missing required fields.' );
      }
      break;

    case 'gallery':
      // Validate required fields for gallery section.
      if ( ! isset( $section_data['title'] ) || ! isset( $section_data['summary'] ) ||
           ! isset( $section_data['media_items'] ) || ! is_array( $section_data['media_items'] ) ) {
        return new WP_Error( 'invalid_gallery', 'The gallery section is missing required fields.' );
      }
      break;

    case 'quote':
      // Validate required fields for quote section.
      if ( ! isset( $section_data['quote'] ) || ! isset( $section_data['author'] ) ) {
        return new WP_Error( 'invalid_quote', 'The quote section is missing required fields.' );
      }
      break;

    case 'accordion':
      // Validate required fields for accordion section.
      if ( ! isset( $section_data['title'] ) || ! isset( $section_data['summary'] ) ||
           ! isset( $section_data['items'] ) || ! is_array( $section_data['items'] ) ) {
        return new WP_Error( 'invalid_accordion', 'The accordion section is missing required fields.' );
      }
      break;

    case 'pricing':
      return pressx_validate_pricing_section( $section_data );

    default:
      return new WP_Error( 'invalid_section_type', 'Invalid section type specified.' );
  }

  return $section_data;
}

/**
 * Validates a pricing section.
 *
 * @param array $section_data
 *   The pricing section data to validate.
 *
 * @return array|WP_Error
 *   The validated pricing section data or WP_Error if validation failed.
 */
function pressx_validate_pricing_section(array $section_data) {
  // Validate required fields for pricing section.
  if ( ! isset( $section_data['eyebrow'] ) || ! isset( $section_data['title'] ) ||
       ! isset( $section_data['summary'] ) || ! isset( $section_data['includes_label'] ) ||
       ! isset( $section_data['cards'] ) || ! is_array( $section_data['cards'] ) ) {
    return new WP_Error( 'invalid_response', 'The AI response is missing required fields for pricing section.' );
  }

  // Ensure we have exactly 2 pricing cards.
  if ( count( $section_data['cards'] ) !== 2 ) {
    return new WP_Error( 'invalid_cards', 'Pricing section must have exactly 2 pricing cards.' );
  }

  // Validate each card.
  foreach ( $section_data['cards'] as $key => $card ) {
    if ( ! isset( $card['eyebrow'] ) || ! isset( $card['title'] ) ||
         ! isset( $card['features'] ) ||
         ! isset( $card['cta_text'] ) || ! isset( $card['cta_link'] ) ||
         ! is_array( $card['features'] ) ) {
      return new WP_Error( 'invalid_card', 'One or more pricing cards are missing required fields.' );
    }

    // Ensure we have 3-5 features.
    if ( count( $card['features'] ) < 3 || count( $card['features'] ) > 5 ) {
      return new WP_Error( 'invalid_features', 'Each pricing card must have 3-5 features.' );
    }

    // Transform features to ensure they are objects with a 'text' property.
    $section_data['cards'][ $key ]['features'] = pressx_transform_pricing_features( $card['features'] );
  }

  return $section_data;
}

/**
 * Transforms pricing features to ensure they have the correct format.
 *
 * @param array $features
 *   The features to transform.
 *
 * @return array
 *   The transformed features.
 */
function pressx_transform_pricing_features(array $features) {
  $transformed_features = [];

  foreach ( $features as $feature ) {
    if ( is_array( $feature ) && isset( $feature['text'] ) ) {
      // If feature is already an object with a text property, use it as is.
      $transformed_features[] = $feature;
    }
    elseif ( is_string( $feature ) ) {
      // If feature is a string, convert it to an object with a text property.
      $transformed_features[] = [ 'text' => $feature, ];
    }
  }

  return $transformed_features;
}

/**
 * Processes images for a section.
 *
 * @param array  $section_data
 *   The section data to process.
 * @param string $section_type
 *   The type of section.
 *
 * @return array
 *   The section data with processed images.
 */
function pressx_process_section_images(array $section_data, $section_type) {
  // Get the default image ID for fallback.
  $default_image_path = plugin_dir_path( dirname( __FILE__ ) ) . 'images/card.png';
  $default_image_id = pressx_ensure_image( $default_image_path );
  $default_image_url = $default_image_id ? wp_get_attachment_url( $default_image_id ) : '';

  error_log( "PressX AI Section: Default image URL: " . $default_image_url . "." );

  // Process images based on section type.
  switch ( $section_type ) {
    case 'hero':
    case 'side_by_side':
    case 'quote':
      $section_data = pressx_process_single_image( $section_data, $default_image_url );
      break;

    case 'gallery':
      $section_data = pressx_process_gallery_images( $section_data, $default_image_url );
      break;

    default:
      // For any other section type, check if it has an image_search field.
      if ( isset( $section_data['image_search'] ) ) {
        $section_data = pressx_process_single_image( $section_data, $default_image_url );
      }
  }

  return $section_data;
}

/**
 * Processes a single image for a section.
 *
 * @param array  $section_data
 *   The section data to process.
 * @param string $default_image_url
 *   The default image URL to use if no image is found.
 *
 * @return array
 *   The section data with the processed image.
 */
function pressx_process_single_image(array $section_data, $default_image_url) {
  $image_search = $section_data['image_search'] ?? '';

  if ( ! empty( $image_search ) ) {
    error_log( "PressX AI Section: Image search query: " . $image_search . "." );

    $image_url = pressx_get_image_from_search( $image_search );

    if ( ! empty( $image_url ) ) {
      $section_data['media'] = $image_url;
      error_log( "PressX AI Section: Set media URL: " . $image_url . "." );
    }
    else {
      $section_data['media'] = $default_image_url;
      error_log( "PressX AI Section: Using default image: " . $default_image_url . "." );
    }
  }
  else {
    // No image search provided, use default image.
    $section_data['media'] = $default_image_url;
    error_log( "PressX AI Section: No image search provided, using default image." );
  }

  return $section_data;
}

/**
 * Processes gallery images for a gallery section.
 *
 * @param array  $section_data
 *   The gallery section data to process.
 * @param string $default_image_url
 *   The default image URL to use if no image is found.
 *
 * @return array
 *   The gallery section data with processed images.
 */
function pressx_process_gallery_images(array $section_data, $default_image_url) {
  // Initialize media_items array if not set.
  if ( ! isset( $section_data['media_items'] ) ) {
    $section_data['media_items'] = [];
  }

  // Ensure we have exactly 4 items.
  while ( count( $section_data['media_items'] ) < 4 ) {
    $section_data['media_items'][] = [
      'title'   => 'Gallery Item ' . ( count( $section_data['media_items'] ) + 1 ),
      'summary' => 'Additional gallery item.',
      'media'   => $default_image_url,
    ];
  }

  // If more than 4 items, trim the excess.
  if ( count( $section_data['media_items'] ) > 4 ) {
    $section_data['media_items'] = array_slice( $section_data['media_items'], 0, 4 );
  }

  // Process each gallery item.
  foreach ( $section_data['media_items'] as $key => $item ) {
    if ( isset( $item['image_search'] ) && ! empty( $item['image_search'] ) ) {
      $image_url = pressx_get_image_from_search( $item['image_search'] );

      if ( ! empty( $image_url ) ) {
        $section_data['media_items'][ $key ]['media'] = $image_url;
      }
      else {
        $section_data['media_items'][ $key ]['media'] = $default_image_url;
      }
    }
    else {
      // No image search provided, use default.
      $section_data['media_items'][ $key ]['media'] = $default_image_url;
    }
  }

  return $section_data;
}

/**
 * Gets an image from a search query.
 *
 * @param string $image_search
 *   The search query for the image.
 *
 * @return string
 *   The URL of the found image, or empty string if none found.
 */
function pressx_get_image_from_search($image_search) {
  $image_url = '';

  // Try to get an image from Pexels if enabled.
  if ( defined( 'PRESSX_USE_PEXELS_IMAGES' ) && PRESSX_USE_PEXELS_IMAGES === TRUE && function_exists( 'pressx_get_pexels_image' ) ) {
    error_log( "PressX AI Section: Attempting to get image from Pexels for: " . $image_search . "." );
    error_log( "PressX AI Section: PRESSX_USE_PEXELS_IMAGES is set to: " . var_export( PRESSX_USE_PEXELS_IMAGES, TRUE ) . "." );

    // Check if PEXELS_API_KEY is defined.
    if ( defined( 'PEXELS_API_KEY' ) ) {
      error_log( "PressX AI Section: PEXELS_API_KEY is defined as: " . substr( PEXELS_API_KEY, 0, 5 ) . "..." );
    }
    else {
      error_log( "PressX AI Section: PEXELS_API_KEY is not defined." );
    }

    // Get the image URL from Pexels.
    $pexels_url = pressx_get_pexels_image( $image_search );

    if ( $pexels_url ) {
      error_log( "PressX AI Section: Got Pexels URL: " . $pexels_url . "." );

      // Import the image to WordPress media library.
      if ( function_exists( 'pressx_import_pexels_image' ) ) {
        $image_id = pressx_import_pexels_image( $pexels_url, $image_search );

        if ( ! is_wp_error( $image_id ) && $image_id ) {
          $image_url = wp_get_attachment_url( $image_id );
          error_log( "PressX AI Section: Imported image with ID: " . $image_id . "." );
          error_log( "PressX AI Section: Image URL: " . $image_url . "." );
        }
        else {
          $error_message = is_wp_error( $image_id ) ? $image_id->get_error_message() : 'Unknown error';
          error_log( "PressX AI Section: Failed to import image. Error: " . $error_message . "." );
        }
      }
      else {
        error_log( "PressX AI Section: pressx_import_pexels_image function not found." );
      }
    }
    else {
      error_log( "PressX AI Section: No image URL returned from Pexels." );
    }
  }
  else {
    error_log( "PressX AI Section: Pexels integration not available." );
    error_log( "PressX AI Section: PRESSX_USE_PEXELS_IMAGES defined: " . ( defined( 'PRESSX_USE_PEXELS_IMAGES' ) ? 'Yes' : 'No' ) . "." );
    if ( defined( 'PRESSX_USE_PEXELS_IMAGES' ) ) {
      error_log( "PressX AI Section: PRESSX_USE_PEXELS_IMAGES value: " . var_export( PRESSX_USE_PEXELS_IMAGES, TRUE ) . "." );
    }
    error_log( "PressX AI Section: pressx_get_pexels_image function exists: " . ( function_exists( 'pressx_get_pexels_image' ) ? 'Yes' : 'No' ) . "." );
  }

  return $image_url;
}
