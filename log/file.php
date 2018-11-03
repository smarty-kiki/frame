<?php

function log_exception(exception $ex)
{/*{{{*/

    $log = config('log');

    error_log($ex."\n", 3, $log['exception_path']);
}/*}}}*/

function log_notice($message)
{/*{{{*/

    $log = config('log');

    error_log($message."\n", 3, $log['notice_path']);
}/*}}}*/
