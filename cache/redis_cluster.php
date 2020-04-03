<?php

function _redis_cluster_connection(array $config)
{/*{{{*/
    static $container = [];

    if (empty($config)) {

        foreach ($container as $connection) {
            $connection->close();
        }

        return $container = [];
    } else {

        $name = $config['name'] ?? null;

        $sign = serialize($config);

        if (empty($container[$sign])) {

            if (isset($config['name'])) {

                $redis_cluster = new RedisCluster($config['name']);
            } else {

                $redis_cluster = new RedisCluster(null, array_build($config['cluster'], function ($ip, $port) {
                    return [null, $ip.':'.$port];
                }), $config['timeout'], $config['timeout'], $config['auth'] ?? null);
            }

            $container[$sign] = $redis_cluster;
        } else {
            $redis_cluster = $container[$sign];
        }

        if (isset($config['database'])) {
            $redis_cluster->select($config['database']);
        }

        if (isset($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                $redis_cluster->setOption($key, $value);
            }
        }

        return $redis_cluster;
    }
}/*}}}*/

function _redis_cluster_cache_closure($config_key, closure $closure)
{/*{{{*/
    $config = config_midware('redis_cluster', $config_key);

    $redis_cluster = _redis_cluster_connection($config);

    return call_user_func($closure, $redis_cluster);
}/*}}}*/

function cache_get($key, $config_key = 'default')
{/*{{{*/
    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($key) {

        return $redis_cluster->get($key);

    });
}/*}}}*/

function cache_multi_get(array $keys, $config_key = 'default')
{/*{{{*/
    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($keys) {

        $values = $redis_cluster->mGet($keys);

        return array_combine($keys, $values);
    });
}/*}}}*/

function cache_set($key, $value, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($key, $value, $expires) {

        if ($expires) {
            return $redis_cluster->set($key, $value, $expires);
        } else {
            return $redis_cluster->set($key, $value);
        }
    });
}/*}}}*/

function cache_add($key, $value, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($key, $value, $expires) {

        if ($expires) {
            return $redis_cluster->set($key, $value, ['nx', 'ex' => $expires]);
        } else {
            return $redis_cluster->setNx($key, $value);
        }
    });
}/*}}}*/

function cache_replace($key, $value, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($key, $value, $expires) {

        if ($expires) {
            return $redis_cluster->set($key, $value, ['xx', 'ex' => $expires]);
        } else {
            return $redis_cluster->setNx($key, $value);
        }
    });
}/*}}}*/

function cache_delete($key, $config_key = 'default')
{/*{{{*/
    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($key) {

        return $redis_cluster->delete($key);
    });
}/*}}}*/

function cache_multi_delete(array $keys, $config_key = 'default')
{/*{{{*/
    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($keys) {

        return $redis_cluster->delete($keys);
    });
}/*}}}*/

function cache_increment($key, $number = 1, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($key, $number, $expires) {

        $res = $redis_cluster->incr($key, $number);

        if ($expires) {
            $redis_cluster->setTimeout($key, $expires);
        }

        return $res;
    });
}/*}}}*/

function cache_decrement($key, $number = 1, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($key, $number, $expires) {

        $res = $redis_cluster->decr($key, $number);

        if ($expires) {
            $redis_cluster->setTimeout($key, $expires);
        }

        return $res;
    });
}/*}}}*/

function cache_keys($pattern = '*', $config_key = 'default')
{/*{{{*/
    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($pattern) {

        return $redis_cluster->keys($pattern);
    });
}/*}}}*/

function cache_hmset($key, array $array, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($key, $array, $expires) {

        $res = $redis_cluster->hmset($key, $array);

        if ($expires) {
            $redis_cluster->setTimeout($key, $expires);
        }

        return $res;
    });
}/*}}}*/

function cache_hmget($key, array $fields, $config_key = 'default')
{/*{{{*/
    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($key, $fields) {

        return $redis_cluster->hmget($key, $fields);

    });
}/*}}}*/

function cache_lpush($key, $values, $expires = 0, $config_key = 'default')
{/*{{{*/
    $values = (array) $values;

    return _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($key, $values, $expires) {

        $res = $redis_cluster->lpush($key, ...$values);

        if ($expires) {
            $redis_cluster->setTimeout($key, $expires);
        }

        return $res;
    });
}/*}}}*/

function cache_blpop($keys, $wait_time = 0, $config_key = 'default')
{/*{{{*/
    $is_array = is_array($keys);

    $params = (array) $keys;

    $params[] = $wait_time;

    $res = _redis_cluster_cache_closure($config_key, function ($redis_cluster) use ($params) {

        return $redis_cluster->blpop(...$params);
    });

    if ($res) {
        return $is_array? [$res[0] => $res[1]] : $res[1];
    } else {
        return $is_array? []: null;
    }
}/*}}}*/

function cache_close()
{/*{{{*/
    return _redis_cluster_connection([]);
}/*}}}*/
