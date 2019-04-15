<?php

define('SPIDER_POOL_TUBE', 'spider_pool');

/**
 * beanstalk
 */

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
    throw new Exception($status);
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
        throw new Exception($status);
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
    throw new Exception($status);
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
    throw new Exception($status);
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
    throw new Exception($status);
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
    throw new Exception($status);
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
    throw new Exception($status);
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
        throw new Exception($status);
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
    $this->_error($status);
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
    throw new Exception($status);
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
        throw new Exception($status);
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
    throw new Exception($status);
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
        throw new Exception($status);
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
        throw new Exception($status);
        return false;
    }
}/*}}}*/

function _beanstalk_list_tube_watched($fp)
{/*{{{*/
    _beanstalk_connection_write($fp, 'list-tubes-watched');
    return _beanstalk_stats_read($fp);
}/*}}}*/

/**
 * tool
 */

function spider_cron_string_parse($cron_string, $after_timestamp = null)
{/*{{{*/
    otherwise(
        preg_match(
            '/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i',
            trim($cron_string)
        ), 
        "Invalid cron string: ".$cron_string,
        'InvalidArgumentException'
    );

    if (is_null($after_timestamp)) {
        $after_timestamp = time();
    } else {
        otherwise(
            $after_timestamp && !is_numeric($after_timestamp),
            "\$after_timestamp must be a valid unix timestamp ($after_timestamp given)",
            'InvalidArgumentException'
        );
    }

    $cron = preg_split("/[\s]+/i",trim($cron_string));

    $date = [
        'minutes'  =>  _spider_cron_number_parse($cron[0], 0, 59),
        'hours'    =>  _spider_cron_number_parse($cron[1], 0, 23),
        'dom'      =>  _spider_cron_number_parse($cron[2], 1, 31),
        'month'    =>  _spider_cron_number_parse($cron[3], 1, 12),
        'dow'      =>  _spider_cron_number_parse($cron[4], 0, 6),
    ];

    for ($i = 0; $i <= 60 * 60 * 24 * 366; $i += 60) {
        if (
            in_array(intval(date('j', $after_timestamp + $i)), $date['dom']) &&
            in_array(intval(date('n', $after_timestamp + $i)), $date['month']) &&
            in_array(intval(date('w', $after_timestamp + $i)), $date['dow']) &&
            in_array(intval(date('G', $after_timestamp + $i)), $date['hours']) &&
            in_array(intval(date('i', $after_timestamp + $i)), $date['minutes'])
        ) {
            return $after_timestamp + $i;
        }
    }

    return null;
}/*}}}*/

function _spider_cron_number_parse($s, $min, $max)
{/*{{{*/
    $result = [];
    $v = explode(',', $s);

    foreach ($v as $vv) {

        $vvv  = explode('/', $vv);
        $step = empty($vvv[1])? 1: $vvv[1];
        $vvvv = explode('-', $vvv[0]);
        $_min = count($vvvv) == 2? $vvvv[0]: ($vvv[0] == '*'? $min: $vvv[0]);
        $_max = count($vvvv) == 2? $vvvv[1]: ($vvv[0] == '*'? $max: $vvv[0]);

        for ($i = $_min;$i <= $_max; $i += $step) {
            $result[$i] = intval($i);
        }
    }

    ksort($result);

    return $result;
}    /*}}}*/

/**
 *  delegate
 */

function spider_finish_action(closure $action = null)
{/*{{{*/
    static $container = null;

    if (!empty($action)) {
        return $container = $action;
    }

    return $container;
}/*}}}*/

/**
 * dispatch
 */

function spider_trigger()
{/*{{{*/
    $jobs = spider_jobs();

    foreach ($jobs as $job_name => $job) {

        if (spider_cron_string_parse($job['cron_string']) === time()) {

            spider_job_push($job_name);
        }
    }
}/*}}}*/

function spider_job_push($job_name, $url = null, $data = [])
{/*{{{*/
    $job = _spider_job_pickup($job_name);

    $fp = _beanstalk_connection($job['config_key']);

    _beanstalk_use_tube($fp, SPIDER_POOL_TUBE);

    $id = _beanstalk_put(
        $fp,
        $job['priority'], 
        0,
        $run_time = 60,
        serialize([
            'job_name' => $job_name,
            'url' => $url,
            'data' => $data,
            'retry' => 0
        ])
    );

    return $id;
}/*}}}*/

/**
 *  common
 */
function _spider_job(
    string $cron_string,
    string $url,
    string $method,
           $data,
    string $format,
    array  $spider_rule,
           $closure_or_null,
    int    $priority,
    array  $retry,
    string $config_key
)
{/*{{{*/
    otherwise(array_key_exists($format, [
        'json' => true,
        'html' => true,
        'xml' => true,
    ]));

    return [
        'cron_string' => $cron_string,
        'url' => $url,
        'method' => $method,
        'data' => $data,
        'format' => $format,
        'rule' => $spider_rule,
        'closure' => $closure_or_null,
        'priority' => $priority,
        'retry' => $retry,
        'config_key' => $config_key,
    ];
}/*}}}*/

function spider_job_get(string $job_name, string $cron_string, string $url, string $format, array $spider_rule, $closure_or_null = null, $priority = 10, $retry = [], $config_key = 'default')
{/*{{{*/
    $jobs = spider_jobs();

    otherwise(! array_key_exists($job_name, $jobs), "spider job [$job_name] already exists");

    $jobs[$job_name] = _spider_job(
        $cron_string,
        $url,
        'get',
        [],
        $format,
        $spider_rule,
        $closure_or_null,
        $priority,
        $retry,
        $config_key
    );

    spider_jobs($jobs);
}/*}}}*/

function spider_job_post(string $job_name, string $cron_string, string $url, $data, string $format, array $spider_rule, $closure_or_null = null, $priority = 10, $retry = [], $config_key = 'default')
{/*{{{*/
    $jobs = spider_jobs();

    otherwise(! array_key_exists($job_name, $jobs), "spider job [$job_name] already exists");

    $jobs[$job_name] = _spider_job(
        $cron_string,
        $url,
        'post',
        $data,
        $format,
        $spider_rule,
        $closure_or_null,
        $priority,
        $retry,
        $config_key
    );

    spider_jobs($jobs);
}/*}}}*/

function spider_jobs($jobs = null)
{/*{{{*/
    static $container = [];

    if (is_null($jobs)) {
        return $container;
    }

    return $container = $jobs;
}/*}}}*/

function _spider_job_pickup($job_name)
{/*{{{*/
    $jobs = spider_jobs();

    return $jobs[$job_name];
}/*}}}*/

/**
 * operator
 */

function spider_watch($config_key = 'default', $memory_limit = 1048576)
{/*{{{*/
    declare(ticks=1);
    $received_signal = false;
    pcntl_signal(SIGTERM, function () use (&$received_signal) {
        $received_signal = true;
    });

    $finished_action = spider_finish_action();

    $fp = _beanstalk_connection($config_key);

    _beanstalk_watch($fp, SPIDER_POOL_TUBE);

    for (;;) {

        if (memory_get_usage(true) > $memory_limit) {
            throw new Exception('spider_watch out of memory');
        }

        if ($received_signal) {
            break;
        }

        $job_instance = _beanstalk_reserve($fp);

        $id = $job_instance['id'];
        $body = unserialize($job_instance['body']);

        $job_name = $body['job_name'];
        $url = $body['url'];
        $data = $body['data'];
        $retry = $body['retry'];

        $job = _spider_job_pickup($job_name);

        $res = [];

        if ('get' == $job['method']) {

            $res = spider_run_get($url?:$job['url'], $job['format'], $job['rule'], $job['closure']);
        } else {

            $res = spider_run_post($url?:$job['url'], $data?:$job['data'], $job['format'], $job['rule'], $job['closure']);
        }

        if ($res) {

            $res['create_time'] = datetime();

            storage_insert($job_name, $res, $config_key);

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
                        'url' => $url,
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

function _spider_transfer_result($result, $format, array $spider_rule)
{/*{{{*/
    $result_arr = [];

    if ($result) {

        switch ($format) {

            case 'json':

                $result_arr = json_decode($result, true);

                return array_transfer($result_arr, $spider_rule);

                break;

            case 'xml':

                $obj = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);

                $result_arr = json_decode(json_encode($obj), true);

                return array_transfer($result_arr, $spider_rule);

                break;
        }
    }
}/*}}}*/

function spider_run_get($url, string $format, array $spider_rule, $closure_or_null = null)
{/*{{{*/
    $result = remote_get($url, 30);

    $res = _spider_transfer_result($result, $format, $spider_rule);

    if ($closure_or_null) {
        $tmp_res = call_user_func($closure_or_null, $res);
        if ($tmp_res) {
            $res = $tmp_res;
        }
    }

    return $res;
}/*}}}*/

function spider_run_post($url, $data, string $format, array $spider_rule, $closure_or_null = null)
{/*{{{*/
    $result = remote_post($url, $data, 30);

    $res = _spider_transfer_result($result, $format, $spider_rule);

    if ($closure_or_null) {
        $tmp_res = call_user_func($closure_or_null, $res);
        if ($tmp_res) {
            $res = $tmp_res;
        }
    }

    return $res;
}/*}}}*/

/**
 *  querier
 */

function spider_data_query($job_name, array $selections = [],  array $queries = [], array $sorts = [], $offset = 0, $limit = 1000)
{/*{{{*/
    return storage_query($job_name, $selections, $queries, $sorts, $offset, $limit);
}/*}}}*/

function spider_last_data_query($job_name)
{/*{{{*/
    return spider_data_query(
        $job_name,
        $selections = [],
        $queries = [],
        $sorts = ['create_time' => -1],
        $offset = 0,
        $limit = 1
    );
}/*}}}*/
