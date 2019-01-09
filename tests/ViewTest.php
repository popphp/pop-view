<?php

namespace Pop\View\Test;

use Pop\View\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{

    public function testConstructor()
    {
        $view = new View(__DIR__ . '/tmp/index.phtml', [
            'title'   => 'Hello World',
            'content' => 'This is a test.'
        ]);
        $this->assertInstanceOf('Pop\View\View', $view);
        $this->assertInstanceOf('Pop\View\Template\File', $view->getTemplate());
        $this->assertTrue($view->hasTemplate());
        $this->assertTrue($view->isFile());
        $this->assertFalse($view->isStream());
        $this->assertEquals(__DIR__ . '/tmp/index.phtml', $view->getTemplate()->getTemplate());
        $this->assertTrue(is_array($view->getData()));
        $this->assertEquals('Hello World', $view->title);
        $this->assertEquals('This is a test.', $view['content']);
    }

    public function testSetTemplate()
    {
        $view = new View(__DIR__ . '/tmp/index.html');
        $this->assertInstanceOf('Pop\View\Template\Stream', $view->getTemplate());
    }

    public function testSetData()
    {
        $view = new View();
        $view->foo   = 'bar';
        $view['baz'] = 123;
        $this->assertEquals('bar', $view->foo);
        $this->assertEquals(123, $view['baz']);
        $this->assertTrue(isset($view->foo));
        $this->assertTrue(isset($view['baz']));
        unset($view->foo);
        unset($view['baz']);
        $this->assertFalse(isset($view->foo));
        $this->assertFalse(isset($view['baz']));
    }

    public function testAddFilter()
    {
        $view = new View(null, [
            'title' => '"Hello <script>World</script>"',
        ]);
        $view->addFilter('strip_tags');
        $view->addFilter('htmlentities', ENT_QUOTES);

        $view->filter();
        $this->assertEquals('&quot;Hello World&quot;', $view->title);
    }

    public function testAddFilters()
    {
        $view = new View(null, [
            'title' => '"Hello <script>World</script>"',
        ]);
        $view->addFilter('strip_tags');
        $view->addFilters([
            [
                'call'   => 'htmlentities',
                'params' => [ENT_QUOTES, 'UTF-8']
            ]
        ]);

        $view->filter();
        $this->assertEquals('&quot;Hello World&quot;', $view->title);
    }

    public function testAddFiltersException()
    {
        $this->expectException('Pop\View\Exception');
        $view = new View();
        $view->addFilters([
            [
                'params' => [ENT_QUOTES, 'UTF-8']
            ]
        ]);
    }

    public function testClearFilters()
    {
        $view = new View(null, [
            'title' => '"Hello <script>World</script>"',
        ]);
        $view->addFilter('strip_tags');
        $view->addFilters([
            [
                'call'   => 'htmlentities',
                'params' => [ENT_QUOTES, 'UTF-8']
            ]
        ]);

        $view->clearFilters();
        $view->filter();
        $this->assertEquals('"Hello <script>World</script>"', $view->title);
    }

    public function testMerge()
    {
        $view = new View(null, [
            'title' => 'Hello World',
        ]);
        $view->merge([
            'content' => 'This is a test.'
        ]);
        $this->assertEquals('Hello World', $view->title);
        $this->assertEquals('This is a test.', $view['content']);
    }

    public function testOutput()
    {
        $view = new View(__DIR__ . '/tmp/index.phtml', [
            'title'   => 'Hello World',
            'content' => 'This is a test.'
        ]);

        ob_start();
        echo $view;
        $result = ob_get_clean();

        $string = (string)$view;

        $this->assertContains('<title>Hello World</title>', $result);
        $this->assertContains('<h1>Hello World</h1>', $result);
        $this->assertContains('<p>This is a test.</p>', $result);

        $this->assertContains('<title>Hello World</title>', $string);
        $this->assertContains('<h1>Hello World</h1>', $string);
        $this->assertContains('<p>This is a test.</p>', $string);
        $this->assertContains('<p>This is a test.</p>', $view->getOutput());
    }

    public function testRenderException()
    {
        $this->expectException('Pop\View\Exception');
        $view = new View();
        $view->render();
    }

}