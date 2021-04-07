<?php

define('OTHERWISE_MESSAGE_DELIMITER', '---');

class business_exception extends exception { }

function otherwise($assertion, $description = 'assertion is not true', $exception_class_name = 'exception', $exception_code = 'OTHERWISE_DEFAULT')
{/*{{{*/
    if (! $assertion) {
        throw new $exception_class_name($exception_code.OTHERWISE_MESSAGE_DELIMITER.$description, -1);
    }
}/*}}}*/

function otherwise_get_error_info(throwable $ex)
{/*{{{*/
    $message = $ex->getMessage();

    $info = explode(OTHERWISE_MESSAGE_DELIMITER, $message);

    if (count($info) > 1) {
        return [
            'code' => $info[0],
            'message' => $info[1],
        ];
    } else {
        return [
            'code' => $ex->getCode(),
            'message' => $message,
        ];
    }
}/*}}}*/

function otherwise_error_code($error_code, $assertion, array $replace_contents = [])
{/*{{{*/
    if (! $assertion) {

        $config = config('error_code');

        $description = $config[$error_code];

        if ($replace_contents) {
            foreach ($replace_contents as $replace_key => $content) {
                $description = str_replace($replace_key, $content, $description);
            }
        }
        
        throw new business_exception($error_code.OTHERWISE_MESSAGE_DELIMITER.$description, -1);
    }
}/*}}}*/
