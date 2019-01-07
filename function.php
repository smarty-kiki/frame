<?php

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

function array_set($array, $key, $value)
{/*{{{*/
    $res_array = &$array;

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

    return $res_array;
}/*}}}*/

function array_exists($array, $key)
{/*{{{*/
    if (is_null($key)) {
        return false;
    }

    foreach (explode('.', $key) as $segment) {
        if (!is_array($array) || !array_key_exists($segment, $array)) {
            return false;
        }

        $array = $array[$segment];
    }

    return true;
}/*}}}*/

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

        $array = &$original;
    }
}/*}}}*/

function array_divide($array)
{/*{{{*/
    return array(array_keys($array), array_values($array));
}/*}}}*/

function array_build($array, Closure $callback)
{/*{{{*/
    $results = [];

    foreach ($array as $key => $value) {
        list($innerKey, $innerValue) = call_user_func($callback, $key, $value);

        $results[$innerKey] = $innerValue;
    }

    return $results;
}/*}}}*/

function array_key_sort($array)
{/*{{{*/
    ksort($array);
    foreach ($array as $k => $v) {
        if (is_array($v)) {
            $array[$k] = array_key_sort($v);
        }
    }

    return $array;
}/*}}}*/

function array_list(array $array, array $keys)
{/*{{{*/
    if (empty($keys)) {
        return [];
    }

    $values = [];

    foreach ($keys as $key) {
        $values[] = array_get($array, $key);
    }

    return $values;
}/*}}}*/

function array_transfer(array $array, array $rules)
{/*{{{*/
    if (empty($rules)) {
        return [];
    }

    $values = [];

    foreach ($rules as $from => $to) {
        $values = array_set($values, $to, array_get($array, $from));
    }

    return $values;
}/*}}}*/

/**
 * str_cut
 *
 * @param mixed $string
 * @param mixed $len
 * @param string $suffix
 * @access public
 * @return void
 */
function str_cut($string, $len, $suffix = '...')
{/*{{{*/
    $strlen = mb_strlen($string);
    $suffixlen = mb_strlen($suffix);

    if ($strlen > $len) {
        return mb_substr($string, 0, $len - $suffixlen) . $suffix;
    }

    return $string;
}/*}}}*/

/**
 * Dump the passed variables and end the script.
 *
 * @param  dynamic  mixed
 */
function dd(...$args)
{/*{{{*/
    var_dump(...$args);
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
 * not null.
 *
 * @param mixed $mixed
 *
 * @return mixed
 */
function not_null($mixed)
{/*{{{*/
    return !is_null($mixed);
}/*}}}*/

/**
 * all empty.
 *
 * @param mixed $mixed
 *
 * @return mixed
 */
function all_empty(...$args)
{/*{{{*/
    foreach ($args as $arg) {

        if (!empty($arg)) return false;
    }

    return true;
}/*}}}*/

/**
 * all not empty.
 *
 * @param mixed $mixed
 *
 * @return mixed
 */
function all_not_empty(...$args)
{/*{{{*/
    return ! has_empty($args);
}/*}}}*/

/**
 * has empty.
 *
 * @param mixed $mixed
 *
 * @return mixed
 */
function has_empty(...$args)
{/*{{{*/
    foreach ($args as $arg) {

        if (empty($arg)) return true;
    }

    return false;
}/*}}}*/

/**
 * datetime.
 *
 * @param mixed  $expression
 * @param string $format
 *
 * @return string
 */
function datetime($expression = null, $format = 'Y-m-d H:i:s')
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

function datetime_diff($datetime1, $datetime2, $format = '%ts')
{/*{{{*/
    $interval = date_diff(
        date_create($datetime1),
        date_create($datetime2)
    );

    $res = $interval->format($format);

    $total_hours = $interval->days * 24 + $interval->h;

    $total_minutes = $total_hours * 60 + $interval->i;

    $total_seconds = $total_minutes * 60 + $interval->s;

    foreach ([
        '%td' => $interval->days,
        '%th' => $total_hours,
        '%tm' => $total_minutes,
        '%ts' => $total_seconds,
    ] as $k => $v) {
        $res = str_replace($k, $v, $res);
    }

    return $res;
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

/**
 * Convert data to json/jsonp.
 *
 * @param array  $data
 * @param string $callback
 *
 * @return string
 */
function json($data = [])
{/*{{{*/
    return json_encode($data, JSON_UNESCAPED_UNICODE);
}/*}}}*/
