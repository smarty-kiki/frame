<?php

function _beanstalk_error($error)
{/*{{{*/
    throw new Exception($error);
}/*}}}*/

function _beanstalk_connection($config_key)
{/*{{{*/
    static $container = [];

    $config = config_midware('beanstalk', $config_key);

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

function _beanstalk_connection_read($fp, $data_length = null)
{/*{{{*/
    if ($data_length) {

        $data = stream_get_contents($fp, $data_length + 2);
        $meta = stream_get_meta_data($fp);

        if ($meta['timed_out']) {
            throw new RuntimeException('Connection timed out while reading data from socket.');
        }

        return rtrim($data, "\r\n");

    } else {

        return stream_get_line($fp, 16384, "\r\n");
    }
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

function _beanstalk_pause_tube($fp, $tube, $delay)
{
    _beanstalk_connection_write($fp, sprintf('pause-tube %s %d', $tube, $delay));
    $status = strtok(_beanstalk_connection_read($fp), ' ');

    switch ($status) {
    case 'PAUSED':
        return true;
    case 'NOT_FOUND':
    default:
        _beanstalk_error($status);
        return false;
    }
}

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
            'body' => _beanstalk_connection_read($fp, (integer) strtok(' ')),
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
            'body' => _beanstalk_connection_read($fp, (integer) strtok(' ')),
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
        return _beanstalk_connection_read($fp, (integer) strtok(' '));
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

function queue_finish_action(closure $action = null)
{/*{{{*/
    static $container = null;

    if (!empty($action)) {
        return $container = $action;
    }

    return $container;
}/*}}}*/

function queue_job_pickup($job_name)
{/*{{{*/
    $jobs = queue_jobs();

    return $jobs[$job_name];
}/*}}}*/

function queue_jobs($jobs = null)
{/*{{{*/
    static $container = [];

    if (is_null($jobs)) {
        return $container;
    }

    return $container = $jobs;
}/*}}}*/

function queue_job($job_name, closure $closure, $priority = 10, $retry = [], $tube = 'default', $config_key = 'default')
{/*{{{*/
    $jobs = queue_jobs();

    $jobs[$job_name] = [
        'closure' => $closure,
        'priority' => $priority,
        'retry' => $retry,
        'tube' => $tube,
        'config_key' => $config_key,
    ];

    queue_jobs($jobs);
}/*}}}*/

function queue_push($job_name, array $data = [], $delay = 0)
{/*{{{*/
    $job = queue_job_pickup($job_name);

    $fp = _beanstalk_connection($job['config_key']);

    _beanstalk_use_tube($fp, $job['tube']);

    $id = _beanstalk_put(
        $fp,
        $job['priority'], 
        $delay,
        $run_time = 60,
        serialize([
            'job_name' => $job_name,
            'data' => $data,
            'retry' => 0
        ])
    );

    return $id;
}/*}}}*/

function queue_watch($tube = 'default', $config_key = 'default', $memory_limit = 1048576)
{/*{{{*/
    declare(ticks=1);
    $received_signal = false;
    pcntl_signal(SIGTERM, function () use (&$received_signal) {
        $received_signal = true;
    });

    $finished_action = queue_finish_action();

    $fp = _beanstalk_connection($config_key);

    _beanstalk_watch($fp, $tube);

    if ($tube !== 'default') {

        _beanstalk_ignore($fp, 'default');
    }

    for (;;) {

        if (memory_get_usage(true) > $memory_limit) {
            throw new Exception('queue_watch out of memory');
        }

        if ($received_signal) {
            break;
        }

        $job_instance = _beanstalk_reserve($fp);
        $id = $job_instance['id'];
        $body = unserialize($job_instance['body']);
        $job_name = $body['job_name'];
        $data = $body['data'];
        $retry = $body['retry'];

        $job = queue_job_pickup($job_name);

        try {

            $res = false;
            $res = call_user_func_array($job['closure'], [$data, $retry, $id]);

        } catch (exception $exception) {

            log_exception($exception);
        }

        if ($res) {
            _beanstalk_delete($fp, $id);
        } else {
            if (isset($job['retry'][$retry])) {
                $retry_delay = $job['retry'][$retry];

                $new_id = _beanstalk_put(
                    $fp,
                    $job['priority'], 
                    $retry_delay,
                    $run_time = 60,
                    serialize([
                        'job_name' => $job_name,
                        'data' => $data,
                        'retry' => $retry + 1,
                    ])
                );

                _beanstalk_delete($fp, $id);
            } else {
                _beanstalk_bury($fp, $id);
            }
        }

        if ($finished_action instanceof closure) {
            call_user_func($finished_action);
        }
    }
}/*}}}*/

function queue_status($tube = 'default', $config_key = 'default')
{/*{{{*/
    $fp = _beanstalk_connection($config_key);

    return _beanstalk_stats_tube($fp, $tube);
}/*}}}*/
