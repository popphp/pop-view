pop-view
========

[![Build Status](https://travis-ci.org/popphp/pop-view.svg?branch=master)](https://travis-ci.org/popphp/pop-view)

OVERVIEW
--------
`pop-view` is the view template component that can be used as the "V" in an MVC stack or
independently as well. It supports using both PHP-file based templates and stream templates.
Within the stream templates, there is basic support for logic and iteration for dynamic
control over the view template.

`pop-view` is a component of the [Pop PHP Framework](http://www.popphp.org/).

INSTALL
-------

Install `pop-view` using Composer.

    composer require popphp/pop-view

BASIC USAGE
-----------

### Using a PHP-file template

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
use Pop\View;
use Pop\View\Template\File;

$view = new View\View(new View\Template\File('hello.phtml'));
$view->title   = 'Hello World!';
$view->content = 'This is a test!';

echo $view;
```

### Using a basic stream template

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

You can set up the view object in a similar way:

```php
use Pop\View;
use Pop\View\Template\Stream;

$view = new View\View(new View\Template\Stream('hello.html'));
$view->title   = 'Hello World!';
$view->content = 'This is a test!';

echo $view;
```

### Using includes with a stream template

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
use Pop\View;
use Pop\View\Template\Stream;

$view = new View\View(new View\Template\Stream('index.html'));
$view->title   = 'Hello World!';
$view->content = 'This is a test!';

echo $view;
```

### Using inheritance with a stream template

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
use Pop\View;
use Pop\View\Template\Stream;

$view = new View\View(new View\Template\Stream('child.html'));
$view->title   = 'Hello World!';
$view->content = 'This is a test!';

echo $view;
```

### Basic iteration over an array with a stream template

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
use Pop\View;

$data = [
    'items' => [
        'hello' => 'world',
        'foo'   => 'bar',
        'baz'   => 123
    ]
];

$view = new View\View(new View\Template\Stream('index.html'), $data);

echo $view;
```

### Basic conditional logic with a stream template

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
use Pop\View;

$data = ['foo' => 'bar'];

$view = new View\View(new View\Template\Stream('index.html'), $data);

echo $view;
```