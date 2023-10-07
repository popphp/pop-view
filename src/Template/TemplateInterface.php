<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @package    Pop\View
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
interface TemplateInterface
{

    /**
     * Get view template
     *
     * @return string
     */
    public function getTemplate(): string;

    /**
     * Set view template
     *
     * @param  string $template
     * @return TemplateInterface
     */
    public function setTemplate(string $template): TemplateInterface;

    /**
     * Render the view and return the output
     *
     * @param  array $data
     * @return string
     */
    public function render(array $data): string;

}