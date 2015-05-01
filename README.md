Pop View Component
==================
Part of the Pop PHP Framework (http://github.com/popphp/popphp)

OVERVIEW
--------
Pop View is the view template component that can be used as the "V" in
an MVC stack or independently as well. It supports using both PHP-file
based templates and stream templates. Within the stream templates, there
is basic support for logic and iteration for dynamic control over
the view template.

QUICK USE
---------

Using a PHP-file template, 'hello.phtml':

```php
<!DOCTYPE html>
<html>

<head>
    <title><?=$title; ?></title>
</head>

<body>
    <h1><?=$title; ?></h1>
<?=$content; ?>
</body>

</html>
```

```php
use Pop\View;
use Pop\View\Template\File;

$view = new View\View(new View\Template\File('hello.phtml'));
$view->title   = 'Hello World!';
$view->content = '<p>This is a test!</p>';

echo $view;
```

Using a basic stream template, 'hello.html':

```html
<!DOCTYPE html>
<html>

<head>
    <title>[{title}]</title>
</head>

<body>
    <h1>[{title}]</h1>
[{content}]
</body>

</html>
```

```php
use Pop\View;
use Pop\View\Template\Stream;

$view = new View\View(new View\Template\Stream('hello.html'));
$view->title   = 'Hello World!';
$view->content = '<p>This is a test!</p>';

echo $view;
```