<?php

define('SPIDER_POOL_TUBE', 'spider_pool');

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
           $spider_rule,
           $closure_or_null,
           $multi_insert_trace_key,
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
        'multi_insert_trace_key' => $multi_insert_trace_key,
        'priority' => $priority,
        'retry' => $retry,
        'config_key' => $config_key,
    ];
}/*}}}*/

function spider_job_get(string $job_name, string $cron_string, string $url, string $format, $spider_rule, $closure_or_null = null, $multi_insert_trace_key = null, $priority = 10, $retry = [], $config_key = 'spider')
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
        $multi_insert_trace_key,
        $priority,
        $retry,
        $config_key
    );

    spider_jobs($jobs);
}/*}}}*/

function spider_job_post(string $job_name, string $cron_string, string $url, $data, string $format, $spider_rule, $closure_or_null = null, $multi_insert_trace_key = null, $priority = 10, $retry = [], $config_key = 'spider')
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
        $multi_insert_trace_key,
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

function spider_watch($config_key = 'spider', $memory_limit = 1048576)
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

            $res = spider_run_get($url?:$job['url'], $job['format'], $job['rule']);
        } else {

            $res = spider_run_post($url?:$job['url'], $data?:$job['data'], $job['format'], $job['rule']);
        }

        if ($res) {

            if ($multi_insert_trace_key = $job['multi_insert_trace_key']) {

                $last_element = spider_last_data_query($job_name);

                $insert_res = [];

                foreach ($res as $element) {

                    if ($last_element && $last_element[$multi_insert_trace_key] === $element[$multi_insert_trace_key]) {
                        break;
                    }

                    $insert_res[] = $element;
                }

                $insert_res = array_reverse($insert_res);

                foreach ($insert_res as $element) {

                    if (is_string($element)) {
                        log_module('spider', print_r($element, true));
                        log_module('spider', print_r($last_element, true));
                    }

                    if ($job['closure']) {
                        $element = call_user_func($job['closure'], $element);
                    }

                    $element['create_time'] = datetime();

                    storage_insert($job_name, $element, $config_key);

                }

            } else {

                if (is_string($res)) {
                    log_module('spider', print_r($res, true));
                }

                if ($job['closure']) {
                    $res = call_user_func($job['closure'], $res);
                }

                $res['create_time'] = datetime();

                storage_insert($job_name, $res, $config_key);
            }

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

function _spider_transfer_result($result, $format, $spider_rule)
{/*{{{*/
    $result_arr = [];

    if ($result) {

        switch ($format) {

            case 'json':

                $result_arr = json_decode($result, true);

                break;

            case 'xml':

                $obj = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);

                $result_arr = json_decode(json_encode($obj), true);

                break;
        }

        if (is_array($spider_rule)) {

            return array_transfer($result_arr, $spider_rule);

        } elseif (is_string($spider_rule)) {

            return array_get($result_arr, $spider_rule, []);
        }
    }

    return $result_arr;
}/*}}}*/

function spider_run_get($url, string $format, $spider_rule)
{/*{{{*/
    $result = remote_get($url, 30);

    return _spider_transfer_result($result, $format, $spider_rule);
}/*}}}*/

function spider_run_post($url, $data, string $format, $spider_rule)
{/*{{{*/
    $result = remote_post($url, $data, 30);

    return _spider_transfer_result($result, $format, $spider_rule);
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
    $res = spider_data_query(
        $job_name,
        $selections = [],
        $queries = [],
        $sorts = ['_id' => -1],
        $offset = 0,
        $limit = 1
    );

    return reset($res);
}/*}}}*/
