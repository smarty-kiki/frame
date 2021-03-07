<?php

class business_exception extends exception { }

function otherwise($assertion, $description = 'assertion is not true', $exception_class_name = 'Exception', $exception_code = 0)
{/*{{{*/
    if (! $assertion) {
        throw new $exception_class_name($description, $exception_code);
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

        throw new business_exception($description, $error_code);
    }
}/*}}}*/
