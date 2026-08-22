<?php
/**
 * Simple Template Engine for PHP
 *
 * Supports:
 *   {{ variable }}              - Variable output
 *   {{ obj.field }}             - Dot notation for nested access
 *   {{ loop.index/first/last }} - Loop metadata
 *   {% for item in list %}      - Loop (nestable)
 *   {% endfor %}                - End loop
 *   {# comment #}               - Comments (stripped)
 *
 * Architecture: tokenize -> parse (AST) -> render
 */

// =============================================================================
// Tokenizer
// =============================================================================

class TemplateTokenizer
{
    /**
     * Split template string into an array of token objects.
     * Each token has: type ('text'|'output'|'block'), value, pos
     */
    public static function tokenize(string $template): array
    {
        // Strip comments: {# ... #}
        $template = preg_replace('/\{#.*?#\}/s', '', $template);

        // Split by tags, keeping delimiters (PREG_SPLIT_DELIM_CAPTURE)
        $parts = preg_split('/(\{\{.*?\}\}|\{%.*?%\})/s', $template, -1, PREG_SPLIT_DELIM_CAPTURE);

        $tokens = [];
        $pos = 0;

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (strlen($part) >= 4 && substr($part, 0, 2) === '{{' && substr($part, -2) === '}}') {
                $inner = trim(substr($part, 2, -2));
                $tokens[] = (object)[
                    'type'  => 'output',
                    'value' => $inner,
                    'pos'   => $pos,
                ];
            } elseif (strlen($part) >= 4 && substr($part, 0, 2) === '{%' && substr($part, -2) === '%}') {
                $inner = trim(substr($part, 2, -2));
                $tokens[] = (object)[
                    'type'  => 'block',
                    'value' => $inner,
                    'pos'   => $pos,
                ];
            } else {
                $tokens[] = (object)[
                    'type'  => 'text',
                    'value' => $part,
                    'pos'   => $pos,
                ];
            }

            $pos++;
        }

        return $tokens;
    }
}

// =============================================================================
// Parser — builds an AST from tokens
// =============================================================================

class TemplateParser
{
    private array $tokens;
    private int $pos;

    public function parse(array $tokens): array
    {
        $this->tokens = $tokens;
        $this->pos = 0;
        return $this->parseNodes();
    }

    /**
     * Parse nodes until end-of-input or a matching endfor is found.
     */
    private function parseNodes(): array
    {
        $nodes = [];

        while ($this->pos < count($this->tokens)) {
            $token = $this->tokens[$this->pos];

            if ($token->type === 'text') {
                $nodes[] = (object)['type' => 'text', 'value' => $token->value];
                $this->pos++;
            } elseif ($token->type === 'output') {
                $nodes[] = (object)[
                    'type' => 'variable',
                    'path' => self::parsePath($token->value),
                ];
                $this->pos++;
            } elseif ($token->type === 'block') {
                if (preg_match('/^for\s+(\w+)\s+in\s+(\S+)$/', $token->value, $m)) {
                    $node = (object)[
                        'type'     => 'for',
                        'itemVar'  => $m[1],
                        'listPath' => self::parsePath($m[2]),
                        'body'     => [],
                    ];
                    $this->pos++; // consume {% for %}
                    $node->body = $this->parseNodes(); // recurse; stops at {% endfor %}
                    $nodes[] = $node;
                } elseif ($token->value === 'endfor') {
                    $this->pos++; // consume {% endfor %}
                    return $nodes;
                } else {
                    // Unknown block — skip
                    $this->pos++;
                }
            } else {
                $this->pos++;
            }
        }

        return $nodes;
    }

    /**
     * Convert a dot-separated path string to an array of segments.
     * e.g. "item.name" => ["item", "name"]
     */
    public static function parsePath(string $str): array
    {
        return array_values(array_filter(
            explode('.', trim($str)),
            fn($s) => $s !== ''
        ));
    }
}

// =============================================================================
// Renderer — walks the AST and produces output
// =============================================================================

class TemplateRenderer
{
    private array $scopeStack;

    public function render(array $ast, array $data): string
    {
        $this->scopeStack = [$data];
        return $this->renderNodes($ast);
    }

    private function renderNodes(array $nodes): string
    {
        $output = '';
        foreach ($nodes as $node) {
            switch ($node->type) {
                case 'text':
                    $output .= $node->value;
                    break;

                case 'variable':
                    $output .= $this->resolveToString($node->path);
                    break;

                case 'for':
                    $output .= $this->renderFor($node);
                    break;
            }
        }
        return $output;
    }

    /**
     * Render a for-loop node.
     * Pushes a new scope per iteration with the item variable and loop metadata.
     */
    private function renderFor(object $node): string
    {
        $list = $this->resolve($node->listPath);
        if (!is_array($list)) {
            return '';
        }

        $output = '';
        $items = array_values($list); // re-index to 0-based
        $count = count($items);

        foreach ($items as $i => $item) {
            // New scope: inherit parent's loop metadata, then overlay current loop's
            $parentScope = end($this->scopeStack) ?: [];
            $loopMeta = [
                'index' => $i + 1,
                'first' => ($i === 0),
                'last'  => ($i === $count - 1),
            ];
            $scope = array_merge($parentScope, [
                $node->itemVar => $item,
                'loop'         => $loopMeta,
            ]);

            $this->scopeStack[] = $scope;
            $output .= $this->renderNodes($node->body);
            array_pop($this->scopeStack);
        }

        return $output;
    }

    /**
     * Walk the scope stack (innermost first) to find the root variable,
     * then follow the remaining dot-path segments.
     */
    private function resolve(array $path)
    {
        if (empty($path)) {
            return null;
        }

        $root = $path[0];

        // Search scope stack from innermost to outermost
        for ($i = count($this->scopeStack) - 1; $i >= 0; $i--) {
            if (is_array($this->scopeStack[$i]) && array_key_exists($root, $this->scopeStack[$i])) {
                $value = $this->scopeStack[$i][$root];

                // Traverse remaining path segments
                for ($j = 1; $j < count($path); $j++) {
                    if (is_array($value) && array_key_exists($path[$j], $value)) {
                        $value = $value[$path[$j]];
                    } elseif (is_object($value) && isset($value->{$path[$j]})) {
                        $value = $value->{$path[$j]};
                    } else {
                        return null;
                    }
                }

                return $value;
            }
        }

        return null;
    }

    /**
     * Convert a resolved value to a display string.
     */
    private function resolveToString(array $path): string
    {
        $value = $this->resolve($path);

        if (is_bool($value)) {
            return $value ? '1' : '';
        }
        if ($value === null) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string)$value;
    }
}

// =============================================================================
// Public API
// =============================================================================

class TemplateEngine
{
    private ?array $ast = null;
    private string $rawTemplate = '';

    /**
     * Quick check: does the template contain any template syntax tags?
     * Returns false for plain-text strings, allowing callers to skip
     * the entire tokenize → parse → render pipeline.
     */
    public static function hasSyntax(string $template): bool
    {
        return strpos($template, '{{') !== false
            || strpos($template, '{%') !== false
            || strpos($template, '{#') !== false;
    }

    /**
     * Parse a template string into an AST (compile phase).
     * Can be called once and rendered multiple times with different data.
     */
    public function parse(string $template): self
    {
        $this->rawTemplate = $template;

        if (!self::hasSyntax($template)) {
            // Plain text — skip tokenize/parse, store a single text-node AST
            $this->ast = [(object)['type' => 'text', 'value' => $template]];
            return $this;
        }

        $tokens = TemplateTokenizer::tokenize($template);
        $parser = new TemplateParser();
        $this->ast = $parser->parse($tokens);
        return $this;
    }

    /**
     * Render the parsed AST with the given data context.
     */
    public function render(array $data): string
    {
        if ($this->ast === null) {
            throw new \RuntimeException('Template not parsed. Call parse() first.');
        }

        $renderer = new TemplateRenderer();
        return $renderer->render($this->ast, $data);
    }

    /**
     * Convenience: parse and render in one call.
     * Fast path: returns the template as-is when no syntax tags are found.
     */
    public static function compile(string $template, array $data): string
    {
        if (!self::hasSyntax($template)) {
            return $template;
        }

        return (new self())->parse($template)->render($data);
    }
}
