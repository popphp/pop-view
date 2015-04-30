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
 * View template abstract class
 *
 * @category   Pop
 * @package    Pop_View
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
abstract class AbstractTemplate implements TemplateInterface
{

    /**
     * View template
     * @var string
     */
    protected $template = null;

    /**
     * View data
     * @var array
     */
    protected $data = [];

    /**
     * View output string
     * @var string
     */
    protected $output = null;

    /**
     * Get view template
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set view template
     *
     * @param  string $template
     * @return AbstractTemplate
     */
    abstract public function setTemplate($template);

    /**
     * Render the view and return the output
     *
     * @param  array $data
     * @throws Exception
     * @return string
     */
    abstract public function render(array $data);

    /**
     * Render view template file
     *
     * @return void
     */
    abstract protected function renderTemplate();

}