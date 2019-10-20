<?php

function _log_prefix()
{/*{{{*/
    return '['.date('Y-m-d H:i:s '.substr((string) microtime(), 2, 8)).']';
}/*}}}*/

function log_exception(throwable $ex)
{/*{{{*/

    $log = config('log');

    error_log(_log_prefix().$ex."\n", 3, $log['exception_path']);
}/*}}}*/

function log_notice($message)
{/*{{{*/

    $log = config('log');

    error_log(_log_prefix().$message."\n", 3, $log['notice_path']);
}/*}}}*/

function log_module($module, $message)
{/*{{{*/
    $log = config('log');

    error_log(_log_prefix().'['.$module.'] '.$message."\n", 3, $log['module_path']);
}/*}}}*/
