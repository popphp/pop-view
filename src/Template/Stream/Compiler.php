<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\View\Template\Stream;

/**
 * View stream template compiler class
 *
 * Compiles a resolved Stream template string (i.e. one that has already been through
 * Stream::parseParent()/parseIncludes()/parseBlocks()) into PHP source implementing the same
 * behavior as Parser::parseArrays() + parseConditionals() + parseScalars(), as native PHP control
 * structures rather than string substitution. Covers every loop-body shape Parser::parseArrays()
 * supports: numeric lists of scalars, numeric lists of named-field rows, nested named sub-loops, and
 * in-loop [{if(...)}] conditionals - with a small number of deliberate, documented divergences from
 * Parser's own (buggy or crash-prone) behavior for edge cases; see docs/superpowers/specs for details.
 *
 * @category   Pop
 * @package    Pop\View
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Compiler
{

    /**
     * Compile a resolved template string to PHP source
     *
     * @param  string $template
     * @throws Exception
     * @return string
     */
    public static function compile(string $template): string
    {
        return self::compileArrays($template);
    }

    /**
     * Compile top-level [{name}]...[{/name}] loop blocks
     *
     * Detects each loop block the same way the (now-removed) assertNoLoopSyntax() did — a bare
     * '[{name}]' whose name has a later matching '[{/name}]' — but now validates and compiles it
     * instead of unconditionally rejecting it. Text outside loop blocks flows through
     * compileConditionals() unchanged, exactly as compile() did before this method existed.
     *
     * @param  string $template
     * @throws Exception
     * @return string
     */
    protected static function compileArrays(string $template): string
    {
        $php     = '';
        $cursor  = 0;
        $matches = [];

        preg_match_all('/\[\{([^\[\]{}]+?)\}\]/', $template, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $i => $full) {
            [$openTag, $openOffset] = $full;

            if ($openOffset < $cursor) {
                continue;
            }

            $name        = $matches[1][$i][0];
            $closeTag    = '[{/' . $name . '}]';
            $bodyStart   = $openOffset + strlen($openTag);
            $closeOffset = strpos($template, $closeTag, $bodyStart);

            if ($closeOffset === false) {
                continue;
            }

            $preText      = substr($template, $cursor, $openOffset - $cursor);
            $afterLoopEnd = $closeOffset + strlen($closeTag);
            self::assertNoConditionalSpansLoop($name, $preText, $template, $afterLoopEnd);
            $php .= self::compileConditionals($preText);

            $body = substr($template, $bodyStart, $closeOffset - $bodyStart);
            $php .= self::compileLoop($name, $body, $openOffset);

            $cursor = $afterLoopEnd;
        }

        $php .= self::compileConditionals(substr($template, $cursor));

        return $php;
    }

    /**
     * Detect a top-level '[{if(...)}]' block that opens in the text before a loop but whose matching
     * '[{/if}]' falls after the loop closes. compileArrays() slices the template at loop boundaries
     * before handing surrounding text to compileConditionals(), so a conditional spanning a loop this
     * way would otherwise only ever be seen by compileConditionals() as an opening tag with no matching
     * close in that slice - producing a misleading "unclosed block" error even though a '[{/if}]' really
     * does exist later in the template. This isn't a case that needs to be SUPPORTED (Parser itself
     * doesn't handle a conditional spanning a loop correctly either, so rejecting it is correct), but the
     * error message should say what's actually going on instead of sending someone looking for a missing
     * closing tag that isn't actually missing.
     *
     * @param  string $name         the loop name, for the error message
     * @param  string $preText      the text between the previous cursor and this loop's open tag
     * @param  string $template     the full template, searched for a '[{/if}]' after the loop closes
     * @param  int    $afterLoopEnd offset just past this loop's '[{/name}]' close tag
     * @throws Exception
     * @return void
     */
    protected static function assertNoConditionalSpansLoop(string $name, string $preText, string $template, int $afterLoopEnd): void
    {
        $ifPos = stripos($preText, '[{if(');
        if ($ifPos === false || str_contains(substr($preText, $ifPos), '[{/if}]')) {
            return;
        }

        if (strpos($template, '[{/if}]', $afterLoopEnd) !== false) {
            throw new Exception(
                "Error: Stream caching does not yet support a '[{if(...)}]' block that spans a loop " .
                "('[{" . $name . "}]' begins inside an '[{if(...)}]' block that doesn't close before the " .
                "loop starts). Render this template without a cache directory, or restructure the template " .
                "so the conditional doesn't wrap the loop."
            );
        }
    }

    /**
     * Compile a single [{name}]...[{/name}] loop into real PHP control flow, replicating
     * Parser::parseArrays()'s exact per-row runtime dispatch (is_array($val) / is_numeric($key))
     * as native if/foreach - the shape isn't knowable until data arrives, so it can't be resolved
     * at compile time. ArrayAccess/ArrayObject throw rather than attempt parity with Parser's own
     * buggy handling of those types (design decision #4); a missing/null/scalar outer collection
     * normalizes to an empty array - zero iterations, no error (design decision #5).
     *
     * @param  string $name
     * @param  string $body
     * @param  int    $offset
     * @throws Exception
     * @return string
     */
    protected static function compileLoop(string $name, string $body, int $offset): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new Exception(
                "Error: Stream caching does not support the loop name '" . $name . "' (only [a-zA-Z0-9_] " .
                "characters are supported). Render this template without a cache directory, or rename the loop."
            );
        }

        $var           = '__loop_' . $name . '_' . $offset;
        $scalarBranch  = self::compileLoopScalarRow($body, $var);
        $namedBranch   = self::compileLoopNamedRow($body, $var);
        $subLoopBranch = self::compileLoopSubLoopDispatch($name, $body, $var);
        $exceptionFqcn = '\\Pop\\View\\Template\\Stream\\Exception';

        $php  = "<?php \$" . $var . " = \$data['" . $name . "'] ?? null;";
        $php .= " if (\$" . $var . " instanceof \\ArrayAccess || \$" . $var . " instanceof \\ArrayObject) {";
        $php .= " throw new " . $exceptionFqcn . "(\"Error: Compiled loop '" . $name . "' requires a plain " .
            "array; ArrayAccess/ArrayObject is not supported on the compiled path.\"); }";
        $php .= " if (!is_array(\$" . $var . ")) { \$" . $var . " = []; }";
        $php .= " \$" . $var . "_i = 0; \$" . $var . "_count = count(\$" . $var . ");";
        $php .= " foreach (\$" . $var . " as \$" . $var . "_key => \$" . $var . "_val):";
        $php .= " if (\$" . $var . "_val instanceof \\ArrayAccess || \$" . $var . "_val instanceof \\ArrayObject):";
        $php .= " throw new " . $exceptionFqcn . "(\"Error: A row in compiled loop '" . $name . "' is " .
            "ArrayAccess/ArrayObject, which is not supported on the compiled path.\");";
        $php .= " elseif (is_array(\$" . $var . "_val)):";
        $php .= " if (is_numeric(\$" . $var . "_key)): ?>" . $namedBranch . "<?php else: ?>" . $subLoopBranch . "<?php endif;";
        $php .= " else:";
        $php .= " if (!is_object(\$" . $var . "_val) || method_exists(\$" . $var . "_val, '__toString')): ?>" .
            $scalarBranch . "<?php endif;";
        $php .= " endif;";
        $php .= " \$" . $var . "_i++; if (\$" . $var . "_i < \$" . $var . "_count) { echo PHP_EOL; }";
        $php .= " endforeach; ?>";

        return $php;
    }

    /**
     * Compile a loop body for the "scalar row" runtime branch (is_array($val) false):
     * [{key}]/[{value}]/[{i}] are reserved substitutions; any other placeholder falls back to the
     * outer $data scope, matching Parser::parseArrays()'s scalar-row substitution list exactly.
     * Also handles in-loop [{if(...)}] conditionals, which are always false for scalar rows.
     *
     * @param  string $body
     * @param  string $var
     * @throws Exception
     * @return string
     */
    protected static function compileLoopScalarRow(string $body, string $var): string
    {
        $php    = '';
        $cursor = 0;

        while (($start = stripos($body, '[{if(', $cursor)) !== false) {
            $php .= self::compileLoopScalarRowBody(substr($body, $cursor, $start - $cursor), $var);

            $block = self::parseInLoopIfBlock($body, $start);

            // For scalar rows, the condition is always false (scalars have no array fields)
            // So we always render the else branch if it exists, otherwise nothing
            if ($block['else'] !== null) {
                $php .= self::compileLoopScalarRowBody($block['else'], $var);
            }

            $cursor = $block['nextCursor'];
        }

        $php .= self::compileLoopScalarRowBody(substr($body, $cursor), $var);

        return $php;
    }

    /**
     * Parse a single '[{if(rowvar)}]...[{else}]...[{/if}]' block starting at $start within $body -
     * the shared parsing core for every in-loop conditional consumer (compileLoopScalarRow()'s
     * always-false scalar-row branch, compileLoopConditionals()'s named-row branch, and
     * compileSubLoopDispatchBody()'s nested-sub-loop-row branch, Finding C2). Extracts and validates
     * the condition variable (and optional array-index form), splits the body on '[{else}]' if
     * present, and locates where the caller's cursor should resume after this block. Throws on an
     * unclosed block or an invalid variable name - structural syntax, same throw-not-fallback policy
     * as every other loop/conditional name in this compiler.
     *
     * @param  string $body
     * @param  int    $start offset of the '[{if(' this block begins at
     * @throws Exception
     * @return array{rowVar: string, index: ?string, then: string, else: ?string, nextCursor: int}
     */
    protected static function parseInLoopIfBlock(string $body, int $start): array
    {
        $condEnd = strpos($body, '[{/if}]', $start);
        if ($condEnd === false) {
            throw new Exception(
                "Error: Stream caching encountered an unclosed '[{if(...)}]' block inside a loop body " .
                "(no matching '[{/if}]' found). Render this template without a cache directory, or fix " .
                "the template's conditional syntax."
            );
        }
        $block = substr($body, $start, ($condEnd + 7) - $start);

        $rowVar = substr($block, strpos($block, '(') + 1);
        $rowVar = substr($rowVar, 0, strpos($rowVar, ')'));

        $index = null;
        if (str_contains($rowVar, '[')) {
            $index  = substr($rowVar, strpos($rowVar, '[') + 1);
            $index  = substr($index, 0, strpos($index, ']'));
            $rowVar = substr($rowVar, 0, strpos($rowVar, '['));
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $rowVar) || ($index !== null && !preg_match('/^[a-zA-Z0-9_]+$/', $index))) {
            throw new Exception(
                "Error: Stream caching does not support the in-loop conditional variable name '" . $rowVar .
                "' (only [a-zA-Z0-9_] characters are supported). Render this template without a cache " .
                "directory, or rename the variable."
            );
        }

        $openEnd = strpos($block, ')}]') + 3;
        $ifBody  = substr($block, $openEnd, strlen($block) - $openEnd - 7);

        if (str_contains($ifBody, '[{else}]')) {
            $then = substr($ifBody, 0, strpos($ifBody, '[{else}]'));
            $else = substr($ifBody, strpos($ifBody, '[{else}]') + 8);
        } else {
            $then = $ifBody;
            $else = null;
        }

        return [
            'rowVar'     => $rowVar,
            'index'      => $index,
            'then'       => $then,
            'else'       => $else,
            'nextCursor' => $condEnd + 7,
        ];
    }

    /**
     * Helper method to compile placeholder substitution for scalar row body
     *
     * @param  string $body
     * @param  string $var
     * @return string
     */
    protected static function compileLoopScalarRowBody(string $body, string $var): string
    {
        $php     = '';
        $cursor  = 0;
        $matches = [];

        preg_match_all('/\[\{([^\[\]{}]+?)(?:\[([^\[\]{}]+)\])?\}\]/', $body, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $i => $full) {
            [$matchText, $offset] = $full;
            $php .= self::phpEcho(substr($body, $cursor, $offset - $cursor));

            $name  = $matches[1][$i][0];
            $index = ($matches[2][$i][0] !== '') ? $matches[2][$i][0] : null;

            if ($index !== null) {
                if (preg_match('/^[a-zA-Z0-9_]+$/', $name) && preg_match('/^[a-zA-Z0-9_]+$/', $index)) {
                    $php .= self::compileIndexedDataLookup('$data', $name, $index, self::phpEcho($matchText));
                } else {
                    $php .= self::phpEcho($matchText);
                }
            } elseif ($name === 'key') {
                $php .= "<?= \$" . $var . "_key ?>";
            } elseif ($name === 'value') {
                $php .= "<?= \$" . $var . "_val ?>";
            } elseif ($name === 'i') {
                $php .= "<?= (\$" . $var . "_i + 1) ?>";
            } elseif (preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                $php .= self::compileDataLookup('$data', $name, self::phpEcho($matchText));
            } else {
                $php .= self::phpEcho($matchText);
            }

            $cursor = $offset + strlen($matchText);
        }

        $php .= self::phpEcho(substr($body, $cursor));

        return $php;
    }

    /**
     * Compile a loop body for the "named-field row" runtime branch (is_array($val) true, numeric
     * outer key) - entry point that resolves in-loop [{if(...)}] conditionals first, then everything
     * else via compileLoopNamedRowScalars().
     *
     * @param  string $body
     * @param  string $var
     * @throws Exception
     * @return string
     */
    protected static function compileLoopNamedRow(string $body, string $var): string
    {
        return self::compileLoopConditionals($body, $var);
    }

    /**
     * Compile in-loop [{if(rowfield)}]...[{else}]...[{/if}] blocks (Feature B), evaluated purely
     * against the current row - no outer-$data fallback, unlike plain row-field substitution. Mirrors
     * compileConditionals()'s cursor-based single-pass technique. The condition's own placeholder gets
     * a row-only, stringability-guarded inline substitution (compileLoopNamedRowScalars()'s $selfName/
     * $selfIndex params); everything else in the surviving branch goes through the normal row-then-
     * outer-fallback path.
     *
     * @param  string $body
     * @param  string $var
     * @throws Exception
     * @return string
     */
    protected static function compileLoopConditionals(string $body, string $var): string
    {
        $php    = '';
        $cursor = 0;

        while (($start = stripos($body, '[{if(', $cursor)) !== false) {
            $php .= self::compileLoopNamedRowScalars(substr($body, $cursor, $start - $cursor), $var);

            $block = self::parseInLoopIfBlock($body, $start);

            $condExpr = ($block['index'] !== null)
                ? "!empty(\$" . $var . "_val['" . $block['rowVar'] . "']['" . $block['index'] . "'])"
                : "!empty(\$" . $var . "_val['" . $block['rowVar'] . "'])";

            $php .= '<?php if (' . $condExpr . '): ?>' .
                self::compileLoopNamedRowScalars($block['then'], $var, $block['rowVar'], $block['index']);
            if ($block['else'] !== null) {
                $php .= '<?php else: ?>' . self::compileLoopNamedRowScalars($block['else'], $var);
            }
            $php .= '<?php endif; ?>';

            $cursor = $block['nextCursor'];
        }

        $php .= self::compileLoopNamedRowScalars(substr($body, $cursor), $var);

        return $php;
    }

    /**
     * Compile plain placeholder substitution for a named-field-row loop body. Same logic as Task 1's
     * compileLoopNamedRow(), renamed, plus an optional "self" name/index (from an enclosing in-loop
     * [{if(...)}]'s own condition variable, Feature B): a placeholder matching $selfName/$selfIndex
     * gets a row-only, stringability-guarded substitution instead of the normal row-then-outer-
     * fallback path - matching Parser's inline if-branch substitution, which has no outer-scope
     * fallback and (per design decision #2) gets a stringability guard Parser's own version lacks.
     *
     * @param  string  $body
     * @param  string  $var
     * @param  ?string $selfName
     * @param  ?string $selfIndex
     * @return string
     */
    protected static function compileLoopNamedRowScalars(string $body, string $var, ?string $selfName = null, ?string $selfIndex = null): string
    {
        $php     = '';
        $cursor  = 0;
        $matches = [];

        preg_match_all('/\[\{([^\[\]{}]+?)(?:\[([^\[\]{}]+)\])?\}\]/', $body, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $i => $full) {
            [$matchText, $offset] = $full;
            $php .= self::phpEcho(substr($body, $cursor, $offset - $cursor));

            $name  = $matches[1][$i][0];
            $index = ($matches[2][$i][0] !== '') ? $matches[2][$i][0] : null;

            if ($selfName !== null && $name === $selfName && $index === $selfIndex) {
                $valueExpr = ($index !== null)
                    ? "\$" . $var . "_val['" . $name . "']['" . $index . "']"
                    : "\$" . $var . "_val['" . $name . "']";
                $php .= "<?php if ((is_object(" . $valueExpr . ") && method_exists(" . $valueExpr . ", '__toString')) "
                    . "|| (!is_object(" . $valueExpr . ") && !is_array(" . $valueExpr . "))): ?>"
                    . "<?= " . $valueExpr . " ?><?php endif; ?>";
            } elseif ($index !== null) {
                if (preg_match('/^[a-zA-Z0-9_]+$/', $name) && preg_match('/^[a-zA-Z0-9_]+$/', $index)) {
                    $php .= self::compileIndexedDataLookup('$data', $name, $index, self::phpEcho($matchText));
                } else {
                    $php .= self::phpEcho($matchText);
                }
            } elseif ($name === 'i') {
                $php .= "<?= (\$" . $var . "_i + 1) ?>";
            } elseif (preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                $outerFallback = self::compileDataLookup('$data', $name, self::phpEcho($matchText));
                $php .= self::compileDataLookup('$' . $var . '_val', $name, $outerFallback);
            } else {
                $php .= self::phpEcho($matchText);
            }

            $cursor = $offset + strlen($matchText);
        }

        $php .= self::phpEcho(substr($body, $cursor));

        return $php;
    }

    /**
     * Find every [{tag}]...[{/tag}] pair in a loop body, for the nested-sub-loop dispatch (Feature A).
     * Uses the same broad detection regex as compileArrays()'s top-level loop scanner. An unclosed
     * tag-looking span is silently skipped (not an error) - same "continue past an unmatched open tag"
     * precedent compileArrays() itself already establishes for the top-level scanner.
     *
     * @param  string $name the outer loop's name, for error messages
     * @param  string $body
     * @throws Exception
     * @return array list of ['name'=>, 'start'=>, 'end'=>, 'inner'=>, 'full'=>], in document order
     */
    protected static function findSubLoopTags(string $name, string $body): array
    {
        $tags    = [];
        $cursor  = 0;
        $matches = [];

        preg_match_all('/\[\{([^\[\]{}]+?)\}\]/', $body, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $i => $full) {
            [$openTag, $openOffset] = $full;

            if ($openOffset < $cursor) {
                continue;
            }

            $tagName     = $matches[1][$i][0];
            $closeTag    = '[{/' . $tagName . '}]';
            $bodyStart   = $openOffset + strlen($openTag);
            $closeOffset = strpos($body, $closeTag, $bodyStart);

            if ($closeOffset === false) {
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tagName)) {
                throw new Exception(
                    "Error: Stream caching does not support the nested loop name '" . $tagName . "' inside '[{" .
                    $name . "}]' (only [a-zA-Z0-9_] characters are supported). Render this template without a " .
                    "cache directory, or rename the nested loop."
                );
            }

            $tags[] = [
                'name'  => $tagName,
                'start' => $openOffset,
                'end'   => $closeOffset + strlen($closeTag),
                'inner' => substr($body, $bodyStart, $closeOffset - $bodyStart),
                'full'  => substr($body, $openOffset, ($closeOffset + strlen($closeTag)) - $openOffset),
            ];

            $cursor = $closeOffset + strlen($closeTag);
        }

        return $tags;
    }

    /**
     * Compile the "non-numeric outer key, array-shaped row" branch: dispatches on the row's actual key
     * value against every [{tag}]...[{/tag}] pair declared in the loop body (Feature A). A row whose
     * key matches a declared tag renders that tag's sub-loop; a declared tag that ISN'T the one
     * matching this row's key renders as its own raw literal text (matching Parser leaving a
     * non-matching declared tag's markup untouched); if no tag matches the row's key at all, the row
     * contributes nothing at all (no tags, no surrounding text) — matching Parser's behavior exactly.
     * Text outside any tag span is compiled against the outer $data scope only (Parser never
     * row-substitutes this branch's surrounding text at all).
     *
     * @param  string $name
     * @param  string $body
     * @param  string $var
     * @throws Exception
     * @return string
     */
    protected static function compileLoopSubLoopDispatch(string $name, string $body, string $var): string
    {
        $tags = self::findSubLoopTags($name, $body);

        if (empty($tags)) {
            return '';
        }

        $anyMatchCondition = implode(' || ', array_map(
            fn($tag) => "\$" . $var . "_key === '" . $tag['name'] . "'",
            $tags
        ));

        $counter = 0;
        $inner   = self::compileSubLoopDispatchBody($name, $body, $var, $counter);

        return "<?php if (" . $anyMatchCondition . "): ?>" . $inner . "<?php endif; ?>";
    }

    /**
     * If-block-aware sweep over a non-numeric-key row's loop body (Finding C2): an in-loop
     * '[{if(rowvar)}]...[{else}]...[{/if}]' can appear anywhere in this branch's body, including
     * wrapping a nested sub-loop tag - Parser resolves these in-loop conditionals for every
     * array-shaped row uniformly, before the numeric/non-numeric row-shape dispatch, so this branch
     * needs the same conditional handling compileLoopConditionals() already gives the named-row
     * branch. Mirrors that method's cursor-based single-pass technique via the shared
     * parseInLoopIfBlock() helper; each surviving span (both the plain text outside any if-block and
     * each if-block's own then/else content) is compiled by compileSubLoopDispatchTextSpan(), which
     * re-discovers any sub-loop tags nested inside it fresh - correctly finding a tag declared inside
     * an if-block's surviving branch, since if-blocks are resolved as the OUTER pass here rather than
     * splitting the body into tag-bounded spans first.
     *
     * @param  string $name
     * @param  string $body
     * @param  string $var
     * @param  int    $counter running counter (by reference) keeping generated sub-loop variable
     *                         names globally unique across every span this sweep visits
     * @throws Exception
     * @return string
     */
    protected static function compileSubLoopDispatchBody(string $name, string $body, string $var, int &$counter): string
    {
        $php    = '';
        $cursor = 0;

        while (($start = stripos($body, '[{if(', $cursor)) !== false) {
            $php .= self::compileSubLoopDispatchTextSpan($name, substr($body, $cursor, $start - $cursor), $var, $counter);

            $block = self::parseInLoopIfBlock($body, $start);

            $condExpr = ($block['index'] !== null)
                ? "!empty(\$" . $var . "_val['" . $block['rowVar'] . "']['" . $block['index'] . "'])"
                : "!empty(\$" . $var . "_val['" . $block['rowVar'] . "'])";

            $php .= '<?php if (' . $condExpr . '): ?>' .
                self::compileSubLoopDispatchTextSpan($name, $block['then'], $var, $counter, $block['rowVar'], $block['index']);
            if ($block['else'] !== null) {
                $php .= '<?php else: ?>' . self::compileSubLoopDispatchTextSpan($name, $block['else'], $var, $counter);
            }
            $php .= '<?php endif; ?>';

            $cursor = $block['nextCursor'];
        }

        $php .= self::compileSubLoopDispatchTextSpan($name, substr($body, $cursor), $var, $counter);

        return $php;
    }

    /**
     * Compile one if-block-free text span within the non-numeric-key row branch (Finding C2):
     * re-discovers any declared sub-loop tag(s) fresh within THIS span (correctly picking up a tag
     * nested inside an in-loop-if's surviving branch, since compileSubLoopDispatchBody() calls this
     * per-span rather than relying on whole-body tag offsets) and dispatches each one exactly as
     * compileLoopSubLoopDispatch() always has; everything else - including an optional self-value
     * substitution for an enclosing in-loop-if's own condition variable, row-scoped and
     * stringability-guarded per design decision #2, mirroring compileLoopNamedRowScalars()'s
     * $selfName/$selfIndex - is delegated to compileLoopOuterScopeOnly().
     *
     * @param  string  $name
     * @param  string  $text
     * @param  string  $var
     * @param  int     $counter   running counter (by reference), see compileSubLoopDispatchBody()
     * @param  ?string $selfName
     * @param  ?string $selfIndex
     * @throws Exception
     * @return string
     */
    protected static function compileSubLoopDispatchTextSpan(
        string $name,
        string $text,
        string $var,
        int &$counter,
        ?string $selfName = null,
        ?string $selfIndex = null
    ): string {
        $tags = self::findSubLoopTags($name, $text);

        if (empty($tags)) {
            return self::compileLoopOuterScopeOnly($text, $var, $selfName, $selfIndex);
        }

        $php    = '';
        $cursor = 0;

        foreach ($tags as $tag) {
            $php .= self::compileLoopOuterScopeOnly(substr($text, $cursor, $tag['start'] - $cursor), $var, $selfName, $selfIndex);

            $subVar  = $var . '_sub_' . $tag['name'] . '_' . ($counter++);
            $subBody = self::compileSubLoopEntry($tag['inner'], $subVar);

            $php .= "<?php if (\$" . $var . "_key === '" . $tag['name'] . "'): ?>";
            $php .= "<?php \$" . $subVar . "_j = 0; \$" . $subVar . "_count = count(\$" . $var . "_val); ?>";
            $php .= "<?php foreach (\$" . $var . "_val as \$" . $subVar . "_key => \$" . $subVar . "_val): ?>";
            $php .= "<?php if ((is_object(\$" . $subVar . "_val) && method_exists(\$" . $subVar . "_val, '__toString')) "
                . "|| (!is_object(\$" . $subVar . "_val) && !is_array(\$" . $subVar . "_val))): ?>"
                . $subBody . "<?php \$" . $subVar . "_j++; endif; ?>";
            $php .= "<?php endforeach; ?>";
            $php .= "<?php else: ?>" . self::phpEcho($tag['full']) . "<?php endif; ?>";

            $cursor = $tag['end'];
        }

        $php .= self::compileLoopOuterScopeOnly(substr($text, $cursor), $var, $selfName, $selfIndex);

        return $php;
    }

    /**
     * Compile a sub-loop's own per-entry body: [{key}]/[{value}]/[{i}] with a separate, sub-loop-local
     * 1-indexed counter (not the outer loop's), stringability-guarded; any other placeholder falls back
     * to the outer $data scope. Matches Parser's sub-loop entry substitution exactly - a fixed
     * ['[{key}]','[{value}]','[{i}]'] str_replace list, nothing else row-scoped.
     *
     * @param  string $body
     * @param  string $subVar
     * @return string
     */
    protected static function compileSubLoopEntry(string $body, string $subVar): string
    {
        $php     = '';
        $cursor  = 0;
        $matches = [];

        preg_match_all('/\[\{([^\[\]{}]+?)(?:\[([^\[\]{}]+)\])?\}\]/', $body, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $i => $full) {
            [$matchText, $offset] = $full;
            $php .= self::phpEcho(substr($body, $cursor, $offset - $cursor));

            $name  = $matches[1][$i][0];
            $index = ($matches[2][$i][0] !== '') ? $matches[2][$i][0] : null;

            if ($index !== null) {
                if (preg_match('/^[a-zA-Z0-9_]+$/', $name) && preg_match('/^[a-zA-Z0-9_]+$/', $index)) {
                    $php .= self::compileIndexedDataLookup('$data', $name, $index, self::phpEcho($matchText));
                } else {
                    $php .= self::phpEcho($matchText);
                }
            } elseif ($name === 'key') {
                $php .= "<?= \$" . $subVar . "_key ?>";
            } elseif ($name === 'value') {
                $php .= "<?= \$" . $subVar . "_val ?>";
            } elseif ($name === 'i') {
                $php .= "<?= (\$" . $subVar . "_j + 1) ?>";
            } elseif (preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                $php .= self::compileDataLookup('$data', $name, self::phpEcho($matchText));
            } else {
                $php .= self::phpEcho($matchText);
            }

            $cursor = $offset + strlen($matchText);
        }

        $php .= self::phpEcho(substr($body, $cursor));

        return $php;
    }

    /**
     * Compile a text span against the outer $data scope only - no row-scope check at all, not even for
     * [{key}]/[{value}]/[{i}] (no special meaning outside a loop/sub-loop body). Used for text
     * surrounding a matched sub-loop tag within a non-numeric-key row, which Parser never
     * row-substitutes (Feature A design notes).
     *
     * Optionally also handles a row-scoped, stringability-guarded "self" substitution (Finding C2's
     * fix, mirroring compileLoopNamedRowScalars()'s $selfName/$selfIndex): when this span is an
     * in-loop-if's surviving branch, a placeholder matching $selfName/$selfIndex is the condition's
     * own variable and substitutes from $<var>_val (the row's own data) instead of $data - everything
     * else in the span still resolves outer-scope only, matching Feature A's "no row-substitution
     * outside sub-loop tags" rule for this branch.
     *
     * @param  string  $text
     * @param  ?string $var       required only when $selfName is non-null
     * @param  ?string $selfName
     * @param  ?string $selfIndex
     * @return string
     */
    protected static function compileLoopOuterScopeOnly(string $text, ?string $var = null, ?string $selfName = null, ?string $selfIndex = null): string
    {
        $php     = '';
        $cursor  = 0;
        $matches = [];

        preg_match_all('/\[\{([^\[\]{}]+?)(?:\[([^\[\]{}]+)\])?\}\]/', $text, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $i => $full) {
            [$matchText, $offset] = $full;
            $php .= self::phpEcho(substr($text, $cursor, $offset - $cursor));

            $name  = $matches[1][$i][0];
            $index = ($matches[2][$i][0] !== '') ? $matches[2][$i][0] : null;

            if ($selfName !== null && $name === $selfName && $index === $selfIndex) {
                $valueExpr = ($index !== null)
                    ? "\$" . $var . "_val['" . $name . "']['" . $index . "']"
                    : "\$" . $var . "_val['" . $name . "']";
                $php .= "<?php if ((is_object(" . $valueExpr . ") && method_exists(" . $valueExpr . ", '__toString')) "
                    . "|| (!is_object(" . $valueExpr . ") && !is_array(" . $valueExpr . "))): ?>"
                    . "<?= " . $valueExpr . " ?><?php endif; ?>";
            } elseif ($index !== null) {
                if (preg_match('/^[a-zA-Z0-9_]+$/', $name) && preg_match('/^[a-zA-Z0-9_]+$/', $index)) {
                    $php .= self::compileIndexedDataLookup('$data', $name, $index, self::phpEcho($matchText));
                } else {
                    $php .= self::phpEcho($matchText);
                }
            } elseif (preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                $php .= self::compileDataLookup('$data', $name, self::phpEcho($matchText));
            } else {
                $php .= self::phpEcho($matchText);
            }

            $cursor = $offset + strlen($matchText);
        }

        $php .= self::phpEcho(substr($text, $cursor));

        return $php;
    }

    /**
     * Compile top-level [{if(var)}]...[{else}]...[{/if}] blocks
     *
     * Note: the opening '[{if(' tag is matched case-insensitively (via stripos()), matching
     * Parser::parseConditionals()'s case-insensitive '/\[{if/mi' regex. The closing '[{/if}]' and
     * '[{else}]' tags remain case-sensitive, also matching Parser's behavior (it locates those via
     * plain strpos()/str_contains(), not a case-insensitive regex).
     *
     * @param  string $template
     * @throws Exception
     * @return string
     */
    protected static function compileConditionals(string $template): string
    {
        $php    = '';
        $cursor = 0;

        while (($start = stripos($template, '[{if(', $cursor)) !== false) {
            $php .= self::compileScalars(substr($template, $cursor, $start - $cursor));

            $condEnd = strpos($template, '[{/if}]', $start);
            if ($condEnd === false) {
                throw new Exception(
                    "Error: Stream caching encountered an unclosed '[{if(...)}]' block (no matching " .
                    "'[{/if}]' found). Render this template without a cache directory, or fix the " .
                    "template's conditional syntax."
                );
            }
            $block = substr($template, $start, ($condEnd + 7) - $start);

            $var = substr($block, strpos($block, '(') + 1);
            $var = substr($var, 0, strpos($var, ')'));

            $index = null;
            if (str_contains($var, '[')) {
                $index = substr($var, strpos($var, '[') + 1);
                $index = substr($index, 0, strpos($index, ']'));
                $var   = substr($var, 0, strpos($var, '['));
            }

            // Validate that var and index only contain safe identifier characters. This gate must stay
            // exactly as narrow as [a-zA-Z0-9_] - it's the fix for a prior injection vulnerability.
            // A name that fails this gate is not silently treated as literal text (that would silently
            // diverge from Parser, which handles any variable name via plain str_replace()) - it throws.
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $var) || ($index !== null && !preg_match('/^[a-zA-Z0-9_]+$/', $index))) {
                throw new Exception(
                    "Error: Stream caching does not support the conditional variable name '" . $var .
                    "' (only [a-zA-Z0-9_] characters are supported). Render this template without a " .
                    "cache directory, or rename the variable."
                );
            }

            $openEnd = strpos($block, ')}]') + 3;
            $body    = substr($block, $openEnd, strlen($block) - $openEnd - 7);

            if (str_contains($body, '[{else}]')) {
                $then = substr($body, 0, strpos($body, '[{else}]'));
                $else = substr($body, strpos($body, '[{else}]') + 8);
            } else {
                $then = $body;
                $else = null;
            }

            $condExpr = ($index !== null)
                ? "!empty(\$data['" . $var . "']['" . $index . "'])"
                : "!empty(\$data['" . $var . "'])";

            $php .= '<?php if (' . $condExpr . '): ?>' . self::compileScalars($then);
            if ($else !== null) {
                $php .= '<?php else: ?>' . self::compileScalars($else);
            }
            $php .= '<?php endif; ?>';

            $cursor = $condEnd + 7;
        }

        $php .= self::compileScalars(substr($template, $cursor));

        return $php;
    }

    /**
     * Compile [{var}] and [{var[index]}] placeholders in a literal text chunk
     *
     * Detection of what "looks like" a placeholder (the outer regex) is intentionally broader than
     * what's accepted (the [a-zA-Z0-9_] gate below): Parser::parseScalars() only ever substitutes a
     * '[{...}]' span that exactly matches a key actually present in $data, via plain str_replace() -
     * with no character restriction on the key itself, but also no effect whatsoever on a span that
     * doesn't correspond to a real key. Purely incidental bracket-shaped prose (e.g. "[{10% off}]",
     * stray "[{/if}]" fragments) is simply left untouched by Parser, regardless of what's in $data.
     *
     * A name this compiler can't safely lower to a $data[...] array-key expression (e.g. containing a
     * quote, dash, dot, or non-ASCII character) is therefore left as literal text here too, matching
     * Parser's real behavior for the common case of incidental/unsupported-character text. This is a
     * deliberate, accepted scope narrowing (Phase 1): the one remaining gap is a placeholder whose name
     * uses unsupported characters AND exactly matches a key actually present in $data - that specific
     * case still silently diverges from Parser (uncached would substitute it, cached leaves it literal).
     * Do NOT widen the accepted character class itself - it's the fix for a prior injection
     * vulnerability. Do NOT make this throw - see compileConditionals() for why [{if(...)}] is treated
     * differently (a narrow, deliberate syntax where false positives are effectively impossible, unlike
     * this broad bracket-shaped-text scan).
     *
     * @param  string $text
     * @return string
     */
    protected static function compileScalars(string $text): string
    {
        $php     = '';
        $cursor  = 0;
        $matches = [];

        preg_match_all(
            '/\[\{([^\[\]{}]+?)(?:\[([^\[\]{}]+)\])?\}\]/',
            $text,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($matches[0] as $i => $full) {
            [$matchText, $offset] = $full;
            $php .= self::phpEcho(substr($text, $cursor, $offset - $cursor));

            $name  = $matches[1][$i][0];
            $index = ($matches[2][$i][0] !== '') ? $matches[2][$i][0] : null;

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $name) || ($index !== null && !preg_match('/^[a-zA-Z0-9_]+$/', $index))) {
                // Unsupported characters: leave this span exactly as it appeared, as literal text.
                $php   .= self::phpEcho($matchText);
                $cursor = $offset + strlen($matchText);
                continue;
            }

            if ($index !== null) {
                $php .= self::compileIndexedDataLookup('$data', $name, $index, self::phpEcho('[{' . $name . '[' . $index . ']}]'));
            } else {
                $php .= self::compileDataLookup('$data', $name, self::phpEcho('[{' . $name . '}]'));
            }

            $cursor = $offset + strlen($matchText);
        }

        $php .= self::phpEcho(substr($text, $cursor));

        return $php;
    }

    /**
     * Compile a runtime-checked scalar lookup against a given PHP array expression, falling back to
     * pre-compiled PHP when the key is missing, non-scalar/non-stringable, or array/ArrayAccess-valued
     *
     * @param  string $arrayExpr PHP source for the array expression to check (e.g. '$data')
     * @param  string $name      an already-validated [a-zA-Z0-9_]+ key name
     * @param  string $fallback  pre-compiled PHP to emit when the lookup doesn't apply
     * @return string
     */
    protected static function compileDataLookup(string $arrayExpr, string $name, string $fallback): string
    {
        return "<?php if (array_key_exists('" . $name . "', " . $arrayExpr . ") && !is_array(" . $arrayExpr . "['" . $name . "']) "
            . "&& !(" . $arrayExpr . "['" . $name . "'] instanceof \\ArrayAccess) "
            . "&& (!is_object(" . $arrayExpr . "['" . $name . "']) || method_exists(" . $arrayExpr . "['" . $name . "'], '__toString'))): ?>"
            . "<?= " . $arrayExpr . "['" . $name . "'] ?>"
            . "<?php else: ?>" . $fallback . "<?php endif; ?>";
    }

    /**
     * Compile a runtime-checked indexed array lookup against a given PHP array expression, falling
     * back to pre-compiled PHP when the key/index doesn't apply. The indexed sibling of
     * compileDataLookup() - kept separate since the array-index form's guard (is_array(...) on the
     * outer key) differs from the plain form's stringability guard.
     *
     * @param  string $arrayExpr PHP source for the array expression to check (e.g. '$data')
     * @param  string $name      an already-validated [a-zA-Z0-9_]+ key name
     * @param  string $index     an already-validated [a-zA-Z0-9_]+ index name
     * @param  string $fallback  pre-compiled PHP to emit when the lookup doesn't apply
     * @return string
     */
    protected static function compileIndexedDataLookup(string $arrayExpr, string $name, string $index, string $fallback): string
    {
        return "<?php if (array_key_exists('" . $name . "', " . $arrayExpr . ") && is_array(" . $arrayExpr . "['" . $name . "'])): ?>"
            . "<?= " . $arrayExpr . "['" . $name . "']['" . $index . "'] ?? '' ?>"
            . "<?php else: ?>" . $fallback . "<?php endif; ?>";
    }

    /**
     * Safely emit a literal text chunk as an escaped PHP string echo
     *
     * @param  string $literal
     * @return string
     */
    protected static function phpEcho(string $literal): string
    {
        if ($literal === '') {
            return '';
        }
        return "<?php echo '" . addcslashes($literal, "'\\") . "'; ?>";
    }

}
