<?php

function _redis_connection(array $config)
{/*{{{*/
    static $container = [];

    if (empty($config)) {

        foreach ($container as $connection) {
            $connection->close();
        }

        return $container = [];
    } else {

        $is_sock = isset($config['sock']);

        $sign = $is_sock ?
            $config['sock'] . $config['timeout']:
            $config['host'] . $config['port'] . $config['timeout'];

        if (empty($container[$sign])) {

            $redis = new Redis();

            if ($is_sock) {
                $redis->connect($config['sock'], $config['timeout']);
            } else {
                $redis->connect($config['host'], $config['port'], $config['timeout']);
            }

            if (isset($config['auth'])) {
                $redis->auth($config['auth']);
            }

            $container[$sign] = $redis;
        } else {
            $redis = $container[$sign];
        }

        if (isset($config['database'])) {
            $redis->select($config['database']);
        }

        if (isset($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                $redis->setOption($key, $value);
            }
        }

        return $redis;
    }
}/*}}}*/

function _redis_cache_closure($config_key, closure $closure)
{/*{{{*/
    $config = config_midware('redis', $config_key);

    $redis = _redis_connection($config);

    return call_user_func($closure, $redis);
}/*}}}*/

function cache_get($key, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key) {

        return $redis->get($key);

    });
}/*}}}*/

function cache_multi_get(array $keys, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($keys) {

        $values = $redis->mGet($keys);

        return array_combine($keys, $values);
    });
}/*}}}*/

function cache_set($key, $value, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key, $value, $expires) {

        if ($expires) {
            return $redis->set($key, $value, $expires);
        } else {
            return $redis->set($key, $value);
        }
    });
}/*}}}*/

function cache_add($key, $value, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key, $value, $expires) {

        if ($expires) {
            return $redis->set($key, $value, ['nx', 'ex' => $expires]);
        } else {
            return $redis->setNx($key, $value);
        }
    });
}/*}}}*/

function cache_replace($key, $value, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key, $value, $expires) {

        if ($expires) {
            return $redis->set($key, $value, ['xx', 'ex' => $expires]);
        } else {
            return $redis->setNx($key, $value);
        }
    });
}/*}}}*/

function cache_delete($key, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key) {

        return $redis->del($key);
    });
}/*}}}*/

function cache_multi_delete(array $keys, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($keys) {

        return $redis->del($keys);
    });
}/*}}}*/

function cache_increment($key, $number = 1, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key, $number, $expires) {

        $res = $redis->incr($key, $number);

        if ($expires) {
            $redis->expire($key, $expires);
        }

        return $res;
    });
}/*}}}*/

function cache_decrement($key, $number = 1, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key, $number, $expires) {

        $res = $redis->decr($key, $number);

        if ($expires) {
            $redis->expire($key, $expires);
        }

        return $res;
    });
}/*}}}*/

function cache_keys($pattern = '*', $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($pattern) {

        return $redis->keys($pattern);
    });
}/*}}}*/

function cache_hmset($key, array $array, $expires = 0, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key, $array, $expires) {

        $res = $redis->hmset($key, $array);

        if ($expires) {
            $redis->expire($key, $expires);
        }

        return $res;
    });
}/*}}}*/

function cache_hmget($key, array $fields, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key, $fields) {

        return $redis->hmget($key, $fields);

    });
}/*}}}*/

function cache_lpush($key, $values, $expires = 0, $config_key = 'default')
{/*{{{*/
    $values = (array) $values;

    return _redis_cache_closure($config_key, function ($redis) use ($key, $values, $expires) {

        $res = $redis->lpush($key, ...$values);

        if ($expires) {
            $redis->expire($key, $expires);
        }

        return $res;
    });
}/*}}}*/

function cache_blpop($keys, $wait_time = 0, $config_key = 'default')
{/*{{{*/
    $is_array = is_array($keys);

    $params = (array) $keys;

    $params[] = $wait_time;

    $res = _redis_cache_closure($config_key, function ($redis) use ($params) {

        return $redis->blpop(...$params);
    });

    if ($res) {
        return $is_array? [$res[0] => $res[1]] : $res[1];
    } else {
        return $is_array? []: null;
    }
}/*}}}*/

function cache_setbit($key, $offset, $value, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key, $offset, $value) {

        return $redis->setbit($key, $offset, $value);
    });
}/*}}}*/

function cache_getbit($key, $offset, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key, $offset) {

        return $redis->getbit($key, $offset);
    });
}/*}}}*/

function cache_bitcount($key, $start = 0, $end = -1, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key, $start, $end) {

        return $redis->bitcount($key, $start, $end);
    });
}/*}}}*/

function cache_bitop($destkey, $operation, $keys, $config_key = 'default')
{/*{{{*/
    $keys = (array) $keys;

    return _redis_cache_closure($config_key, function ($redis) use ($destkey, $operation, $keys) {

        return $redis->bitop($operation, $destkey, ...$keys);
    });
}/*}}}*/

function cache_bitpos($key, $bit, $start = 0, $end = -1, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($key, $bit, $start, $end) {

        return $redis->bitpos($key, $bit, $start, $end);
    });
}/*}}}*/

function cache_rename($old_key, $new_key, $config_key = 'default')
{/*{{{*/
    return _redis_cache_closure($config_key, function ($redis) use ($old_key, $new_key) {

        return $redis->rename($old_key, $new_key);
    });
}/*}}}*/

function cache_close()
{/*{{{*/
    return _redis_connection([]);
}/*}}}*/
