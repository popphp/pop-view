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
        "popphp/pop-view" : "^4.0.0"
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
use Pop\View\Template\File;

$view        = new View(new File('hello.phtml'));
$view->title = 'Hello World!';

echo $view;
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

[Top](#pop-view)
