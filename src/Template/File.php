<?php
declare(strict_types=1);
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
 * View file template class
 *
 * @category   Pop
 * @package    Pop\View
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class File extends AbstractTemplate
{

    /**
     * Constructor
     *
     * Instantiate the view file template object
     *
     * @param  string $template
     */
    public function __construct(string $template)
    {
        $this->setTemplate($template);
    }

    /**
     * Set view template
     *
     * @param  string $template
     * @throws Exception
     * @return static
     */
    public function setTemplate(string $template): static
    {
        if (!file_exists($template)) {
            throw new Exception("Error: The template file '" . $template . "' does not exist.");
        }
        $this->template = $template;

        return $this;
    }

    /**
     * Render the view and return the output
     *
     * @param  ?array $data
     * @throws \Exception
     * @return string
     */
    public function render(?array $data = null): string
    {
        if ($data !== null) {
            $this->data = $data;
        }
        $this->renderTemplate();
        return $this->output;
    }

    /**
     * Render view template file
     *
     * @return void
     */
    protected function renderTemplate(): void
    {
        foreach ($this->data as $key => $value) {
            ${$key} = $value;
        }

        try {
            ob_start();
            include $this->template;
            $this->output = ob_get_clean();
        } catch (\Throwable $e) {
            // ob_end_clean() (not ob_clean()) is required here: ob_clean() only empties the buffer's
            // contents but leaves it on PHP's output-buffer stack, so a caller that catches this
            // exception and continues execution is left with a dangling output buffer that silently
            // swallows subsequent unrelated output. Catching \Throwable (not \Exception) matters too -
            // a raw PHP/PHTML template can throw an Error (e.g. a typo'd function call), which would
            // otherwise skip this cleanup entirely and leak the buffer via a different path.
            ob_end_clean();
            throw $e;
        }
    }

}
