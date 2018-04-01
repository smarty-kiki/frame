<?php

function otherwise($assertion, $description = 'assertion is not true', $exception_class_name = 'Exception', $exception_code = 0)
{
    if (! $assertion) {
        throw new $exception_class_name($description, $exception_code);
    }
}
