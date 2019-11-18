<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
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
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class Stream extends AbstractTemplate
{

    /**
     * View template file
     * @var string
     */
    protected $file = null;

    /**
     * View parent template
     * @var Stream
     */
    protected $parent = null;

    /**
     * Block templates
     * @var array
     */
    protected $blocks = [];

    /**
     * Master template
     * @var string
     */
    protected $master = null;

    /**
     * Master block templates
     * @var array
     */
    protected $masterBlocks = [];

    /**
     * Constructor
     *
     * Instantiate the view stream template object
     *
     * @param  string $template
     */
    public function __construct($template)
    {
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
     * @return Stream
     */
    public function setTemplate($template)
    {
        if ((strlen($template) <= 255) && file_exists($template)) {
            $this->template = file_get_contents($template);
            $this->file     = $template;
        } else {
            $this->template = $template;
        }
        return $this;
    }

    /**
     * Set master
     *
     * @param  string $master
     * @return Stream
     */
    public function setMaster($master)
    {
        $this->master = $master;
        return $this;
    }

    /**
     * Get blocks
     *
     * @return array
     */
    public function getBlocks()
    {
        return $this->blocks;
    }

    /**
     * Get block by name
     *
     * @param  string $name
     * @return string
     */
    public function getBlock($name)
    {
        return (isset($this->blocks[$name])) ? $this->blocks[$name] : null;
    }

    /**
     * Get master blocks
     *
     * @return array
     */
    public function getMasterBlocks()
    {
        return $this->masterBlocks;
    }

    /**
     * Get master block by name
     *
     * @param  string $name
     * @return string
     */
    public function getMasterBlock($name)
    {
        return (isset($this->masterBlocks[$name])) ? $this->masterBlocks[$name] : null;
    }

    /**
     * Set blocks
     *
     * @param  array $blocks
     * @return Stream
     */
    public function setBlocks(array $blocks)
    {
        $this->blocks = $blocks;
        return $this;
    }

    /**
     * Set block
     *
     * @param  string $name
     * @param  string $value
     * @return Stream
     */
    public function setBlock($name, $value)
    {
        $this->blocks[$name] = $value;
        return $this;
    }

    /**
     * Set master blocks
     *
     * @param  array $blocks
     * @return Stream
     */
    public function setMasterBlocks(array $blocks)
    {
        $this->masterBlocks = $blocks;
        return $this;
    }

    /**
     * Set master block
     *
     * @param  string $name
     * @param  string $value
     * @return Stream
     */
    public function setMasterBlock($name, $value)
    {
        $this->masterBlocks[$name] = $value;
        return $this;
    }

    /**
     * Get parent
     *
     * @return Stream
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Get master
     *
     * @return string
     */
    public function getMaster()
    {
        return $this->master;
    }

    /**
     * Determine if the template stream is from a file
     *
     * @return boolean
     */
    public function isFile()
    {
        return (null !== $this->file);
    }

    /**
     * Determine if the template stream is from a string
     *
     * @return boolean
     */
    public function isString()
    {
        return (null === $this->file);
    }

    /**
     * Render the view and return the output
     *
     * @param  array $data
     * @return string
     */
    public function render(array $data = null)
    {
        if (null !== $data) {
            $this->data = $data;
        }
        $this->renderTemplate();
        return $this->output;
    }

    /**
     * Parse template parent/child blocks
     *
     * @return void
     */
    protected function parseParent()
    {
        $matches = [];
        preg_match_all('/\{\{\@extends(.*?)\}\}/s', $this->template, $matches);

        if (isset($matches[0]) && isset($matches[0][0])) {
            foreach ($matches[0] as $key => $match) {
                $tmpl = trim($matches[1][$key]);
                if ($tmpl != $this->file) {
                    $dir            = ($this->isFile()) ? dirname($this->file) . DIRECTORY_SEPARATOR : null;
                    $this->template = str_replace($match, '', $this->template);
                    $this->parent   = new Stream($dir . $tmpl);
                }
            }
        }
    }

    /**
     * Parse template includes
     *
     * @return void
     */
    protected function parseIncludes()
    {
        $matches = [];
        preg_match_all('/\{\{\@include(.*?)\}\}/s', $this->template, $matches);

        if (isset($matches[0]) && isset($matches[0][0])) {
            foreach ($matches[0] as $key => $match) {
                $tmpl = trim($matches[1][$key]);
                if ($tmpl != $this->file) {
                    $dir  = ($this->isFile()) ? dirname($this->file) . DIRECTORY_SEPARATOR : null;
                    $view = new Stream($dir . $tmpl);
                    $this->template = str_replace($match, $view->render($this->data), $this->template);
                }
            }
        }
    }

    /**
     * Parse template parent/child blocks
     *
     * @return void
     */
    protected function parseBlocks()
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

        if (null === $parent) {
            $this->setMaster($this->template);
            $this->setMasterBlocks($this->blocks);
        }

        while (null !== $parent) {
            $this->setMaster($parent->getMaster());
            $this->setMasterBlocks($parent->getMasterBlocks());

            foreach ($this->blocks as $block => $tmpl) {
                $this->setBlock('header', str_replace('{{parent}}', $parent->getBlock($block), $tmpl));
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
    protected function renderTemplate()
    {
        if (null !== $this->data) {
            $this->output = $this->template;

            // Parse array values
            $this->output = Stream\Parser::parseArrays($this->template, $this->data, $this->output);

            // Parse conditionals
            $this->output = Stream\Parser::parseConditionals($this->template, $this->data, $this->output);

            // Parse scalar values
            $this->output = Stream\Parser::parseScalars($this->data, $this->output);
        }
    }

}
