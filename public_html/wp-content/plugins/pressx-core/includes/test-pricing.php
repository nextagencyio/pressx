<?php

/**
 * @file
 * Test file for debugging the pricing section structure.
 */

// Include the rest-api.php file.
require_once __DIR__ . '/rest-api.php';

// Get the pricing section structure.
$pricing_section = pressx_get_pricing_section_structure();

// Print the pricing section structure.
echo "Pricing Section Structure:\n";
print_r($pricing_section);
echo "\n\n";

// Print the create-pricing.php file path.
$create_pricing_path = plugin_dir_path(dirname(__FILE__)) . 'includes/cli/commands/create-pricing.php';
echo "Create Pricing Path: $create_pricing_path\n";
echo "File Exists: " . (file_exists($create_pricing_path) ? 'Yes' : 'No') . "\n\n";

// If the file exists, print a portion of its contents.
if (file_exists($create_pricing_path)) {
  echo "File Contents (excerpt):\n";
  $file_contents = file_get_contents($create_pricing_path);
  echo substr($file_contents, 0, 500) . "...\n\n";

  // Check if the file contains a pricing section.
  echo "Contains '_type' => 'pricing': " . (strpos($file_contents, "'_type' => 'pricing'") !== FALSE ? 'Yes' : 'No') . "\n";
  echo "Contains \"_type\" => \"pricing\": " . (strpos($file_contents, '"_type" => "pricing"') !== FALSE ? 'Yes' : 'No') . "\n";
}
