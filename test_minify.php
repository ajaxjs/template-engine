<?php
/**
 * Test runner for HtmlMinify.
 *
 * Usage:  php test_minify.php
 */

require_once __DIR__ . '/HtmlMinify.php';

$pass = 0;
$fail = 0;

function assertEqual(string $label, string $expected, string $actual): void
{
    global $pass, $fail;
    if ($expected === $actual) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}\n";
        echo "    expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n";
        echo "    actual:   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

// ===========================================================================
echo "=== CSS minification ===\n";
// ===========================================================================

assertEqual(
    'strip comments + collapse',
    '.a{color:red;margin:0}',
    HtmlMinify::minifyCss("/* comment */\n.a {\n  color: red;\n  margin: 0px;\n}")
);

assertEqual(
    'zero units',
    'a{margin:0;padding:0}',
    HtmlMinify::minifyCss('a { margin: 0em; padding: 0%; }')
);

assertEqual(
    'leading zero',
    'a{opacity:.5}',
    HtmlMinify::minifyCss('a { opacity: 0.5; }')
);

assertEqual(
    'shorten hex color',
    'a{color:#abc}',
    HtmlMinify::minifyCss('a { color: #aabbcc; }')
);

assertEqual(
    'remove empty rule',
    '.b{font-size:14px}',
    HtmlMinify::minifyCss('.a {} .b { font-size: 14px; }')
);

assertEqual(
    'trailing semicolon',
    'a{color:red}',
    HtmlMinify::minifyCss('a { color: red; }')
);

assertEqual(
    'calc() kept verbatim',
    'a{width:calc(100% - 10px)}',
    HtmlMinify::minifyCss('a { width: calc(100% - 10px); }')
);

assertEqual(
    'calc() with nested vars kept verbatim',
    '.g{grid-template-columns:calc((var(--n) - 1) * var(--gap) + var(--iw))}',
    HtmlMinify::minifyCss('.g { grid-template-columns: calc((var(--n) - 1) * var(--gap) + var(--iw)); }')
);

assertEqual(
    'multiple calc() expressions',
    'a{margin:calc(1px + 2px) calc(50% - 1rem)}',
    HtmlMinify::minifyCss('a { margin: calc(1px + 2px) calc(50% - 1rem); }')
);

assertEqual(
    'nested calc() kept verbatim',
    'a{width:calc(calc(1rem + 2px) * 2)}',
    HtmlMinify::minifyCss('a { width: calc(calc(1rem + 2px) * 2); }')
);

assertEqual(
    'calc() inside other functions',
    'a{grid-template-columns:repeat(2, minmax(calc(50% - 10px), 1fr))}',
    HtmlMinify::minifyCss('a { grid-template-columns: repeat(2, minmax(calc(50% - 10px), 1fr)); }')
);

assertEqual(
    'my-calc( is not protected but still valid',
    'a{background:url(my-calc(1px))}',
    HtmlMinify::minifyCss('a { background: url(my-calc(1px)); }')
);

assertEqual(
    'uppercase CALC() kept verbatim',
    'a{width:CALC(100% - 10px)}',
    HtmlMinify::minifyCss('a { width: CALC(100% - 10px); }')
);

// ===========================================================================
echo "\n=== HTML minification ===\n";
// ===========================================================================

assertEqual(
    'strip HTML comments',
    '<div><p>Hello</p></div>',
    HtmlMinify::minify("<div>\n  <!-- a comment -->\n  <p>Hello</p>\n</div>")
);

assertEqual(
    'collapse whitespace',
    '<div><p>Hello</p><p>World</p></div>',
    HtmlMinify::minify("<div>\n  <p>Hello</p>\n  <p>World</p>\n</div>")
);

assertEqual(
    'protect <pre> content',
    "<p>before</p><pre>  hello\n  world</pre><p>after</p>",
    HtmlMinify::minify("<p>before</p>\n<pre>  hello\n  world</pre>\n<p>after</p>")
);

assertEqual(
    'protect <script> content',
    "<p>text</p><script>  var x = 1;\n  alert(x);</script>",
    HtmlMinify::minify("<p>text</p>\n<script>  var x = 1;\n  alert(x);</script>")
);

assertEqual(
    'minify inline style',
    '<div style="color:red;margin:0">text</div>',
    HtmlMinify::minify('<div style="color: red; margin: 0px;">text</div>')
);

assertEqual(
    'minify <style> block',
    '<style>.a{color:red}</style><div>text</div>',
    HtmlMinify::minify("<style>\n  .a {\n    color: red;\n  }\n</style>\n<div>text</div>")
);

assertEqual(
    'adjacent inline tags compacted',
    '<span>Hello</span><span>World</span>',
    HtmlMinify::minify('<span>Hello</span> <span>World</span>')
);

assertEqual(
    'calc() in inline style kept verbatim',
    '<div style="width:calc(50% - 10px)">x</div>',
    HtmlMinify::minify('<div style="width: calc(50% - 10px)">x</div>')
);

assertEqual(
    'calc() in <style> block kept verbatim',
    '<style>.g{width:calc((var(--n) - 1) * var(--gap))}</style>',
    HtmlMinify::minify("<style>\n  .g {\n    width: calc((var(--n) - 1) * var(--gap));\n  }\n</style>")
);

// ===========================================================================
echo "\n=== Integration: template engine + minify ===\n";
// ===========================================================================

require_once __DIR__ . '/TemplateEngine.php';

$template = <<<'TPL'
<!-- Store listing -->
<div class="store">
  <h1>{{ store_name }}</h1>
  <style>
    /* Main styles */
    .store {
      margin: 0px;
      padding: 0px;
      color: #aabbcc;
    }
    .empty {
    }
  </style>
  {% for section in sections %}
  <div class="section">
    <h2>{{ section.name }}</h2>
  </div>
  {% endfor %}
</div>
TPL;

$data = [
    'store_name' => 'TechMart',
    'sections'   => [
        ['name' => 'Electronics'],
        ['name' => 'Accessories'],
    ],
];

$rendered = TemplateEngine::compile($template, $data);
$minified = HtmlMinify::minify($rendered);

echo "  --- rendered (" . strlen($rendered) . " bytes) ---\n";
echo $rendered . "\n";
echo "  --- minified (" . strlen($minified) . " bytes) ---\n";
echo $minified . "\n\n";

$saved = strlen($rendered) - strlen($minified);
$pct   = round($saved / strlen($rendered) * 100, 1);
echo "  saved: {$saved} bytes ({$pct}%)\n\n";

// Verify minified output still contains expected content
$boolToStr = function ($v) { return $v ? '1' : ''; };

assertEqual('store name present',  '1', $boolToStr(strpos($minified, 'TechMart') !== false));
assertEqual('section 1 present',   '1', $boolToStr(strpos($minified, 'Electronics') !== false));
assertEqual('section 2 present',   '1', $boolToStr(strpos($minified, 'Accessories') !== false));
assertEqual('comment removed',     '1', $boolToStr(strpos($minified, '<!--') === false));
assertEqual('CSS comment removed', '1', $boolToStr(strpos($minified, '/* Main') === false));
assertEqual('empty rule removed',  '1', $boolToStr(strpos($minified, '.empty') === false));
assertEqual('hex color shortened', '1', $boolToStr(strpos($minified, '#abc') !== false));

// ===========================================================================
echo "\n=== Summary ===\n";
echo "  {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
