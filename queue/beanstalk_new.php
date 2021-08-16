<?php

function _beanstalk_error($error)
{/*{{{*/
    throw new Exception($error);
}/*}}}*/

function _beanstalk_container(array $config)
{/*{{{*/
    static $container = [];

    if (empty($config)) {

        return $container = [];
    } else {

        $host = $config['host'];
        $port = $config['port'];
        $timeout = $config['timeout'];

        $identifier = $host . $port . $timeout;

        if (!isset($container[$identifier])) {

            $fp = fsockopen($host, $port, $error_number, $error_str, $timeout);

            if ($fp === false) {
                _beanstalk_error('ERROR: '.$errno.' - '.$errstr);
            }

            stream_set_timeout($fp, $timeout);

            $container[$identifier] = $fp;
        }

        return $container[$identifier];
    }
}/*}}}*/

function _beanstalk_connection($config_key)
{/*{{{*/
    $config = config_midware('beanstalk', $config_key);

    return _beanstalk_container([
        'host' => $config['host'],
        'port' => $config['port'],
        'timeout' => $config['timeout'],
    ]);
}/*}}}*/

function _beanstalk_disconnect($fp)
{/*{{{*/
    _beanstalk_connection_write($fp, 'quit');

    return fclose($fp);
}/*}}}*/

function _beanstalk_close_all()
{/*{{{*/
    _beanstalk_container([]);
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
{/*{{{*/
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
                'body' => _beanstalk_connection_read($fp, (integer) strtok(' ')),
            ];
        case 'TIMED_OUT':
            return false;
        case 'DEADLINE_SOON':
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

function _beanstalk_stats_job($fp, $id, $array_result = false)
{/*{{{*/
    _beanstalk_connection_write($fp, sprintf('stats-job %d', $id));

    $res = _beanstalk_stats_read($fp);

    if ($array_result) {

        $tmp = strtok($res, "\n");

        $res = [];

        while ($tmp !== false) {
            $key = $tmp = strtok(':');
            $tmp = $value = strtok("\n");
            if ($value !== false) {
                $res[$key] = trim($value);
            }
        }
    }

    return $res;
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

function _queue_last_reserved_job_id($id = null)
{/*{{{*/
    static $container = null;

    if (! is_null($id)) {
        return $container = $id;
    }

    return $container;
}/*}}}*/

function _queue_last_watched_config_key($config_key = null)
{/*{{{*/
    static $container = null;

    if (! is_null($config_key)) {
        return $container = $config_key;
    }

    return $container;
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
        ])
    );

    return $id;
}/*}}}*/

function queue_pause($tube = 'default', $config_key = 'default', $delay = 3600)
{/*{{{*/
    $fp = _beanstalk_connection($config_key);

    _beanstalk_pause_tube($fp, $tube, $delay);
}/*}}}*/

function queue_watch($tube = 'default', $config_key = 'default', $memory_limit = 1048576)
{/*{{{*/
    $out_of_run_time_deleted_job_ids = [];

    declare(ticks=1);
    $received_signal = false;
    pcntl_signal(SIGTERM, function () use (&$received_signal) {
        $received_signal = true;
    });

    _queue_last_watched_config_key($config_key);
    $finished_action = queue_finish_action();

    for (;;) {

        if (memory_get_usage(true) > $memory_limit) {
            throw new Exception('queue_watch out of memory');
        }

        if ($received_signal) {
            break;
        }

        _beanstalk_close_all();
        $fp = _beanstalk_connection($config_key);

        _beanstalk_watch($fp, $tube);

        if ($tube !== 'default') {

            _beanstalk_ignore($fp, 'default');
        }

        $job_instance = _beanstalk_reserve($fp, 5);
        if ($job_instance === false) {
            continue;
        }

        $id = $job_instance['id'];
        $body = unserialize($job_instance['body']);
        $job_name = $body['job_name'];
        $data = $body['data'];

        $job = queue_job_pickup($job_name);
        _queue_last_reserved_job_id($id);

        try {

            $res = isset($out_of_run_time_deleted_job_ids[$id]);

            if (! $res) {
                $res = call_user_func_array($job['closure'], [$data, $id]);
            }

        } catch (exception $exception) {

            log_exception($exception);
        }

        if ($res) {
            try {
                _beanstalk_delete($fp, $id);
            } catch (exception $exception) {
                if ($exception->getMessage() === 'NOT_FOUND') {
                    $out_of_run_time_deleted_job_ids[$id] = true;
                }
                log_exception($exception);
            }
        } else {

            $stats_info = _beanstalk_stats_job($fp, $id, true);

            $retry = $stats_info['releases'];

            if (isset($job['retry'][$retry])) {

                $retry_delay = $job['retry'][$retry];

                _beanstalk_release($fp, $id, $job['priority'], $retry_delay);

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

function queue_job_touch()
{/*{{{*/
    $config_key = _queue_last_watched_config_key();

    if ($config_key) {

        $fp = _beanstalk_connection($config_key);

        $job_id = _queue_last_reserved_job_id();

        if ($job_id) {

            return _beanstalk_touch($fp, $job_id);
        }
    }
}/*}}}*/
