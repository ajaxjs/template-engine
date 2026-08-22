# Template Engine

轻量级跨语言模板引擎 —— PHP 与 JavaScript 双实现，同一份模板 + 同一份数据产生**逐字节一致**的输出。

## 特性

- 变量输出与点号路径访问（`{{ user.name }}`）
- 嵌套 `for` 循环（`{% for item in list %}`）
- 循环元数据（`loop.index` / `loop.first` / `loop.last`）
- 注释（`{# ... #}`）
- 纯文本快速通道 —— 无模板语法时跳过解析，直接返回
- 零依赖，PHP / Node.js 原生运行

## 模板语法

| 语法 | 说明 | 示例 |
|------|------|------|
| `{{ variable }}` | 输出变量 | `{{ title }}` |
| `{{ obj.field }}` | 点号访问嵌套属性 | `{{ user.name }}` |
| `{{ loop.index }}` | 当前迭代序号（从 1 开始） | `{{ loop.index }}` |
| `{{ loop.first }}` | 是否首次迭代 | `{{ loop.first }}` |
| `{{ loop.last }}` | 是否末次迭代 | `{{ loop.last }}` |
| `{% for item in list %} ... {% endfor %}` | 循环（可无限嵌套） | 见下方示例 |
| `{# comment #}` | 注释，不输出 | `{# TODO #}` |

## 快速开始

### 一次性渲染 — `compile()`

最简洁的用法，解析 + 渲染一步完成：

```php
<?php
// PHP
require_once 'TemplateEngine.php';

$output = TemplateEngine::compile(
    'Hello, {{ name }}!',
    ['name' => 'World']
);
// => "Hello, World!"
```

```js
// JavaScript
const TemplateEngine = require('./TemplateEngine');

const output = TemplateEngine.compile(
    'Hello, {{ name }}!',
    { name: 'World' }
);
// => "Hello, World!"
```

### 一次解析，多次渲染 — `parse()` + `render()`

模板只需编译一次，可用不同数据反复渲染：

```php
<?php
$engine = (new TemplateEngine())->parse('Dear {{ name }}, welcome!');

echo $engine->render(['name' => 'Alice']);  // => "Dear Alice, welcome!"
echo $engine->render(['name' => 'Bob']);    // => "Dear Bob, welcome!"
```

```js
const engine = new TemplateEngine().parse('Dear {{ name }}, welcome!');

console.log(engine.render({ name: 'Alice' }));  // => "Dear Alice, welcome!"
console.log(engine.render({ name: 'Bob' }));    // => "Dear Bob, welcome!"
```

## 示例

### 变量替换

```php
<?php
$template = '{{ user.name }} ({{ user.email }})';
$data    = ['user' => ['name' => 'Alice', 'email' => 'alice@example.com']];

echo TemplateEngine::compile($template, $data);
// => "Alice (alice@example.com)"
```

```js
const template = '{{ user.name }} ({{ user.email }})';
const data     = { user: { name: 'Alice', email: 'alice@example.com' } };

console.log(TemplateEngine.compile(template, data));
// => "Alice (alice@example.com)"
```

### 循环

```php
<?php
$template = '{% for item in items %}{{ loop.index }}. {{ item }}' . "\n" . '{% endfor %}';
$data    = ['items' => ['Apple', 'Banana', 'Cherry']];

echo TemplateEngine::compile($template, $data);
```

```js
const template = '{% for item in items %}{{ loop.index }}. {{ item }}\n{% endfor %}';
const data     = { items: ['Apple', 'Banana', 'Cherry'] };

console.log(TemplateEngine.compile(template, data));
```

两端输出一致：

```
1. Apple
2. Banana
3. Cherry
```

### 嵌套循环

```php
<?php
$template = <<<'TPL'
{% for group in groups %}[{{ group.name }}]
{% for item in group.items %}  - {{ item }}
{% endfor %}{% endfor %}
TPL;

$data = [
    'groups' => [
        ['name' => 'Fruits',     'items' => ['Apple', 'Banana']],
        ['name' => 'Vegetables', 'items' => ['Carrot', 'Potato']],
    ],
];

echo TemplateEngine::compile($template, $data);
```

```js
const template =
    '{% for group in groups %}[{{ group.name }}]\n' +
    '{% for item in group.items %}  - {{ item }}\n' +
    '{% endfor %}{% endfor %}';

const data = {
    groups: [
        { name: 'Fruits',     items: ['Apple', 'Banana'] },
        { name: 'Vegetables', items: ['Carrot', 'Potato'] },
    ],
};

console.log(TemplateEngine.compile(template, data));
```

输出：

```
[Fruits]
  - Apple
  - Banana
[Vegetables]
  - Carrot
  - Potato
```

### 循环元数据

```php
<?php
$template = '{% for item in items %}{{ loop.index }}/{{ item }}{% if loop.last %} (last){% endif %}' . "\n" . '{% endfor %}';
```

`loop` 对象在每次迭代中可用：

| 属性 | 类型 | 说明 |
|------|------|------|
| `loop.index` | `int` | 当前迭代序号，从 1 开始 |
| `loop.first` | `bool` | 首次迭代时为 `true`（渲染为 `1`） |
| `loop.last` | `bool` | 末次迭代时为 `true`（渲染为 `1`） |

嵌套循环中，`loop` 始终指向**当前层**的循环元数据，不会被外层覆盖。

### 注释

```
{# 这是一段注释，不会出现在输出中 #}
Hello, {{ name }}!
```

## 语法检测 — `hasSyntax()`

用于判断模板是否包含模板语法标记，不含语法的纯文本会走快速通道：

```php
<?php
TemplateEngine::hasSyntax('Hello World');      // => false
TemplateEngine::hasSyntax('Hello {{ name }}'); // => true
TemplateEngine::hasSyntax('{% for i in x %}'); // => true
TemplateEngine::hasSyntax('{# comment #}');    // => true
```

```js
TemplateEngine.hasSyntax('Hello World');      // => false
TemplateEngine.hasSyntax('Hello {{ name }}'); // => true
TemplateEngine.hasSyntax('{% for i in x %}'); // => true
TemplateEngine.hasSyntax('{# comment #}');    // => true
```

当 `hasSyntax()` 返回 `false` 时：
- `compile()` 直接返回原字符串，**跳过整个解析/渲染流程**
- `parse()` 仅生成一个单文本节点的 AST

## API 参考

### 静态方法

| 方法 | 说明 |
|------|------|
| `compile(template, data)` | 解析并渲染，纯文本走快速通道 |
| `hasSyntax(template)` | 检测模板是否包含语法标记 |

### 实例方法

| 方法 | 说明 |
|------|------|
| `parse(template)` | 将模板编译为 AST，返回自身（支持链式调用） |
| `render(data)` | 用给定数据渲染已编译的 AST，返回字符串 |

## 运行测试

```bash
# JavaScript
node test.js

# PHP
php test.php
```

两端读取同一份 `test.tpl` + `test_data.json`，输出完全一致。

## 项目结构

```
template-parse/
├── TemplateEngine.php   # PHP 实现
├── TemplateEngine.js    # JavaScript 实现
├── test.tpl             # 共享测试模板
├── test_data.json       # 共享测试数据
├── test.php             # PHP 测试脚本
├── test.js              # JavaScript 测试脚本
└── README.md            # 本文档
```

## 架构

```
Template String
       │
       ▼
  ┌────────────┐
  │ Tokenizer  │  正则拆分 → text / output / block tokens
  └─────┬──────┘
        ▼
  ┌────────────┐
  │   Parser   │  递归下降 → AST (text / variable / for 节点)
  └─────┬──────┘
        ▼
  ┌────────────┐
  │  Renderer  │  遍历 AST + 作用域栈 → 输出字符串
  └────────────┘
```

## 错误处理

| 场景 | 行为 |
|------|------|
| 变量不存在 | 输出空字符串 |
| 循环目标非数组 | 输出空字符串 |
| 未知 `{% block %}` | 静默跳过 |
| 未 `parse()` 直接 `render()` | 抛出运行时异常 |
