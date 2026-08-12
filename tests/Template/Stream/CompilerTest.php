<?php

namespace Pop\View\Test\Template\Stream;

use Pop\View\Template\Stream\Compiler;
use PHPUnit\Framework\TestCase;

class CompilerTest extends TestCase
{
    /**
     * Compile $template, include it with $data in scope, and return the rendered output.
     */
    protected function render(string $template, array $data): string
    {
        $php  = Compiler::compile($template);
        $file = tempnam(sys_get_temp_dir(), 'pop-view-compiled-');
        file_put_contents($file, $php);

        ob_start();
        try {
            include $file;
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            unlink($file);
            throw $e;
        }

        unlink($file);

        return $output;
    }

    public function testPlainScalarIsSubstituted()
    {
        $output = $this->render('<p>[{hello}]</p>', ['hello' => 'world']);
        $this->assertSame('<p>world</p>', $output);
    }

    public function testMissingScalarKeyLeavesPlaceholderLiteral()
    {
        $output = $this->render('<p>[{missing}]</p>', ['hello' => 'world']);
        $this->assertSame('<p>[{missing}]</p>', $output);
    }

    public function testArrayIndexScalarIsSubstituted()
    {
        $output = $this->render('<p>[{foo[bar]}]</p>', ['foo' => ['bar' => 123]]);
        $this->assertSame('<p>123</p>', $output);
    }

    public function testArrayIndexScalarMissingIndexIsEmpty()
    {
        $output = $this->render('<p>[{foo[baz]}]</p>', ['foo' => ['bar' => 123]]);
        $this->assertSame('<p></p>', $output);
    }

    public function testLiteralTextWithQuotesAndBackslashesIsPreserved()
    {
        $output = $this->render(
            "<p>It's a \"test\" with a \\ backslash and [{hello}]</p>",
            ['hello' => 'world']
        );
        $this->assertSame(
            "<p>It's a \"test\" with a \\ backslash and world</p>",
            $output
        );
    }

    public function testNonStringableObjectLeavesPlaceholderLiteral()
    {
        $output = $this->render('<p>[{obj}]</p>', ['obj' => new \stdClass()]);
        $this->assertSame('<p>[{obj}]</p>', $output);
    }

    public function testConditionalSetRendersThenBranch()
    {
        $output = $this->render(
            "[{if(foo)}]<p>foo is [{foo}]</p>[{/if}]",
            ['foo' => 'bar']
        );
        $this->assertSame('<p>foo is bar</p>', $output);
    }

    public function testConditionalNotSetRendersNothing()
    {
        $output = $this->render(
            "[{if(foo)}]<p>foo is [{foo}]</p>[{/if}]",
            []
        );
        $this->assertSame('', $output);
    }

    public function testConditionalElseSetRendersThenBranch()
    {
        $output = $this->render(
            "[{if(foo)}]<p>set</p>[{else}]<p>not set</p>[{/if}]",
            ['foo' => 'bar']
        );
        $this->assertSame('<p>set</p>', $output);
    }

    public function testConditionalElseNotSetRendersElseBranch()
    {
        $output = $this->render(
            "[{if(foo)}]<p>set</p>[{else}]<p>not set</p>[{/if}]",
            []
        );
        $this->assertSame('<p>not set</p>', $output);
    }

    public function testConditionalArrayIndexSetRendersThenBranch()
    {
        $output = $this->render(
            "[{if(foo[bar])}]<p>[{foo[bar]}]</p>[{/if}]",
            ['foo' => ['bar' => 123]]
        );
        $this->assertSame('<p>123</p>', $output);
    }

    public function testComplexLoopWithNestedSubLoopNowCompiles()
    {
        // Phase 3: nested sub-loops now compile successfully.
        // This test verifies that a nested sub-loop compiles without throwing.
        $output = $this->render(
            '[{rows}]<span>[{title}]</span>[{nested}]<p>[{value}]</p>[{/nested}][{/rows}]',
            ['rows' => [
                0 => ['title' => 'First Row'],
                'nested' => ['a', 'b']
            ]]
        );
        $this->assertStringContainsString('First Row', $output);
        $this->assertStringContainsString('<p>a</p>', $output);
        $this->assertStringContainsString('<p>b</p>', $output);
    }

    public function testQuoteInjectionInIfConditionThrows()
    {
        // Critical security regression: a single quote in an if-condition variable name must never be
        // allowed to reach the generated PHP source (that's exactly the earlier injection vulnerability
        // this gate fixes). Input: [{if(a'x)}] would previously generate !empty($data['a'x']), invalid/
        // dangerous PHP. The gate correctly rejects it - and per the final-review fix, a rejected name
        // now throws instead of silently falling back to literal text (which would silently diverge from
        // Parser for any template+data pair where such a key actually exists).
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        $this->render(
            "<p>[{if(a'x)}]body[{/if}]</p>",
            ['a' => 'value']
        );
    }

    public function testMalformedIfWithoutClosingTagThrows()
    {
        // Regression: unclosed [{if(...)}] (no matching [{/if}]) must throw rather than silently
        // fall back to literal text - a silent fallback here previously meant one bad conditional could
        // disable compilation of everything after it in the same template.
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        $this->render(
            "<p>[{if(foo)}]bar</p>",
            []
        );
    }

    public function testDashedPlaceholderNameLeftLiteral()
    {
        // Deliberate Phase-1 scope narrowing (human-approved, see Compiler::compileScalars() docblock):
        // Compiler cannot safely lower a dashed name into a $data['...'] PHP expression under its narrow
        // [a-zA-Z0-9_] gate. Rather than throw (which would hard-fail on purely incidental bracket-shaped
        // template text that was never meant as a placeholder at all), it silently leaves the span as
        // literal text - matching Parser::parseScalars()'s real behavior for a '[{...}]' span that
        // doesn't correspond to a key actually present in $data. The known, accepted gap: if $data did
        // contain a 'foo-bar' key, the uncached path would substitute it while this stays literal.
        $output = $this->render('<p>[{foo-bar}]</p>', []);
        $this->assertSame('<p>[{foo-bar}]</p>', $output);
    }

    public function testDottedPlaceholderNameLeftLiteral()
    {
        $output = $this->render('<p>[{foo.bar}]</p>', []);
        $this->assertSame('<p>[{foo.bar}]</p>', $output);
    }

    public function testIncidentalBracketShapedTextRendersUnchanged()
    {
        // Regression guard for the Phase-1 scope narrowing: purely incidental bracket-shaped prose that
        // was never meant as a placeholder (contains a space/percent sign - well outside [a-zA-Z0-9_])
        // must render completely unchanged, exactly like the uncached Parser path, even with unrelated
        // data present.
        $template = '<p>Save [{10% off}] today</p>';
        $output   = $this->render($template, ['unrelated' => 'value']);
        $this->assertSame($template, $output);
    }

    public function testStrayClosingIfFragmentRendersUnchanged()
    {
        // A mismatched/stray '[{/if}]' fragment with no opening '[{if(...)}]' is incidental bracket-
        // shaped text as far as compileScalars() is concerned (compileConditionals() never matches it,
        // since there's no '[{if(' to anchor a block) and must render unchanged rather than throw.
        $template = '<p>oops [{/if}] leftover</p>';
        $output   = $this->render($template, []);
        $this->assertSame($template, $output);
    }

    public function testCaseInsensitiveIfTagCompilesCorrectly()
    {
        // Finding 1 regression: Compiler previously matched '[{if(' case-sensitively via strpos(),
        // while Parser::parseConditionals() matches case-insensitively via '/\[{if/mi'. An uppercase
        // (or mixed-case) '[{IF(...)}]' tag must be recognized and compiled the same as lowercase.
        $output = $this->render(
            "[{IF(foo)}]<p>set</p>[{else}]<p>not set</p>[{/if}]",
            ['foo' => 'bar']
        );
        $this->assertSame('<p>set</p>', $output);
    }

    public function testCaseInsensitiveIfTagWithInvalidNameThrows()
    {
        // Combines both parts of Finding 1: with case-sensitive detection, '[{IF(...)}]' was
        // previously invisible to compileConditionals() entirely and fell through to compileScalars()
        // as inert literal text (silently wrong vs. Parser). Once recognized case-insensitively, an
        // invalid variable name inside it must still be rejected loudly, not silently re-emitted as
        // literal text.
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        Compiler::compile('[{IF(foo-bar)}]x[{/if}]');
    }

    public function testLiteralPhpTagsInTemplateAreNotExecuted()
    {
        // Security: literal opening tag in template should be output as literal text, not executed as PHP.
        $template = '<p>Code: ' . implode('', ['<', '?', 'php', ' ', 'test', '?', '>']) . ' is literal</p>';
        $output = $this->render($template, []);
        $this->assertSame($template, $output);
    }

    public function testNonStringableObjectInIfConditionWithPlaceholder()
    {
        // Parser behavioral note: this tests a divergence from Parser::parseConditionals().
        // Parser would attempt raw str_replace() of a non-stringable object, potentially causing errors.
        // Compiler applies stringability guards and leaves the placeholder instead.
        // This is more robust and won't crash the rendering, though it technically diverges from Parser behavior.
        $output = $this->render(
            "[{if(obj)}][{obj}][{/if}]",
            ['obj' => new \stdClass()]
        );
        // With Compiler's stringability guard, the non-stringable object fails the condition and placeholder is left.
        $this->assertSame('[{obj}]', $output);
    }

    public function testScalarListLoopSubstitutesKeyValueAndI()
    {
        $output = $this->render(
            '[{items}]<p>[{key}]:[{value}] ([{i}])</p>[{/items}]',
            ['items' => ['hello' => 'world', 'foo' => 'bar']]
        );
        $this->assertSame(
            '<p>hello:world (1)</p>' . PHP_EOL . '<p>foo:bar (2)</p>',
            $output
        );
    }

    public function testScalarListLoopWithNoDataRendersNothing()
    {
        $output = $this->render('[{items}]<p>[{key}]:[{value}]</p>[{/items}]', ['items' => []]);
        $this->assertSame('', $output);
    }

    public function testNamedFieldRowLoopSubstitutesRowFieldsAndI()
    {
        $output = $this->render(
            '[{rows}]<h4>[{title}] ([{i}])</h4><p>[{content}]</p>[{/rows}]',
            ['rows' => [
                ['title' => 'Title #1', 'content' => 'Content #1'],
                ['title' => 'Title #2', 'content' => 'Content #2'],
            ]]
        );
        $this->assertSame(
            '<h4>Title #1 (1)</h4><p>Content #1</p>' . PHP_EOL . '<h4>Title #2 (2)</h4><p>Content #2</p>',
            $output
        );
    }

    public function testNamedFieldRowFallsBackToOuterScopeWhenFieldMissingFromRow()
    {
        $output = $this->render(
            '[{rows}]<p>[{title}] - [{sitename}]</p>[{/rows}]',
            ['sitename' => 'My Site', 'rows' => [['title' => 'Row Title']]]
        );
        $this->assertSame('<p>Row Title - My Site</p>', $output);
    }

    public function testNamedFieldRowScopeShadowsOuterScopeForSameName()
    {
        $output = $this->render(
            '[{rows}]<p>[{title}]</p>[{/rows}]',
            ['title' => 'Outer Title', 'rows' => [['title' => 'Row Title']]]
        );
        $this->assertSame('<p>Row Title</p>', $output);
    }

    public function testArrayObjectOuterCollectionThrows()
    {
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        $this->render('[{items}]<p>[{value}]</p>[{/items}]', ['items' => new \ArrayObject(['a', 'b'])]);
    }

    public function testArrayObjectRowThrows()
    {
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        $this->render(
            '[{items}]<p>[{foo}]</p>[{/items}]',
            ['items' => [new \ArrayObject(['foo' => 'bar'])]]
        );
    }

    public function testMissingLoopKeyRendersNothing()
    {
        $output = $this->render('before[{items}]<p>[{value}]</p>[{/items}]after', ['other' => 'x']);
        $this->assertSame('beforeafter', $output);
    }

    public function testScalarLoopKeyRendersNothing()
    {
        $output = $this->render('before[{items}]<p>[{value}]</p>[{/items}]after', ['items' => 'not an array']);
        $this->assertSame('beforeafter', $output);
    }

    public function testNestedSubLoopRendersKeyValueI()
    {
        $output = $this->render(
            '[{items}][{pages}]<div>[{key}]: [{value}] ([{i}])</div>[{/pages}][{/items}]',
            ['items' => ['pages' => ['hello' => 'world', 'foo' => 'bar']]]
        );
        $this->assertSame('<div>hello: world (1)</div><div>foo: bar (2)</div>', $output);
    }

    public function testNestedSubLoopNonStringableEntrySkipped()
    {
        $output = $this->render(
            '[{items}][{pages}]<span>[{value}]</span>[{/pages}][{/items}]',
            ['items' => ['pages' => ['a' => 'x', 'b' => new \stdClass(), 'c' => 'y']]]
        );
        $this->assertSame('<span>x</span><span>y</span>', $output);
    }

    public function testNestedSubLoopNoMatchingKeyRendersNothing()
    {
        // When a row's key doesn't match ANY declared sub-loop tag, the entire row renders nothing
        // (matching Parser's behavior — no tags, no surrounding text).
        $output = $this->render(
            '[{items}][{pages}]<span>[{value}]</span>[{/pages}][{/items}]',
            ['items' => ['sections' => ['x', 'y']]]
        );
        $this->assertSame('', $output);
    }

    public function testNonMatchingSubLoopTagRendersAsLiteralText()
    {
        // Critical parity regression test: when a loop body declares multiple [{tag}]...[{/tag}] pairs
        // and a row's key matches only one of them, the non-matching tags must render as literal text,
        // not be silently dropped. Parser leaves non-matching declared tags untouched; compiled must too.
        $output = $this->render(
            '[{items}][{pages}]<span>[{value}]</span>[{/pages}][{sections}]<div>[{value}]</div>[{/sections}][{/items}]',
            ['items' => ['sections' => ['x', 'y']]]
        );
        $this->assertSame(
            '[{pages}]<span>[{value}]</span>[{/pages}]<div>x</div><div>y</div>',
            $output
        );
    }

    public function testNoMatchingSubLoopTagRendersNothingIncludingSurroundingText()
    {
        // Critical parity regression test for scenario 3: when a row's key matches no declared tags,
        // the entire row renders nothing — not even surrounding text that appears around the tags.
        // This matches Parser's behavior where a non-matching row's str_replace() never fires,
        // leaving $outputLoop unchanged (and thus the row contributes zero characters total).
        $output = $this->render(
            '[{items}]Site: [{sitename}] - [{pages}]<span>[{value}]</span>[{/pages}][{sections}]<div>[{value}]</div>[{/sections}] End[{/items}]',
            ['sitename' => 'MySite', 'items' => ['zzz' => ['x', 'y']]]
        );
        $this->assertSame('', $output);
    }

    public function testInvalidNestedSubLoopNameThrows()
    {
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        Compiler::compile('[{items}][{foo-bar}]<p>x</p>[{/foo-bar}][{/items}]');
    }

    public function testOuterScopeTextAroundNestedSubLoopUsesOuterScopeOnly()
    {
        $output = $this->render(
            '[{items}]Site: [{sitename}] - [{pages}]<span>[{value}]</span>[{/pages}][{/items}]',
            ['sitename' => 'MySite', 'items' => ['pages' => ['a', 'b']]]
        );
        $this->assertSame('Site: MySite - <span>a</span><span>b</span>', $output);
    }

    public function testMultipleRowsWithNestedSubLoopDoesNotOverwritePriorRows()
    {
        // Regression test for design decision #1 (Phase 3 spec): Parser's own code reassigns rather
        // than accumulates $outputLoop for a nested-sub-loop row, silently dropping earlier rows'
        // output in a multi-row array. The compiled path can't reproduce this (real control flow, not
        // string reassignment) - assert every row's distinctive content survives together.
        $output = $this->render(
            '[{items}][{pages}]<em>[{value}]</em>[{/pages}][{title}][{/items}]',
            ['items' => [
                0       => ['title' => 'FIRST'],
                'pages' => ['a', 'b'],
                1       => ['title' => 'SECOND'],
            ]]
        );
        $this->assertStringContainsString('FIRST', $output);
        $this->assertStringContainsString('<em>a</em>', $output);
        $this->assertStringContainsString('<em>b</em>', $output);
        $this->assertStringContainsString('SECOND', $output);
    }

    public function testInLoopConditionalSetSubstitutesOwnValue()
    {
        $output = $this->render(
            '[{rows}][{if(foo)}]<p>[{foo}]</p>[{/if}][{/rows}]',
            ['rows' => [['foo' => 'bar']]]
        );
        $this->assertSame('<p>bar</p>', $output);
    }

    public function testInLoopConditionalNotSetRendersNothing()
    {
        $output = $this->render(
            '[{rows}][{if(foo)}]<p>[{foo}]</p>[{/if}][{/rows}]',
            ['rows' => [['baz' => 'test']]]
        );
        $this->assertSame('', $output);
    }

    public function testInLoopConditionalElseSetRendersThenBranch()
    {
        $output = $this->render(
            '[{rows}][{if(foo)}]<p>set: [{foo}]</p>[{else}]<p>not set</p>[{/if}][{/rows}]',
            ['rows' => [['foo' => 'bar']]]
        );
        $this->assertSame('<p>set: bar</p>', $output);
    }

    public function testInLoopConditionalElseNotSetRendersElseBranch()
    {
        $output = $this->render(
            '[{rows}][{if(foo)}]<p>set</p>[{else}]<p>not set</p>[{/if}][{/rows}]',
            ['rows' => [['baz' => 'test']]]
        );
        $this->assertSame('<p>not set</p>', $output);
    }

    public function testInLoopConditionalArrayIndexForm()
    {
        $output = $this->render(
            '[{items}][{if(foo[bar])}]<p>[{foo[bar]}]</p>[{/if}][{/items}]',
            ['items' => [['foo' => ['bar' => 'baz']]]]
        );
        $this->assertSame('<p>baz</p>', $output);
    }

    public function testInLoopConditionalOtherRowFieldsStillSubstitute()
    {
        $output = $this->render(
            '[{rows}][{if(foo)}]<p>[{foo}] - [{other}]</p>[{/if}][{/rows}]',
            ['rows' => [['foo' => 'bar', 'other' => 'field']]]
        );
        $this->assertSame('<p>bar - field</p>', $output);
    }

    public function testInLoopConditionalNoOuterScopeFallback()
    {
        // Purely row-scoped: an outer $data value of the same name must NOT make the condition "set"
        // for a row that doesn't have that field.
        $output = $this->render(
            '[{rows}][{if(foo)}]<p>set</p>[{/if}][{/rows}]',
            ['foo' => 'outer value', 'rows' => [['baz' => 'test']]]
        );
        $this->assertSame('', $output);
    }

    public function testInLoopConditionalNonStringableValueRendersEmptyNotCrash()
    {
        $output = $this->render(
            '[{rows}][{if(foo)}]<p>[{foo}]</p>[{/if}][{/rows}]',
            ['rows' => [['foo' => new \stdClass()]]]
        );
        $this->assertSame('<p></p>', $output);
    }

    public function testInLoopConditionalOnScalarRowTreatedAsNotSet()
    {
        $output = $this->render(
            '[{items}][{if(foo)}]<p>set</p>[{else}]<p>not set</p>[{/if}][{/items}]',
            ['items' => ['a scalar value']]
        );
        $this->assertSame('<p>not set</p>', $output);
    }

    public function testInvalidInLoopConditionalVariableNameThrows()
    {
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        Compiler::compile('[{rows}][{if(foo-bar)}]<p>x</p>[{/if}][{/rows}]');
    }

    public function testUnclosedInLoopConditionalThrows()
    {
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        Compiler::compile('[{rows}][{if(foo)}]<p>x</p>[{/rows}]');
    }

    public function testArrayIndexScalarInScalarLoopBodyResolvesFromOuterScope()
    {
        $output = $this->render(
            '[{items}]<p>[{key}]:[{foo[bar]}]</p>[{/items}]',
            ['items' => ['a' => 'x', 'b' => 'y'], 'foo' => ['bar' => 'BAZ']]
        );
        $this->assertSame('<p>a:BAZ</p>' . PHP_EOL . '<p>b:BAZ</p>', $output);
    }

    public function testArrayIndexScalarInNamedRowLoopBodyAlwaysUsesOuterScope()
    {
        // Even though the row ALSO has a 'foo' field (itself array-shaped), [{foo[bar]}] must resolve
        // from outer $data, never the row - Parser's row-substitution never handles the indexed form.
        $output = $this->render(
            '[{rows}]<p>[{foo[bar]}]</p>[{/rows}]',
            ['foo' => ['bar' => 'OUTER'], 'rows' => [['foo' => ['bar' => 'ROW-SHOULD-NOT-WIN']]]]
        );
        $this->assertSame('<p>OUTER</p>', $output);
    }

    public function testArrayIndexScalarInLoopBodyWithMissingIndexRendersEmpty()
    {
        $output = $this->render(
            '[{items}]<p>[{foo[missing]}]</p>[{/items}]',
            ['items' => ['a'], 'foo' => ['bar' => 'x']]
        );
        $this->assertSame('<p></p>', $output);
    }

    public function testArrayIndexScalarInScalarLoopBodyWithInvalidNameFallsBackToLiteral()
    {
        $output = $this->render('[{items}]<p>[{foo-bar[baz]}]</p>[{/items}]', ['items' => ['a']]);
        $this->assertSame('<p>[{foo-bar[baz]}]</p>', $output);
    }

    public function testArrayIndexScalarInNamedRowLoopBodyWithInvalidNameFallsBackToLiteral()
    {
        $output = $this->render(
            '[{rows}]<p>[{title}] [{foo-bar[baz]}]</p>[{/rows}]',
            ['rows' => [['title' => 'T']]]
        );
        $this->assertSame('<p>T [{foo-bar[baz]}]</p>', $output);
    }

    public function testConditionalSpanningLoopThrowsClearMessage()
    {
        // Final-review Finding 3: an [{if(...)}] that opens before a loop and closes after it isn't
        // supported (Parser itself doesn't handle this correctly either), but the error message must
        // not falsely claim there's no matching '[{/if}]' - there is one, it's just on the other side
        // of a loop that compileArrays() already consumed before compileConditionals() ever saw it.
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        $this->expectExceptionMessageMatches('/spans a loop/');
        Compiler::compile('[{if(items)}][{items}]<p>[{value}]</p>[{/items}][{/if}]');
    }

    public function testInvalidLoopNameThrows()
    {
        $this->expectException(\Pop\View\Template\Stream\Exception::class);
        Compiler::compile('[{foo-bar}]<p>x</p>[{/foo-bar}]');
    }

    public function testInvalidRowFieldNameFallsBackToLiteral()
    {
        $output = $this->render(
            '[{rows}]<p>[{foo-bar}]</p>[{/rows}]',
            ['rows' => [['foo-bar' => 'should not matter']]]
        );
        $this->assertSame('<p>[{foo-bar}]</p>', $output);
    }

    public function testNamedFieldRowFieldsLiterallyCalledKeyAndValueAreSubstitutedNormally()
    {
        $output = $this->render(
            '[{rows}]<p>[{key}] / [{value}]</p>[{/rows}]',
            ['rows' => [['key' => 'a key', 'value' => 'a value']]]
        );
        $this->assertSame('<p>a key / a value</p>', $output);
    }

    public function testScalarLoopWithNonStringableValueSilentlyDropsRow()
    {
        // Regression test for stringability guard in scalar rows.
        // Parser silently drops rows where value is non-stringable (array or non-__toString object),
        // but still outputs PHP_EOL for each iteration (including non-rendered rows).
        // Compiler must match this behavior exactly, not crash with "could not convert to string" error.
        $output = $this->render(
            '[{items}]<p>[{key}]:[{value}]</p>[{/items}]',
            ['items' => ['hello' => 'world', 'broken' => new \stdClass(), 'working' => 'again']]
        );
        // Parser behavior: stringable rows included (with PHP_EOL after each), non-stringable rows skipped
        // but their PHP_EOL still output. Result: <row1> + PHP_EOL + PHP_EOL (for skipped row 2) + <row3>
        $this->assertSame(
            '<p>hello:world</p>' . PHP_EOL . PHP_EOL . '<p>working:again</p>',
            $output
        );
    }

    public function testNestedSubLoopEntryArrayValueSkippedNotRenderedAsArray()
    {
        // Final-review Finding C1: an array-valued sub-loop entry must be silently skipped, like
        // Parser does (Parser.php's stringability guard explicitly excludes arrays), not rendered as
        // the literal string "Array" with a PHP warning.
        $output = $this->render(
            '[{items}][{pages}]<p>[{value}]</p>[{/pages}][{/items}]',
            ['items' => ['pages' => ['a' => 'x', 'b' => ['nested'], 'c' => 'y']]]
        );
        $this->assertSame('<p>x</p><p>y</p>', $output);
    }

    public function testInLoopConditionalSelfValueArrayRendersEmptyNotArray()
    {
        // Final-review Finding C4: same bug class as C1 but in the in-loop-conditional self-value
        // substitution guard. The condition is "set" (!empty() on a non-empty array is true), but the
        // substitution site must gracefully render nothing since an array isn't stringable - not
        // "Array" with a warning. Cached path only: the uncached Parser path actually throws a
        // TypeError here (Parser's own equivalent substitution has no stringability guard at all,
        // per Phase 3 spec design decision #2), which this compiler deliberately does not replicate.
        $output = $this->render(
            '[{rows}][{if(foo)}]<p>[{foo}]</p>[{/if}][{/rows}]',
            ['rows' => [['foo' => ['a', 'b']]]]
        );
        $this->assertSame('<p></p>', $output);
    }

    public function testNestedSubLoopICounterSkipsNonStringableEntry()
    {
        // Final-review Finding C3: the sub-loop's own [{i}] counter must only advance for entries that
        // actually render (matching Parser, which increments $j inside its stringability guard) - a
        // skipped non-stringable entry must not consume a number.
        $output = $this->render(
            '[{items}][{pages}]<p>[{i}]:[{key}]=[{value}]</p>[{/pages}][{/items}]',
            ['items' => ['pages' => ['a' => 'x', 'b' => new \stdClass(), 'c' => 'y']]]
        );
        $this->assertSame('<p>1:a=x</p><p>2:c=y</p>', $output);
    }

    public function testInLoopConditionalFalseInsideNestedSubLoopBodyIsResolvedNotLeaked()
    {
        // Final-review Finding C2: an in-loop [{if(...)}] inside a nested-sub-loop-shaped row's body
        // (non-numeric outer key) must be evaluated and stripped like the named-row branch already
        // does, not leaked as raw markup because compileLoopSubLoopDispatch()'s text spans previously
        // had no [{if(} handling at all.
        $output = $this->render(
            '[{items}][{if(x)}]<b>[{x}]</b>[{/if}][{pages}]<p>[{value}]</p>[{/pages}][{/items}]',
            ['items' => ['pages' => ['a', 'b']]]
        );
        $this->assertSame('<p>a</p><p>b</p>', $output);
    }

    public function testInLoopConditionalTrueWithNestedSubLoopTagInsideSurvivingBranch()
    {
        // Final-review Finding C2 (S7 scenario): a sub-loop tag declared INSIDE an in-loop-if's
        // surviving (then) branch must still be found and dispatched, and the condition's own value
        // must still be substituted into the branch (row-scoped, matching Parser exactly).
        $output = $this->render(
            '[{items}][{if(x)}]<b>[{x}]</b>[{pages}]<p>[{value}]</p>[{/pages}][{/if}][{/items}]',
            ['items' => ['pages' => ['x' => 'YES', 0 => 'a', 1 => 'b']]]
        );
        $this->assertSame('<b>YES</b><p>YES</p><p>a</p><p>b</p>', $output);
    }

    public function testInLoopConditionalArrayIndexSelfSubstitutionInsideSubLoopShapedBody()
    {
        // Coverage: an in-loop [{if(x[y])}] wrapping a nested sub-loop tag, where the surviving
        // branch's own text self-substitutes the array-index form [{x[y]}] - the indexed sibling of
        // the plain self-substitution already covered by
        // testInLoopConditionalTrueWithNestedSubLoopTagInsideSurvivingBranch().
        $output = $this->render(
            '[{items}][{if(x[y])}]<b>[{x[y]}]</b>[{pages}]<p>[{value}]</p>[{/pages}][{/if}][{/items}]',
            ['items' => ['pages' => ['x' => ['y' => 'YES'], 0 => 'a', 1 => 'b']]]
        );
        $this->assertSame('<b>YES</b><p>a</p><p>b</p>', $output);
    }

    public function testInLoopConditionalElseInsideSubLoopShapedBodyRendersElseBranch()
    {
        // Coverage: an in-loop [{if(...)}] wrapping a nested sub-loop tag, WITH an [{else}] clause,
        // whose condition is false. Sibling of
        // testInLoopConditionalFalseInsideNestedSubLoopBodyIsResolvedNotLeaked() (which has no else).
        $output = $this->render(
            '[{items}][{if(x)}]<b>YES</b>[{else}]<b>NO</b>[{/if}][{pages}]<p>[{value}]</p>[{/pages}][{/items}]',
            ['items' => ['pages' => ['a', 'b']]]
        );
        $this->assertSame('<b>NO</b><p>a</p><p>b</p>', $output);
    }

    public function testNestedSubLoopEntryResolvesOuterScopeFieldsIncludingArrayIndex()
    {
        // Coverage: a sub-loop entry's own body referencing a placeholder other than
        // [{key}]/[{value}]/[{i}] - both the plain and array-index forms - falls back to the outer
        // $data scope, same as any other loop-body scalar.
        $output = $this->render(
            '[{items}][{pages}]<p>[{value}]-[{site}]-[{foo[bar]}]</p>[{/pages}][{/items}]',
            ['items' => ['pages' => ['a', 'b']], 'site' => 'MySite', 'foo' => ['bar' => 'X']]
        );
        $this->assertSame('<p>a-MySite-X</p><p>b-MySite-X</p>', $output);
    }

    public function testNestedSubLoopEntryInvalidPlaceholderNamesFallBackToLiteral()
    {
        // Coverage: the invalid-character fallback (silent literal text, not a throw) applies inside
        // a sub-loop entry's own body too, for both the plain and array-index placeholder forms.
        $output = $this->render(
            '[{items}][{pages}]<p>[{value}]-[{foo-bar[baz]}]-[{qux-quux}]</p>[{/pages}][{/items}]',
            ['items' => ['pages' => ['a']]]
        );
        $this->assertSame('<p>a-[{foo-bar[baz]}]-[{qux-quux}]</p>', $output);
    }

    public function testOuterScopeTextAroundSubLoopTagResolvesArrayIndexScalar()
    {
        // Coverage: array-index sibling of testOuterScopeTextAroundNestedSubLoopUsesOuterScopeOnly(),
        // which only covers the plain-name form.
        $output = $this->render(
            '[{items}]Val: [{foo[bar]}] - [{pages}]<span>[{value}]</span>[{/pages}][{/items}]',
            ['foo' => ['bar' => 'X'], 'items' => ['pages' => ['a', 'b']]]
        );
        $this->assertSame('Val: X - <span>a</span><span>b</span>', $output);
    }

    public function testOuterScopeTextAroundSubLoopTagInvalidPlaceholdersFallBackToLiteral()
    {
        // Coverage: the invalid-character fallback applies to outer-scope-only text surrounding a
        // sub-loop tag too, for both the array-index and plain placeholder forms.
        $output = $this->render(
            '[{items}]Val: [{foo-bar[baz]}] / [{qux-quux}] - [{pages}]<span>[{value}]</span>[{/pages}][{/items}]',
            ['items' => ['pages' => ['a']]]
        );
        $this->assertSame('Val: [{foo-bar[baz]}] / [{qux-quux}] - <span>a</span>', $output);
    }
}
