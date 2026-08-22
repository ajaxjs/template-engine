<?php
/**
 * Test runner for the PHP Template Engine.
 *
 * Usage:  php test.php
 * Reads test.tpl and test_data.json, renders the template, and prints the result.
 */

require_once __DIR__ . '/TemplateEngine.php';

// Read template
$template = file_get_contents(__DIR__ . '/test.tpl');
if ($template === false) {
    fwrite(STDERR, "Error: Cannot read test.tpl\n");
    exit(1);
}

// Read data (use associative arrays for consistent dot-path access)
$jsonStr = file_get_contents(__DIR__ . '/test_data.json');
if ($jsonStr === false) {
    fwrite(STDERR, "Error: Cannot read test_data.json\n");
    exit(1);
}

$data = json_decode($jsonStr, true);
if ($data === null) {
    fwrite(STDERR, "Error: Invalid JSON in test_data.json\n");
    exit(1);
}

// Render
$engine = new TemplateEngine();
$result = $engine->parse($template)->render($data);

echo $result;
