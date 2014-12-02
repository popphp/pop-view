<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp2
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
 * View template interface
 *
 * @category   Pop
 * @package    Pop_View
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2014 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
interface TemplateInterface
{

    /**
     * Get view template
     *
     * @return string
     */
    public function getTemplate();

    /**
     * Set view template
     *
     * @param  string $template
     * @return TemplateInterface
     */
    public function setTemplate($template);

    /**
     * Render the view and return the output
     *
     * @param  array $data
     * @throws Exception
     * @return string
     */
    public function render(array $data);

}