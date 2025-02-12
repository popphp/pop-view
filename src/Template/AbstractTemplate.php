<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\View\Template;

/**
 * View template abstract class
 *
 * @category   Pop
 * @package    Pop\View
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    4.0.3
 */
abstract class AbstractTemplate implements TemplateInterface
{

    /**
     * View template
     * @var ?string
     */
    protected ?string $template = null;

    /**
     * View data
     * @var array
     */
    protected array $data = [];

    /**
     * View output string
     * @var ?string
     */
    protected ?string $output = null;

    /**
     * Get view template
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Set view template
     *
     * @param  string $template
     * @return AbstractTemplate
     */
    abstract public function setTemplate(string $template): AbstractTemplate;

    /**
     * Render the view and return the output
     *
     * @param  array $data
     * @return string
     */
    abstract public function render(array $data): string;

    /**
     * Render view template file
     *
     * @return void
     */
    abstract protected function renderTemplate(): void;

}
