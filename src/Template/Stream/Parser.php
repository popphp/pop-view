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
namespace Pop\View\Template\Stream;

/**
 * View stream template parser class
 *
 * @category   Pop
 * @package    Pop\View
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    4.0.3
 */
class Parser
{

    /**
     * Parse arrays in the template string
     *
     * @param  string $template
     * @param  array  $data
     * @param  string $output
     * @return string
     */
    public static function parseArrays(string $template, array $data, string $output): string
    {
        foreach ($data as $key => $value) {
            if (is_array($value) || ($value instanceof \ArrayAccess)) {
                $start = '[{' . $key . '}]';
                $end   = '[{/' . $key . '}]';
                if ((str_contains($template, $start)) && (str_contains($template, $end))) {
                    $loopCode = substr($template, strpos($template, $start));
                    $loopCode = substr($loopCode, 0, (strpos($loopCode, $end) + strlen($end)));

                    $outputLoop = '';
                    $i = 0;
                    foreach ($value as $ky => $val) {
                        $loop = str_replace($start, '', $loopCode);
                        $loop = str_replace($end, '', $loop);
                        if (str_contains($loop, '[{if(')) {
                            $matches = [];
                            preg_match_all('/\[{if/mi', $loop, $matches, PREG_OFFSET_CAPTURE);

                            if (isset($matches[0]) && isset($matches[0][0])) {
                                foreach ($matches[0] as $match) {
                                    $cond = substr($loop, $match[1]);
                                    $cond = substr($cond, 0, strpos($cond, '[{/if}]') + 7);
                                    $var  = substr($cond, strpos($cond, '(') + 1);
                                    $var  = substr($var, 0, strpos($var, ')'));
                                    // If var is an array
                                    if (str_contains($var, '[')) {
                                        $index  = substr($var, (strpos($var, '[') + 1));
                                        $index  = substr($index, 0, strpos($index, ']'));
                                        $var    = substr($var, 0, strpos($var, '['));
                                        $varSet = (!empty($val[$var][$index]));
                                    } else {
                                        $index  = null;
                                        $varSet = (!empty($val[$var]));
                                    }
                                    if (str_contains($cond, '[{else}]')) {
                                        if ($varSet) {
                                            $code = substr($cond, (strpos($cond, ')}]') + 3));
                                            $code = substr($code, 0, strpos($code, '[{else}]'));
                                            $code = ($index !== null) ?
                                                str_replace('[{' . $var . '[' . $index . ']}]', $val[$var][$index], $code) :
                                                str_replace('[{' . $var . '}]', $val[$var], $code);
                                            $loop = str_replace($cond, $code, $loop);
                                        } else {
                                            $code = substr($cond, (strpos($cond, '[{else}]') + 8));
                                            $code = substr($code, 0, strpos($code, '[{/if}]'));
                                            $loop = str_replace($cond, $code, $loop);
                                        }
                                    } else {
                                        if ($varSet) {
                                            $code = substr($cond, (strpos($cond, ')}]') + 3));
                                            $code = substr($code, 0, strpos($code, '[{/if}]'));
                                            $code = ($index !== null) ?
                                                str_replace('[{' . $var . '[' . $index . ']}]', $val[$var][$index], $code) :
                                                str_replace('[{' . $var . '}]', $val[$var], $code);
                                            $loop = str_replace($cond, $code, $loop);
                                        } else {
                                            $loop = str_replace($cond, '', $loop);
                                        }
                                    }
                                }
                            }
                        }

                        // Handle nested array
                        if (is_array($val) || ($value instanceof \ArrayAccess) || ($val instanceof \ArrayObject)) {
                            if (is_numeric($ky)) {
                                $oLoop = $loop;
                                foreach ($val as $k => $v) {
                                    // Check is value is stringable
                                    if ((is_object($v) && method_exists($v, '__toString')) ||
                                        (!is_object($v) && !is_array($v))) {
                                        $oLoop = str_replace('[{' . $k . '}]', $v, $oLoop);
                                    }
                                }
                                if (str_contains($oLoop, '[{i}]')) {
                                    $oLoop = str_replace('[{i}]', ($i + 1), $oLoop);
                                }
                                $outputLoop .= $oLoop;
                            } else {
                                $s = '[{' . $ky . '}]';
                                $e = '[{/' . $ky . '}]';
                                if ((str_contains($loop, $s)) && (str_contains($loop, $e))) {
                                    $l     = $loop;
                                    $lCode = substr($l, strpos($l, $s));
                                    $lCode = substr($lCode, 0, (strpos($lCode, $e) + strlen($e)));

                                    $l     = str_replace($s, '', $lCode);
                                    $l     = str_replace($e, '', $l);
                                    $oLoop = '';
                                    $j     = 1;

                                    foreach ($val as $k => $v) {
                                        // Check is value is stringable
                                        if ((is_object($v) && method_exists($v, '__toString')) ||
                                            (!is_object($v) && !is_array($v))) {
                                            if (str_contains($l, '[{i}]')) {
                                                $oLoop .= str_replace(['[{key}]', '[{value}]', '[{i}]'], [$k, $v, $j], $l);
                                            } else {
                                                $oLoop .= str_replace(['[{key}]', '[{value}]'], [$k, $v], $l);
                                            }
                                            $j++;
                                        }
                                    }
                                    $outputLoop = str_replace($lCode, $oLoop, $loop);
                                }
                            }
                        // Handle scalar
                        } else {
                            // Check is value is stringable
                            if ((is_object($val) && method_exists($val, '__toString')) ||
                                (!is_object($val) && !is_array($val))) {
                                if (str_contains($loop, '[{i}]')) {
                                    $outputLoop .= str_replace(['[{key}]', '[{value}]', '[{i}]'], [$ky, $val, ($i + 1)], $loop);
                                } else {
                                    $outputLoop .= str_replace(['[{key}]', '[{value}]'], [$ky, $val], $loop);
                                }
                            }
                        }
                        $i++;
                        if ($i < count($value)) {
                            $outputLoop .= PHP_EOL;
                        }
                    }
                    $output = str_replace($loopCode, $outputLoop, $output);
                }
            }
        }

        return $output;
    }

    /**
     * Parse conditionals in the template string
     *
     * @param  string $template
     * @param  array  $data
     * @param  string $output
     * @return string
     */
    public static function parseConditionals(string $template, array $data, string $output): string
    {
        $matches = [];
        preg_match_all('/\[{if/mi', $template, $matches, PREG_OFFSET_CAPTURE);
        if (isset($matches[0]) && isset($matches[0][0])) {
            foreach ($matches[0] as $match) {
                $cond = substr($template, $match[1]);
                $cond = substr($cond, 0, strpos($cond, '[{/if}]') + 7);
                $var  = substr($cond, strpos($cond, '(') + 1);
                $var  = substr($var, 0, strpos($var, ')'));

                // If var is an array
                if (str_contains($var, '[')) {
                    $index  = substr($var, (strpos($var, '[') + 1));
                    $index  = substr($index, 0, strpos($index, ']'));
                    $var    = substr($var, 0, strpos($var, '['));
                    $varSet = (!empty($data[$var][$index]));
                } else {
                    $index  = null;
                    $varSet = (!empty($data[$var]));
                }
                if (str_contains($cond, '[{else}]')) {
                    if ($varSet) {
                        $code = substr($cond, (strpos($cond, ')}]') + 3));
                        $code = substr($code, 0, strpos($code, '[{else}]'));
                        $code = ($index !== null) ?
                            str_replace('[{' . $var . '[' . $index . ']}]', $data[$var][$index], $code) :
                            str_replace('[{' . $var . '}]', $data[$var], $code);
                        $output = str_replace($cond, $code, $output);
                    } else {
                        $code = substr($cond, (strpos($cond, '[{else}]') + 8));
                        $code = substr($code, 0, strpos($code, '[{/if}]'));
                        $output = str_replace($cond, $code, $output);
                    }
                } else {
                    if ($varSet) {
                        $code = substr($cond, (strpos($cond, ')}]') + 3));
                        $code = substr($code, 0, strpos($code, '[{/if}]'));
                        $code = ($index !== null) ?
                            str_replace('[{' . $var . '[' . $index . ']}]', $data[$var][$index], $code) :
                            str_replace('[{' . $var . '}]', $data[$var], $code);
                        $output = str_replace($cond, $code, $output);
                    } else {
                        $output = str_replace($cond, '', $output);
                    }
                }
            }
        }

        return $output;
    }

    /**
     * Parse scalar values in the template string
     *
     * @param  array  $data
     * @param  string $output
     * @return string
     */
    public static function parseScalars(array $data, string $output): string
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && (str_contains($output, '[{' . $key . '['))) {
                $matches = [];
                preg_match_all('/\[{' . $key .'\[/mi', $output, $matches, PREG_OFFSET_CAPTURE);

                if (isset($matches[0]) && isset($matches[0][0])) {
                    $indices = [];
                    foreach ($matches[0] as $match) {
                        $i = substr($output, $match[1] + (strlen($key) + 3));
                        $i = substr($i, 0, strpos($i, ']'));
                        $indices[] = $i;
                    }
                    foreach ($indices as $i) {
                        if (isset($value[$i])) {
                            $output = str_replace('[{' . $key . '[' . $i . ']}]', $value[$i], $output);
                        } else {
                            $output = str_replace('[{' . $key . '[' . $i . ']}]', '', $output);
                        }
                    }
                }
            } else if (!is_array($value) && !($value instanceof \ArrayAccess)) {
                // Check is value is stringable
                if ((is_object($value) && method_exists($value, '__toString')) || (!is_object($value) && !is_array($value))) {
                    $output = str_replace('[{' . $key . '}]', $value, $output);
                }
            }
        }

        return $output;
    }
}
