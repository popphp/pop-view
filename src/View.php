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
namespace Pop\View;

/**
 * View class
 *
 * @category   Pop
 * @package    Pop_View
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2014 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0a
 */
class View
{

    /**
     * View template object
     * @var Template\TemplateInterface
     */
    protected $template = null;

    /**
     * Model data
     * @var array
     */
    protected $data = [];

    /**
     * View output string
     * @var string
     */
    protected $output = null;

    /**
     * Constructor
     *
     * Instantiate the view object
     *
     * @param  Template\TemplateInterface $template
     * @param  array                      $data
     * @return View
     */
    public function __construct(Template\TemplateInterface $template = null, array $data = null)
    {
        if (null !== $template) {
            $this->setTemplate($template);
        }
        if (null !== $data) {
            $this->setData($data);
        }
    }

    /**
     * Get view template
     *
     * @return Template\TemplateInterface
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Get all model data
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get data
     *
     * @param  string $key
     * @return mixed
     */
    public function get($key)
    {
        return (isset($this->data[$key])) ? $this->data[$key] : null;
    }

    /**
     * Set view template
     *
     * @param  Template\TemplateInterface $template
     * @return View
     */
    public function setTemplate(Template\TemplateInterface $template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Set all model data
     *
     * @param  array $data
     * @return View
     */
    public function setData(array $data = [])
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set model data
     *
     * @param  string $name
     * @param  mixed  $value
     * @return View
     */
    public function set($name, $value)
    {
        $this->data[$name] = $value;
        return $this;
    }

    /**
     * Merge new model data
     *
     * @param  array $data
     * @return View
     */
    public function merge(array $data)
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Render the view.
     *
     * @param  boolean $ret
     * @throws Exception
     * @return mixed
     */
    public function render($ret = false)
    {
        if (null === $this->template) {
            throw new Exception('A template asset has not been assigned.');
        }

        $this->output = $this->template->render($this->data);

        if ($ret) {
            return $this->output;
        } else {
            echo $this->output;
        }
    }

    /**
     * Return rendered view as string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render(true);
    }

    /**
     * Get method to return the value of data[$name].
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * Set method to set the property to the value of data[$name].
     *
     * @param  string $name
     * @param  mixed $value
     * @return mixed
     */
    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }

    /**
     * Return the isset value of data[$name].
     *
     * @param  string $name
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * Unset data[$name].
     *
     * @param  string $name
     * @return void
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }

}
