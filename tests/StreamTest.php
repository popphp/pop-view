<?php

namespace Pop\View\Test;

use Pop\View\Template\Stream;

class StreamTest extends \PHPUnit_Framework_TestCase
{

    public function testSetTemplate()
    {
        $template = new Stream(file_get_contents(__DIR__ . '/tmp/iteration.html'));
        $this->assertContains('<title>[{title}]</title>', $template->getTemplate());
        $this->assertTrue($template->isString());
        $this->assertFalse($template->isFile());
    }

    public function testScalars()
    {
        $template = new Stream(__DIR__ . '/tmp/scalars.html');
        $render = $template->render($data = [
            'hello' => 'world',
            'foo'   => ['bar' => 123],
            'baz'   => 456
        ]);
        $this->assertContains('<p>world</p>', $render);
        $this->assertContains('<p>123</p>', $render);
        $this->assertContains('<p>456</p>', $render);
        $this->assertContains('<p></p>', $render);
    }

    public function testIteration()
    {
        $template = new Stream(__DIR__ . '/tmp/iteration.html');
        $render = $template->render($data = [
            'items' => [
                'hello' => 'world',
                'foo'   => 'bar',
                'baz'   => 123
            ]
        ]);
        $this->assertContains('<div><strong>hello</strong>: world</div>', $render);
        $this->assertContains('<div><strong>foo</strong>: bar</div>', $render);
        $this->assertContains('<div><strong>baz</strong>: 123</div>', $render);
    }

    public function testNestedIteration()
    {
        $template = new Stream(__DIR__ . '/tmp/iteration-nested.html');
        $render = $template->render($data = [
            'items' => [
                'pages' => [
                    'hello' => 'world',
                    'foo'   => 'bar',
                    'baz'   => 123
                ]
            ]
        ]);
        $this->assertContains('<div><strong>hello</strong>: world</div>', $render);
        $this->assertContains('<div><strong>foo</strong>: bar</div>', $render);
        $this->assertContains('<div><strong>baz</strong>: 123</div>', $render);
    }

    public function testConditionalSet()
    {
        $template = new Stream(__DIR__ . '/tmp/conditional.html');
        $render = $template->render(['foo' => ['bar' => 123]]);
        $this->assertContains("<p>The variable 'foo' is set to 123.</p>", $render);
    }

    public function testConditionalNotSet()
    {
        $template = new Stream(__DIR__ . '/tmp/conditional.html');
        $render = $template->render(['baz' => 123]);
        $this->assertNotContains("<p>The variable 'foo' is set to bar.</p>", $render);
    }

    public function testConditionalElseSet()
    {
        $template = new Stream(__DIR__ . '/tmp/conditional-else.html');
        $render = $template->render(['foo' => 'bar']);
        $this->assertContains("<p>The variable 'foo' is set to bar.</p>", $render);
    }

    public function testConditionalElseNotSet()
    {
        $template = new Stream(__DIR__ . '/tmp/conditional-else.html');
        $render = $template->render(['baz' => 123]);
        $this->assertContains("<p>The variable 'foo' is not set.</p>", $render);
    }

    public function testIncludes()
    {
        $template = new Stream(__DIR__ . '/tmp/index.html');
        $render = $template->render([
            'title'   => 'Hello World',
            'content' => 'This is a test'
        ]);
        $this->assertContains('<title>Hello World</title>', $render);
        $this->assertContains('<header>This is the header</header>', $render);
        $this->assertContains('<h1>Hello World</h1>', $render);
        $this->assertContains('<p>This is a test</p>', $render);
        $this->assertContains('<footer>This is the footer</footer>', $render);
    }

    public function testInheritance()
    {
        $template = new Stream(__DIR__ . '/tmp/child.html');
        $render = $template->render([
            'title'   => 'Hello World',
            'content' => 'This is a test'
        ]);
        $this->assertContains('<title>Hello World</title>', $render);
        $this->assertContains('<h1>Hello World</h1>', $render);
        $this->assertContains('<p>This is a test</p>', $render);
        $this->assertContains('body { margin: 0; padding: 0; color: #bbb;}', $render);
        $blocks = $template->getBlocks();
        $this->assertTrue(isset($blocks['header']));
        $template->setBlocks($blocks);
        $template->setMasterBlock('header', $blocks['header']);
        $this->assertTrue(isset($template->getBlocks()['header']));
    }

}