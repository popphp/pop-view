<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class View implements \ArrayAccess
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
     * Filters
     * @var array
     */
    protected $filters = [];

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
     * @param  mixed $template
     * @param  array $data
     * @return View
     */
    public function __construct($template = null, array $data = null)
    {
        if (null !== $template) {
            $this->setTemplate($template);
        }
        if (null !== $data) {
            $this->setData($data);
        }
    }

    /**
     * Has a view template
     *
     * @return boolean
     */
    public function hasTemplate()
    {
        return (null !== $this->template);
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
     * Is view template a file
     *
     * @return boolean
     */
    public function isFile()
    {
        return ((null !== $this->template) && ($this->template instanceof Template\File));
    }

    /**
     * Is view template a stream
     *
     * @return boolean
     */
    public function isStream()
    {
        return ((null !== $this->template) && ($this->template instanceof Template\Stream));
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
     * @param  mixed $template
     * @return View
     */
    public function setTemplate($template)
    {
        if (!($template instanceof Template\TemplateInterface)) {
            // If a native PHP file template
            if (((substr($template, -6) == '.phtml') ||
                    (substr($template, -5, 4) == '.php') ||
                    (substr($template, -4) == '.php')) && (file_exists($template))) {
                $template = new Template\File($template);
            // If a string template, or a string template from a non-PHP file
            } else {
                $template = new Template\Stream($template);
            }
        }
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
     * Add filter
     *
     * @param  mixed $call
     * @param  mixed $params
     * @return View
     */
    public function addFilter($call, $params = null)
    {
        $this->filters[] = [
            'call'   => $call,
            'params' => $params
        ];
        return $this;
    }

    /**
     * Add filters
     *
     * @param  array $filters
     * @throws Exception
     * @return View
     */
    public function addFilters(array $filters)
    {
        foreach ($filters as $filter) {
            if (!isset($filter['call'])) {
                throw new Exception('Error: The \'call\' key must be set.');
            }
            $params = (isset($filter['params'])) ? $filter['params'] : null;
            $this->addFilter($filter['call'], $params);
        }
        return $this;
    }

    /**
     * Clear filters
     *
     * @return View
     */
    public function clearFilters()
    {
        $this->filters = [];
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
     * Filter of data with the filters that have been set
     *
     * @return View
     */
    public function filter()
    {
        $this->filterData();
        return $this;
    }

    /**
     * Render and output the view
     *
     * @return mixed
     */
    public function output()
    {
        echo $this->render();
    }

    /**
     * Render the view
     *
     * @throws Exception
     * @return mixed
     */
    public function render()
    {
        if (null === $this->template) {
            throw new Exception('A template asset has not been assigned.');
        }

        $this->filterData();

        $this->output = $this->template->render($this->data);
        return $this->output;
    }

    /**
     * Return rendered view as string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render();
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

    /**
     * ArrayAccess offsetGet
     *
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * ArrayAccess offsetSet
     *
     * @param  mixed $offset
     * @param  mixed $value
     * @throws Exception
     * @return mixed
     */
    public function offsetSet($offset, $value)
    {
        return $this->set($offset, $value);
    }

    /**
     * ArrayAccess offsetExists
     *
     * @param  mixed $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * ArrayAccess offsetUnset
     *
     * @param  mixed $offset
     * @throws Exception
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

    /**
     * Filter data
     *
     * @return void
     */
    protected function filterData()
    {
        if (count($this->filters) > 0) {
            foreach ($this->filters as $filter) {
                $params = [];
                if (isset($filter['params'])) {
                    $params = (!is_array($filter['params'])) ? [$filter['params']] : $filter['params'];
                }
                $this->applyFilter($this->data, $filter['call'], $params);
            }
        }
    }

    /**
     * Execute filter over data
     *
     * @param  array  $array
     * @param  string $call
     * @param  array  $params
     * @return void
     */
    protected function applyFilter(&$array, $call, $params = [])
    {
        array_walk_recursive($array, function(&$value, $key, $userdata) {
            $params = array_merge([$value], $userdata[1]);
            $value = call_user_func_array($userdata[0], $params);
        }, [$call, $params]);
    }

}
