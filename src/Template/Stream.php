<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp
 * @category   Pop
 * @package    Pop_View
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @package    Pop_View
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
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
     * @return Stream
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
        if (file_exists($template)) {
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
     * Render view template string
     *
     * @return void
     */
    protected function renderTemplate()
    {
        if (null !== $this->data) {
            $this->output = $this->template;

            // Parse conditionals
            $this->parseConditionals();

            // Parse array values
            $this->parseArrays();

            // Parse scalar values
            $this->parseScalars();
        }
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
     * Parse conditionals in the template string
     *
     * @return void
     */
    protected function parseConditionals()
    {
        $matches = [];
        preg_match_all('/\[{if/mi', $this->template, $matches, PREG_OFFSET_CAPTURE);
        if (isset($matches[0]) && isset($matches[0][0])) {
            foreach ($matches[0] as $match) {
                $cond = substr($this->template, $match[1]);
                $cond = substr($cond, 0, strpos($cond, '[{/if}]') + 7);
                $var  = substr($cond, strpos($cond, '(') + 1);
                $var  = substr($var, 0, strpos($var, ')'));
                // If var is an array
                if (strpos($var, '[') !== false) {
                    $index  = substr($var, (strpos($var, '[') + 1));
                    $index  = substr($index, 0, strpos($index, ']'));
                    $var    = substr($var, 0, strpos($var, '['));
                    $varSet = (isset($this->data[$var][$index]));
                } else {
                    $index = null;
                    $varSet = (isset($this->data[$var]));
                }
                if (strpos($cond, '[{else}]') !== false) {
                    if ($varSet) {
                        $code = substr($cond, (strpos($cond, ')}]') + 3));
                        $code = substr($code, 0, strpos($code, '[{else}]'));
                        $code = (null !== $index) ?
                            str_replace('[{' . $var . '[' . $index . ']}]', $this->data[$var][$index], $code) :
                            str_replace('[{' . $var . '}]', $this->data[$var], $code);
                        $this->output = str_replace($cond, $code, $this->output);
                    } else {
                        $code = substr($cond, (strpos($cond, '[{else}]') + 8));
                        $code = substr($code, 0, strpos($code, '[{/if}]'));
                        $this->output = str_replace($cond, $code, $this->output);
                    }
                } else {
                    if ($varSet) {
                        $code = substr($cond, (strpos($cond, ')}]') + 3));
                        $code = substr($code, 0, strpos($code, '[{/if}]'));
                        $code = (null !== $index) ?
                            str_replace('[{' . $var . '[' . $index . ']}]', $this->data[$var][$index], $code) :
                            str_replace('[{' . $var . '}]', $this->data[$var], $code);
                        $this->output = str_replace($cond, $code, $this->output);
                    } else {
                        $this->output = str_replace($cond, '', $this->output);
                    }
                }
            }
        }
    }

    /**
     * Parse arrays in the template string
     *
     * @return void
     */
    protected function parseArrays()
    {
        foreach ($this->data as $key => $value) {
            if (is_array($value) || ($value instanceof \ArrayObject)) {
                $start = '[{' . $key . '}]';
                $end   = '[{/' . $key . '}]';
                if ((strpos($this->template, $start) !== false) && (strpos($this->template, $end) !== false)) {
                    $loopCode = substr($this->template, strpos($this->template, $start));
                    $loopCode = substr($loopCode, 0, (strpos($loopCode, $end) + strlen($end)));

                    $loop = str_replace($start, '', $loopCode);
                    $loop = str_replace($end, '', $loop);
                    $outputLoop = '';
                    $i = 0;
                    foreach ($value as $ky => $val) {
                        // Handle nested array
                        if (is_array($val) || ($val instanceof \ArrayObject)) {
                            $s = '[{' . $ky . '}]';
                            $e = '[{/' . $ky . '}]';
                            if ((strpos($loop, $s) !== false) && (strpos($loop, $e) !== false)) {
                                $l = $loop;
                                $lCode = substr($l, strpos($l, $s));
                                $lCode = substr($lCode, 0, (strpos($lCode, $e) + strlen($e)));

                                $l = str_replace($s, '', $lCode);
                                $l = str_replace($e, '', $l);
                                $oLoop = '';
                                foreach ($val as $k => $v) {
                                    // Check is value is stringable
                                    if ((is_object($v) && method_exists($v, '__toString')) || (!is_object($v) && !is_array($v))) {
                                        $oLoop .= str_replace(['[{key}]', '[{value}]'], [$k, $v], $l);
                                    }
                                }
                                $outputLoop = str_replace($lCode, $oLoop, $loop);
                            }
                        // Handle scalar
                        } else {
                            // Check is value is stringable
                            if ((is_object($val) && method_exists($val, '__toString')) || (!is_object($val) && !is_array($val))) {
                                $outputLoop .= str_replace(['[{key}]', '[{value}]'], [$ky, $val], $loop);
                            }
                        }
                        $i++;
                        if ($i < count($value)) {
                            $outputLoop .= PHP_EOL;
                        }
                    }
                    $this->output = str_replace($loopCode, $outputLoop, $this->output);
                }
            }
        }
    }

    /**
     * Parse scalar values in the template string
     *
     * @return void
     */
    protected function parseScalars()
    {
        foreach ($this->data as $key => $value) {
            if (is_array($value) && (strpos($this->output, '[{' . $key . '[') !== false)) {
                $matches = [];
                preg_match_all('/\[{' . $key .'\[/mi', $this->output, $matches, PREG_OFFSET_CAPTURE);
                if (isset($matches[0]) && isset($matches[0][0])) {
                    $indices = [];
                    foreach ($matches[0] as $match) {
                        $i = substr($this->output, $match[1] + (strlen($key) + 3));
                        $i = substr($i, 0, strpos($i, ']'));
                        $indices[] = $i;
                    }
                    foreach ($indices as $i) {
                        if (isset($value[$i])) {
                            $this->output = str_replace('[{' . $key . '[' . $i . ']}]', $value[$i], $this->output);
                        } else {
                            $this->output = str_replace('[{' . $key . '[' . $i . ']}]', '', $this->output);
                        }
                    }
                }
            } else if (!is_array($value) && !($value instanceof \ArrayObject)) {
                // Check is value is stringable
                if ((is_object($value) && method_exists($value, '__toString')) || (!is_object($value) && !is_array($value))) {
                    $this->output = str_replace('[{' . $key . '}]', $value, $this->output);
                }
            }
        }
    }

}
