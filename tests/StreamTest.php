<?php

namespace Pop\View\Test;

use Pop\View\Template\Stream;
use Pop\View\Template\Stream\Cache;
use PHPUnit\Framework\TestCase;

class StreamTest extends TestCase
{

    protected string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pop-view-stream-cache-' . uniqid();
        mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDir . DIRECTORY_SEPARATOR . '*') as $file) {
            unlink($file);
        }
        rmdir($this->cacheDir);
    }

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

    public function testConditionalElseSetWithArrayIndex()
    {
        $template = new Stream(__DIR__ . '/tmp/conditional-else-index.html');
        $render = $template->render(['foo' => ['bar' => 'baz']]);
        $this->assertStringContainsString("<p>The variable 'foo.bar' is set to baz.</p>", $render);
    }

    public function testConditionalElseNotSetWithArrayIndex()
    {
        $template = new Stream(__DIR__ . '/tmp/conditional-else-index.html');
        $render = $template->render(['other' => 123]);
        $this->assertStringContainsString("<p>The variable 'foo.bar' is not set.</p>", $render);
    }

    public function testConditionalPlainSetWithoutElse()
    {
        $template = new Stream(__DIR__ . '/tmp/conditional-plain.html');
        $render = $template->render(['foo' => 'bar']);
        $this->assertStringContainsString("<p>The variable 'foo' is set to bar.</p>", $render);
    }

    public function testConditionalPlainNotSetWithoutElse()
    {
        $template = new Stream(__DIR__ . '/tmp/conditional-plain.html');
        $render = $template->render(['other' => 123]);
        $this->assertStringNotContainsString("<p>The variable 'foo' is set to", $render);
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

    public function testIncludeWithConditionalSet()
    {
        $template = new Stream(__DIR__ . '/tmp/include-conditional.html');
        $render = $template->render(['foo' => 'bar']);
        $this->assertStringContainsString('<p>Foo is bar</p>', $render);
    }

    public function testIncludeWithConditionalNotSet()
    {
        $template = new Stream(__DIR__ . '/tmp/include-conditional.html');
        $render = $template->render([]);
        $this->assertStringContainsString('<p>No foo</p>', $render);
    }

    public function testIncludeWithParentTraversalThrows()
    {
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        new Stream(__DIR__ . '/tmp/traversal-include.html');
    }

    public function testExtendsWithParentTraversalThrows()
    {
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        new Stream(__DIR__ . '/tmp/traversal-extends.html');
    }

    public function testIncludeWithAbsolutePathThrows()
    {
        $secret = tempnam(sys_get_temp_dir(), 'pop-view-secret-');
        file_put_contents($secret, 'SECRET');

        try {
            $this->expectException(\Pop\View\Template\Stream\Exception::class);
            new Stream('{{@include ' . $secret . '}}');
        } finally {
            unlink($secret);
        }
    }

    public function testExtendsWithAbsolutePathThrows()
    {
        $secret = tempnam(sys_get_temp_dir(), 'pop-view-secret-');
        file_put_contents($secret, 'SECRET');

        try {
            $this->expectException(\Pop\View\Template\Stream\Exception::class);
            new Stream('{{@extends ' . $secret . '}}');
        } finally {
            unlink($secret);
        }
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

    public function testMultiBlockInheritance()
    {
        $template = new Stream(__DIR__ . '/tmp/child-multi.html');
        $render = $template->render([
            'title'   => 'Hello World',
            'content' => 'This is a test'
        ]);
        $this->assertStringContainsString('<title>Hello World</title>', $render);
        $this->assertStringContainsString('<meta name="child" content="1" />', $render);
        $this->assertStringContainsString('<aside>Child sidebar</aside>', $render);
        $this->assertStringNotContainsString('<aside>Default sidebar</aside>', $render);
    }

    public function testHasCacheDirDefaultsFalse()
    {
        $template = new Stream(__DIR__ . '/tmp/scalars.html');
        $this->assertFalse($template->hasCacheDir());
        $this->assertNull($template->getCacheDir());
    }

    public function testSetCacheDir()
    {
        $template = new Stream(__DIR__ . '/tmp/scalars.html');
        $template->setCacheDir($this->cacheDir);
        $this->assertTrue($template->hasCacheDir());
        $this->assertEquals($this->cacheDir, $template->getCacheDir());
    }

    public function testCachedRenderMatchesUncachedForScalars()
    {
        $data = ['hello' => 'world', 'foo' => ['bar' => 123], 'baz' => 456];

        $uncached = new Stream(__DIR__ . '/tmp/scalars.html');
        $cached   = new Stream(__DIR__ . '/tmp/scalars.html', $this->cacheDir);

        $this->assertSame($uncached->render($data), $cached->render($data));
    }

    public function testCachedRenderMatchesUncachedForIncidentalBracketShapedText()
    {
        // Deliberate Phase-1 scope narrowing (human-approved): purely incidental bracket-shaped prose
        // that was never meant as a placeholder (unsupported characters like '%', '.', or a stray
        // '[{/if}]' fragment) must render identically cached vs. uncached - both leaving it as literal
        // text unchanged - rather than the cached path hard-failing on it.
        $data = ['hello' => 'world'];

        $uncached = new Stream(__DIR__ . '/tmp/incidental-brackets.html');
        $cached   = new Stream(__DIR__ . '/tmp/incidental-brackets.html', $this->cacheDir);

        $uncachedOutput = $uncached->render($data);
        $cachedOutput   = $cached->render($data);

        $this->assertSame($uncachedOutput, $cachedOutput);
        $this->assertStringContainsString('[{10% off}]', $uncachedOutput);
        $this->assertStringContainsString('[{no.reply}]', $uncachedOutput);
        $this->assertStringContainsString('[{/if}]', $uncachedOutput);
    }

    public function testCachedRenderMatchesUncachedForConditionalElse()
    {
        $uncached = new Stream(__DIR__ . '/tmp/conditional-else.html');
        $cached   = new Stream(__DIR__ . '/tmp/conditional-else.html', $this->cacheDir);

        $this->assertSame($uncached->render(['foo' => 'bar']), $cached->render(['foo' => 'bar']));
        $this->assertSame($uncached->render(['baz' => 123]), $cached->render(['baz' => 123]));
    }

    public function testCachedRenderMatchesUncachedForIncludesAndInheritance()
    {
        $data = ['title' => 'Hello World', 'content' => 'This is a test'];

        $uncachedIndex = new Stream(__DIR__ . '/tmp/index.html');
        $cachedIndex   = new Stream(__DIR__ . '/tmp/index.html', $this->cacheDir);
        $this->assertSame($uncachedIndex->render($data), $cachedIndex->render($data));

        $uncachedChild = new Stream(__DIR__ . '/tmp/child.html');
        $cachedChild   = new Stream(__DIR__ . '/tmp/child.html', $this->cacheDir);
        $this->assertSame($uncachedChild->render($data), $cachedChild->render($data));
    }

    public function testCachedRenderMatchesUncachedForConditionalWithoutElse()
    {
        $uncached = new Stream(__DIR__ . '/tmp/conditional.html');
        $cached   = new Stream(__DIR__ . '/tmp/conditional.html', $this->cacheDir);

        $this->assertSame($uncached->render(['foo' => ['bar' => 123]]), $cached->render(['foo' => ['bar' => 123]]));
        $this->assertSame($uncached->render(['baz' => 123]), $cached->render(['baz' => 123]));
    }

    public function testCachedRenderMatchesUncachedForConditionalElseWithArrayIndex()
    {
        $uncached = new Stream(__DIR__ . '/tmp/conditional-else-index.html');
        $cached   = new Stream(__DIR__ . '/tmp/conditional-else-index.html', $this->cacheDir);

        $this->assertSame($uncached->render(['foo' => ['bar' => 'baz']]), $cached->render(['foo' => ['bar' => 'baz']]));
        $this->assertSame($uncached->render(['other' => 123]), $cached->render(['other' => 123]));
    }

    public function testCachedRenderMatchesUncachedForConditionalPlain()
    {
        $uncached = new Stream(__DIR__ . '/tmp/conditional-plain.html');
        $cached   = new Stream(__DIR__ . '/tmp/conditional-plain.html', $this->cacheDir);

        $this->assertSame($uncached->render(['foo' => 'bar']), $cached->render(['foo' => 'bar']));
        $this->assertSame($uncached->render(['other' => 123]), $cached->render(['other' => 123]));
    }

    public function testCachedRenderMatchesUncachedForMultiBlockInheritance()
    {
        $data = ['title' => 'Hello World', 'content' => 'This is a test'];

        $uncached = new Stream(__DIR__ . '/tmp/child-multi.html');
        $cached   = new Stream(__DIR__ . '/tmp/child-multi.html', $this->cacheDir);

        $this->assertSame($uncached->render($data), $cached->render($data));
    }

    public function testCachedRenderMatchesUncachedForIncludeConditional()
    {
        $uncached = new Stream(__DIR__ . '/tmp/include-conditional.html');
        $cached   = new Stream(__DIR__ . '/tmp/include-conditional.html', $this->cacheDir);

        $this->assertSame($uncached->render(['foo' => 'bar']), $cached->render(['foo' => 'bar']));
        $this->assertSame($uncached->render([]), $cached->render([]));
    }

    public function testCacheDirRendersWriteACompiledFile()
    {
        $template = new Stream(__DIR__ . '/tmp/scalars.html', $this->cacheDir);
        $template->render(['hello' => 'world', 'foo' => ['bar' => 1], 'baz' => 2]);

        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.php');
        $this->assertCount(1, $files);
    }

    public function testCacheHitServesCachedFileContentInsteadOfRecompiling()
    {
        $data = ['hello' => 'world', 'foo' => ['bar' => 1], 'baz' => 2];

        $template = new Stream(__DIR__ . '/tmp/scalars.html', $this->cacheDir);
        $template->render($data);

        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.php');
        $this->assertCount(1, $files);
        $cacheFile = $files[0];

        // Tamper with the cached compiled file directly, bypassing Compiler entirely. The source
        // template's mtime is left untouched, so this doesn't trigger cache invalidation - a correct
        // implementation must serve this tampered content back on the next render rather than silently
        // recompiling and overwriting it.
        file_put_contents($cacheFile, "<?php echo 'TAMPERED-CACHE-HIT'; ?>");

        $template2 = new Stream(__DIR__ . '/tmp/scalars.html', $this->cacheDir);
        $render    = $template2->render($data);

        $this->assertSame('TAMPERED-CACHE-HIT', $render);
    }

    public function testCachedRenderMatchesUncachedForArrays1()
    {
        $data = [
            'title' => 'Page Title',
            'rows'  => [
                ['title' => 'Title #1', 'content' => 'Content #1'],
                ['title' => 'Title #2', 'content' => 'Content #2'],
                ['title' => 'Title #3', 'content' => 'Content #3'],
            ],
            'items' => ['info' => ['foo' => 'bar', 'baz' => 123]],
            'other' => ['thing' => 1, 'something' => 2],
            'blah'  => ['other', 'something-else'],
        ];

        $uncached = new Stream(__DIR__ . '/tmp/arrays1.html');
        $cached   = new Stream(__DIR__ . '/tmp/arrays1.html', $this->cacheDir);

        $this->assertSame($uncached->render($data), $cached->render($data));
    }

    public function testRenderCompiledExceptionDoesNotLeakOutputBuffer()
    {
        // Final-review Finding 2 regression: Stream::renderCompiled()'s catch block previously called
        // ob_clean() instead of ob_end_clean(), which empties the output buffer's contents but leaves it
        // on PHP's output-buffer stack. Phase 1's compiled output could never throw at runtime, so this
        // was dead code; Phase 2's ArrayAccess/ArrayObject guards inside compiled loops are the first
        // thing that can throw here, making the leak reachable for the first time. This test goes
        // through Stream's public API (not Compiler's own render() helper, which has its own separate
        // buffer handling and couldn't have caught an integration-level leak like this) to prove a
        // caught exception from a compiled render doesn't leave a dangling output buffer behind.
        $levelBefore = ob_get_level();

        $template = new Stream(__DIR__ . '/tmp/iteration.html', $this->cacheDir);

        try {
            $template->render(['title' => 'T', 'items' => new \ArrayObject(['a', 'b'])]);
            $this->fail('Expected a Stream\\Exception to be thrown.');
        } catch (\Pop\View\Template\Stream\Exception $e) {
            // expected
        }

        $this->assertSame($levelBefore, ob_get_level());
    }

    public function testCachedRenderMatchesUncachedForScalarListLoop()
    {
        $data = [
            'title' => 'Hello World',
            'items' => ['hello' => 'world', 'foo' => 'bar', 'baz' => 123],
        ];

        $uncached = new Stream(__DIR__ . '/tmp/iteration.html');
        $cached   = new Stream(__DIR__ . '/tmp/iteration.html', $this->cacheDir);

        $this->assertSame($uncached->render($data), $cached->render($data));
    }

    public function testCachedRenderMatchesUncachedForNamedFieldRowLoop()
    {
        // Regression test: a named-field row loop with a non-string field value (an integer id being
        // the common case) used to throw a TypeError from Parser::parseArrays() under strict_types=1,
        // since that one field-substitution call site passed the raw field value into str_replace()'s
        // $replace parameter without a string cast.
        $data = [
            'title'    => 'Page Title',
            'sitename' => 'My Site',
            'rows'     => [
                ['id' => 1, 'title' => 'Title #1', 'content' => 'Content #1'],
                ['id' => 2, 'title' => 'Title #2', 'content' => 'Content #2'],
                ['id' => 3, 'title' => 'Title #3', 'content' => 'Content #3'],
            ],
        ];

        $uncached = new Stream(__DIR__ . '/tmp/rows-simple.html');
        $cached   = new Stream(__DIR__ . '/tmp/rows-simple.html', $this->cacheDir);

        $uncachedRender = $uncached->render($data);

        $this->assertStringContainsString('ID: 1', $uncachedRender);
        $this->assertStringContainsString('ID: 2', $uncachedRender);
        $this->assertStringContainsString('ID: 3', $uncachedRender);
        $this->assertSame($uncachedRender, $cached->render($data));
    }

    public function testCachedRenderMatchesUncachedForArrays2()
    {
        $data = [
            'rows'  => [['foo' => 'bar'], ['baz' => 'test']],
            'items' => [['foo' => ['bar' => 'baz']]],
        ];

        $uncached = new Stream(__DIR__ . '/tmp/arrays2.html');
        $cached   = new Stream(__DIR__ . '/tmp/arrays2.html', $this->cacheDir);

        $this->assertSame($uncached->render($data), $cached->render($data));
    }

    public function testCachedRenderMatchesUncachedForNestedIteration()
    {
        $data = ['items' => ['pages' => ['hello' => 'world', 'foo' => 'bar', 'baz' => 123]]];

        $uncached = new Stream(__DIR__ . '/tmp/iteration-nested.html');
        $cached   = new Stream(__DIR__ . '/tmp/iteration-nested.html', $this->cacheDir);

        $this->assertSame($uncached->render($data), $cached->render($data));
    }

    public function testCachedRenderMatchesUncachedForArrays3()
    {
        $data = [
            'rows'  => [['foo' => 'bar'], ['baz' => 'test']],
            'items' => [['foo' => ['bar' => 'baz']]],
        ];

        $uncached = new Stream(__DIR__ . '/tmp/arrays3.html');
        $cached   = new Stream(__DIR__ . '/tmp/arrays3.html', $this->cacheDir);

        $this->assertSame($uncached->render($data), $cached->render($data));
    }

    public function testCachedRenderMatchesUncachedForLoopIndexScalar()
    {
        $data = ['items' => ['a' => 1, 'b' => 2], 'foo' => ['bar' => 'BAZ']];

        $uncached = new Stream(__DIR__ . '/tmp/loop-index-scalar.html');
        $cached   = new Stream(__DIR__ . '/tmp/loop-index-scalar.html', $this->cacheDir);

        $this->assertSame($uncached->render($data), $cached->render($data));
    }

    public function testCachedRenderOfStringBackedTemplate()
    {
        // No file behind this template at all — Cache::key() must still produce a stable,
        // usable key from the raw string content, with no mtime-based invalidation involved
        // (contributingFiles stays empty for a purely string-backed Stream).
        $raw = '<p>[{hello}]</p>';

        $uncached = new Stream($raw);
        $cached   = new Stream($raw, $this->cacheDir);

        $this->assertSame([], $cached->getContributingFiles());
        $this->assertSame($uncached->render(['hello' => 'world']), $cached->render(['hello' => 'world']));

        // Second render reuses the cached file rather than recompiling.
        $filesAfterFirst = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.php');
        $cached->render(['hello' => 'world again']);
        $filesAfterSecond = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.php');
        $this->assertSame($filesAfterFirst, $filesAfterSecond);
    }

    public function testCacheKeyChangesWhenSourceContentChanges()
    {
        // Cache::key() hashes the fully resolved template content, so a genuine content change
        // produces a brand-new cache key (a cache miss on a new entry) on its own - this proves
        // correctly-updated output is served, not that any particular invalidation mechanism ran.
        $file = $this->cacheDir . DIRECTORY_SEPARATOR . 'source.html';
        file_put_contents($file, '<p>[{hello}]</p>');

        $template = new Stream($file, $this->cacheDir);
        $this->assertSame('<p>world</p>', $template->render(['hello' => 'world']));

        file_put_contents($file, '<p>updated: [{hello}]</p>');

        $template2 = new Stream($file, $this->cacheDir);
        $this->assertSame('<p>updated: world</p>', $template2->render(['hello' => 'world']));
    }

    public function testMtimeTouchTriggersRedundantRecompileWithoutBreakingOutput()
    {
        // Because Cache's key is a hash of the fully resolved template content, a content change
        // already produces a new key on its own - there is no scenario where the mtime staleness
        // check is what prevents a stale render from being served. Its only real effect is
        // triggering a redundant recompile (of byte-identical output) when a contributing file's
        // mtime advances without its content changing (e.g. touch()). This test proves that when
        // the mtime check does trigger such a redundant recompile, it doesn't crash or corrupt
        // output - see the "Cache invalidation" note in
        // docs/superpowers/specs/2026-08-08-stream-template-compiler-design.md.
        $file = $this->cacheDir . DIRECTORY_SEPARATOR . 'source.html';
        file_put_contents($file, '<p>[{hello}]</p>');

        $template = new Stream($file, $this->cacheDir);
        $this->assertSame('<p>world</p>', $template->render(['hello' => 'world']));

        $filesAfterFirst = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.php');

        // Advance the file's mtime without changing its content - the content hash (and thus the
        // cache key) stays identical, but Cache::get()'s staleness check will see the cached file's
        // mtime as older than this new source mtime and force a recompile.
        touch($file, time() + 60);

        $template2 = new Stream($file, $this->cacheDir);
        $this->assertSame('<p>world</p>', $template2->render(['hello' => 'world']));

        // Same key (content-addressed), so still exactly one cache file - just rewritten in place.
        $filesAfterSecond = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.php');
        $this->assertSame($filesAfterFirst, $filesAfterSecond);
    }

}