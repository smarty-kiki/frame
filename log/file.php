<?php

function log_exception(throwable $ex)
{/*{{{*/

    $log = config('log');

    error_log($ex."\n", 3, $log['exception_path']);
}/*}}}*/

function log_notice($message)
{/*{{{*/

    $log = config('log');

    error_log($message."\n", 3, $log['notice_path']);
}/*}}}*/

function log_module($module, $message)
{/*{{{*/
    $log = config('log');

    error_log('['.$module.'] '.$message."\n", 3, $log['module_path']);
}/*}}}*/
