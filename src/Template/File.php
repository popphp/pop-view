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
 * View file template class
 *
 * @category   Pop
 * @package    Pop_View
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
class File extends AbstractTemplate
{

    /**
     * Constructor
     *
     * Instantiate the view file template object
     *
     * @param  string $template
     * @return File
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
            throw new Exception('Error: The template file does not exist.');
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
    public function render(array $data = [])
    {
        $this->data = $data;
        $this->renderTemplate();
        return $this->output;
    }

    /**
     * Render view template file
     *
     * @return void
     */
    protected function renderTemplate()
    {
        if (null !== $this->data) {
            foreach ($this->data as $key => $value) {
                ${$key} = $value;
            }
        }

        ob_start();
        include $this->template;
        $this->output = ob_get_clean();
    }

}
