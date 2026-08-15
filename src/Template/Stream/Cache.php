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
namespace Pop\View\Template\Stream;

/**
 * View stream template compiled-cache class
 *
 * @category   Pop
 * @package    Pop\View
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Cache
{

    /**
     * Cache directory
     * @var string
     */
    protected string $dir;

    /**
     * Constructor
     *
     * @param  string $dir
     */
    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/\\');
    }

    /**
     * Get the cache directory
     *
     * @return string
     */
    public function getDir(): string
    {
        return $this->dir;
    }

    /**
     * Derive a stable cache key from resolved template content
     *
     * @param  string $resolvedTemplate
     * @return string
     */
    public static function key(string $resolvedTemplate): string
    {
        return hash('sha256', $resolvedTemplate);
    }

    /**
     * Get the absolute path to the cache file for a given key
     *
     * @param  string $key
     * @return string
     */
    public function path(string $key): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . $key . '.php';
    }

    /**
     * Get cached compiled source for a key, or null on miss/stale
     *
     * @param  string $key
     * @param  int    $newestSourceMtime
     * @return ?string
     */
    public function get(string $key, int $newestSourceMtime): ?string
    {
        $file = $this->path($key);

        if (!file_exists($file)) {
            return null;
        }

        if (($newestSourceMtime > 0) && (filemtime($file) < $newestSourceMtime)) {
            return null;
        }

        $content = file_get_contents($file);

        return ($content === false) ? null : $content;
    }

    /**
     * Write compiled source to the cache, atomically
     *
     * @param  string $key
     * @param  string $source
     * @throws Exception
     * @return void
     */
    public function put(string $key, string $source): void
    {
        if (!is_dir($this->dir) || !is_writable($this->dir)) {
            throw new Exception("Error: The cache directory '" . $this->dir . "' does not exist or is not writable.");
        }

        $file = $this->path($key);
        $tmp  = $file . '.' . uniqid('', true) . '.tmp';

        if (@file_put_contents($tmp, $source) === false) {
            throw new Exception("Error: Failed to write compiled template to '" . $tmp . "'.");
        }

        if (!@rename($tmp, $file)) {
            throw new Exception("Error: Failed to move compiled template into place at '" . $file . "'.");
        }
    }

}
