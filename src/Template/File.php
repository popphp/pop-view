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
 * View file template class
 *
 * @category   Pop
 * @package    Pop\View
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
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
    public function __construct($template)
    {
        $this->setTemplate($template);
    }

    /**
     * Set view template
     *
     * @param  string $template
     * @throws Exception
     * @return File
     */
    public function setTemplate($template)
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
     * Render view template file
     *
     * @throws \Exception
     * @return void
     */
    protected function renderTemplate()
    {
        if (null !== $this->data) {
            foreach ($this->data as $key => $value) {
                ${$key} = $value;
            }
        }

        try {
            ob_start();
            include $this->template;
            $this->output = ob_get_clean();
        } catch (\Exception $e) {
            ob_clean();
            throw $e;
        }
    }

}
