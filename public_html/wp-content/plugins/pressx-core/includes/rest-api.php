<?php

/**
 * @file
 * REST API implementation for PressX.
 */

if (!defined('ABSPATH')) {
  exit;
}

use WPGraphQL\JWT_Authentication\Auth;

/**
 * Register REST API routes for PressX.
 */
function pressx_register_rest_routes() {
  register_rest_route('pressx/v1', '/chat', [
    'methods' => 'POST',
    'callback' => 'pressx_chat_callback',
    // Require JWT authentication.
    'permission_callback' => function () {
      // Check for JWT authentication.
      $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
      if (strpos($auth_header, 'Bearer ') === 0) {
        $token = substr($auth_header, 7);
        // Verify JWT token using wp-graphql-jwt-authentication plugin.
        if (class_exists('WPGraphQL\JWT_Authentication\Auth') && method_exists('WPGraphQL\JWT_Authentication\Auth', 'validate_token')) {
          return Auth::validate_token($token);
        }
      }

      return FALSE;
    },
    'args' => [
      'message' => [
        'required' => TRUE,
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
      ],
      'confirmed' => [
        'required' => FALSE,
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
      ],
      'command_type' => [
        'required' => FALSE,
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
      ],
      'command_prompt' => [
        'required' => FALSE,
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
      ],
      'needs_more_info' => [
        'required' => FALSE,
        'type' => 'boolean',
      ],
      'section_type' => [
        'required' => FALSE,
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
      ],
      'page_id' => [
        'required' => FALSE,
        'type' => 'integer',
        'sanitize_callback' => 'absint',
      ],
    ],
  ]);
}

add_action('rest_api_init', 'pressx_register_rest_routes');

/**
 * Callback for the chat endpoint.
 *
 * @param WP_REST_Request $request
 *   The request object.
 *
 * @return WP_REST_Response
 *   The response object.
 */
function pressx_chat_callback(WP_REST_Request $request) {
  // Get the message from the request.
  $message = $request->get_param('message');
  $confirmed = $request->get_param('confirmed');
  $command_type = $request->get_param('command_type');
  $command_prompt = $request->get_param('command_prompt');
  $needs_more_info = $request->get_param('needs_more_info');
  $section_type = $request->get_param('section_type');
  $page_id = $request->get_param('page_id');

  // Check if API keys are configured.
  if (!defined('OPENROUTER_API_KEY') && !defined('GROQ_API_KEY')) {
    return new WP_Error(
      'ai_api_not_configured',
      'AI API keys are not configured. Please set up OPENROUTER_API_KEY or GROQ_API_KEY in wp-config.php.',
      ['status' => 500]
    );
  }

  // If this is a response to a request for more information about a landing page.
  if ($needs_more_info && $command_type === 'landing_page' && !empty($message)) {
    // Use the message as the prompt and ask for confirmation.
    return new WP_REST_Response([
      'response' => "Would you like me to create a landing page about \"$message\"? Reply with 'yes' to proceed or 'no' to cancel.",
      'command_detected' => TRUE,
      'command_type' => 'landing_page',
      'command_prompt' => $message,
      'needs_confirmation' => TRUE,
    ], 200);
  }

  // If this is a response to a request for more information about adding a section.
  if ($needs_more_info && $command_type === 'add_section' && !empty($message)) {
    // Use the message as the prompt and ask for confirmation.
    $section_type = $request->get_param('section_type');
    return new WP_REST_Response([
      'response' => "Would you like me to add a {$section_type} section with the description \"$message\" to this landing page? Reply with 'yes' to proceed or 'no' to cancel.",
      'command_detected' => TRUE,
      'command_type' => 'add_section',
      'command_prompt' => $message,
      'section_type' => $section_type,
      'needs_confirmation' => TRUE,
    ], 200);
  }

  // If this is a confirmed command execution, process it directly.
  if ($confirmed === 'yes' && $command_type === 'landing_page' && !empty($command_prompt)) {
    try {
      // Include the CLI command file.
      $file_path = plugin_dir_path(dirname(__FILE__)) . 'includes/cli/commands/create-ai-landing.php';
      if (!file_exists($file_path)) {
        return new WP_Error(
          'file_not_found',
          'Required file not found: ' . $file_path,
          ['status' => 500]
        );
      }

      require_once $file_path;

      if (!function_exists('pressx_create_ai_landing')) {
        return new WP_Error(
          'function_not_found',
          'Required function not found: pressx_create_ai_landing',
          ['status' => 500]
        );
      }

      // Call the function with is_cli set to FALSE.
      $result = pressx_create_ai_landing($command_prompt, FALSE);

      if ($result && isset($result['post_id'])) {
        $post = get_post($result['post_id']);
        $permalink = get_permalink($post->ID);

        return new WP_REST_Response([
          'response' => "I've created an AI landing page about \"$command_prompt\".",
          'links' => [
            [
              'text' => "View \"$post->post_title\"",
              'url' => $permalink,
            ],
          ],
          'command_executed' => 'create_ai_landing',
        ], 200);
      }
      else {
        return new WP_REST_Response([
          'response' => "I tried to create a landing pageut \"$command_prompt\", but encountered an error. Please try again with a more specific topic.",
          'command_executed' => 'create_ai_landing',
          'command_failed' => TRUE,
        ], 200);
      }
    }
    catch (Exception $e) {
      return new WP_REST_Response([
        'response' => "I couldn't create a landing page to an error: " . $e->getMessage(),
        'command_executed' => 'create_ai_landing',
        'command_failed' => TRUE,
      ], 200);
    }
  }

  // If this is a confirmed command to add a section, process it.
  if ($confirmed === 'yes' && $command_type === 'add_section' && !empty($command_prompt)) {
    try {
      // Get the section type from the request.
      $section_type = $request->get_param('section_type');

      // Get the current page ID from the request.
      $page_id = $request->get_param('page_id');

      if (empty($page_id)) {
        return new WP_REST_Response([
          'response' => "I couldn't add the section because no landing page was specified. Please try again while viewing a landing page.",
          'command_executed' => 'add_section',
          'command_failed' => TRUE,
        ], 200);
      }

      // Ensure page_id is an integer.
      $page_id = absint($page_id);

      // Verify the post exists and is a landing page.
      $post = get_post($page_id);
      if (!$post || $post->post_type !== 'landing') {
        return new WP_REST_Response([
          'response' => "I couldn't find a landing page with ID $page_id. Please try again while viewing a landing page.",
          'command_executed' => 'add_section',
          'command_failed' => TRUE,
        ], 200);
      }

      // Get existing sections.
      $sections = carbon_get_post_meta($page_id, 'sections') ?: [];

      // Create a new section based on the type and description.
      $new_section = [];

      // Try to generate the section using AI if it's a supported section type.
      if (in_array($section_type, [
        'hero',
        'side_by_side',
        'card_group',
        'gallery',
        'accordion',
        'quote',
        'text',
      ])) {
        // Include the AI section generator.
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ai-section-generator.php';

        // Generate the section using AI.
        $ai_section = pressx_generate_ai_section($section_type, $command_prompt);

        // If AI generation was successful, use the AI-generated section.
        if (!empty($ai_section) && isset($ai_section['_type']) && $ai_section['_type'] === $section_type) {
          $new_section = $ai_section;
        }
      }

      // If AI generation failed or wasn't attempted, create a basic section.
      if (empty($new_section) || !isset($new_section['_type'])) {
        switch ($section_type) {
          case 'hero':
            $new_section = [
              '_type' => 'hero',
              'eyebrow' => 'Feature',
              'hero_layout' => 'full_width',
              'heading' => ucfirst($command_prompt),
              'summary' => $command_prompt,
              'link_title' => 'Learn More',
              'link_url' => '#',
            ];

            // Only add default image if no media is set.
            if (!isset($new_section['media'])) {
              // Include the image handler.
              require_once plugin_dir_path(dirname(__FILE__)) . 'includes/image-handler.php';

              // Get a default image.
              $image_path = plugin_dir_path(dirname(__FILE__)) . 'images/card.png';
              $image_id = pressx_ensure_image($image_path);
              $image_url = $image_id ? wp_get_attachment_url($image_id) : '';

              $new_section['media'] = $image_url;
            }
            break;

          case 'side_by_side':
            $new_section = [
              '_type' => 'side_by_side',
              'eyebrow' => 'Feature',
              'layout' => 'image_right',
              'title' => ucfirst($command_prompt),
              'summary' => $command_prompt,
              'link_title' => 'Learn More',
              'link_url' => '#',
            ];

            // Only add default image if no media is set.
            if (!isset($new_section['media'])) {
              // Include the image handler.
              require_once plugin_dir_path(dirname(__FILE__)) . 'includes/image-handler.php';

              // Get a default image.
              $image_path = plugin_dir_path(dirname(__FILE__)) . 'images/card.png';
              $image_id = pressx_ensure_image($image_path);
              $image_url = $image_id ? wp_get_attachment_url($image_id) : '';

              $new_section['media'] = $image_url;
            }
            break;

          case 'quote':
            $new_section = [
              '_type' => 'quote',
              'quote' => $command_prompt,
              'author' => 'Anonymous',
            ];

            // Only add default image if no media is set.
            if (!isset($new_section['media'])) {
              // Include the image handler.
              require_once plugin_dir_path(dirname(__FILE__)) . 'includes/image-handler.php';

              // Get a default image.
              $image_path = plugin_dir_path(dirname(__FILE__)) . 'images/card.png';
              $image_id = pressx_ensure_image($image_path);
              $image_url = $image_id ? wp_get_attachment_url($image_id) : '';

              $new_section['media'] = $image_url;
            }
            break;

          case 'text':
            $new_section = [
              '_type' => 'text',
              'title' => ucfirst($command_prompt),
              'body' => $command_prompt,
            ];
            break;

          case 'newsletter':
            $new_section = [
              '_type' => 'newsletter',
              'title' => 'Stay Updated',
              'summary' => $command_prompt,
            ];
            break;

          default:
            return new WP_REST_Response([
              'response' => "I don't know how to add a section of type '$section_type'. Please try a different section type.",
              'command_executed' => 'add_section',
              'command_failed' => TRUE,
            ], 200);
        }
      }

      // Add the new section to the existing sections.
      $sections[] = $new_section;

      // Update the post meta with the new sections.
      carbon_set_post_meta($page_id, 'sections', $sections);

      // Get the permalink for the response.
      $permalink = get_permalink($page_id);

      // Check if we're in development mode.
      $dev_mode = defined('WP_DEBUG') && WP_DEBUG;

      return new WP_REST_Response([
        'response' => "I've added a new {$section_type} section to the landing page. You can view it at: $permalink",
        'links' => [
          [
            'text' => "View \"$post->post_title\"",
            'url' => $permalink,
          ],
        ],
        'command_executed' => 'add_section',
        'section_added' => TRUE,
        'section_data' => $dev_mode ? [
          'type' => $section_type,
          'title' => $section_type === 'hero' ? ($new_section['heading'] ?? '') : ($new_section['title'] ?? ''),
          'content' => $section_type === 'hero' ? ($new_section['summary'] ?? '') : ($section_type === 'text' ? ($new_section['body'] ?? '') : ($section_type === 'quote' ? ($new_section['quote'] ?? '') : '')),
          'attribution' => $section_type === 'quote' ? ($new_section['author'] ?? '') : '',
          'media' => $new_section['media'] ?? '',
          'page_id' => $page_id,
          'page_title' => $post->post_title,
          'page_url' => $permalink,
          'timestamp' => time(),
          'raw_section' => $new_section,
        ] : NULL,
        'dev_mode' => $dev_mode,
      ], 200);
    }
    catch (Exception $e) {
      return new WP_REST_Response([
        'response' => "I couldn't add the section due to an error: " . $e->getMessage(),
        'command_executed' => 'add_section',
        'command_failed' => TRUE,
      ], 200);
    }
  }

  // If user explicitly declined the command.
  if ($confirmed === 'no' && !empty($command_type)) {
    // Process the original message as a regular chat message.
    $system_prompt = "You are a helpful assistant for the PressX website. Your role is to provide concise, accurate information about PressX features, WordPress, Next.js, and web development topics. Keep your responses friendly, informative, and to the point. When appropriate, suggest relevant content or features from the PressX platform that might help the user. IMPORTANT: Your response must be 500 characters or less, but should be at least 2-3 sentences to provide adequate information.";

    try {
      // Make the AI request using the shared utility function.
      $response = pressx_ai_request($message, $system_prompt, FALSE, FALSE);

      // Limit the response to 500 characters for regular chat (non-command) responses.
      if (strlen($response) > 500) {
        $response = substr($response, 0, 497) . '...';
      }

      // Find relevant links based on the content.
      $links = pressx_find_relevant_links($response);

      return new WP_REST_Response([
        'response' => $response,
        'links' => $links,
      ], 200);
    }
    catch (Exception $e) {
      return new WP_REST_Response([
        'error' => $e->getMessage(),
        'response' => 'Sorry, I encountered an error. Please try again later.',
      ], 500);
    }
  }

  // Check if the message is a command to create a landing page.
  $command_pattern = '/add\s+landing/i';
  if (preg_match($command_pattern, $message)) {
    // No prompt provided, ask for one.
    return new WP_REST_Response([
      'response' => "I'd be happy to create a landing page for you. What topic or business would you like it to be about?",
      'command_detected' => TRUE,
      'command_type' => 'landing_page',
      'needs_more_info' => TRUE,
    ], 200);
  }

  // Check if the message is a command to add a section to a landing page.
  $add_component_pattern = '/add\s+section/i';

  if (preg_match($add_component_pattern, $message)) {
    // Generic "add section" command - show available options.
    return new WP_REST_Response([
      'response' => "I can add the following types of sections to your landing page. Please click on one to continue:",
      'command_detected' => TRUE,
      'command_type' => 'add_section_options',
      'links' => [
        [
          'text' => 'Hero Section',
          'url' => '#add-hero-section',
          'command' => 'add hero section',
        ],
        [
          'text' => 'Text Section',
          'url' => '#add-text-section',
          'command' => 'add text section',
        ],
        [
          'text' => 'Newsletter Section',
          'url' => '#add-newsletter-section',
          'command' => 'add newsletter section',
        ],
        [
          'text' => 'Quote Section',
          'url' => '#add-quote-section',
          'command' => 'add quote section',
        ],
        [
          'text' => 'Side by Side Section',
          'url' => '#add-side-by-side-section',
          'command' => 'add side by side section',
        ],
      ],
    ], 200);
  }

  // If not a command, proceed with regular AI response.
  // Create the system prompt.
  $system_prompt = "You are a helpful assistant for the PressX website. Your role is to provide concise, accurate information about PressX features, WordPress, Next.js, and web development topics. Keep your responses friendly, informative, and to the point. When appropriate, suggest relevant content or features from the PressX platform that might help the user. IMPORTANT: Your response must be 500 characters or less, but should be at least 2-3 sentences to provide adequate information.";

  try {
    // Make the AI request using the shared utility function.
    $response = pressx_ai_request($message, $system_prompt, FALSE, FALSE);

    // Limit the response to 500 characters for regular chat (non-command) responses.
    if (strlen($response) > 500) {
      $response = substr($response, 0, 497) . '...';
    }

    // Find relevant links based on the content.
    $links = pressx_find_relevant_links($response);

    return new WP_REST_Response([
      'response' => $response,
      'links' => $links,
    ], 200);
  }
  catch (Exception $e) {
    return new WP_REST_Response([
      'error' => $e->getMessage(),
      'response' => 'Sorry, I encountered an error. Please try again later.',
    ], 500);
  }
}
