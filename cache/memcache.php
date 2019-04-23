<?php

function _memcache_connection(array $config)
{/*{{{*/
    static $container = [];

    if (empty($config)) {

        foreach ($container as $connection) {

            memcache_close($connection);
        }

        return $container = [];
    } else {

        $sign = $config['host'] . $config['port'];

        if (empty($container[$sign])) {

            $connection = memcache_connect($config['host'], $config['port']);

            $container[$sign] = $connection;
        } else {
            $connection = $container[$sign];
        }

        return $connection;
    }
}/*}}}*/

function _memcache_cache_closure($config_key, closure $closure)
{/*{{{*/
    $config = config_midware('memcache', $config_key);

    $connection = _memcache_connection($config);

    return call_user_func($closure, $connection);
}/*}}}*/

function cache_get($key, $config_key = 'default')
{/*{{{*/
    return _memcache_cache_closure($config_key, function ($connection) use ($key) {

        return memcache_get($connection, (string) $key);

    });
}/*}}}*/

function cache_multi_get($keys, $config_key = 'default')
{/*{{{*/
    return _memcache_cache_closure($config_key, function ($connection) use ($keys) {

        $res = [];
        foreach ($keys as $key) {
            $res[$key] = memcache_get($connection, (string) $key);
        }

        return $res;
    });
}/*}}}*/

function cache_set($key, $value, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _memcache_cache_closure($config_key, function ($connection) use ($key, $value, $expires) {

        return memcache_set($connection, (string) $key, $value, false, (int) $expires);

    });
}/*}}}*/

function cache_add($key, $value, $expires, $config_key = 'default')
{/*{{{*/
    return _memcache_cache_closure($config_key, function ($connection) use ($key, $value, $expires) {

        return memcache_add($connection, (string) $key, $value, false, (int) $expires);

    });
}/*}}}*/

function cache_replace($key, $value, $expires, $config_key = 'default')
{/*{{{*/
    return _memcache_cache_closure($config_key, function ($connection) use ($key, $value, $expires) {

        return memcache_replace($connection, (string) $key, $value, false, (int) $expires);

    });
}/*}}}*/

function cache_delete($key, $config_key = 'default')
{/*{{{*/
    return _memcache_cache_closure($config_key, function ($connection) use ($key) {

        return memcache_delete($connection, (string) $key);

    });
}/*}}}*/

function cache_multi_delete($keys, $config_key = 'default')
{/*{{{*/
    return _memcache_cache_closure($config_key, function ($connection) use ($keys) {

        foreach($keys as $key) {
            memcache_delete($connection, (string) $key);
        }

    });
}/*}}}*/

function cache_increment($key, $number = 1, $expires = 86400, $config_key = 'default')
{/*{{{*/
    return _memcache_cache_closure($config_key, function ($connection) use ($key, $number, $expires) {

        memcache_add($connection, (string) $key, 0, false, $expires);

        return memcache_increment($connection, (string) $key, (int) $number);

    });
}/*}}}*/

function cache_decrement($key, $number = 1, $expires = 86400, $config_key = 'default')
{/*{{{*/
    return _memcache_cache_closure($config_key, function ($connection) use ($key, $number, $expires) {

        memcache_add($connection, (string) $key, 0, false, $expires);

        return memcache_decrement($connection, (string) $key, (int) $number);

    });
}/*}}}*/

function cache_close()
{/*{{{*/
    return _memcache_connection([]);
}/*}}}*/
