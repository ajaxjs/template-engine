/**
 * Simple Template Engine for JavaScript
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
 *
 * This implementation mirrors the PHP version (TemplateEngine.php) exactly,
 * so the same template + same data produces identical output.
 */

'use strict';

// =============================================================================
// Tokenizer
// =============================================================================

class TemplateTokenizer {
    /**
     * Split template string into an array of token objects.
     * Each token has: type ('text'|'output'|'block'), value, pos
     */
    static tokenize(template) {
        // Strip comments: {# ... #}
        template = template.replace(/\{#[\s\S]*?#\}/g, '');

        // Split by tags, keeping delimiters (capturing group)
        const parts = template.split(/(\{\{[\s\S]*?\}\}|\{%[\s\S]*?%\})/);

        const tokens = [];
        let pos = 0;

        for (const part of parts) {
            if (part === '') continue;

            if (part.length >= 4 && part.startsWith('{{') && part.endsWith('}}')) {
                tokens.push({
                    type: 'output',
                    value: part.slice(2, -2).trim(),
                    pos: pos++,
                });
            } else if (part.length >= 4 && part.startsWith('{%') && part.endsWith('%}')) {
                tokens.push({
                    type: 'block',
                    value: part.slice(2, -2).trim(),
                    pos: pos++,
                });
            } else {
                tokens.push({
                    type: 'text',
                    value: part,
                    pos: pos++,
                });
            }
        }

        return tokens;
    }
}

// =============================================================================
// Parser — builds an AST from tokens
// =============================================================================

class TemplateParser {
    parse(tokens) {
        this.tokens = tokens;
        this.pos = 0;
        return this._parseNodes();
    }

    /**
     * Parse nodes until end-of-input or a matching endfor is found.
     */
    _parseNodes() {
        const nodes = [];

        while (this.pos < this.tokens.length) {
            const token = this.tokens[this.pos];

            if (token.type === 'text') {
                nodes.push({ type: 'text', value: token.value });
                this.pos++;
            } else if (token.type === 'output') {
                nodes.push({
                    type: 'variable',
                    path: TemplateParser.parsePath(token.value),
                });
                this.pos++;
            } else if (token.type === 'block') {
                const forMatch = token.value.match(/^for\s+(\w+)\s+in\s+(\S+)$/);

                if (forMatch) {
                    const node = {
                        type: 'for',
                        itemVar: forMatch[1],
                        listPath: null,
                        count: null,
                        body: [],
                    };

                    if (/^\d+$/.test(forMatch[2])) {
                        // Numeric range: {% for i in 5 %}
                        node.count = parseInt(forMatch[2], 10);
                    } else {
                        // Array iteration: {% for item in list %}
                        node.listPath = TemplateParser.parsePath(forMatch[2]);
                    }

                    this.pos++; // consume {% for %}
                    node.body = this._parseNodes(); // recurse; stops at {% endfor %}
                    nodes.push(node);
                } else if (token.value === 'endfor') {
                    this.pos++; // consume {% endfor %}
                    return nodes;
                } else {
                    // Unknown block — skip
                    this.pos++;
                }
            } else {
                this.pos++;
            }
        }

        return nodes;
    }

    /**
     * Convert a dot-separated path string to an array of segments.
     * e.g. "item.name" => ["item", "name"]
     */
    static parsePath(str) {
        return str.trim().split('.').filter(s => s !== '');
    }
}

// =============================================================================
// Renderer — walks the AST and produces output
// =============================================================================

class TemplateRenderer {
    render(ast, data) {
        this.scopeStack = [data];
        return this._renderNodes(ast);
    }

    _renderNodes(nodes) {
        let output = '';

        for (const node of nodes) {
            switch (node.type) {
                case 'text':
                    output += node.value;
                    break;

                case 'variable':
                    output += this._resolveToString(node.path);
                    break;

                case 'for':
                    output += this._renderFor(node);
                    break;
            }
        }

        return output;
    }

    /**
     * Render a for-loop node.
     * Pushes a new scope per iteration with the item variable and loop metadata.
     */
    _renderFor(node) {
        // Build the iteration list: numeric range or array variable
        let items;
        if (node.count !== null) {
            items = [];
            for (let n = 1; n <= Math.max(0, node.count); n++) {
                items.push(n);
            }
        } else {
            const list = this._resolve(node.listPath);
            if (!Array.isArray(list)) {
                return '';
            }
            items = list;
        }

        let output = '';
        const count = items.length;

        for (let i = 0; i < count; i++) {
            // New scope: inherit parent's loop metadata, then overlay current loop's
            const parentScope = this.scopeStack[this.scopeStack.length - 1] || {};
            const loopMeta = {
                index: i + 1,
                first: i === 0,
                last: i === count - 1,
            };
            const scope = Object.assign({}, parentScope, {
                [node.itemVar]: items[i],
                loop: loopMeta,
            });

            this.scopeStack.push(scope);
            output += this._renderNodes(node.body);
            this.scopeStack.pop();
        }

        return output;
    }

    /**
     * Walk the scope stack (innermost first) to find the root variable,
     * then follow the remaining dot-path segments.
     */
    _resolve(path) {
        if (path.length === 0) return null;

        const root = path[0];

        // Search scope stack from innermost to outermost
        for (let i = this.scopeStack.length - 1; i >= 0; i--) {
            const scope = this.scopeStack[i];
            if (scope != null && typeof scope === 'object' && root in scope) {
                let value = scope[root];

                // Traverse remaining path segments
                for (let j = 1; j < path.length; j++) {
                    if (value != null && typeof value === 'object' && path[j] in value) {
                        value = value[path[j]];
                    } else {
                        return null;
                    }
                }

                return value;
            }
        }

        return null;
    }

    /**
     * Convert a resolved value to a display string.
     */
    _resolveToString(path) {
        const value = this._resolve(path);

        if (value === null || value === undefined) {
            return '';
        }
        if (typeof value === 'boolean') {
            return value ? '1' : '';
        }
        if (typeof value === 'object') {
            return JSON.stringify(value);
        }

        return String(value);
    }
}

// =============================================================================
// Public API
// =============================================================================

class TemplateEngine {
    constructor() {
        this.ast = null;
        this.rawTemplate = '';
    }

    /**
     * Quick check: does the template contain any template syntax tags?
     * Returns false for plain-text strings, allowing callers to skip
     * the entire tokenize → parse → render pipeline.
     */
    static hasSyntax(template) {
        return template.includes('{{')
            || template.includes('{%')
            || template.includes('{#');
    }

    /**
     * Parse a template string into an AST (compile phase).
     * Can be called once and rendered multiple times with different data.
     */
    parse(template) {
        this.rawTemplate = template;

        if (!TemplateEngine.hasSyntax(template)) {
            // Plain text — skip tokenize/parse, store a single text-node AST
            this.ast = [{ type: 'text', value: template }];
            return this;
        }

        const tokens = TemplateTokenizer.tokenize(template);
        const parser = new TemplateParser();
        this.ast = parser.parse(tokens);
        return this;
    }

    /**
     * Render the parsed AST with the given data context.
     */
    render(data) {
        if (!this.ast) {
            throw new Error('Template not parsed. Call parse() first.');
        }

        const renderer = new TemplateRenderer();
        return renderer.render(this.ast, data);
    }

    /**
     * Convenience: parse and render in one call.
     * Fast path: returns the template as-is when no syntax tags are found.
     */
    static compile(template, data) {
        if (!TemplateEngine.hasSyntax(template)) {
            return template;
        }

        return new TemplateEngine().parse(template).render(data);
    }
}

// Export for Node.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TemplateEngine;
}
