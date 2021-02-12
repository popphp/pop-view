<?php

namespace Pop\View\Test;

use Pop\View\Template\Stream;
use PHPUnit\Framework\TestCase;

class StreamTest extends TestCase
{

    public function testSetTemplate()
    {
        $template = new Stream(file_get_contents(__DIR__ . '/tmp/iteration.html'));
        $this->assertStringContainsString('<title>[{title}]</title>', $template->getTemplate());
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
        $this->assertStringContainsString('<p>world</p>', $render);
        $this->assertStringContainsString('<p>123</p>', $render);
        $this->assertStringContainsString('<p>456</p>', $render);
        $this->assertStringContainsString('<p></p>', $render);
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
        $this->assertStringContainsString('<div><strong>hello</strong>: world</div>', $render);
        $this->assertStringContainsString('<div><strong>foo</strong>: bar</div>', $render);
        $this->assertStringContainsString('<div><strong>baz</strong>: 123</div>', $render);
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
        $this->assertStringContainsString('<div><strong>hello</strong>: world</div>', $render);
        $this->assertStringContainsString('<div><strong>foo</strong>: bar</div>', $render);
        $this->assertStringContainsString('<div><strong>baz</strong>: 123</div>', $render);
    }

    public function testArrays1()
    {
        $template = new Stream(__DIR__ . '/tmp/arrays1.html');
        $render = $template->render([
            'title' => 'Page Title',
            'rows'  => [
                [
                    'title'   => 'Title #1',
                    'content' => 'Content #1'
                ],
                [
                    'title'   => 'Title #2',
                    'content' => 'Content #2'
                ],
                [
                    'title'   => 'Title #3',
                    'content' => 'Content #3'
                ]
            ],
            'items' => [
                'info' => [
                    'foo' => 'bar',
                    'baz' => 123
                ]
            ],
            'other' => [
                'thing' => 1,
                'something' => 2
            ],
            'blah' => [
                'other',
                'something-else'
            ]

        ]);
        $this->assertStringContainsString('<h4>Title #1 (1)</h4>', $render);
        $this->assertStringContainsString('<p>foo : bar  (1)</p>', $render);
        $this->assertStringContainsString('<p>something-else (2)</p>', $render);
    }

    public function testArrays2()
    {
        $template = new Stream(__DIR__ . '/tmp/arrays2.html');
        $render = $template->render([
            'rows'  => [
                [
                    'foo' => 'bar'
                ],
                [
                    'baz' => 'test'
                ]
            ],
            'items' => [
                [
                    'foo' => [
                        'bar' => 'baz'
                    ]
                ]
            ]

        ]);
        $this->assertStringContainsString('<p>bar</p>', $render);
        $this->assertStringContainsString('<p>baz</p>', $render);
    }

    public function testArrays3()
    {
        $template = new Stream(__DIR__ . '/tmp/arrays3.html');
        $render = $template->render([
            'rows'  => [
                [
                    'foo' => 'bar'
                ],
                [
                    'baz' => 'test'
                ]
            ],
            'items' => [
                [
                    'foo' => [
                        'bar' => 'baz'
                    ]
                ]
            ]

        ]);
        $this->assertStringContainsString('<p>bar</p>', $render);
        $this->assertStringContainsString('<p>baz</p>', $render);
    }

    public function testConditionalSet()
    {
        $template = new Stream(__DIR__ . '/tmp/conditional.html');
        $render = $template->render(['foo' => ['bar' => 123]]);
        $this->assertStringContainsString("<p>The variable 'foo' is set to 123.</p>", $render);
    }

    public function testConditionalNotSet()
    {
        $template = new Stream(__DIR__ . '/tmp/conditional.html');
        $render = $template->render(['baz' => 123]);
        $this->assertStringNotContainsString("<p>The variable 'foo' is set to bar.</p>", $render);
    }

    public function testConditionalElseSet()
    {
        $template = new Stream(__DIR__ . '/tmp/conditional-else.html');
        $render = $template->render(['foo' => 'bar']);
        $this->assertStringContainsString("<p>The variable 'foo' is set to bar.</p>", $render);
    }

    public function testConditionalElseNotSet()
    {
        $template = new Stream(__DIR__ . '/tmp/conditional-else.html');
        $render = $template->render(['baz' => 123]);
        $this->assertStringContainsString("<p>The variable 'foo' is not set.</p>", $render);
    }

    public function testIncludes()
    {
        $template = new Stream(__DIR__ . '/tmp/index.html');
        $render = $template->render([
            'title'   => 'Hello World',
            'content' => 'This is a test'
        ]);
        $this->assertStringContainsString('<title>Hello World</title>', $render);
        $this->assertStringContainsString('<header>This is the header</header>', $render);
        $this->assertStringContainsString('<h1>Hello World</h1>', $render);
        $this->assertStringContainsString('<p>This is a test</p>', $render);
        $this->assertStringContainsString('<footer>This is the footer</footer>', $render);
    }

    public function testInheritance()
    {
        $template = new Stream(__DIR__ . '/tmp/child.html');
        $render = $template->render([
            'title'   => 'Hello World',
            'content' => 'This is a test'
        ]);
        $this->assertStringContainsString('<title>Hello World</title>', $render);
        $this->assertStringContainsString('<h1>Hello World</h1>', $render);
        $this->assertStringContainsString('<p>This is a test</p>', $render);
        $this->assertStringContainsString('body { margin: 0; padding: 0; color: #bbb;}', $render);
        $blocks = $template->getBlocks();
        $this->assertTrue(isset($blocks['header']));
        $template->setBlocks($blocks);
        $template->setMasterBlock('header', $blocks['header']);
        $this->assertTrue(isset($template->getBlocks()['header']));
    }

}