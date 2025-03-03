<?php

/**
 * @file
 * Utility functions for AI functionality in PressX.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Make an AI request to OpenRouter or Groq.
 *
 * @param string $prompt
 *   The user prompt.
 * @param string $system_prompt
 *   The system prompt.
 * @param bool $is_cli
 *   Whether this is being called from CLI.
 * @param bool $is_command
 *   Whether this is a command request that needs more tokens.
 *
 * @return string|array
 *   The AI response as string or full response array if $return_full_response is TRUE.
 *
 * @throws Exception
 *   If the request fails.
 */
function pressx_ai_request($prompt, $system_prompt = NULL, $is_cli = FALSE, $is_command = FALSE) {
  // Get the API keys from wp-config.php.
  $openrouter_api_key = defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '';
  $groq_api_key = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
  $preferred_api = defined('PRESSX_AI_API') ? PRESSX_AI_API : 'openrouter';

  $url = '';
  $headers = [];
  $model = '';

  // Helper function to configure Groq.
  $configure_groq = function () use ($groq_api_key) {
    return [
      'url' => 'https://api.groq.com/openai/v1/chat/completions',
      'headers' => [
        'Authorization: Bearer ' . $groq_api_key,
        'Content-Type: application/json',
      ],
      'model' => defined('GROQ_MODEL') ? GROQ_MODEL : 'llama-3.3-70b-versatile',
    ];
  };

  // Helper function to configure OpenRouter.
  $configure_openrouter = function () use ($openrouter_api_key) {
    return [
      'url' => 'https://openrouter.ai/api/v1/chat/completions',
      'headers' => [
        'Authorization: Bearer ' . $openrouter_api_key,
        'Content-Type: application/json',
        'HTTP-Referer: ' . home_url(),
        'X-Title: PressX',
      ],
      'model' => defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'mistralai/mixtral-8x7b-instruct',
    ];
  };

  // Initialize config array.
  $config = NULL;

  // Try preferred API first.
  if ($preferred_api === 'groq' && $groq_api_key) {
    $config = $configure_groq();
  }
  elseif ($preferred_api === 'openrouter' && $openrouter_api_key) {
    $config = $configure_openrouter();
  }
  // Handle fallback if preferred API isn't available.
  elseif ($preferred_api === 'groq' && !$groq_api_key && $openrouter_api_key) {
    if ($is_cli) {
      WP_CLI::warning("Groq API is configured but GROQ_API_KEY is not set. Falling back to OpenRouter.");
    }
    $config = $configure_openrouter();
  }
  elseif ($preferred_api === 'openrouter' && !$openrouter_api_key && $groq_api_key) {
    if ($is_cli) {
      WP_CLI::warning("OpenRouter API is configured but OPENROUTER_API_KEY is not set. Falling back to Groq.");
    }
    $config = $configure_groq();
  }

  // Check if we have a valid configuration.
  if (!$config) {
    // Just use any available API.
    if ($groq_api_key) {
      if ($is_cli) {
        WP_CLI::log("Using available Groq API.");
      }
      $config = $configure_groq();
    }
    elseif ($openrouter_api_key) {
      if ($is_cli) {
        WP_CLI::log("Using available OpenRouter API.");
      }
      $config = $configure_openrouter();
    }
    else {
      throw new Exception('No valid API configuration available.');
    }
  }

  // Set the configuration.
  $url = $config['url'];
  $headers = $config['headers'];
  $model = $config['model'];

  if ($is_cli && class_exists('WP_CLI') && defined('WP_CLI') && WP_CLI) {
    WP_CLI::log("Using API: " . ($url === 'https://api.groq.com/openai/v1/chat/completions' ? 'Groq' : 'OpenRouter'));
    WP_CLI::log("Model: " . $model);
  }

  // Determine max tokens based on context.
  // Default for CLI.
  $max_tokens = 4000;
  if (!$is_cli) {
    if ($is_command) {
      // Commands need more tokens.
      $max_tokens = 1000;
    }
    else {
      // Regular chat responses are limited.
      $max_tokens = 600;
    }
  }

  $data = [
    'model' => $model,
    'messages' => array_filter([
      $system_prompt ? ['role' => 'system', 'content' => $system_prompt] : NULL,
      ['role' => 'user', 'content' => $prompt],
    ]),
    'temperature' => 0.7,
    'max_tokens' => $max_tokens,
  ];

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  if (curl_errno($ch)) {
    throw new Exception('cURL error: ' . curl_error($ch));
  }

  curl_close($ch);

  if ($http_code !== 200) {
    throw new Exception('API request failed with status code: ' . $http_code . '. Response: ' . $response);
  }

  $response_data = json_decode($response, TRUE);

  if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception('Failed to parse API response: ' . json_last_error_msg());
  }

  if (empty($response_data['choices'][0]['message']['content'])) {
    throw new Exception('API response is missing expected content.');
  }

  return $response_data['choices'][0]['message']['content'];
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
function pressx_sanitize_prompt($prompt) {
  // Remove any potentially harmful characters.
  $sanitized = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $prompt);

  // Trim and convert to lowercase.
  $sanitized = strtolower(trim($sanitized));

  return $sanitized;
}

/**
 * Find relevant links based on the content.
 *
 * @param string $content
 *   The content to analyze.
 *
 * @return array
 *   An array of relevant links.
 */
function pressx_find_relevant_links($content) {
  $links = [];

  // Extract keywords from the content.
  $keywords = pressx_extract_keywords($content);

  // Find relevant pages based on keywords.
  foreach ($keywords as $keyword) {
    $posts = get_posts([
      'post_type' => ['post', 'page'],
      'post_status' => 'publish',
      'posts_per_page' => 3,
      's' => $keyword,
    ]);

    foreach ($posts as $post) {
      $links[] = [
        'text' => $post->post_title,
        'url' => get_permalink($post->ID),
      ];
    }

    // Limit to 3 links maximum.
    if (count($links) >= 3) {
      break;
    }
  }

  // Remove duplicates.
  $unique_links = [];
  $seen_urls = [];

  foreach ($links as $link) {
    if (!isset($seen_urls[$link['url']])) {
      $unique_links[] = $link;
      $seen_urls[$link['url']] = TRUE;
    }
  }

  return array_slice($unique_links, 0, 3);
}

/**
 * Extract keywords from content.
 *
 * @param string $content
 *   The content to analyze.
 *
 * @return array
 *   An array of keywords.
 */
function pressx_extract_keywords($content) {
  // Simple keyword extraction based on common words.
  $content = strtolower($content);
  $content = preg_replace('/[^\w\s]/', ' ', $content);
  $words = preg_split('/\s+/', $content);

  // Remove common stop words.
  $stop_words = [
    'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
    'with', 'by', 'about', 'as', 'of', 'is', 'are', 'was', 'were', 'be',
    'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
    'would', 'should', 'can', 'could', 'may', 'might', 'must', 'i', 'you',
    'he', 'she', 'it', 'we', 'they', 'this', 'that', 'these', 'those',
  ];

  $filtered_words = array_filter($words, function ($word) use ($stop_words) {
    return strlen($word) > 3 && !in_array($word, $stop_words);
  });

  // Count word frequencies.
  $word_counts = array_count_values($filtered_words);

  // Sort by frequency.
  arsort($word_counts);

  // Return top keywords.
  return array_slice(array_keys($word_counts), 0, 5);
}

/**
 * Generate content using Groq API.
 *
 * @param string $system_prompt
 *   The system prompt.
 * @param string $user_prompt
 *   The user prompt.
 * @param string $api_key
 *   The Groq API key.
 *
 * @return string
 *   The generated content.
 */
function pressx_generate_with_groq($system_prompt, $user_prompt = '', $api_key = '') {
  if (empty($api_key)) {
    $api_key = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
  }

  if (empty($api_key)) {
    error_log('Groq API key is not set.');
    return '';
  }

  $url = 'https://api.groq.com/openai/v1/chat/completions';
  $headers = [
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json',
  ];
  $model = defined('GROQ_MODEL') ? GROQ_MODEL : 'llama-3.3-70b-versatile';

  $data = [
    'model' => $model,
    'messages' => array_filter([
      !empty($system_prompt) ? ['role' => 'system', 'content' => $system_prompt] : NULL,
      !empty($user_prompt) ? ['role' => 'user', 'content' => $user_prompt] : NULL,
    ]),
    'temperature' => 0.7,
    'max_tokens' => 4000,
  ];

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($http_code !== 200) {
    error_log('Groq API request failed with HTTP code ' . $http_code . ': ' . $response);
    return '';
  }

  $response_data = json_decode($response, TRUE);
  if (isset($response_data['choices'][0]['message']['content'])) {
    return $response_data['choices'][0]['message']['content'];
  }

  return '';
}

/**
 * Generate content using OpenRouter API.
 *
 * @param string $system_prompt
 *   The system prompt.
 * @param string $user_prompt
 *   The user prompt.
 * @param string $api_key
 *   The OpenRouter API key.
 *
 * @return string
 *   The generated content.
 */
function pressx_generate_with_openrouter($system_prompt, $user_prompt = '', $api_key = '') {
  if (empty($api_key)) {
    $api_key = defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '';
  }

  if (empty($api_key)) {
    error_log('OpenRouter API key is not set.');
    return '';
  }

  $url = 'https://openrouter.ai/api/v1/chat/completions';
  $headers = [
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json',
    'HTTP-Referer: https://pressx.io',
    'X-Title: PressX',
  ];
  $model = defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'anthropic/claude-3-opus:beta';

  $data = [
    'model' => $model,
    'messages' => array_filter([
      !empty($system_prompt) ? ['role' => 'system', 'content' => $system_prompt] : NULL,
      !empty($user_prompt) ? ['role' => 'user', 'content' => $user_prompt] : NULL,
    ]),
    'temperature' => 0.7,
    'max_tokens' => 4000,
  ];

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($http_code !== 200) {
    error_log('OpenRouter API request failed with HTTP code ' . $http_code . ': ' . $response);
    return '';
  }

  $response_data = json_decode($response, TRUE);
  if (isset($response_data['choices'][0]['message']['content'])) {
    return $response_data['choices'][0]['message']['content'];
  }

  return '';
}
