<?php

/**
 * @param array  $array
 * @param string $key
 * @param mixed  $default
 *
 * @return mixed
 */
function array_get($array, $key, $default = null)
{/*{{{*/
    if (is_null($key)) {
        return $default;
    }

    foreach (explode('.', $key) as $segment) {
        if (!is_array($array) || !array_key_exists($segment, $array)) {
            return value($default);
        }

        $array = $array[$segment];
    }

    return $array;
}/*}}}*/

/**
 * Set an array item to a given value using "dot" notation.
 * If no key is given to the method, the entire array will be replaced.
 *
 * @param array  $array
 * @param string $key
 * @param mixed  $value
 *
 * @return array
 */
function array_set(&$array, $key, $value)
{/*{{{*/
    if (is_null($key)) {
        return $array = $value;
    }

    $keys = explode('.', $key);

    while (count($keys) > 1) {
        $key = array_shift($keys);

        if (!isset($array[$key]) || !is_array($array[$key])) {
            $array[$key] = [];
        }

        $array = &$array[$key];
    }

    $array[array_shift($keys)] = $value;

    return $array;
}/*}}}*/

/**
 * Fetch a flattened array of a nested array element.
 *
 * @param array  $array
 * @param string $key
 *
 * @return array
 */
function array_fetch($array, $key)
{/*{{{*/
    foreach (explode('.', $key) as $segment) {
        $results = [];

        foreach ($array as $value) {
            $value = (array) $value;

            $results[] = $value[$segment];
        }

        $array = array_values($results);
    }

    return array_values($results);
}/*}}}*/

/**
 * Remove one or many array items from a given array using "dot" notation.
 *
 * @param array        $array
 * @param array|string $keys
 */
function array_forget(&$array, $keys)
{/*{{{*/
    $original = &$array;

    foreach ((array) $keys as $key) {
        $parts = explode('.', $key);

        while (count($parts) > 1) {
            $part = array_shift($parts);

            if (isset($array[$part]) && is_array($array[$part])) {
                $array = &$array[$part];
            }
        }

        unset($array[array_shift($parts)]);

        // clean up after each pass
        $array = &$original;
    }
}/*}}}*/

/**
 * Divide an array into two arrays. One with keys and the other with values.
 *
 * @param array $array
 *
 * @return array
 */
function array_divide($array)
{/*{{{*/
    return array(array_keys($array), array_values($array));
}/*}}}*/

/**
 * Build a new array using a callback.
 *
 * @param array    $array
 * @param \Closure $callback
 *
 * @return array
 */
function array_build($array, Closure $callback)
{/*{{{*/
    $results = [];

    foreach ($array as $key => $value) {
        list($innerKey, $innerValue) = call_user_func($callback, $key, $value);

        $results[$innerKey] = $innerValue;
    }

    return $results;
}/*}}}*/

/**
 * 递归ksort数组.
 *
 * @param array    $array
 *
 * @return array
 */
function array_key_sort($arr)
{/*{{{*/
    ksort($arr);
    foreach ($arr as $k => $v) {
        if (is_array($v)) {
            $arr[$k] = array_key_sort($v);
        }
    }

    return $arr;
}/*}}}*/

/**
 * sort an indexed array by value first, and then by key.
 *
 * @param array $array  排序数组
 * @param bool  $valrev value DESC or ASC
 * @param bool  $keyrev key DESC or ASC
 *
 * @return sort_array
 */
function aksort(&$array, $valrev = false, $keyrev = false)
{/*{{{*/
    $sort_array = [];
    if ($valrev) {
        arsort($array);
    } else {
        asort($array);
    }
    $vals = array_count_values($array);
    $i = 0;
    foreach ($vals as $val => $num) {
        $tmp = array_slice($array, $i, $num, true);
        if ($keyrev) {
            krsort($tmp);
        } else {
            ksort($tmp);
        }
        $sort_array += $tmp;
        unset($tmp);
        $i += $num;
    }

    return $sort_array;
}/*}}}*/

/**
 * Dump the passed variables and end the script.
 *
 * @param  dynamic  mixed
 */
function dd()
{/*{{{*/
    array_map(function ($x) { var_dump($x); }, func_get_args());
    die;
}/*}}}*/

/**
 * Return the default value of the given value.
 *
 * @param mixed $value
 *
 * @return mixed
 */
function value($value)
{/*{{{*/
    return $value instanceof Closure ? $value() : $value;
}/*}}}*/

/**
 * @param mixed $value
 *
 * @return mixed
 */
function closure_name($closure)
{/*{{{*/
    $closure_ref = new ReflectionFunction($closure);

    return $closure_ref->getName();
}/*}}}*/

/**
 * Determine if a given string starts with a given substring.
 *
 * @param string       $haystack
 * @param string|array $needles
 *
 * @return bool
 */
function starts_with($haystack, $needles)
{/*{{{*/
    foreach ((array) $needles as $needle) {
        if ($needle != '' && strpos($haystack, $needle) === 0) {
            return true;
        }
    }

    return false;
}/*}}}*/

/**
 * Determine if a given string ends with a given substring.
 *
 * @param  string  $haystack
 * @param  string|array  $needles
 * @return bool
 */
function ends_with($haystack, $needles)
{/*{{{*/
    foreach ((array) $needles as $needle)
    {
        if ((string) $needle === substr($haystack, -strlen($needle))) return true;
    }

    return false;
}/*}}}*/

/**
 * Cap a string with a single instance of a given value.
 *
 * @param string $value
 * @param string $cap
 *
 * @return string
 */
function str_finish($value, $cap)
{/*{{{*/
    $quoted = preg_quote($cap, '/');

    return preg_replace('/(?:'.$quoted.')+$/', '', $value).$cap;
}/*}}}*/

/**
 * Determine if the given path is a valid URL.
 *
 * @param string $path
 *
 * @return bool
 */
function is_url($path)
{/*{{{*/
    if (starts_with($path, array('#', '//', 'mailto:', 'tel:'))) {
        return true;
    }

    return filter_var($path, FILTER_VALIDATE_URL) !== false;
}/*}}}*/

/**
 * Init configuration from array or file.
 *
 * @param mixed $configs
 *
 * @return mixed
 */
function config_dir($dir = null)
{/*{{{*/
    static $container = [];

    if (! is_null($dir)) {
        $container[] = $dir;
    }

    return $container;
}/*}}}*/

/**
 * Get the specified configuration value.
 *
 * @param string $key
 * @param mixed  $default
 *
 * @return mixed
 */
function config($key)
{/*{{{*/
    static $configs = [];

    if (! array_key_exists($key, $configs)) {

        $directories = config_dir();

        $configs[$key] = [];

        foreach ($directories as $dir) {

            if (is_file($config_file = $dir.'/'.$key.'.php')) {

                $configs[$key] = array_replace_recursive($configs[$key], include $config_file);
            }

            if (is_file($env_config_file = $dir.'/'.env().'/'.$key.'.php')) {

                $configs[$key] = array_replace_recursive($configs[$key], include $env_config_file);
            }
        }
    }

    return $configs[$key];
}/*}}}*/

/**
 * env.
 *
 * get the environment
 *
 * @return string
 */
function env()
{/*{{{*/
    return isset($_SERVER['ENV']) ? $_SERVER['ENV'] : 'production';
}/*}}}*/

/**
 * is_env.
 *
 * @param string $env
 *
 * @return bool
 */
function is_env($env)
{/*{{{*/
    return env() === $env;
}/*}}}*/

/**
 * not empty.
 *
 * @param mixed $mixed
 *
 * @return mixed
 */
function not_empty($mixed)
{/*{{{*/
    return !empty($mixed);
}/*}}}*/

/**
 * all not empty.
 *
 * @param mixed $mixed
 *
 * @return mixed
 */
function all_not_empty()
{/*{{{*/
    foreach (func_get_args() as $arg) {

        if (empty($arg)) return false;
    }

    return true;
}/*}}}*/

/**
 * has empty.
 *
 * @param mixed $mixed
 *
 * @return mixed
 */
function has_empty()
{/*{{{*/
    foreach (func_get_args() as $arg) {

        if (empty($arg)) return true;
    }

    return false;
}/*}}}*/

/**
 * now.
 *
 * @param mixed  $expression
 * @param string $format
 *
 * @return string
 */
function now($expression = null, $format = 'Y-m-d H:i:s')
{/*{{{*/
    if (is_null($expression)) {
        $time = time();
    } elseif (is_numeric($expression)) {
        $time = $expression;
    } else {
        $time = strtotime($expression);
    }

    return date($format, $time);
}/*}}}*/

/**
 * if date between start date and end date.
 *
 * @param string $date
 * @param string $start
 * @param string $end
 *
 * @return bool
 */
function date_between($date, $start, $end)
{/*{{{*/
    return strtotime($date) >= strtotime($start) && strtotime($date) <= strtotime($end);
}/*}}}*/

/**
 * remote_post.
 *
 * @param mixed $url
 * @param array $data
 * @param int   $timeout
 * @param int   $retry
 *
 * @return string
 */
function remote_post($url, $data = [], $timeout = 3, $retry = 3, array $headers = [], array $cookies = [])
{/*{{{*/
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => 1,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36',
    ]);

    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    if ($cookies) {
        curl_setopt ($ch, CURLOPT_COOKIE, http_build_query($cookies, '', ';').';');
    }

    while ($retry-- > 0) {
        $res = curl_exec($ch);
        $errno = curl_errno($ch);

        if (0 === $errno) {
            break;
        }
    }

    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if (404 === $code) {
        return false;
    }

    return $res;
}/*}}}*/

function remote_post_json($url, $data = [], $timeout = 3, $retry = 3, array $headers = [], array $cookies = [])
{/*{{{*/
    return json_decode(remote_post($url, $data, $timeout, $retry, $headers, $cookies), true);
}/*}}}*/

/**
 * remote_get.
 *
 * @param mixed $url
 * @param int   $timeout
 * @param int   $retry
 *
 * @return string
 */
function remote_get($url, $timeout = 3, $retry = 3, array $headers = [], array $cookies = [])
{/*{{{*/
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36',
    ]);

    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    if ($cookies) {
        curl_setopt ($ch, CURLOPT_COOKIE, http_build_query($cookies, '', ';').';');
    }

    while ($retry-- > 0) {
        $res = curl_exec($ch);
        $errno = curl_errno($ch);

        if (0 === $errno) {
            break;
        }
    }

    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if (404 === $code) {
        return false;
    }

    return $res;
}/*}}}*/

function remote_get_json($url, $timeout = 3, $retry = 3, array $headers = [], array $cookies = [])
{/*{{{*/
    return json_decode(remote_get($url, $timeout, $retry, $headers, $cookies), true);
}/*}}}*/

function instance($class_name)
{/*{{{*/
    static $container = [];

    if (!isset($container[$class_name])) {
        $container[$class_name] = new $class_name();
    }

    return $container[$class_name];
}/*}}}*/
