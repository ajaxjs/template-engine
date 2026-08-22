/**
 * Test runner for the JavaScript Template Engine.
 *
 * Usage:  node test.js
 * Reads test.tpl and test_data.json, renders the template, and prints the result.
 */

'use strict';

const fs   = require('fs');
const path = require('path');
const TemplateEngine = require('./TemplateEngine');

// Read template
const template = fs.readFileSync(path.join(__dirname, 'test.tpl'), 'utf8');

// Read data
const data = JSON.parse(
    fs.readFileSync(path.join(__dirname, 'test_data.json'), 'utf8')
);

// Render
const engine = new TemplateEngine();
const result = engine.parse(template).render(data);

process.stdout.write(result);
