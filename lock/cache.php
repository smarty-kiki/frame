<?php

define('LOCK_CACHE_MIDWARE_KEY', 'lock');

function singly_run($key, $expire_second, closure $closure, $fail_closure = null)
{/*{{{*/
    $lock_key = 'singly_run_'.$key;

    $lock_num = cache_increment($lock_key, 1, $expire_second, LOCK_CACHE_MIDWARE_KEY);

    $locked = ($lock_num > 1);

    if (! $locked) {
        $res = call_user_func($closure);

        cache_delete($lock_key, LOCK_CACHE_MIDWARE_KEY);
    } else {
        $res = value($fail_closure);
    }

    return $res;
}/*}}}*/

function serially_run($key, $expire_second, $wait_second, closure $closure, $fail_closure = null)
{/*{{{*/
    $counter_key = 'serially_queue_counter_'.$key;
    $list_key = 'serially_queue_list_'.$key;

    $t1 = cache_increment($counter_key, 1, $expire_second, LOCK_CACHE_MIDWARE_KEY);

    if ($t1 == 1 || $turn = cache_blpop($list_key, $wait_second)) {

        $res = call_user_func($closure);

        $t2 = cache_decrement($counter_key, 1, $expire_second, LOCK_CACHE_MIDWARE_KEY);

        if ($t2 != 0) {

            cache_lpush($list_key, $t1 + 1, $expire_second);
        }
    } else {

        $res = value($fail_closure);
    }

    return $res;
}/*}}}*/
