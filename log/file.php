<?php

function log_exception(throwable $ex)
{/*{{{*/

    $log = config('log');

    error_log('['.date('Y-m-d H:i:s u').']'.$ex."\n", 3, $log['exception_path']);
}/*}}}*/

function log_notice($message)
{/*{{{*/

    $log = config('log');

    error_log('['.date('Y-m-d H:i:s u').']'.$message."\n", 3, $log['notice_path']);
}/*}}}*/

function log_module($module, $message)
{/*{{{*/
    $log = config('log');

    error_log('['.date('Y-m-d H:i:s u').']'.'['.$module.'] '.$message."\n", 3, $log['module_path']);
}/*}}}*/
