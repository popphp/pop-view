<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp
 * @category   Pop
 * @package    Pop_View
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2014 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\View\Template;

/**
 * View block class
 *
 * @category   Pop
 * @package    Pop_View
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2014 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
class Block
{

    /**
     * Template string
     * @var string
     */
    protected $template = null;

    /**
     * View data
     * @var array
     */
    protected $data = null;

    /**
     * Template blocks
     * @var array
     */
    protected $blocks = [];

    /**
     * Template parent
     * @var Block
     */
    protected $parent = null;

    /**
     * Block constructor
     *
     * @param  string $template
     * @param  array  $data
     * @return Block
     */
    public function __construct($template, array $data)
    {
        $this->setTemplate($template);
        $this->setData($data);

        if (self::hasParent($template)) {
            $this->parent   = new Block(file_get_contents(self::getParent($template)), $this->data);
            $this->template = ltrim(substr($this->template, (strpos($this->template, '}') + 1)));
        }

        $this->parseBlocks();
        $this->parseTemplate();
    }

    /**
     * Does template have parent
     *
     * @param  string $template
     * @return boolean
     */
    public static function hasParent($template)
    {
        return (strpos($template, '{@extends') !== false);
    }

    /**
     * Get template parent
     *
     * @param  string $template
     * @return string
     */
    public static function getParent($template)
    {
        $parent = substr($template, (strpos($template, '{@extends ') + 10));
        $parent = trim(substr($parent, 0, strpos($parent, '}')));
        return $parent;
    }

    /**
     * Does template have blocks
     *
     * @param  string $template
     * @return boolean
     */
    public static function hasBlocks($template)
    {
        return (strpos($template, '{{/') !== false);
    }

    /**
     * Set template
     *
     * @param  string $template
     * @return Block
     */
    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Set data
     *
     * @param  array  $data
     * @return Block
     */
    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get template blocks
     *
     * @return array
     */
    public function getBlocks()
    {
        return $this->blocks;
    }

    /**
     * Get template
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Parse template blocks
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
                $this->blocks[$name] = [
                    'block'   => $match,
                    'content' => $content
                ];
            }
        }
    }

    /**
     * Parse template
     *
     * @return void
     */
    protected function parseTemplate()
    {
        foreach ($this->blocks as $name => $block) {
            $view = new Stream($block['content']);
            $data = $this->data;

            if (null !== $this->parent) {
                $blocks = $this->parent->getBlocks();
                $data['parent'] = $blocks[$name]['rendered'];
            }

            $this->blocks[$name]['rendered'] = $view->render($data);
            $this->template = str_replace($block['block'], $this->blocks[$name]['rendered'], $this->template);
        }
    }

}
