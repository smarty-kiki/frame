<?php

function array_get($array, $key, $default = null)
{/*{{{*/
    $delimiter = '.';

    if (is_null($key)) {

        return $default;
    }

    $exploded_keys = explode($delimiter, $key);

    foreach ($exploded_keys as $i => $segment) {

        if ('*' === $segment) {

            $res_array = [];

            $new_key = implode($delimiter, array_slice($exploded_keys, $i + 1));

            foreach ($array as $k => $v) {

                $res_array[$k] = array_get($v, $new_key, $default);
            }

            return $res_array;

        } else {

            if (!is_array($array) || !array_key_exists($segment, $array)) {

                return value($default);
            }

            $array = $array[$segment];
        }
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

function array_build($array, closure $callback)
{/*{{{*/
    $results = [];

    foreach ($array as $key => $value) {

        list($innerKey, $innerValue) = call_user_func($callback, $key, $value);

        if (is_null($innerKey)) {

            $results[] = $innerValue;
        } else {
            $results[$innerKey] = $innerValue;
        }
    }

    return $results;
}/*}}}*/

function array_indexed(array $array, closure $callback)
{/*{{{*/
    $results = [];

    foreach ($array as $key => $value) {

        list($index, $innerKey, $innerValue) = call_user_func($callback, $key, $value);

        if (! isset($results[$index])) {
            $results[$index] = [];
        }

        if (is_null($innerKey)) {

            $results[$index][] = $innerValue;
        } else {
            if (! isset($results[$index][$innerKey])) {

                $results[$index][$innerKey] = [];
            }

            $results[$index][$innerKey] = $innerValue;
        }
    }

    return $results;
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

function str_tail_cut($string, $len, $suffix = '...')
{/*{{{*/
    $strlen = mb_strlen($string);
    $suffixlen = mb_strlen($suffix);

    if ($strlen > $len) {
        return mb_substr($string, 0, $len - $suffixlen) . $suffix;
    }

    return $string;
}/*}}}*/

function str_head_cut($string, $len, $prefix = '...')
{/*{{{*/
    $strlen = mb_strlen($string);
    $prefixlen = mb_strlen($prefix);

    if ($strlen > $len) {
        return $prefix . mb_substr($string, $prefixlen - $len, $len - $prefixlen);
    }

    return $string;
}/*}}}*/

function str_middle_cut($string, $len, $middle = '...')
{/*{{{*/
    $strlen = mb_strlen($string);
    $middlelen = mb_strlen($middle);

    $res_strlen = $len - $middlelen;

    if ($res_strlen % 2) {
        $first_len  = floor($res_strlen / 2);
        $second_len = ceil($res_strlen / 2);
    } else {
        $first_len = $second_len = $res_strlen / 2;
    }

    if ($strlen > $len) {
        return mb_substr($string, 0, $first_len) . $middle . mb_substr($string, 0 - $second_len, $second_len);
    }

    return $string;
}/*}}}*/

function dd(...$args)
{/*{{{*/
    var_dump(...$args);
    die;
}/*}}}*/

function trace($message = 'exception for trace')
{/*{{{*/
    try {
        throw new Exception($message);
    } catch (Exception $ex) {
        log_exception($ex);
    }
}/*}}}*/

function value($value)
{/*{{{*/
    return $value instanceof Closure ? $value() : $value;
}/*}}}*/

function closure_id($closure)
{/*{{{*/
    $closure_ref = new ReflectionFunction($closure);

    return md5((string) $closure_ref);
}/*}}}*/

function starts_with($haystack, $needles)
{/*{{{*/
    foreach ((array) $needles as $needle) {
        if ($needle != '' && mb_strpos($haystack, $needle) === 0) {
            return true;
        }
    }

    return false;
}/*}}}*/

function ends_with($haystack, $needles)
{/*{{{*/
    foreach ((array) $needles as $needle)
    {
        if ((string) $needle === mb_substr($haystack, - mb_strlen($needle))) return true;
    }

    return false;
}/*}}}*/

function str_finish($value, $cap)
{/*{{{*/
    $quoted = preg_quote($cap, '/');

    return preg_replace('/(?:'.$quoted.')+$/', '', $value).$cap;
}/*}}}*/

function is_url($path)
{/*{{{*/
    if (starts_with($path, array('#', '//', 'mailto:', 'tel:'))) {
        return true;
    }

    return filter_var($path, FILTER_VALIDATE_URL) !== false;
}/*}}}*/

function unparse_url(array $parsed)
{/*{{{*/
    $get = function ($key) use ($parsed) {
        return isset($parsed[$key]) ? $parsed[$key] : null;
    };

    $pass      = $get('pass');
    $user      = $get('user');
    $userinfo  = $pass !== null ? "$user:$pass" : $user;
    $port      = $get('port');
    $scheme    = $get('scheme');
    $query     = $get('query');
    $fragment  = $get('fragment');
    $authority =
        ($userinfo !== null ? "$userinfo@" : '') .
        $get('host') .
        ($port ? ":$port" : '');

    return
        (strlen($scheme) ? "$scheme:" : '') .
        (strlen($authority) ? "//$authority" : '') .
        $get('path') .
        (strlen($query) ? "?$query" : '') .
        (strlen($fragment) ? "#$fragment" : '');
}/*}}}*/

function url_transfer($url, closure $transfer_action)
{/*{{{*/
    $url_info = parse_url($url);

    if (isset($url_info['query'])) {
        parse_str($url_info['query'], $query_info);
        $url_info['query'] = $query_info;
    } else {
        $url_info['query'] = [];
    }
    $url_info = call_user_func($transfer_action, $url_info);
    $url_info['query'] = http_build_query($url_info['query']);

    return unparse_url($url_info);
}/*}}}*/

function config_dir($dir = null)
{/*{{{*/
    static $container = [];

    if (! is_null($dir)) {
        $container[] = $dir;
    }

    return $container;
}/*}}}*/

function config($key)
{/*{{{*/
    static $configs = [];

    if (! isset($configs[$key])) {

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

function env()
{/*{{{*/
    return $_SERVER['ENV'] ?? 'production';
}/*}}}*/

function is_env($env)
{/*{{{*/
    return env() === $env;
}/*}}}*/

function not_empty($mixed)
{/*{{{*/
    return !empty($mixed);
}/*}}}*/

function not_null($mixed)
{/*{{{*/
    return !is_null($mixed);
}/*}}}*/

function all_empty(...$args)
{/*{{{*/
    foreach ($args as $arg) {

        if (not_empty($arg)) return false;
    }

    return true;
}/*}}}*/

function all_null(...$args)
{/*{{{*/
    foreach ($args as $arg) {

        if (not_null($arg)) return false;
    }

    return true;
}/*}}}*/

function all_not_empty(...$args)
{/*{{{*/
    return ! has_empty(...$args);
}/*}}}*/

function all_not_null(...$args)
{/*{{{*/
    return ! has_null(...$args);
}/*}}}*/

function has_empty(...$args)
{/*{{{*/
    foreach ($args as $arg) {

        if (empty($arg)) return true;
    }

    return false;
}/*}}}*/

function has_null(...$args)
{/*{{{*/
    foreach ($args as $arg) {

        if (is_null($arg)) return true;
    }

    return false;
}/*}}}*/

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
        curl_setopt($ch, CURLOPT_COOKIE, http_build_query($cookies, '', ';').';');
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

function remote_post_xml($url, $data = [], $timeout = 3, $retry = 3, array $headers = [], array $cookies = [])
{/*{{{*/
    $raw_res = remote_post($url, $data, $timeout, $retry, $headers, $cookies);

    $raw_xml = simplexml_load_string($raw_res, 'SimpleXMLElement', LIBXML_NOCDATA);

    return json_decode(json_encode($raw_xml), true);
}/*}}}*/

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

function remote_get_xml($url, $timeout = 3, $retry = 3, array $headers = [], array $cookies = [])
{/*{{{*/
    $raw_res = remote_get($url, $timeout, $retry, $headers, $cookies);

    $raw_xml = simplexml_load_string($raw_res, 'SimpleXMLElement', LIBXML_NOCDATA);

    return json_decode(json_encode($raw_xml), true);
}/*}}}*/

function instance($class_name, array $args = [])
{/*{{{*/
    static $container = [];

    if (empty($args)) {
        $instance_identifier = $class_name;
    } else {
        $instance_identifier = $class_name.serialize($args);
    }

    if (!isset($container[$instance_identifier])) {

        $container[$instance_identifier] = new $class_name(...$args);
    }

    return $container[$instance_identifier];
}/*}}}*/

function json($data = [])
{/*{{{*/
    return json_encode($data, JSON_UNESCAPED_UNICODE);
}/*}}}*/
