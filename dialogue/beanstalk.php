<?php

define('DIALOGUE_POOL_TUBE', 'dialogue_pool');
define('DIALOGUE_WAITING_USER_TUBE_PREFIX', 'dialogue_waiting_');
define('DIALOGUE_PUSH_SYNC_USER_TUBE_PREFIX', 'dialogue_push_sync_');


/**
 * beanstalk
 */

function _beanstalk_error($error)
{/*{{{*/
    throw new Exception($error);
}/*}}}*/

function _beanstalk_connection($config_key)
{/*{{{*/
    static $configs = [];
    static $container = [];

    if (empty($configs)) {
        $configs = config('beanstalk');
    }

    $config = $configs[$config_key];

    $sign = $config['host'] . $config['port'] . $config['timeout'];

    if (empty($container[$sign])) {

        $fp = pfsockopen($config['host'], $config['port'], $error_number, $error_str, $config['timeout']);

        stream_set_timeout($fp, -1);

        $container[$sign] = $fp;
    }

    return $container[$sign];
}/*}}}*/

function _beanstalk_disconnect($fp)
{/*{{{*/
    _beanstalk_connection_write($fp, 'quit');

    return fclose($fp);
}/*}}}*/

function _beanstalk_connection_read($fp)
{/*{{{*/
    return stream_get_line($fp, 32768, "\r\n");
}/*}}}*/

function _beanstalk_connection_write($fp, $data)
{/*{{{*/
    $data .= "\r\n";
    return fwrite($fp, $data, strlen($data));
}/*}}}*/

function _beanstalk_put($fp, $priority, $delay, $run_time, $data)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf("put %d %d %d %d\r\n%s", $priority, $delay, $run_time, strlen($data), $data));

    $status = strtok(_beanstalk_connection_read($fp), ' ');

    switch ($status) {
    case 'INSERTED':
    case 'BURIED':
        return (integer) strtok(' '); // job id
    case 'EXPECTED_CRLF':
    case 'JOB_TOO_BIG':
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_use_tube($fp, $tube)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('use %s', $tube));
    $status = strtok(_beanstalk_connection_read($fp), ' ');

    switch ($status) {
    case 'USING':
        return strtok(' ');
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_reserve($fp, $timeout = null)
{/*{{{*/
    if (is_null($timeout)) {
        _beanstalk_connection_write($fp, 'reserve');
    } else {
        _beanstalk_connection_write($fp, sprintf('reserve-with-timeout %d', $timeout));
    }
    $status = strtok(_beanstalk_connection_read($fp), ' ');

    switch ($status) {
    case 'RESERVED':
        return [
            'id' => (integer) strtok(' '),
            'body' => _beanstalk_connection_read($fp),
        ];
    case 'DEADLINE_SOON':
    case 'TIMED_OUT':
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_delete($fp, $id)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('delete %d', $id));
    $status = _beanstalk_connection_read($fp);

    switch ($status) {
    case 'DELETED':
        return true;
    case 'NOT_FOUND':
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_release($fp, $id, $priority, $delay)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('release %d %d %d', $id, $priority, $delay));
    $status = _beanstalk_connection_read($fp);

    switch ($status) {
    case 'RELEASED':
    case 'BURIED':
        return true;
    case 'NOT_FOUND':
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_bury($fp, $id, $priority = 10)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('bury %d %d', $id, $priority));
    $status = _beanstalk_connection_read($fp);

    switch ($status) {
    case 'BURIED':
        return true;
    case 'NOT_FOUND':
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_touch($fp, $id)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('touch %d', $id));
    $status = _beanstalk_connection_read($fp);

    switch ($status) {
    case 'TOUCHED':
        return true;
    case 'NOT_TOUCHED':
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_watch($fp, $tube)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('watch %s', $tube));
    $status = strtok(_beanstalk_connection_read($fp), ' ');

    switch ($status) {
    case 'WATCHING':
        return (integer) strtok(' ');
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_ignore($fp, $tube)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('ignore %s', $tube));
    $status = strtok(_beanstalk_connection_read($fp), ' ');
    switch ($status) {
    case 'WATCHING':
        return (integer) strtok(' ');
    case 'NOT_IGNORED':
    default:
    _beanstalk_error($status);
    return false;
    }
}/*}}}*/

function _beanstalk_peek_read($fp)
{/*{{{*/
    $status = strtok(_beanstalk_connection_read($fp), ' ');
    switch ($status) {
    case 'FOUND':
        return [
            'id' => (integer) strtok(' '),
            'body' => _beanstalk_connection_read($fp),
        ];
    case 'NOT_FOUND':
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_peek($fp, $id)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('peek %d', $id));
    return _beanstalk_peek_read($fp);
}/*}}}*/

function _beanstalk_peek_ready($fp)
{/*{{{*/
    _beanstalk_connection_write($fp, 'peek-ready');
    return _beanstalk_peek_read($fp);
}/*}}}*/

function _beanstalk_peek_delayed($fp)
{/*{{{*/
    _beanstalk_connection_write($fp, 'peek-delayed');
    return _beanstalk_peek_read($fp);
}/*}}}*/

function _beanstalk_peek_buried($fp)
{/*{{{*/
    _beanstalk_connection_write($fp, 'peek-buried');
    return _beanstalk_peek_read($fp);
}/*}}}*/

function _beanstalk_kick($fp, $bound)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('kick %d', $bound));
    $status = strtok(_beanstalk_connection_read($fp), ' ');
    switch ($status) {
    case 'KICKED':
        return (integer) strtok(' ');
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_kick_job($fp, $id)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('kick-job %d', $id));
    $status = strtok(_beanstalk_connection_read($fp), ' ');
    switch ($status) {
    case 'KICKED':
        return true;
    case 'NOT_FOUND':
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_stats_read($fp)
{/*{{{*/
    $status = strtok(_beanstalk_connection_read($fp), ' ');
    switch ($status) {
    case 'OK':
        return _beanstalk_connection_read($fp);
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_stats($fp)
{/*{{{*/
    _beanstalk_connection_write($fp, 'stats');
    return _beanstalk_stats_read($fp);
}/*}}}*/

function _beanstalk_stats_job($fp, $id)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('stats-job %d', $id));
    return _beanstalk_stats_read($fp);
}/*}}}*/

function _beanstalk_stats_tube($fp, $tube)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('stats-tube %s', $tube));
    return _beanstalk_stats_read($fp);
}/*}}}*/

function _beanstalk_list_tube($fp)
{/*{{{*/
    _beanstalk_connection_write($fp, 'list-tubes');
    return _beanstalk_stats_read($fp);
}/*}}}*/

function _beanstalk_list_tube_used($fp)
{/*{{{*/
    _beanstalk_connection_write($fp, 'list-tube-used');
    $status = strtok(_beanstalk_connection_read($fp), ' ');
    switch ($status) {
    case 'USING':
        return strtok(' ');
    default:
        _beanstalk_error($status);
        return false;
    }
}/*}}}*/

function _beanstalk_list_tube_watched($fp)
{/*{{{*/
    _beanstalk_connection_write($fp, 'list-tubes-watched');
    return _beanstalk_stats_read($fp);
}/*}}}*/

/**
 * delegate
 */

function dialogue_send_action(closure $action = null)
{/*{{{*/
    static $container = null;

    if (!empty($action)) {
        return $container = $action;
    }

    return $container;
}/*}}}*/

function dialogue_topic_miss_action(closure $action = null)
{/*{{{*/
    static $container = null;

    if (!empty($action)) {
        return $container = $action;
    }

    return $container;
}/*}}}*/

function dialogue_topic_finish_action(closure $action = null)
{/*{{{*/
    static $container = null;

    if (!empty($action)) {
        return $container = $action;
    }

    return $container;
}/*}}}*/

function dialogue_topic_match_extension_action(closure $action = null)
{/*{{{*/
    static $container = null;

    if (!empty($action)) {
        return $container = $action;
    }

    return $container;
}/*}}}*/

/**
 * tool
 */

function dialogue_topics($topics = null)
{/*{{{*/
    static $container = [];

    if (is_null($topics)) {
        return $container;
    }

    return $container = $topics;
}/*}}}*/

function dialogue_topic_match($content, $topic)
{/*{{{*/
    $reg = '/^'.str_replace('\*', '([^\/]+?)', preg_quote($topic, '/')).'$/';

    preg_match_all($reg, $content, $matches);

    $args = [];

    if ($matched = !empty($matches[0])) {
        unset($matches[0]);

        foreach ($matches as $v) {
            $args[] = $v[0];
        }
    }

    if (! $matched) {

        $match_extension_action = dialogue_topic_match_extension_action();

        if ($match_extension_action instanceof closure) {
            list($matched, $args) = call_user_func($match_extension_action, $content, $topic);
        }
    }

    return [$matched, $args];
}/*}}}*/

function _dialogue_pull_message($tube, $timeout = null, $config_key = 'default')
{/*{{{*/
    $fp = _beanstalk_connection($config_key);

    $tmp = _beanstalk_list_tube_used($fp);
    /**kiki*/error_log(strip_tags(print_r($tmp, true))."\n", 3, "/tmp/error_user.log");

    _beanstalk_watch($fp, $tube);

    $job_instance = _beanstalk_reserve($fp, $timeout);
    $id = $job_instance['id'];

    _beanstalk_delete($fp, $id);
    _beanstalk_ignore($fp, $tube);

    $message = unserialize($job_instance['body']);

    if ($is_sync = array_key_exists('sync_user_tube', $message)) {

        _dialogue_force_say_sync(true);

        _dialogue_push_sync_user_tube($message['sync_user_tube']);
    } else {
        _dialogue_force_say_sync(false);

        _dialogue_push_sync_user_tube(false);
    }

    return $message;
}/*}}}*/

/**
 * dispatch
 */

function _dialogue_waiting_user_tubes($user_id)
{/*{{{*/
    $tubes = cache_keys(DIALOGUE_WAITING_USER_TUBE_PREFIX.$user_id.'_*');

    sort($tubes);

    return $tubes;
}/*}}}*/

function _dialogue_generate_sync_user_tube($user_id)
{/*{{{*/
    return DIALOGUE_PUSH_SYNC_USER_TUBE_PREFIX.$user_id.'_'.microtime(true);
}/*}}}*/

function _dialogue_push_sync_user_tube($sync_user_tube = null)
{/*{{{*/
    static $container = null;

    if (! is_null($sync_user_tube)) {
        $container = $sync_user_tube;
    }

    return $container;
}/*}}}*/

function _dialogue_push($user_id, $content, $tube, $is_sync = false, $delay = 0, $priority = 10, $config_key = 'default')
{/*{{{*/
    $message = [
        'user_id' => $user_id,
        'content' => $content,
        'time' => datetime(),
    ];

    if ($is_sync) {

        $sync_user_tube = $message['sync_user_tube'] = _dialogue_generate_sync_user_tube($user_id);

        $id = _dialogue_push_message($message, $tube, $delay, $priority, $config_key);

        $recvd_message = _dialogue_pull_message($sync_user_tube, 10, $config_key);

        return $recvd_message;
    } else {
        return _dialogue_push_message($message, $tube, $delay, $priority, $config_key);
    }
}/*}}}*/

function _dialogue_push_message($message, $tube, $delay = 0, $priority = 10, $config_key = 'default')
{/*{{{*/
    $fp = _beanstalk_connection($config_key);

    $tmp = _beanstalk_list_tube_used($fp);
    /**kiki*/error_log(strip_tags(print_r($tmp, true))."\n", 3, "/tmp/error_user.log");

    _beanstalk_use_tube($fp, $tube);

    return _beanstalk_put(
        $fp,
        $priority,
        $delay,
        $run_time = 3,
        serialize($message)
    );
}/*}}}*/

function dialogue_push($user_id, $content, $is_sync = false, $delay = 0, $priority = 10, $config_key = 'default')
{/*{{{*/
    $tubes = _dialogue_waiting_user_tubes($user_id);

    $tube = reset($tubes);

    $tube = $tube? $tube: DIALOGUE_POOL_TUBE;

    return _dialogue_push($user_id, $content, $tube, $is_sync, $delay, $priority, $config_key);
}/*}}}*/

/**
 * operator
 */

function dialogue_push_to_other_operator($message, $delay = 0, $priority = 10, $config_key = 'default')
{/*{{{*/
    $user_id = $message['user_id'];

    $tubes = _dialogue_waiting_user_tubes($user_id);

    $now_tube = _dialogue_waiting_user_tube();

    $now_index = array_search($now_tube, $tubes);

    $tube = array_key_exists($now_index + 1, $tubes)? $tubes[$now_index + 1]: DIALOGUE_POOL_TUBE;

    return _dialogue_push_message($message, $tube, $delay, $priority, $config_key);
}/*}}}*/

function _dialogue_content_match($content, $pattern)
{/*{{{*/
    static $match_key = 0;

    if (is_null($pattern)) {
        return $content;
    }

    if ($pattern instanceof closure) {

        return call_user_func($pattern, $content);

    } elseif (is_array($pattern)) {

        return array_search($content, $pattern);

    } else {

        $count = preg_match_all($pattern, $content, $matches);

        if (array_key_exists($match_key, $matches) && $matches[$match_key]) {
            return $content;
        } else {
            return false;
        }
    }
}/*}}}*/

function _dialogue_operator_topic_user($user_id = null)
{/*{{{*/
    static $container = null;

    if (! is_null($user_id)) {
        $container = $user_id;
    }

    return $container;
}/*}}}*/

function _dialogue_operator_talking_with_user($user_id, closure $action)
{/*{{{*/
    _dialogue_operator_topic_user($user_id);

    $res = call_user_func($action);

    _dialogue_operator_topic_user(false);

    return $res;
}/*}}}*/

function _dialogue_waiting_user_tube($user_id = null)
{/*{{{*/
    static $container = null;

    if (! is_null($user_id)) {
        if ($user_id) {
            $container = DIALOGUE_WAITING_USER_TUBE_PREFIX.$user_id.'_'.microtime(true);
        } else {
            $container = null;
        }
    }

    return $container;
}/*}}}*/

function _dialogue_operator_waiting_with_user($user_id, $timeout, closure $action)
{/*{{{*/
    $user_tube = _dialogue_waiting_user_tube($user_id);

    cache_increment($user_tube, 1, $timeout);

    $res = null;

    try {

        $res = call_user_func($action, $user_tube);

    } catch (Exception $ex) {
    } finally {

        cache_delete($user_tube);
        _dialogue_waiting_user_tube(false);

        return $res;
    }
}/*}}}*/

function dialogue_watch($config_key = 'default', $memory_limit = 1048576)
{/*{{{*/
    declare(ticks=1);
    $received_signal = false;
    pcntl_signal(SIGTERM, function () use (&$received_signal) {
        $received_signal = true;
    });

    $topics = dialogue_topics();
    $missed_action = dialogue_topic_miss_action();
    $finished_action = dialogue_topic_finish_action();

    for (;;) {

        if (memory_get_usage(true) > $memory_limit) {
            throw new Exception('dialogue_watch out of memory');
        }

        if ($received_signal) {
            break;
        }

        $message = _dialogue_pull_message(DIALOGUE_POOL_TUBE, null, $config_key);

        /**kiki*/error_log(strip_tags(print_r($message, true))."\n", 3, "/tmp/error_user.log");

        $user_id = $message['user_id'];
        $content = $message['content'];
        $time = $message['time'];

        if (datetime($time." +1 min") > datetime()) {

            $matched_topic = false;

            foreach ($topics as $info) {

                foreach ((array) $info['topic'] as $topic) {

                    list($matched_topic, $args) = dialogue_topic_match($content, $topic);

                    if ($matched_topic) {

                        _dialogue_operator_talking_with_user($user_id, function () use ($info, $user_id, $content, $time, $args) {
                            call_user_func_array($info['closure'], array_merge([$user_id, $content, $time], $args));
                        });

                        break(2);
                    }
                }
            }

            if ($matched_topic) {
                if ($finished_action instanceof closure) {
                    call_user_func($finished_action, $user_id, $content, $time);
                }
            } else {
                if ($missed_action instanceof closure) {
                    call_user_func($missed_action, $user_id, $content, $time);
                }
            }
        }
    }
}/*}}}*/

function dialogue_ask_and_wait($user_id, $ask, $pattern = null, $timeout = 60, $config_key = 'default')
{/*{{{*/
    $timeout_time = time() + $timeout;

    return _dialogue_operator_waiting_with_user($user_id, $timeout, function ($user_tube) use ($timeout_time, $user_id, $ask, $pattern, $config_key) {

        dialogue_say($user_id, $ask);

        for (;;) {

            $timeout = $timeout_time - time();

            if ($timeout <= 0) {
                return null;
            }

            $message = _dialogue_pull_message($user_tube, $timeout, $config_key);

            $content = $message['content'];

            if (is_null($pattern)) {
                return $content;
            }

            $matched = _dialogue_content_match($content, $pattern);

            if (false !== $matched) {
                return $matched;
            } else {
                dialogue_push_to_other_operator($message, $delay = 0, $priority = 10, $config_key);
            }
        }
    });
}/*}}}*/

function dialogue_choice_and_wait($user_id, $ask, array $choice, $timeout, closure $action)
{/*{{{*/

}/*}}}*/

function dialogue_form_and_wait($user_id, $ask, array $form, $timeout, closure $action)
{/*{{{*/

}/*}}}*/

function dialogue_topic($topic, closure $closure)
{/*{{{*/
    $topics = dialogue_topics();

    $topics[] = [
        'topic' => $topic,
        'closure' => $closure,
    ];

    dialogue_topics($topics);
}/*}}}*/

function _dialogue_force_say_sync($bool = null)
{/*{{{*/
    static $container = null;

    if (! is_null($bool)) {
        return $container = $bool;
    }

    return $container;
}/*}}}*/

function dialogue_say($user_id, $content)
{/*{{{*/
    if (_dialogue_force_say_sync()) {

        _dialogue_push($user_id, $content, _dialogue_push_sync_user_tube());

        _dialogue_force_say_sync(false);

        _dialogue_push_sync_user_tube(false);
    } else {

        $action = dialogue_send_action();

        call_user_func($action, $user_id, $content);
    }
}/*}}}*/
