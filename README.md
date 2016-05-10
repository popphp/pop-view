pop-view
========

[![Build Status](https://travis-ci.org/popphp/pop-view.svg?branch=master)](https://travis-ci.org/popphp/pop-view)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-view)](http://cc.popphp.org/pop-view/)

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

## BASIC USAGE

* [Using a PHP-file template](#using-a-php-file-template)
* [Using a basic stream template](#using-a-basic-stream-template)
* [Includes with a stream template](#includes-with-a-stream-template)
* [Inheritance with a stream template](#inheritance-with-a-stream-template)
* [Iteration over an array with a stream template](#iteration-over-an-array-with-a-stream-template)
* [Conditional logic with a stream template](#conditional-logic-with-a-stream-template)

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
use Pop\View\View;
use Pop\View\Template\File;

$view = new View(new File('hello.phtml'));
$view->title   = 'Hello World!';
$view->content = 'This is a test!';

echo $view;
```

[Top](#basic-usage)

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
use Pop\View\View;
use Pop\View\Template\Stream;

$view = new View(new Stream('hello.html'));
$view->title   = 'Hello World!';
$view->content = 'This is a test!';

echo $view;
```

[Top](#basic-usage)

### Includes with a stream template

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

[Top](#basic-usage)

### Inheritance with a stream template

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

[Top](#basic-usage)

### Iteration over an array with a stream template

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

[Top](#basic-usage)

### Conditional logic with a stream template

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


[Top](#basic-usage)
