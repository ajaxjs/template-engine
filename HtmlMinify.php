<?php
/**
 * HTML & CSS Minifier
 *
 * Compresses HTML and CSS output without affecting rendering:
 *   - Strips HTML comments
 *   - Strips CSS comments
 *   - Collapses redundant whitespace
 *   - Trims whitespace around block-level tags and structural characters
 *   - Optimizes CSS values (zeros, hex colors, empty rules)
 *
 * Protected blocks (<pre>, <script>, <style>, <textarea>, <code>)
 * are preserved as-is to avoid breaking their content.
 *
 * Usage:
 *   $minified = HtmlMinify::minify($html);       // HTML + inline CSS
 *   $minified = HtmlMinify::minifyCss($css);      // standalone CSS
 */

class HtmlMinify
{
    /** Block-level tag names used for safe whitespace removal. */
    private const BLOCK_TAGS =
        'div|p|h[1-6]|ul|ol|li|table|thead|tbody|tfoot|tr|td|th|' .
        'header|footer|nav|aside|main|section|article|form|fieldset|' .
        'blockquote|dl|dt|dd|figure|figcaption|details|summary';

    // =========================================================================
    // HTML
    // =========================================================================

    /**
     * Minify HTML and all inline/embedded CSS.
     */
    public static function minify(string $html): string
    {
        // 1. Protect blocks whose content must not be altered
        $segments = self::protectBlocks($html);

        // 2. Minify non-protected segments
        for ($i = 0, $len = count($segments); $i < $len; $i += 2) {
            $segments[$i] = self::compressHtml($segments[$i]);
        }

        $html = implode('', $segments);

        // 3. Minify CSS inside <style> tags
        $html = self::processTagContent($html, 'style', function ($css) {
            return self::minifyCss($css);
        });

        // 4. Minify inline style="..." attributes
        $html = preg_replace_callback(
            '/(style\s*=\s*")([^"]*?)(")/i',
            function ($m) {
                return $m[1] . self::minifyCss($m[2]) . $m[3];
            },
            $html
        );

        return $html;
    }

    /**
     * Compress a single HTML fragment (no protected blocks inside).
     *
     * Only removes whitespace around block-level elements to avoid
     * collapsing meaningful spaces between inline elements.
     */
    private static function compressHtml(string $html): string
    {
        // Strip HTML comments
        $html = preg_replace('/<!--[\s\S]*?-->/s', '', $html);

        // Collapse runs of whitespace (including newlines) into a single space
        $html = preg_replace('/\s+/s', ' ', $html);

        // Remove space between adjacent tags: ">  <" → "><"
        $html = preg_replace('/>\s+</', '><', $html);

        // Remove whitespace after opening block-level tags
        $html = preg_replace(
            '/(<(?:' . self::BLOCK_TAGS . ')(?:\s[^>]*)?>)\s+/i',
            '$1',
            $html
        );

        // Remove whitespace before block-level tags (both opening and closing)
        $html = preg_replace(
            '/\s+(<\/?(?:' . self::BLOCK_TAGS . ')\b)/i',
            '$1',
            $html
        );

        return $html;
    }

    // =========================================================================
    // CSS
    // =========================================================================

    /**
     * Minify a standalone CSS string.
     */
    public static function minifyCss(string $css): string
    {
        // Strip CSS comments
        $css = preg_replace('/\/\*[\s\S]*?\*\//s', '', $css);

        // Collapse whitespace
        $css = preg_replace('/\s+/s', ' ', $css);
        $css = trim($css);

        // Trim space around structural characters
        $css = preg_replace('/\s*\{\s*/', '{', $css);
        $css = preg_replace('/\s*\}\s*/', '}', $css);
        $css = preg_replace('/\s*\(\s*/', '(', $css);
        $css = preg_replace('/\s*\)\s*/', ')', $css);

        // Trim space around colons and semicolons
        $css = preg_replace('/\s*:\s*/', ':', $css);
        $css = preg_replace('/\s*;\s*/', ';', $css);

        // Remove trailing semicolons before closing brace
        $css = str_replace(';}', '}', $css);

        // Remove empty rulesets
        $css = preg_replace('/[^{}]+\{\}/', '', $css);

        // Optimize zero values: "0px" → "0", "0em" → "0", etc.
        $css = preg_replace('/(?<![#\w])0(?:px|em|rem|pt|pc|ex|ch|vw|vh|vmin|vmax|%)/i', '0', $css);

        // Remove leading zero: "0.5" → ".5"
        $css = preg_replace('/(?<!\d)0+\.(\d+)/', '.$1', $css);

        // Shorten hex colors: "#aabbcc" → "#abc"
        $css = preg_replace(
            '/#([a-fA-F0-9])\1([a-fA-F0-9])\2([a-fA-F0-9])\3\b/',
            '#$1$2$3',
            $css
        );

        return $css;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Split HTML into alternating [html, protected, html, protected, ...] segments.
     * Odd-indexed segments are protected blocks that should not be minified.
     */
    private static function protectBlocks(string $html): array
    {
        $inner = '<(script|style|pre|textarea|code)\b[^>]*>[\s\S]*?<\/\1>';
        return preg_split(
            '/(' . $inner . ')/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
    }

    /**
     * Apply a callback to the inner content of every <tag>...</tag> occurrence.
     */
    private static function processTagContent(string $html, string $tag, callable $callback): string
    {
        return preg_replace_callback(
            '/(<' . $tag . '\b[^>]*>)([\s\S]*?)(<\/' . $tag . '>)/i',
            function ($m) use ($callback) {
                return $m[1] . $callback($m[2]) . $m[3];
            },
            $html
        );
    }
}
