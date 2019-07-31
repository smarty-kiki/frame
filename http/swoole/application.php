<?php

function _request($request = false)
{/*{{{*/
    static $container = null;

    if ($request !== false) {

        return $container = $request;
    }

    return $container;
}/*}}}*/

function _response($response = false)
{/*{{{*/
    static $container = null;

    if ($response !== false) {

        return $container = $response;
    }

    return $container;
}/*}}}*/

function http_server()
{/*{{{*/
    static $container = null;

    if (is_null($container)) {

        $config = config('swoole_server');

        $container = $http = new Swoole\Http\Server($config['ip'], $config['port']);

        $http->set($config['set']);

        $http->on('request', function ($request, $response) {

            _request($request); _response($response);

            try {

                list($matched_closure, $args) = route();

                if ($matched_closure) {

                    flush_action($matched_closure, $args, if_verify());
                } else {

                    not_found();
                }

            } catch (Exception $ex) {

                http_ex_action($ex);
            }

            _request(null); _response(null);
        });

        $http->start();
    }

    return $container;
}/*}}}*/

/**
 * if is https.
 *
 * @return bool
 */
function is_https()
{
    //todo::kiki
}

/**
 * Get the current URI.
 *
 * @return string
 */
function uri()
{/*{{{*/
    $schema = is_https() ? 'https://' : 'http://';

    $request = _request();

    return $schema.$request->header['host'].$request->server['request_uri'];
}/*}}}*/

/**
 * Get the specified URI info.
 *
 * @param string $name
 *
 * @return mixed
 */
function uri_info($name = null)
{/*{{{*/
    $url = uri();

    $uri_info = parse_url($url);

    return (null === $name) ? $uri_info : $uri_info[$name];
}/*}}}*/

function _routers($method, $rule = null, closure $action = null)
{/*{{{*/
    static $routes = [];

    if (is_null($rule)) {
        return $routes[$method] ?? [];
    }

    $rule_info = explode('/', $rule);

    $rule_info[0] = $method;

    $rule_info[] = '_action';

    $rule = implode('.', $rule_info);

    if (is_null($action)) {

        throw new Exception('传入 rule 时必须传入 action 来注册路由响应闭包');
    } else {

        $routes = array_set($routes, $rule, $action);
    }
}/*}}}*/

/**
 * Route.
 *
 * @param string $rule
 *
 * @return array
 */
function route()
{/*{{{*/
    $request = _request();

    $path = $request->server['request_uri'];
    $method = $request->server['request_method'];

    $path_parts = explode('/', $path);

    array_shift($path_parts);
    $path_parts[] = '_action';

    $routes = _routers($method);
    
    $args = [];
    foreach ($path_parts as $path_part) {

        if (isset($routes[$path_part])) {

            $routes = $routes[$path_part];

        } elseif ($path_part == '_action') {

            return [false, []];

        } elseif (isset($routes['*'])) {

            $routes = $routes['*'];
            $args[] = $path_part;
        }
    }

    return [$routes, $args];
}/*}}}*/

/**
 * Flush the action result.
 *
 * @param closure $action
 * @param array   $args
 */
function flush_action(closure $action, $args = [], closure $verify = null)
{/*{{{*/
    if (is_null($verify)) {
        $output = $action(...$args);
    } else {
        $output = $verify($action, $args);
    }

    if (! is_null($output)) {

        _response()->end($output);
    }
}/*}}}*/

/**
 * Route for all method.
 *
 * @param string  $rule
 * @param closure $action
 */
function if_any($rule, closure $action)
{/*{{{*/
    if_get($rule, $action);
    if_post($rule, $action);
    if_put($rule, $action);
    if_delete($rule, $action);
}/*}}}*/

/**
 * Route for get method.
 *
 * @param string  $rule
 * @param closure $action
 */
function if_get($rule, closure $action)
{/*{{{*/
    _routers('GET', $rule, $action);
}/*}}}*/

/**
 * Route for post method.
 *
 * @param string  $rule
 * @param closure $action
 */
function if_post($rule, closure $action)
{/*{{{*/
    _routers('POST', $rule, $action);
}/*}}}*/

/**
 * Route for put method.
 *
 * @param string  $rule
 * @param closure $action
 */
function if_put($rule, closure $action)
{/*{{{*/
    _routers('PUT', $rule, $action);
}/*}}}*/

/**
 * Route for delete method.
 *
 * @param string  $rule
 * @param closure $action
 */
function if_delete($rule, closure $action)
{/*{{{*/
    _routers('DELETE', $rule, $action);
}/*}}}*/

/**
 * Get or set the verify closure.
 *
 * @param closure $action
 */
function if_verify(closure $action = null)
{/*{{{*/
    static $container = null;

    if (!empty($action)) {
        return $container = $action;
    }

    return $container;
}/*}}}*/

/**
 * Get or set the 404 handler.
 *
 * @param closure $action
 */
function if_not_found(closure $action = null)
{/*{{{*/
    static $container = null;

    if (!empty($action)) {
        return $container = $action;
    }

    return $container;
}/*}}}*/

/**
 * Redirect to 404.
 *
 * @param mix $action
 */
function not_found($action = null)
{/*{{{*/
    _response()->status(404);

    if ($action instanceof closure) {
        flush_action($action);
        return;
    }

    $action = if_not_found();

    if ($action instanceof closure) {
        flush_action($action, func_get_args());
        return;
    }
}/*}}}*/

/**
 * Redirect to a URI.
 *
 * @param string $uri
 * @param bool   $forever
 */
function redirect($uri, $forever = false)
{/*{{{*/
    if (empty($uri) || !is_string($uri)) {
        return $uri;
    }

    _response()->redirect($uri, $forever ? 302: 301);
}/*}}}*/

/**
 * Get specified _GET/_POST without filte XSS.
 *
 * @param string $name
 * @param mix    $default
 *
 * @return mixed
 */
function input_safe($name, $default = null)
{/*{{{*/
    $request = _request();

    if (isset($request->post[$name])) {
        return filter_var($request->post[$name], FILTER_SANITIZE_SPECIAL_CHARS);
    }

    if (isset($request->get[$name])) {
        return filter_var($request->get[$name], FILTER_SANITIZE_SPECIAL_CHARS);
    }

    return $default;
}/*}}}*/

/**
 * Get specified _GET, _POST.
 *
 * @param string $name
 * @param mix    $default
 *
 * @return mixed
 */
function input($name, $default = null)
{/*{{{*/
    $request = _request();

    if (isset($request->post[$name])) {
        return $request->post[$name];
    }

    if (isset($request->get[$name])) {
        return $request->get[$name];
    }

    return $default;
}/*}}}*/

/**
 * Get specified _GET/_POST array.
 *
 * @param string $name ..
 *
 * @return array
 */
function input_list(...$names)
{/*{{{*/
    if (empty($names)) {
        return [];
    }

    $values = [];

    foreach ($names as $name) {
        $values[] = input($name);
    }

    return $values;
}/*}}}*/

/**
 * Get an item from Json Decode _POST using "dot" notation.
 *
 * @param mixed $name
 * @param mixed $default
 * @access public
 * @return mix
 */
function input_json($name, $default = null)
{/*{{{*/
    $post_data = json_decode(input_post_raw(), true);

    return array_get($post_data, $name, $default);
}/*}}}*/

/**
 * Get items from Json Decode _POST using "dot" notation.
 *
 * @access public
 * @return array
 */
function input_json_list(...$names)
{/*{{{*/
    if (empty($names)) {
        return [];
    }

    $values = [];

    foreach ($names as $name) {
        $values[] = input_json($name);
    }

    return $values;
}/*}}}*/

function input_xml($name, $default = null)
{/*{{{*/
    $raw_post_data = simplexml_load_string(input_post_raw(), 'SimpleXMLElement', LIBXML_NOCDATA);

    $post_data = json_decode(json_encode($raw_post_data), true);

    return array_get($post_data, $name, $default);
}/*}}}*/

/**
 * Get items from Json Decode _POST using "dot" notation.
 *
 * @access public
 * @return array
 */
function input_xml_list(...$names)
{/*{{{*/
    if (empty($names)) {
        return [];
    }

    $values = [];

    foreach ($names as $name) {
        $values[] = input_xml($name);
    }

    return $values;
}/*}}}*/

function input_post_raw()
{/*{{{*/
    return _request()->rawContent();
}/*}}}*/

/**
 * Get specified cookie without filte XSS.
 *
 * @param string $name
 *
 * @return mixed
 */
function cookie_safe($name, $default = null)
{/*{{{*/
    $request = _request();

    if (isset($request->cookie[$name])) {
        return filter_var($request->cookie[$name], FILTER_SANITIZE_SPECIAL_CHARS);
    }

    return $default;
}/*}}}*/

/**
 * Get specified _COOKIE.
 *
 * @param string $name
 *
 * @return mixed
 */
function cookie($name, $default = null)
{/*{{{*/
    $request = _request();

    if (isset($request->cookie[$name])) {
        return $request->cookie[$name];
    }

    return $default;
}/*}}}*/

/**
 * Get specified _COOKIE array.
 *
 * @param string $name ..
 *
 * @return mixed
 */
function cookie_list(...$names)
{/*{{{*/
    if (empty($names)) {
        return [];
    }

    $values = [];

    foreach ($names as $name) {
        $values[] = cookie($name);
    }

    return $values;
}/*}}}*/

function server_safe($name, $default = null)
{/*{{{*/
    $request = _request();

    if (isset($request->server[$name])) {
        return filter_var($request->server[$name], FILTER_SANITIZE_SPECIAL_CHARS);
    }

    return $default;
}/*}}}*/

function server($name, $default = null)
{/*{{{*/
    $request = _request();

    if (isset($request->server[$name])) {
        return $request->server[$name];
    }

    return $default;
}/*}}}*/

function server_list(...$names)
{/*{{{*/
    if (empty($names)) {
        return [];
    }

    $values = [];

    foreach ($names as $name) {
        $values[] = server($name);
    }

    return $values;
}/*}}}*/

/**
 * Set or get view file path.
 *
 * @param string $path
 * @return string
 */
function view_path($path = null)
{/*{{{*/
    static $container = '';

    if (!empty($path)) {
        return $container = $path;
    }

    return $container;
}/*}}}*/

function view_compiler(closure $closure = null)
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    if (is_null($container)) {
        $container = function ($view) {
            return view_path().$view.'.php';
        };
    }

    return $container;
}/*}}}*/

/**
 * Render view.
 *
 * @param string $view
 * @param array  $args
 */
function render($view, $args = [])
{/*{{{*/
    if (!empty($args)) {
        extract($args);
    }

    $render = view_compiler();

    ob_start();

    //todo::kiki
    include $render($view);

    $echo = ob_get_contents();

    ob_end_clean();

    return $echo;
}/*}}}*/

/**
 * include view and send arguments.
 *
 * @param string $view
 * @param array  $args
 */
function include_view($view, $args = [])
{/*{{{*/
    if (!empty($args)) {
        extract($args);
    }

    //todo::kiki
    include view_path().$view.'.php';
}/*}}}*/

/**
 * Response 304 with ETag.
 *
 * @param string
 */
function cache_with_etag($etag)
{/*{{{*/
    $request = _request();

    //todo::kiki 测试是否生效
    if (isset($request->server['http_if_none_match']) && $request->server['http_if_none_match'] === $etag) {

        _response()->status(304);
    } else {
        _response()->header('ETag', $etag);
    }
}/*}}}*/

/**
 * get client ip.
 *
 * @return string
 */
function ip()
{/*{{{*/
    $request = _request();

    if ($request->header['x-real-ip']) {
        $ip = $request->header['x-real-ip'];
    } else {
        $ip = 'unknown';
    }

    return preg_replace('/^[^0-9]*?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*$/', '\1', $ip);
}/*}}}*/

/**
 * Get or set the exception handler.
 *
 * @param closure $action
 */
function if_has_exception(closure $action = null)
{/*{{{*/
    static $container = null;

    if (!empty($action)) {
        return $container = $action;
    }

    return $container;
}/*}}}*/

function http_err_action($error_type, $error_message, $error_file, $error_line, $error_context = null)
{/*{{{*/
    $message = $error_message.' '.$error_file.' '.$error_line;

    http_ex_action(new Exception($message));
}/*}}}*/

function http_ex_action($ex)
{/*{{{*/
    $action = if_has_exception();

    if ($action instanceof closure) {

        flush_action($action, [$ex]);
    } else {

        throw $ex;
    }
}/*}}}*/

function http_fatel_err_action()
{/*{{{*/
    $err = error_get_last();

    if (not_empty($err)) {
        http_err_action($err['type'], $err['message'], $err['file'], $err['line']);
    }
}/*}}}*/
