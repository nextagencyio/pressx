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
      'response' => "I'll create a landing page about \"$message\". Would you like to proceed?",
      'command_detected' => TRUE,
      'command_type' => 'landing_page',
      'command_prompt' => $message,
      'needs_confirmation' => TRUE,
    ], 200);
  }

  // Check if this is a confirmed command execution, process it directly.
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

      // Call the function with is_cli set to FALSE and auto_enhance set to TRUE.
      $result = pressx_create_ai_landing($command_prompt, FALSE, TRUE);

      if ($result && isset($result['post_id'])) {
        $post = get_post($result['post_id']);
        $permalink = get_permalink($post->ID);

        return new WP_REST_Response([
          'response' => "I've created your landing page about \"$command_prompt\". You can view it at: $permalink",
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
          'response' => "I tried to create your landing page about \"$command_prompt\", but encountered an error. Please try again with a more specific topic.",
          'command_executed' => 'create_ai_landing',
          'command_failed' => TRUE,
        ], 200);
      }
    }
    catch (Exception $e) {
      return new WP_REST_Response([
        'response' => "I couldn't create your landing page due to an error: " . $e->getMessage(),
        'command_executed' => 'create_ai_landing',
        'command_failed' => TRUE,
      ], 200);
    }
  }

  // If user explicitly declined the command.
  if ($confirmed === 'no' && !empty($command_type)) {
    return new WP_REST_Response([
      'response' => "No problem, I won't create a landing page. What would you like me to help you with instead?",
      'command_detected' => FALSE
    ], 200);
  }

  // Treat all input as a landing page topic, no exceptions
  $prompt = trim($message);
  
  return new WP_REST_Response([
    'response' => "I'll create a landing page about \"$prompt\". Would you like to proceed?",
    'command_detected' => TRUE,
    'command_type' => 'landing_page',
    'command_prompt' => $prompt,
    'needs_confirmation' => TRUE,
  ], 200);

  // The following code will never be reached, as we're treating all inputs as landing page topics
  /*
  $system_prompt = "You are a helpful assistant for the PressX website...";
  
  try {
    // ...existing code...
  }
  catch (Exception $e) {
    // ...existing code...
  }
  */
}
