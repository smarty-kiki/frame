<?php

define('DIALOGUE_POOL_TUBE', 'dialogue_pool');
define('DIALOGUE_WAITING_USER_TUBE_PREFIX', 'dialogue_waiting_');
define('DIALOGUE_PUSH_SYNC_USER_TUBE_PREFIX', 'dialogue_push_sync_');

/**
 * delegate
 */

function dialogue_async_send_action(closure $action = null)
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

function dialogue_user_tube_string_action(closure $action = null)
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

    return [$matched, $args];
}/*}}}*/

function _dialogue_topic_match_closure($content, closure $closure)
{/*{{{*/
    $topics = dialogue_topics();

    $matched_topic = false;

    foreach ($topics as $topic) {

        foreach ($topic['topic'] as $topic_string) {

            list($matched_topic, $args) = dialogue_topic_match($content, $topic_string);

            if ($matched_topic) {

                call_user_func($closure, $topic, $args);

                break(2);
            }
        }
    }

    if (! $matched_topic) {

        $match_extension_action = dialogue_topic_match_extension_action();

        if ($match_extension_action instanceof closure) {

            foreach ($topics as $topic) {

                list($matched_topic, $args) = call_user_func($match_extension_action, $content, $topic['topic']);

                if ($matched_topic) {

                    call_user_func($closure, $topic, $args);

                    break;
                }
            }
        }
    }

    return $matched_topic;
}/*}}}*/

function _dialogue_pull_message($tube, $timeout = null, $config_key = 'dialogue')
{/*{{{*/
    $fp = _beanstalk_connection($config_key);

    _beanstalk_watch($fp, $tube);
    _beanstalk_ignore($fp, 'default');

    $job_instance = _beanstalk_reserve($fp, $timeout);
    $id = $job_instance['id'];

    _beanstalk_delete($fp, $id);
    _beanstalk_watch($fp, 'default');
    _beanstalk_ignore($fp, $tube);

    $message = unserialize($job_instance['body']);

    log_module('dialogue', 'PULL '.$tube.' MESSAGE '.json_encode($message, JSON_UNESCAPED_UNICODE));

    if ($is_sync = array_key_exists('sync_user_tube', $message)) {

        _dialogue_force_say_sync(true);

        _dialogue_push_sync_user_tube($message['sync_user_tube']);
    } else {
        _dialogue_force_say_sync(false);

        _dialogue_push_sync_user_tube(false);
    }

    return $message;
}/*}}}*/

function _dialogue_user_tube_string($user_info)
{/*{{{*/
    $action = dialogue_user_tube_string_action();

    if ($action instanceof closure) {

        return call_user_func($action, $user_info);
    }

    return $user_info;
}/*}}}*/

/**
 * dispatch
 */

function _dialogue_waiting_user_tubes($user_info)
{/*{{{*/
    $user_tube_string = _dialogue_user_tube_string($user_info);

    $tubes = cache_keys(DIALOGUE_WAITING_USER_TUBE_PREFIX.$user_tube_string.'_*');

    sort($tubes);

    return $tubes;
}/*}}}*/

function _dialogue_generate_sync_user_tube($user_info)
{/*{{{*/
    $user_tube_string = _dialogue_user_tube_string($user_info);

    return DIALOGUE_PUSH_SYNC_USER_TUBE_PREFIX.$user_tube_string.'_'.microtime(true);
}/*}}}*/

function _dialogue_push_sync_user_tube($sync_user_tube = null)
{/*{{{*/
    static $container = null;

    if (! is_null($sync_user_tube)) {
        $container = $sync_user_tube;
    }

    return $container;
}/*}}}*/

function _dialogue_push($user_info, $content, $tube, $is_sync = false, $delay = 0, $priority = 10, $config_key = 'dialogue')
{/*{{{*/
    $message = [
        'user_info' => $user_info,
        'content' => $content,
        'time' => datetime(),
    ];

    if ($is_sync) {

        $sync_user_tube = $message['sync_user_tube'] = _dialogue_generate_sync_user_tube($user_info);

        $id = _dialogue_push_message($message, $tube, $delay, $priority, $config_key);

        $recvd_message = _dialogue_pull_message($sync_user_tube, 10, $config_key);

        return $recvd_message;
    } else {
        return _dialogue_push_message($message, $tube, $delay, $priority, $config_key);
    }
}/*}}}*/

function _dialogue_push_message($message, $tube, $delay = 0, $priority = 10, $config_key = 'dialogue')
{/*{{{*/
    log_module('dialogue', 'PUSH '.$tube.' MESSAGE '.json_encode($message, JSON_UNESCAPED_UNICODE));

    $fp = _beanstalk_connection($config_key);

    _beanstalk_use_tube($fp, $tube);

    return _beanstalk_put(
        $fp,
        $priority,
        $delay,
        $run_time = 3,
        serialize($message)
    );
}/*}}}*/

function dialogue_push($user_info, $content, $is_sync = false, $delay = 0, $priority = 10, $config_key = 'dialogue')
{/*{{{*/
    $tubes = _dialogue_waiting_user_tubes($user_info);

    $tube = reset($tubes);

    $tube = $tube? $tube: DIALOGUE_POOL_TUBE;

    return _dialogue_push($user_info, $content, $tube, $is_sync, $delay, $priority, $config_key);
}/*}}}*/

/**
 * operator
 */

function dialogue_push_to_other_operator($message, $delay = 0, $priority = 10, $config_key = 'dialogue')
{/*{{{*/
    $user_info = $message['user_info'];

    $tubes = _dialogue_waiting_user_tubes($user_info);

    $now_tube = _dialogue_waiting_user_tube();

    $now_index = array_search($now_tube, $tubes);

    $tube = array_key_exists($now_index + 1, $tubes)? $tubes[$now_index + 1]: DIALOGUE_POOL_TUBE;

    return _dialogue_push_message($message, $tube, $delay, $priority, $config_key);
}/*}}}*/

function _dialogue_content_match($message, $pattern)
{/*{{{*/
    static $match_key = 0;

    $content = $message['content'];

    if ($pattern instanceof closure) {

        return call_user_func($pattern, $message);

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

function _dialogue_operator_topic_user($user_info = null)
{/*{{{*/
    static $container = null;

    if (! is_null($user_info)) {
        $container = $user_info;
    }

    return $container;
}/*}}}*/

function _dialogue_operator_talking_with_user($user_info, closure $action)
{/*{{{*/
    _dialogue_operator_topic_user($user_info);

    $res = call_user_func($action);

    _dialogue_operator_topic_user(false);

    return $res;
}/*}}}*/

function _dialogue_waiting_user_tube($user_info = null)
{/*{{{*/
    static $container = null;

    if (! is_null($user_info)) {

        if ($user_info) {

            $user_tube_string = _dialogue_user_tube_string($user_info);

            $container = DIALOGUE_WAITING_USER_TUBE_PREFIX.$user_tube_string.'_'.microtime(true);
        } else {
            $container = null;
        }
    }

    return $container;
}/*}}}*/

function _dialogue_operator_waiting_with_user($user_info, $timeout, closure $action)
{/*{{{*/
    $user_tube = _dialogue_waiting_user_tube($user_info);

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

function dialogue_watch($config_key = 'dialogue', $memory_limit = 1048576)
{/*{{{*/
    declare(ticks=1);
    $received_signal = false;
    pcntl_signal(SIGTERM, function () use (&$received_signal) {
        $received_signal = true;
    });

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

        $user_info = $message['user_info'];
        $content = $message['content'];
        $time = $message['time'];

        if (datetime($time." +1 min") > datetime()) {

            $matched_topic = _dialogue_topic_match_closure($content, function ($topic, $args) use ($user_info, $content, $time) {

                _dialogue_operator_talking_with_user($user_info, function () use ($topic, $user_info, $content, $time, $args) {

                    call_user_func_array($topic['closure'], array_merge([$user_info, $content, $time], $args));
                });
            });

            if ($matched_topic) {
                if ($finished_action instanceof closure) {
                    call_user_func($finished_action, $user_info, $content, $time);
                }
            } else {
                if ($missed_action instanceof closure) {
                    call_user_func($missed_action, $user_info, $content, $time);
                }
            }
        }
    }
}/*}}}*/

function dialogue_ask_and_wait($user_info, $ask, $pattern = null, $timeout = 60, $config_key = 'dialogue')
{/*{{{*/
    $timeout_time = time() + $timeout;

    return _dialogue_operator_waiting_with_user($user_info, $timeout, function ($user_tube) use ($timeout_time, $user_info, $ask, $pattern, $config_key) {

        dialogue_say($user_info, $ask);

        for (;;) {

            $timeout = $timeout_time - time();

            if ($timeout <= 0) {
                return null;
            }

            $message = _dialogue_pull_message($user_tube, $timeout, $config_key);

            if (is_null($pattern)) {
                return $message;
            }

            $matched = _dialogue_content_match($message, $pattern);

            if (false !== $matched) {

                $message['content'] = $matched;

                return $message;
            } else {
                dialogue_push_to_other_operator($message, $delay = 0, $priority = 10, $config_key);
            }
        }
    });
}/*}}}*/

function dialogue_choice_and_wait($user_info, $ask, array $choice, $timeout, closure $action)
{/*{{{*/

}/*}}}*/

function dialogue_form_and_wait($user_info, $ask, array $form, $timeout, closure $action)
{/*{{{*/

}/*}}}*/

function dialogue_topic($topic, closure $closure)
{/*{{{*/
    $topics = dialogue_topics();

    $topics[] = [
        'topic' => (array) $topic,
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

function dialogue_say($user_info, $content)
{/*{{{*/
    if (_dialogue_force_say_sync()) {

        _dialogue_push($user_info, $content, _dialogue_push_sync_user_tube());

        _dialogue_force_say_sync(false);

        _dialogue_push_sync_user_tube(false);
    } else {

        $action = dialogue_async_send_action();

        call_user_func($action, $user_info, $content);
    }
}/*}}}*/
