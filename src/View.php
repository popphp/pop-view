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
namespace Pop\View;

use Pop\Filter\FilterableTrait;
use Pop\Utils;

/**
 * View class
 *
 * @category   Pop
 * @package    Pop\View
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class View extends Utils\ArrayObject
{

    use FilterableTrait;

    /**
     * View template object
     * @var ?Template\TemplateInterface
     */
    protected ?Template\TemplateInterface $template = null;

    /**
     * Model data
     * @var mixed
     */
    protected mixed $data = [];

    /**
     * View output string
     * @var ?string
     */
    protected ?string $output = null;

    /**
     * Constructor
     *
     * Instantiate the view object
     *
     * @param  mixed  $template
     * @param  ?array $data
     * @param  mixed  $filters
     */
    public function __construct(mixed $template = null, ?array $data = null, mixed $filters = null)
    {
        if ($template !== null) {
            $this->setTemplate($template);
        }
        if ($data !== null) {
            parent::__construct($data);
        }
        if ($filters !== null) {
            if (is_array($filters)) {
                $this->addFilters($filters);
            } else {
                $this->addFilter($filters);
            }
        }
    }

    /**
     * Has a view template
     *
     * @return bool
     */
    public function hasTemplate(): bool
    {
        return ($this->template !== null);
    }

    /**
     * Get view template
     *
     * @return Template\TemplateInterface
     */
    public function getTemplate(): Template\TemplateInterface
    {
        return $this->template;
    }

    /**
     * Get rendered output
     *
     * @return string
     */
    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Is view template a file
     *
     * @return bool
     */
    public function isFile(): bool
    {
        return (($this->template !== null) && ($this->template instanceof Template\File));
    }

    /**
     * Is view template a stream
     *
     * @return bool
     */
    public function isStream(): bool
    {
        return (($this->template !== null) && ($this->template instanceof Template\Stream));
    }

    /**
     * Get all model data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get data
     *
     * @param  string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Set view template
     *
     * @param  mixed $template
     * @return static
     */
    public function setTemplate(mixed $template): static
    {
        if (!($template instanceof Template\TemplateInterface)) {
            // If a native PHP file template
            if (((str_ends_with($template, '.phtml')) ||
                    (substr($template, -5, 4) == '.php') ||
                    (str_ends_with($template, '.php'))) && (strlen($template) <= 255) && (file_exists($template))) {
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
     * @return static
     */
    public function setData(array $data = []): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set model data
     *
     * @param  string $name
     * @param  mixed  $value
     * @return static
     */
    public function set(string $name, mixed $value): static
    {
        $this->data[$name] = $value;
        return $this;
    }

    /**
     * Merge new model data
     *
     * @param  array $data
     * @return static
     */
    public function merge(array $data): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Filter values
     *
     * @param  mixed $values
     * @return mixed
     */
    public function filter(mixed $values): mixed
    {
        foreach ($this->filters as $filter) {
            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    $values[$key] = $filter->filter($value, $key);
                }
            } else {
                $values = [$filter->filter($values)];
            }
        }

        return $values;
    }

    /**
     * Render the view
     *
     * @throws Exception|Template\Exception
     * @return ?string
     */
    public function render(): ?string
    {
        if ($this->template === null) {
            throw new Exception('A template asset has not been assigned.');
        }

        if ($this->hasFilters()) {
            $this->data = $this->filter($this->data);
        }

        $this->output = $this->template->render($this->data);
        return $this->output;
    }

    /**
     * Return rendered view as string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->render();
    }

}
