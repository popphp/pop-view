pop-view
========

[![Build Status](https://github.com/popphp/pop-view/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-view/actions)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-view)](http://cc.popphp.org/pop-view/)

[![Join the chat at https://discord.gg/TZjgT74U7E](https://media.popphp.org/img/discord.svg)](https://discord.gg/TZjgT74U7E)

* [Overview](#overview)
* [Install](#install)
* [Quickstart](#quickstart)
* [File Template](#file-template)
* [Stream Template](#stream-template)
    - [Includes](#includes)
    - [Inheritance](#inheritance)
    - [Iteration](#iteration)
    - [Conditionals](#conditionals)
    - [Compiled & Cached Templates](#compiled--cached-templates)
* [Filters](#filters)
* [Working with View Data](#working-with-view-data)

Overview
--------
`pop-view` is the view template component that can be used as the "V" in an MVC stack or
independently as well. It supports using both PHP-file based templates and stream templates.
Within the stream templates, there is basic support for logic and iteration for dynamic
control over the view template.

`pop-view` is a component of the [Pop PHP Framework](https://www.popphp.org/).

[Top](#pop-view)

Install
-------

Install `pop-view` using Composer.

    composer require popphp/pop-view

Or, require it in your composer.json file

    "require": {
        "popphp/pop-view" : "^5.0.0"
    }

[Top](#pop-view)

Quickstart
----------

Consider a `phtml` template file like this:

```php
<html>
<body>
    <h1><?=$title; ?></h1>
</body>
</html>
```

You can set up a view object and populate data like this:

```php
use Pop\View\View;

$view        = new View('hello.phtml');
$view->title = 'Hello World!';

echo $view;
```

`View` auto-detects the template type from what you pass it: a path ending in `.phtml`/`.php` that
exists on disk becomes a file template, and anything else (an `.html` path, or a raw template string)
becomes a stream template - no need to explicitly wrap it in `Template\File`/`Template\Stream`
yourself unless you want more control, such as passing a
[cache directory](#compiled--cached-templates) to a stream template. See the
[File Template](#file-template) and [Stream Template](#stream-template) sections below for the
explicit form.

Data can also be passed directly to the constructor instead of being set afterward:

```php
$view = new View('hello.phtml', ['title' => 'Hello World!']);
```

which will produce:

```html
<html>
<body>
    <h1>Hello World!</h1>
</body>
</html>
```

[Top](#pop-view)

File Template
-------------

A file template simply uses PHP variables to deliver the data and content to template to be rendered.
With a file template, you have full access to the PHP environment to write any additional code or
helper scripts. However, in using this, you must make sure to adhere to the best practices and standards
regarding the security of the application. 

##### hello.phtml

```php
<!DOCTYPE html>
<html>
<head>
    <title><?=$title; ?></title>
</head>
<body>
    <h1><?=$title; ?></h1>
    <p><?=$content; ?></p>
</body>
</html>
```

You can set up the view object like this:

```php
use Pop\View\View;
use Pop\View\Template\File;

$view = new View(new File('hello.phtml'));
$view->title   = 'Hello World!';
$view->content = 'This is a test!';

echo $view;
```

[Top](#pop-view)


Stream Template
---------------

A stream template uses a formatted string placeholder to deliver the data and content to template to be rendered:

##### hello.html

```html
<!DOCTYPE html>
<html>
<head>
    <title>[{title}]</title>
</head>
<body>
    <h1>[{title}]</h1>
    <p>[{content}]</p>
</body>

</html>
```

You can set up the view object in a similar way and it will render the exact
same as the file template example.

```php
use Pop\View\View;
use Pop\View\Template\Stream;

$view = new View(new Stream('hello.html'));
$view->title   = 'Hello World!';
$view->content = 'This is a test!';

echo $view;
```

A value nested one level down in an array can be substituted directly with `[{name[index]}]`,
without having to flatten the data first:

```html
<p>Welcome, [{user[name]}]!</p>
```

```php
$view = new View(new Stream('welcome.html'), ['user' => ['name' => 'Nick']]);
```

[Top](#pop-view)

### Includes

Stream templates support includes to allow you to include other templates within them.

##### header.html

```html
<!-- header.html //-->
<!DOCTYPE html>
<html>

<head>
    <title>[{title}]</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>

<body>
    <header>This is the header</header>
```

##### footer.html

```html
<!-- footer.html //-->
    <footer>This is the footer</footer>
</body>

</html>
```

##### index.html

```html
<!-- index.html //-->
{{@include header.html}}
    <h1>[{title}]</h1>
    <p>[{content}]</p>
{{@include footer.html}}
```

You can set up the view object like before:

```php
use Pop\View\View;
use Pop\View\Template\Stream;

$view = new View(new Stream('index.html'));
$view->title   = 'Hello World!';
$view->content = 'This is a test!';

echo $view;
```

[Top](#pop-view)

### Inheritance

Stream templates support inheritance to allow you to extend other templates.

##### parent.html

```html
<!-- parent.html //-->
<!DOCTYPE html>
<html>

<head>
{{header}}
    <title>[{title}]</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
{{/header}}
</head>

<body>
    <h1>[{title}]</h1>
    [{content}]
</body>

</html>
```

##### child.html

```html
<!-- child.html //-->
{{@extends parent.html}}

{{header}}
{{parent}}
    <style>
        body { margin: 0; padding: 0; color: #bbb;}
    </style>
{{/header}}
```

You can set up the view object like before:

```php
use Pop\View\View;
use Pop\View\Template\Stream;

$view = new View(new Stream('child.html'));
$view->title   = 'Hello World!';
$view->content = 'This is a test!';

echo $view;
```

A parent template isn't limited to a single named block - declare as many `{{blockname}}...{{/blockname}}`
pairs as you need, and a child can override any subset of them:

```html
<!-- parent.html //-->
<head>
{{header}}
    <title>[{title}]</title>
{{/header}}
</head>
<body>
{{sidebar}}
    <aside>Default sidebar</aside>
{{/sidebar}}
</body>
```

```html
<!-- child.html //-->
{{@extends parent.html}}

{{header}}
{{parent}}
    <meta name="child" content="1" />
{{/header}}

{{sidebar}}
    <aside>Child sidebar</aside>
{{/sidebar}}
```

[Top](#pop-view)

### Iteration

Iteration is possible in stream templates when working with arrays and array-like objects.

```html
<!-- index.html //-->
<!DOCTYPE html>
<html>

<head>
    <title>[{title}]</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>

<body>

[{items}]
    <div><strong>[{key}]</strong>: [{value}]</div>
[{/items}]

</body>

</html>
```

```php
use Pop\View\View;
use Pop\View\Template\Stream;

$data = [
    'items' => [
        'hello' => 'world',
        'foo'   => 'bar',
        'baz'   => 123
    ]
];

$view = new View(new Stream('index.html'), $data);

echo $view;
```

A loop over a plain, numerically-indexed list of records works the same way, except each entry's own
fields are addressed directly by name instead of via `[{key}]`/`[{value}]`. `[{i}]` is available in
either form and gives the 1-indexed position within the loop:

```html
[{rows}]
    <div class="row-[{i}]">
        <h4>[{title}]</h4>
        <p>[{content}]</p>
    </div>
[{/rows}]
```

```php
$data = [
    'rows' => [
        ['title' => 'First Post',  'content' => 'Some content here.'],
        ['title' => 'Second Post', 'content' => 'Some more content.'],
    ]
];
```

A loop entry whose own value is itself an array, keyed by a non-numeric name, is treated as a nested
sub-loop - declare a tag with that same name inside the outer loop's body:

```html
[{items}]
    [{pages}]
        <p>[{value}]</p>
    [{/pages}]
[{/items}]
```

```php
$data = [
    'items' => [
        'pages' => ['Page One', 'Page Two', 'Page Three']
    ]
];
```

[Top](#pop-view)

### Conditionals

Conditional logic is possible within a stream template as well.

```html
<!-- index.html //-->
<!DOCTYPE html>
<html>

<head>
    <title>[{title}]</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>

<body>

[{if(foo)}]
    <p>The variable 'foo' is set to [{foo}].</p>
[{else}]
    <p>The variable 'foo' is not set.</p>
[{/if}]

</body>

</html>
```

```php
use Pop\View\View;
use Pop\View\Template\Stream;

$data = ['foo' => 'bar'];

$view = new View(new Stream('index.html'), $data);

echo $view;
```

A nested array value can be checked and printed with the same `[{name[index]}]` syntax used for
[array-index scalars](#stream-template) outside of a conditional:

```html
[{if(user[name])}]
    <p>Hello, [{user[name]}]!</p>
[{else}]
    <p>Hello, guest!</p>
[{/if}]
```

```php
$data = ['user' => ['name' => 'Nick']];
```

Conditionals also work inside a loop body, evaluated per-row against that row's own fields:

```html
[{rows}]
    <div>
        [{if(featured)}]<strong>[{title}] (Featured)</strong>[{else}][{title}][{/if}]
    </div>
[{/rows}]
```

```php
$data = [
    'rows' => [
        ['title' => 'First Post', 'featured' => true],
        ['title' => 'Second Post'],
    ]
];
```

[Top](#pop-view)

### Compiled & Cached Templates

By default, a stream template is re-parsed on every `render()` call. For templates rendered
repeatedly (e.g. on every request), you can opt in to a compiled/cached render path by giving
`Template\Stream` a writable cache directory - either as the constructor's second argument, or via
`setCacheDir()`:

```php
use Pop\View\View;
use Pop\View\Template\Stream;

$view = new View(new Stream('index.html', '/path/to/cache/dir'), $data);

echo $view;
```

```php
// or, equivalently:
$template = new Stream('index.html');
$template->setCacheDir('/path/to/cache/dir');

$view = new View($template, $data);
```

The first render compiles the template into plain PHP and writes it to the cache directory, keyed by
a hash of the fully-resolved template content (after `@extends`/`@include`/blocks have all been
merged in). Subsequent renders - including across requests, once the compiled file exists on disk -
skip parsing entirely and just `include` the compiled PHP file. The cache is automatically
invalidated whenever the resolved template content changes, so there's nothing to clear by hand
during normal development.

Every placeholder, loop, and conditional shown above is supported identically on the compiled path -
switching a template over to a cache directory is a pure performance opt-in with no change in
rendered output.

`Template\File` templates don't need this: they're already plain PHP, `include`d directly with no
separate parsing step.

[Top](#pop-view)

Filters
-------

`View` can run its data through one or more [`Pop\Filter`](https://github.com/popphp/pop-filter)
filters before rendering, useful for things like sanitizing output. Pass a filter (or an array of
filters) as the third constructor argument, or add them afterward:

```php
use Pop\View\View;
use Pop\Filter\Filter;

$view = new View('hello.html', [
    'title' => '<b>Hello World</b>',
], new Filter('strip_tags'));

echo $view; // <b> tags stripped from every value in the data
```

```php
$view = new View('hello.html', $data);
$view->addFilter(new Filter('strip_tags'));
$view->addFilters([
    new Filter('htmlentities', [ENT_QUOTES, 'UTF-8']),
]);
```

A filter can be excluded from specific fields by name, so it only applies to the rest of the data:

```php
$view = new View('hello.html', [
    'title'   => '<b>Hello</b>',
    'content' => '<b>World</b>',
]);

// 'content' is left untouched; only 'title' gets stripped of tags
$view->addFilter(new Filter('strip_tags', null, 'content'));
```

Other available methods: `hasFilters()`, `getFilters()`, and `clearFilters()` to remove all
configured filters.

[Top](#pop-view)

Working with View Data
-----------------------

Besides passing data through the constructor, `View` supports a few equivalent ways of getting data
in and out, since it extends [`Pop\Utils\ArrayObject`](https://github.com/popphp/pop-utils):

```php
use Pop\View\View;

$view = new View('hello.html');

// Property access
$view->title = 'Hello World!';

// Array access
$view['content'] = 'This is a test!';

// Explicit accessors
$view->set('foo', 'bar');
echo $view->get('foo');

// Merge additional data in without disturbing what's already set
$view->merge(['content' => 'This is a test!']);

// Replace all data at once
$view->setData(['title' => 'Hello World!']);

// Read everything back
$allData = $view->getData();
```

[Top](#pop-view)
