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
namespace Pop\View\Template;

/**
 * View stream template class
 *
 * @category   Pop
 * @package    Pop\View
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Stream extends AbstractTemplate
{

    /**
     * View template file
     * @var ?string
     */
    protected ?string $file = null;

    /**
     * View parent template
     * @var ?Stream
     */
    protected ?Stream $parent = null;

    /**
     * Block templates
     * @var array
     */
    protected array $blocks = [];

    /**
     * Master template
     * @var ?string
     */
    protected ?string $master = null;

    /**
     * Master block templates
     * @var array
     */
    protected array $masterBlocks = [];

    /**
     * Cache directory for compiled templates
     * @var ?string
     */
    protected ?string $cacheDir = null;

    /**
     * Files that contributed to the resolved template (path => mtime),
     * across the full @extends/@include chain
     * @var array
     */
    protected array $contributingFiles = [];

    /**
     * Constructor
     *
     * Instantiate the view stream template object
     *
     * @param string  $template
     * @param ?string $cacheDir
     */
    public function __construct(string $template, ?string $cacheDir = null)
    {
        if ($cacheDir !== null) {
            $this->setCacheDir($cacheDir);
        }

        $this->setTemplate($template);

        // Parse parent template
        $this->parseParent();

        // Parse includes
        $this->parseIncludes();

        // Parse blocks
        $this->parseBlocks();
    }

    /**
     * Set view template with auto-detect
     *
     * @param  string $template
     * @return static
     */
    public function setTemplate(string $template): static
    {
        if ((strlen($template) <= 255) && file_exists($template)) {
            $this->template = file_get_contents($template);
            $this->file     = $template;
            $this->contributingFiles[$template] = filemtime($template);
        } else {
            $this->template = $template;
        }
        return $this;
    }

    /**
     * Set master
     *
     * @param  string $master
     * @return static
     */
    public function setMaster(string $master): static
    {
        $this->master = $master;
        return $this;
    }

    /**
     * Get blocks
     *
     * @return array
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Get block by name
     *
     * @param  string $name
     * @return string|null
     */
    public function getBlock(string $name): string|null
    {
        return $this->blocks[$name] ?? null;
    }

    /**
     * Get master blocks
     *
     * @return array
     */
    public function getMasterBlocks(): array
    {
        return $this->masterBlocks;
    }

    /**
     * Get master block by name
     *
     * @param  string $name
     * @return string|null
     */
    public function getMasterBlock(string $name): string|null
    {
        return $this->masterBlocks[$name] ?? null;
    }

    /**
     * Set blocks
     *
     * @param  array $blocks
     * @return static
     */
    public function setBlocks(array $blocks): static
    {
        $this->blocks = $blocks;
        return $this;
    }

    /**
     * Set block
     *
     * @param  string $name
     * @param  string $value
     * @return static
     */
    public function setBlock($name, $value): static
    {
        $this->blocks[$name] = $value;
        return $this;
    }

    /**
     * Set master blocks
     *
     * @param  array $blocks
     * @return static
     */
    public function setMasterBlocks(array $blocks): static
    {
        $this->masterBlocks = $blocks;
        return $this;
    }

    /**
     * Set master block
     *
     * @param  string $name
     * @param  string $value
     * @return static
     */
    public function setMasterBlock(string $name, string $value): static
    {
        $this->masterBlocks[$name] = $value;
        return $this;
    }

    /**
     * Get parent
     *
     * @return static|null
     */
    public function getParent(): static|null
    {
        return $this->parent;
    }

    /**
     * Get master
     *
     * @return string
     */
    public function getMaster(): string
    {
        return $this->master;
    }

    /**
     * Determine if the template stream is from a file
     *
     * @return bool
     */
    public function isFile(): bool
    {
        return ($this->file !== null);
    }

    /**
     * Determine if the template stream is from a string
     *
     * @return bool
     */
    public function isString(): bool
    {
        return ($this->file === null);
    }

    /**
     * Set cache directory for compiled templates
     *
     * @param  string $dir
     * @return static
     */
    public function setCacheDir(string $dir): static
    {
        $this->cacheDir = $dir;
        return $this;
    }

    /**
     * Get cache directory
     *
     * @return ?string
     */
    public function getCacheDir(): ?string
    {
        return $this->cacheDir;
    }

    /**
     * Has a cache directory been configured
     *
     * @return bool
     */
    public function hasCacheDir(): bool
    {
        return ($this->cacheDir !== null);
    }

    /**
     * Get files (path => mtime) that contributed to the resolved template
     *
     * @return array
     */
    public function getContributingFiles(): array
    {
        return $this->contributingFiles;
    }

    /**
     * Render the view and return the output
     *
     * @param  ?array $data
     * @return string
     */
    public function render(?array $data = null): string
    {
        if ($data !== null) {
            $this->data = $data;
        }

        if ($this->hasCacheDir()) {
            $this->renderCompiled();
        } else {
            $this->renderTemplate();
        }

        return $this->output;
    }

    /**
     * Parse template parent/child blocks
     *
     * @return void
     */
    protected function parseParent(): void
    {
        $matches = [];
        preg_match_all('/\{\{\@extends(.*?)\}\}/s', $this->template, $matches);

        if (isset($matches[0]) && isset($matches[0][0])) {
            foreach ($matches[0] as $key => $match) {
                $tmpl = trim($matches[1][$key]);
                self::assertSafeTemplatePath($tmpl);
                if ($tmpl != $this->file) {
                    $dir            = ($this->isFile()) ? dirname($this->file) . DIRECTORY_SEPARATOR : null;
                    $this->template = str_replace($match, '', $this->template);
                    $this->parent   = new Stream($dir . $tmpl);
                    $this->contributingFiles = array_merge($this->contributingFiles, $this->parent->getContributingFiles());
                }
            }
        }
    }

    /**
     * Parse template includes
     *
     * @return void
     */
    protected function parseIncludes(): void
    {
        $matches = [];
        preg_match_all('/\{\{\@include(.*?)\}\}/s', $this->template, $matches);

        if (isset($matches[0]) && isset($matches[0][0])) {
            foreach ($matches[0] as $key => $match) {
                $tmpl = trim($matches[1][$key]);
                self::assertSafeTemplatePath($tmpl);
                if ($tmpl != $this->file) {
                    $dir  = ($this->isFile()) ? dirname($this->file) . DIRECTORY_SEPARATOR : null;
                    $view = new Stream($dir . $tmpl);
                    $this->template = str_replace($match, $view->getTemplate(), $this->template);
                    $this->contributingFiles = array_merge($this->contributingFiles, $view->getContributingFiles());
                }
            }
        }
    }

    /**
     * Guard against path traversal / absolute-path escapes in @extends/@include targets
     *
     * @param  string $tmpl
     * @throws Stream\Exception
     * @return void
     */
    protected static function assertSafeTemplatePath(string $tmpl): void
    {
        if ((str_starts_with($tmpl, '/')) || (str_starts_with($tmpl, '\\')) ||
            (preg_match('/^[a-zA-Z]:[\/\\\\]/', $tmpl)) ||
            (preg_match('/(^|[\/\\\\])\.\.($|[\/\\\\])/', $tmpl))) {
            throw new Stream\Exception(
                "Error: The @extends/@include template path '" . $tmpl . "' is not allowed " .
                "(absolute paths and '..' segments are not permitted)."
            );
        }
    }

    /**
     * Parse template parent/child blocks
     *
     * @return void
     */
    protected function parseBlocks(): void
    {
        $matches = [];
        preg_match_all('/\{\{(.*?)\{\{\/(.*?)\}\}/s', $this->template, $matches);

        if (isset($matches[0]) && isset($matches[0][0])) {
            foreach ($matches[0] as $match) {
                $name    = substr($match, 2);
                $name    = substr($name, 0, strpos($name, '}}'));
                $content = substr($match, (strpos($match, '}}') + 2));
                $content = substr($content, 0, strpos($content, '{{/'));
                $this->blocks[$name] = $content;
            }
        }

        $parent = $this->parent;

        if ($parent === null) {
            $this->setMaster($this->template);
            $this->setMasterBlocks($this->blocks);
        }

        while ($parent !== null) {
            $this->setMaster($parent->getMaster());
            $this->setMasterBlocks($parent->getMasterBlocks());

            foreach ($this->blocks as $block => $tmpl) {
                $this->setBlock($block, str_replace('{{parent}}', $parent->getBlock($block), $tmpl));
            }

            $parent = $parent->getParent();
        }

        $this->template = $this->master;
        foreach ($this->blocks as $block => $tmpl) {
            $this->template = str_replace(
                '{{' . $block . '}}' . $this->getMasterBlock($block) . '{{/' . $block . '}}',
                $tmpl,
                $this->template
            );
        }
    }

    /**
     * Render view template string
     *
     * @return void
     */
    protected function renderTemplate(): void
    {
        if ($this->data !== null) {
            $this->output = $this->template;

            // Parse array values
            $this->output = Stream\Parser::parseArrays($this->template, $this->data, $this->output);

            // Parse conditionals
            $this->output = Stream\Parser::parseConditionals($this->template, $this->data, $this->output);

            // Parse scalar values
            $this->output = Stream\Parser::parseScalars($this->data, $this->output);
        }
    }

    /**
     * Render the view template string via the compiled/cached path
     *
     * @return void
     */
    protected function renderCompiled(): void
    {
        $cache = new Stream\Cache($this->cacheDir);
        $key   = Stream\Cache::key($this->template);

        $newestMtime = empty($this->contributingFiles) ? 0 : max($this->contributingFiles);

        $source = $cache->get($key, $newestMtime);
        if ($source === null) {
            $source = Stream\Compiler::compile($this->template);
            $cache->put($key, $source);
        }

        $data = $this->data;

        try {
            ob_start();
            include $cache->path($key);
            $this->output = ob_get_clean();
        } catch (\Throwable $e) {
            // ob_end_clean() (not ob_clean()) is required here: ob_clean() only empties the buffer's
            // contents but leaves it on PHP's output-buffer stack, so a caller that catches this
            // exception and continues execution is left with a dangling output buffer that silently
            // swallows subsequent unrelated output (ob_get_level() grows unboundedly across repeated
            // catches). Compiled loop bodies (Phase 2) are the first thing on this path that can throw
            // at runtime (the ArrayAccess/ArrayObject guards), making this reachable for the first time.
            ob_end_clean();
            throw $e;
        }
    }

}
