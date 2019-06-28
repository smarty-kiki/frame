<?php

/**
 * if is https.
 *
 * @return bool
 */
function is_https()
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
 * Get the current URI.
 *
 * @return string
 */
function uri()
{
    $url = is_https() ? 'https://' : 'http://';

    return $url.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}

/**
 * Get the specified URI info.
 *
 * @param string $name
 *
 * @return mixed
 */
function uri_info($name = null)
{
    static $container = [];

    if (empty($container)) {
        $url = uri();

        $container = parse_url($url);
    }

    return (null === $name) ? $container : $container[$name];
}

function service($path, $action)
{
    if ('/'.$path === uri_info('path')) {

        service_action($action);
    }
}

function service_action($action)
{
    try {
        $res = unit_of_work(function () use ($action) {
            return $action(...service_args());
        });
        header('Content-type: text/plain');
        echo service_data_serialize($res);
        flush();
        exit;
    } catch (Exception $ex) {
        service_ex_serialize($ex);
    }
}

function service_args()
{
    $args = (array) unserialize(file_get_contents('php://input'));

    $returns = [];
    foreach ($args as $arg) {

        if ($arg instanceof entity && false === $arg instanceof null_entity) {
            $arg = dao(get_class($arg))->find($arg->id);
        }

        $returns[] = $arg;
    }

    return $returns;
}

function service_data_serialize($data)
{
    return serialize([
        'res' => true,
        'data' => $data,
    ]);
}

function service_ex_serialize($ex)
{
    log_exception($ex);

    echo serialize([
        'res' => false,
        'exception' => [
            'class' => get_class($ex),
            'message' => $ex->getMessage(),
            'code' => $ex->getCode(),
            'line' => $ex->getLine(),
            'trace' => $ex->getTraceAsString(),
        ],
    ]);
    flush();
    exit;
}

function service_err_serialize($error_type, $error_message, $error_file, $error_line, $error_context = null)
{
    $message = $error_message.' '.$error_file.' '.$error_line;

    service_ex_serialize(new Exception($message));
}

function service_fatel_err_serialize()
{
    $err = error_get_last();

    if (not_empty($err)) {
        service_err_serialize($err['type'], $err['message'], $err['file'], $err['line']);
    }
}

function service_method_not_found()
{
    throw new Exception('调用了不存在的 service 方法');
}
