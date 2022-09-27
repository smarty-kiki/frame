<?php

/**
 * if is https.
 *
 * @return bool
 */
function is_https(): bool
{
    if (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == 1)) {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') {
        return true;
    }

    return false;
}

/**
 * if is ajax.
 *
 * @return bool
 */
function is_ajax(): bool
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
        || ! empty($_POST['VAR_AJAX_SUBMIT']) || ! empty($_GET['VAR_AJAX_SUBMIT']);
}

/**
 * Get the current URI.
 *
 * @return string
 */
function uri(): string
{
    $schema = is_https() ? 'https://' : 'http://';

    return $schema.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}

/**
 * Get the refer URI.
 *
 * @return string
 */
function refer_uri(): string
{
    return $_SERVER['HTTP_REFERER'] ?? '';
}

/**
 * Get the specified URI info.
 *
 * @param string|null $name
 *
 * @return mixed
 */
function uri_info(string $name = null)
{
    static $container = [];

    if (empty($container)) {

        $url = uri();

        $container = parse_url($url);
    }

    return (null === $name) ? $container : $container[$name];
}

/**
 * Route.
 *
 * @param string $rule
 *
 * @return array
 */
function route(string $rule): array
{
    $reg = '/^'.str_replace('\*', '([^\/]+?)', preg_quote($rule, '/')).'$/';

    preg_match_all($reg, uri_info('path'), $matches);

    $args = [];

    if ($matched = ! empty($matches[0])) {

        unset($matches[0]);

        foreach ($matches as $v) {
            $args[] = $v[0];
        }
    }

    return [$matched, $args];
}

/**
 * Flush the action result.
 *
 * @param closure $action
 * @param array $args
 * @param closure|null $verify
 * @return null
 */
function flush_action(closure $action, array $args = [], closure $verify = null)
{
    if (is_null($verify)) {
        $output = $action(...$args);
    } else {
        $output = $verify($action, $args);
    }

    if (! is_null($output)) {
        echo $output;
        flush();
    }
}

/**
 * Get the matched rule.
 *
 * @param string|null $rule
 * @return string|null
 */
function matched_rule(string $rule = null): ?string
{
    static $container = null;

    if (! is_null($rule)) {
        return $container = $rule;
    }

    return $container;
}

function request_method()
{
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Route for all method.
 *
 * @param string $rule
 * @param closure $action
 */
function if_any(string $rule, closure $action)
{
    list($matched, $args) = route($rule);

    if ($matched) {

        matched_rule($rule);
        flush_action($action, $args, if_verify());
        trigger_redirect();
        exit;
    }
}

/**
 * Route for get method.
 *
 * @param string $rule
 * @param closure $action
 */
function if_get(string $rule, closure $action)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        return;
    }

    if_any($rule, $action);
}

/**
 * Route for post method.
 *
 * @param string $rule
 * @param closure $action
 */
function if_post(string $rule, closure $action)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if_any($rule, $action);
}

/**
 * Route for put method.
 *
 * @param string $rule
 * @param closure $action
 */
function if_put(string $rule, closure $action)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        return;
    }

    if_any($rule, $action);
}

/**
 * Route for delete method.
 *
 * @param string $rule
 * @param closure $action
 */
function if_delete(string $rule, closure $action)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        return;
    }

    if_any($rule, $action);
}

/**
 * Get or set the verify closure.
 *
 * @param closure|null $action
 * @return closure|null
 */
function if_verify(closure $action = null): ?closure
{
    static $container = null;

    if (! empty($action)) {
        return $container = $action;
    }

    return $container;
}

/**
 * Get or set the 404 handler.
 *
 * @param closure|null $action
 * @return closure|null
 */
function if_not_found(closure $action = null): ?closure
{
    static $container = null;

    if (! empty($action)) {
        return $container = $action;
    }

    return $container;
}

/**
 * Redirect to 404.
 *
 * @param closure|null $action
 */
function not_found(closure $action = null)
{
    header('HTTP/1.1 404 Not Found');
    header('status: 404 Not Found');

    if ($action instanceof closure) {
        flush_action($action);
        exit;
    }

    $action = if_not_found();

    if ($action instanceof closure) {
        flush_action($action, func_get_args());
        exit;
    }
}

/**
 * Redirect to a URI.
 *
 * @param string|null $uri
 * @param bool $forever
 * @return array
 */
function redirect(string $uri = null, bool $forever = false): array
{
    static $container = [];

    if (! is_null($uri)) {
        $container = [
            'uri' => $uri,
            'forever' => $forever,
        ];
    }

    return $container;
}

function trigger_redirect($uri = null, $forever = false)
{/*{{{*/

    if (is_null($uri)) {

        $redirect_info = redirect();
        if (! empty($redirect_info)) {
            $uri = $redirect_info['uri'];
            $forever = $redirect_info['forever'];
        }
    }

    if (! is_null($uri)) {

        if ($forever) {
            header('HTTP/1.1 301 Moved Permanently');
        } else {
            header('HTTP/1.1 302 Found');
        }

        header('Location: '.$uri);
    }
}/*}}}*/

/**
 * Get specified _GET/_POST without filter XSS.
 *
 * @param string $name
 * @param     $default
 *
 * @return mixed
 */
function input_safe(string $name, $default = null)
{
    if (isset($_POST[$name])) {
        return filter_input(INPUT_POST, $name, FILTER_SANITIZE_SPECIAL_CHARS);
    }

    if (isset($_GET[$name])) {
        return filter_input(INPUT_GET, $name, FILTER_SANITIZE_SPECIAL_CHARS);
    }

    return $default;
}

/**
 * Get specified _GET, _POST.
 *
 * @param string $name
 * @param     $default
 *
 * @return mixed
 */
function input(string $name, $default = null)
{
    if (isset($_POST[$name])) {
        return $_POST[$name];
    }

    if (isset($_GET[$name])) {
        return $_GET[$name];
    }

    return $default;
}

/**
 * Get specified _GET/_POST array.
 *
 * @param mixed ...$names
 * @return array
 */
function input_list(...$names): array
{
    if (empty($names)) {
        return [];
    }

    $values = [];

    foreach ($names as $name) {
        $values[] = input($name);
    }

    return $values;
}

/**
 * Get an item from Json Decode _POST using "dot" notation.
 *
 * @param mixed $name
 * @param mixed $default
 * @access public
 * @return mixed
 */
function input_json($name, $default = null)
{
    static $post_data = null;

    if (is_null($post_data)) {
        $post_data = json_decode(input_post_raw(), true);
    }

    return array_get($post_data, $name, $default);
}

/**
 * Get items from Json Decode _POST using "dot" notation.
 *
 * @access public
 * @param mixed ...$names
 * @return array
 */
function input_json_list(...$names): array
{
    if (empty($names)) {
        return [];
    }

    $values = [];

    foreach ($names as $name) {
        $values[] = input_json($name);
    }

    return $values;
}

function input_xml($name, $default = null)
{
    static $post_data = null;

    if (is_null($post_data)) {

        $raw_post_data = simplexml_load_string(input_post_raw(), 'SimpleXMLElement', LIBXML_NOCDATA);

        $post_data = json_decode(json_encode($raw_post_data), true);
    }

    return array_get($post_data, $name, $default);
}

/**
 * Get items from Json Decode _POST using "dot" notation.
 *
 * @access public
 * @param mixed ...$names
 * @return array
 */
function input_xml_list(...$names): array
{
    if (empty($names)) {
        return [];
    }

    $values = [];

    foreach ($names as $name) {
        $values[] = input_xml($name);
    }

    return $values;
}

function input_post_raw()
{
    return file_get_contents('php://input');
}

function input_file($name, $default = [])
{
    return $_FILES[$name] ?? $default;
}

/**
 * Get specified cookie without filter XSS.
 *
 * @param string $name
 * @param null $default
 * @return mixed
 */
function cookie_safe(string $name, $default = null)
{
    if (isset($_COOKIE[$name])) {
        return filter_input(INPUT_COOKIE, $name, FILTER_SANITIZE_SPECIAL_CHARS);
    }

    return $default;
}

/**
 * Get specified _COOKIE.
 *
 * @param string $name
 * @param string|null $default
 * @return mixed
 */
function cookie(string $name, string $default = null): ?string
{
    if (isset($_COOKIE[$name])) {
        return $_COOKIE[$name];
    }

    return $default;
}

/**
 * Get specified _COOKIE array.
 *
 * @param mixed ...$names
 * @return array
 */
function cookie_list(...$names): array
{
    if (empty($names)) {
        return [];
    }

    $values = [];

    foreach ($names as $name) {
        $values[] = cookie($name);
    }

    return $values;
}

function server_safe($name, $default = null)
{
    if (isset($_SERVER[$name])) {
        return filter_input(INPUT_SERVER, $name, FILTER_SANITIZE_SPECIAL_CHARS);
    }

    return $default;
}

function server($name, $default = null)
{
    if (isset($_SERVER[$name])) {
        return $_SERVER[$name];
    }

    return $default;
}

function server_list(...$names): array
{
    if (empty($names)) {
        return [];
    }

    $values = [];

    foreach ($names as $name) {
        $values[] = server($name);
    }

    return $values;
}

/**
 * Set or get view file path.
 *
 * @param string|null $path
 * @return string
 */
function view_path(string $path = null): string
{
    static $container = '';

    if (! empty($path)) {
        return $container = $path;
    }

    return $container;
}

function view_compiler(closure $closure = null): ?closure
{
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
}

/**
 * Render view.
 *
 * @param string $view
 * @param array $args
 * @return false|string
 */
function render(string $view, array $args = [])
{
    if (! empty($args)) {
        extract($args);
    }

    $render = view_compiler();

    ob_start();

    include $render($view);

    $echo = ob_get_contents();

    ob_end_clean();

    return $echo;
}

/**
 * include view and send arguments.
 *
 * @param string $view
 * @param array $args
 */
function include_view(string $view, array $args = [])
{
    if (! empty($args)) {
        extract($args);
    }

    include view_path().$view.'.php';
}

/**
 * Response 304 with ETag.
 *
 * @param string
 */
function cache_with_etag($etag)
{
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
        header('HTTP/1.1 304 Not Modified');

        exit;
    }

    header('ETag: '.$etag);
}

/**
 * get client ip.
 *
 * @return string
 */
function ip(): string
{
    static $container = null;

    if (is_null($container)) {
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = 'unknown';
        }

        return $container = preg_replace('/^[^0-9]*?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*$/', '\1', $ip);
    }

    return $container;
}

/**
 * Get or set the exception handler.
 *
 * @param closure|null $action
 * @return closure|null
 */
function if_has_exception(closure $action = null): ?closure
{
    static $container = null;

    if (! empty($action)) {
        return $container = $action;
    }

    return $container;
}

function http_err_action($error_type, $error_message, $error_file, $error_line, $error_context = null)
{
    $message = $error_message.' '.$error_file.' '.$error_line;

    http_ex_action(new Exception($message));
}

function http_ex_action($ex)
{
    $action = if_has_exception();

    if ($action instanceof closure) {
        flush_action($action, [$ex]);
        exit;
    }

    throw $ex;
}

function http_fatal_err_action()
{
    $err = error_get_last();

    if (! empty($err)) {
        http_err_action($err['type'], $err['message'], $err['file'], $err['line']);
    }
}
